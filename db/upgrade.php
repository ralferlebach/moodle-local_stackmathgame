<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade steps for local_stackmathgame.
 *
 * The plugin currently ships only with its install.xml baseline schema.
 * Add incremental upgrade steps here once post-release schema changes are needed.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_stackmathgame_upgrade(int $oldversion): bool {
    return true;
}
