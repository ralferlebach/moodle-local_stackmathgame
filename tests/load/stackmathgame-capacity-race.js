// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/*
 * Concurrency (write) gate: many simultaneous submissions of the SAME answer to the SAME
 * slot compete to be rewarded.
 *
 * This is the complement to the PHPUnit tests for issue #5 ("wiederholtes Absenden derselben
 * bereits geloesten Frage erzeugt kein Reward-Farming"). Those can prove that a second,
 * sequential submission grants nothing, but they cannot prove mutual exclusion: a single PHP
 * process reads and writes the profile inside one database session, so the read-modify-write
 * on local_stackmathgame_profile always sees its own earlier write. Only genuinely parallel
 * requests - separate sessions - can interleave between the "already solved?" check and the
 * XP increment, which is exactly what this scenario produces.
 *
 * The gate is the plugin's own behaviour, not just latency: one solved slot must increase XP
 * exactly once, no matter how many requests arrive together. Over-granting shows up as a
 * final XP total above the slot's configured reward.
 *
 * Prerequisites: a seeded course and token from tests/load/seed_large.php, and a slot whose
 * configjson carries a non-zero rewards.xp (the load seed leaves the schema default of 0, so
 * set one first - see tests/load/README.md).
 *
 * Environment: BASE_URL, TOKEN, QUIZID, ATTEMPTID, SLOT, ANSWER, EXPECTED_XP, optional VUS.
 *
 * Run: k6 run -e BASE_URL=... -e TOKEN=... -e ATTEMPTID=... tests/load/stackmathgame-capacity-race.js
 */

import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

const BASE_URL = __ENV.BASE_URL;
const TOKEN = __ENV.TOKEN;
const QUIZID = __ENV.QUIZID;
const ATTEMPTID = __ENV.ATTEMPTID;
const SLOT = Number(__ENV.SLOT || 1);
const ANSWER = __ENV.ANSWER || '2';
const EXPECTED_XP = Number(__ENV.EXPECTED_XP || 10);
const VUS = Number(__ENV.VUS || 30);

const WS = `${BASE_URL}/webservice/rest/server.php`;

const accepted = new Counter('stackmathgame_accepted');
const refused = new Counter('stackmathgame_refused');

export const options = {
  scenarios: {
    // All virtual users start at once and fire a single write each: the sharpest possible race.
    stampede: {
      executor: 'shared-iterations',
      vus: VUS,
      iterations: VUS,
      maxDuration: '60s',
    },
  },
  thresholds: {
    // Losing the race must be a clean refusal, not a 500.
    http_req_failed: ['rate<0.01'],
    // Every request must be answered one way or the other; a silent drop would let an
    // over-grant slip past the teardown check below.
    'checks{kind:answered}': ['rate>0.99'],
  },
};

/**
 * Call a local_stackmathgame web service function.
 *
 * @param {string} fn The function name without the plugin prefix.
 * @param {Object} params Additional POST parameters.
 * @returns {Object} The k6 response object.
 */
function ws(fn, params) {
  const body = Object.assign(
    {
      wstoken: TOKEN,
      wsfunction: `local_stackmathgame_${fn}`,
      moodlewsrestformat: 'json',
    },
    params
  );
  return http.post(WS, body, { tags: { endpoint: fn } });
}

/**
 * Read the current XP of the seeded player.
 *
 * @returns {number} The XP value, or -1 when it cannot be read.
 */
function readXp() {
  const response = ws('get_profile_state', { quizid: QUIZID });
  try {
    return Number(JSON.parse(response.body).profile.xp);
  } catch (e) {
    return -1;
  }
}

/**
 * Fail fast on a broken environment and record the XP the race starts from.
 *
 * @returns {Object} The baseline XP, handed to the default function and teardown.
 */
export function setup() {
  if (!BASE_URL || !TOKEN || !QUIZID || !ATTEMPTID) {
    throw new Error('BASE_URL, TOKEN, QUIZID und ATTEMPTID muessen gesetzt sein.');
  }
  const baseline = readXp();
  if (baseline < 0) {
    throw new Error('Profilstand nicht lesbar - Token oder Quiz pruefen.');
  }
  return { baseline: baseline };
}

export default function () {
  const response = ws('submit_answer', {
    attemptid: ATTEMPTID,
    slot: SLOT,
    'answers[0][name]': `q${ATTEMPTID}:${SLOT}_ans1`,
    'answers[0][value]': ANSWER,
  });

  check(response, { 'Submit wurde beantwortet': (r) => r.status === 200 }, { kind: 'answered' });

  let payload = null;
  try {
    payload = JSON.parse(response.body);
  } catch (e) {
    payload = null;
  }
  if (payload && !payload.exception) {
    accepted.add(1);
  } else {
    refused.add(1);
  }
}

/**
 * The decisive gate. Counting "accepted" responses is not enough: every parallel submission of
 * an already-solved question is a legitimate 200, so the invariant lives in the profile, not
 * in the response codes. XP may grow by the reward of one solve - never by a multiple of it.
 *
 * @param {Object} data The baseline recorded in setup().
 * @returns {void}
 */
export function teardown(data) {
  const finalxp = readXp();
  const gained = finalxp - data.baseline;
  console.log(`XP vorher: ${data.baseline}, nachher: ${finalxp}, Zuwachs: ${gained}`);
  if (gained > EXPECTED_XP) {
    throw new Error(
      `Reward-Farming: ${VUS} parallele Submits haben ${gained} XP vergeben, erlaubt waren ${EXPECTED_XP}.`
    );
  }
}
