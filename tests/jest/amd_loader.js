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
 * Minimal AMD loader for testing Moodle AMD sources under Jest.
 *
 * Moodle AMD modules are written as `define([deps], factory)`, which Node cannot require
 * directly. Rather than restructuring the sources for the benefit of the test runner - which
 * would mean the tests no longer exercise what actually ships - this evaluates the source with a
 * `define` in scope and returns the module.
 *
 * Dependencies are supplied by the caller, so a module can be tested against stubs without the
 * Moodle AMD runtime being present.
 *
 * @module     local_stackmathgame/tests/amd_loader
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

const fs = require('fs');
const path = require('path');
const vm = require('vm');

/**
 * Load a Moodle AMD module from source.
 *
 * @param {string} relativePath Path to the source file, relative to the plugin root.
 * @param {Object} dependencies Map of AMD dependency name to the stub to inject.
 * @returns {*} Whatever the module's factory returned.
 */
function loadAmd(relativePath, dependencies = {}) {
  const pluginRoot = path.resolve(__dirname, '..', '..');
  const source = fs.readFileSync(path.join(pluginRoot, relativePath), 'utf8');

  let exported = null;
  const sandbox = {
    document: global.document,
    window: global.window,
    console: global.console,
    sessionStorage: global.sessionStorage,
    define: (deps, factory) => {
      // Moodle allows define(factory) as well as define(deps, factory).
      if (typeof deps === 'function') {
        exported = deps();
        return;
      }
      const resolved = deps.map((name) => {
        if (!(name in dependencies)) {
          throw new Error(`No stub provided for AMD dependency "${name}".`);
        }
        return dependencies[name];
      });
      exported = factory(...resolved);
    },
  };

  vm.createContext(sandbox);
  vm.runInContext(source, sandbox, { filename: relativePath });

  if (exported === null) {
    throw new Error(`${relativePath} did not call define().`);
  }
  return exported;
}

module.exports = { loadAmd };
