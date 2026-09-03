<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Shared STACK CAS initialisation for the CI helper scripts.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Configure qtype_stack, write maximalocal.mac and verify the CAS answers.
 *
 * Three things have to happen, and leaving any of them out looks like a
 * different bug entirely:
 *
 *  1. platform / maximacommand / castimeout. A fresh install times out after
 *     10 seconds; a cold Maxima on a runner needs far longer, and STACK then
 *     renders "CAS failed to return any data due to timeout" instead of the
 *     question - which reads in a Behat log as a missing input field.
 *  2. maximalocal.mac, written into $CFG->dataroot by STACK's installer. The
 *     test datarootS never get one, so compute() returns an empty result even
 *     where Maxima itself answers in seconds.
 *  3. A genuine connect, once, here. The first call compiles STACK's library
 *     and is by far the slowest; doing it now both warms the cache and fails
 *     loudly in a step that names the cause.
 *
 * @param stdClass $cfg The Moodle configuration object.
 * @param string $context Human-readable name of the environment, for the log.
 * @return int Exit code: 0 on success, 1 when the CAS cannot be reached.
 */
function local_stackmathgame_init_stack_cas(stdClass $cfg, string $context): int {
    $candidates = [
        $cfg->dirroot . '/question/type/stack',
        $cfg->dirroot . '/public/question/type/stack',
    ];
    $stackroot = null;
    foreach ($candidates as $candidate) {
        if (file_exists($candidate . '/stack/cas/installhelper.class.php')) {
            $stackroot = $candidate;
            break;
        }
    }
    if (!$stackroot) {
        fwrite(STDERR, "ERROR: qtype_stack not found under {$cfg->dirroot}.\n");
        return 1;
    }

    require_once($stackroot . '/stack/cas/installhelper.class.php');
    require_once($stackroot . '/stack/cas/connectorhelper.class.php');

    echo "STACK CAS setup for: $context\n";
    echo "  STACK root: $stackroot\n";
    echo "  dataroot:   {$cfg->dataroot}\n";

    $maxima = trim((string)shell_exec('command -v maxima'));
    if ($maxima === '') {
        fwrite(STDERR, "ERROR: no maxima binary on PATH.\n");
        return 1;
    }

    set_config('platform', 'linux', 'qtype_stack');
    set_config('maximacommand', $maxima, 'qtype_stack');
    set_config('maximacommandopt', '', 'qtype_stack');
    set_config('maximaversion', 'default', 'qtype_stack');
    // 300 seconds, not 60: the very first call compiles the STACK library, and a
    // cold runner has been seen to need minutes for it.
    set_config('castimeout', '300', 'qtype_stack');
    set_config('casresultscache', 'db', 'qtype_stack');
    set_config('casdebugging', '0', 'qtype_stack');
    set_config('maximalibraries', '', 'qtype_stack');

    purge_all_caches();
    stack_cas_configuration::create_maximalocal();
    echo "  maximalocal.mac written to {$cfg->dataroot}/stack/\n";

    [$message, $debug, $ok] = stack_connection_helper::stackmaxima_genuine_connect();
    if (!$ok) {
        fwrite(STDERR, "\nSTACK CAS connection FAILED in $context.\n");
        fwrite(STDERR, "  maxima:     $maxima\n");
        fwrite(STDERR, "  platform:   " . get_config('qtype_stack', 'platform') . "\n");
        fwrite(STDERR, "  castimeout: " . get_config('qtype_stack', 'castimeout') . "\n");
        fwrite(STDERR, "  message:    $message\n");
        fwrite(STDERR, "  debug:\n$debug\n");
        return 1;
    }

    echo "  CAS OK: $message\n";
    return 0;
}
