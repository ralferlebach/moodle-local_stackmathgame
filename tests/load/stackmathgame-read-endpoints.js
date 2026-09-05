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

/**
 * k6 read-endpoint plateau load test for local_stackmathgame - the k6 twin of
 * stackmathgame-read-endpoints.jmx. It keeps VUS virtual users concurrently active for
 * DURATION, repeatedly issuing the four web-service calls game_engine.js makes on every
 * quiz attempt page, and asserting they stay healthy.
 *
 * The bootstrap is deliberately reproduced as a whole rather than one endpoint at a time:
 * game_engine.js fires get_quiz_config, get_profile_state, get_narrative and
 * prefetch_next_node in a single Promise.all, so measuring them individually would report
 * a latency the player never experiences.
 *
 * Environment (exported by tests/load/seed_large.php): BASE_URL, QUIZID, TOKEN.
 * Tuning: VUS (default 25), DURATION (default 90s). The thresholds are an initial baseline -
 * tune them after the first real run, exactly like the JMeter assertions.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL;
const QUIZID = __ENV.QUIZID;
const TOKEN = __ENV.TOKEN;

const WS = `${BASE_URL}/webservice/rest/server.php`;

export const options = {
  scenarios: {
    plateau: {
      executor: 'constant-vus',
      vus: Number(__ENV.VUS || 25),
      duration: __ENV.DURATION || '90s',
    },
  },
  thresholds: {
    // Measured on a GitHub runner: 5750 requests, 0 failures, p95 363 ms, max 1600 ms at 63
    // requests per second. The failure threshold stays at 1% because the observed rate is zero -
    // anything above noise is a real regression.
    http_req_failed: ['rate<0.01'],
    // 1000 ms is roughly three times the measured p95, which leaves room for a slower runner
    // without letting a genuine slowdown through. The old 2000 ms was a guess and would have
    // absorbed a fivefold regression silently.
    http_req_duration: ['p(95)<1000'],
    // The bootstrap fires four calls at once, so the iteration is what a player actually waits
    // for. Measured at about 570 ms.
    iteration_duration: ['p(95)<2500'],
  },
};

/**
 * Call a local_stackmathgame web service function.
 *
 * @param {string} fn The web service function name without the plugin prefix.
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
 * A Moodle web service answers HTTP 200 even when it raises an exception, so the status code
 * alone proves nothing. Anything carrying an "exception" key is a failure however it is
 * dressed up.
 *
 * @param {Object} response The k6 response object.
 * @returns {boolean} True when the payload is a successful web-service result.
 */
function isHealthy(response) {
  if (response.status !== 200) {
    return false;
  }
  try {
    return !JSON.parse(response.body).exception;
  } catch (e) {
    return false;
  }
}

/**
 * Fail fast on missing or unreachable targets.
 *
 * Without this the plan happily generates load against an empty or dead URL and reports
 * "100% of requests failed", which reads like a plugin defect instead of a broken environment.
 *
 * @returns {void}
 */
export function setup() {
  if (!BASE_URL || !QUIZID || !TOKEN) {
    throw new Error('BASE_URL, QUIZID und TOKEN muessen gesetzt sein (siehe seed_large.php).');
  }
  const probe = http.get(`${BASE_URL}/login/index.php`);
  if (probe.status !== 200) {
    throw new Error(`Ziel nicht erreichbar: ${BASE_URL} lieferte HTTP ${probe.status}.`);
  }
  const auth = ws('get_quiz_config', { quizid: QUIZID });
  if (!isHealthy(auth)) {
    throw new Error(`Token oder Quiz ungueltig: ${auth.body}`);
  }
}

export default function () {
  // The heaviest read: it resolves the design, the runtime asset map and the whole
  // question map in one call, and it is the one every attempt page starts with.
  const config = ws('get_quiz_config', { quizid: QUIZID });
  check(config, { 'get_quiz_config liefert ein Ergebnis': isHealthy });

  // Per-user state. Unlike the config this cannot be cached across users, so it is the
  // call most likely to degrade first under concurrency.
  const profile = ws('get_profile_state', { quizid: QUIZID });
  check(profile, { 'get_profile_state liefert ein Ergebnis': isHealthy });

  const narrative = ws('get_narrative', { quizid: QUIZID, scene: 'world_enter' });
  check(narrative, { 'get_narrative liefert ein Ergebnis': isHealthy });

  // Branch resolution. It runs question_map_service::ensure_for_cmid() on every call, so it
  // is the endpoint where a missing index shows up soonest.
  const next = ws('prefetch_next_node', { quizid: QUIZID, currentslot: 1 });
  check(next, { 'prefetch_next_node liefert ein Ergebnis': isHealthy });

  sleep(1);
}
