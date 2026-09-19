#!/usr/bin/env bash
#
# Fail when a committed AMD build is older than its source.
#
# This is the most expensive recurring mistake in this project, and it has happened repeatedly:
# a change to amd/src/ is committed without rebuilding amd/build/, so the browser keeps running
# the old module while the source beside it looks perfectly current. Nothing errors; the feature
# simply does not do what the code says.
#
# CI catches it - moodle-plugin-ci's grunt task reports "File is stale and needs to be rebuilt" -
# but only after a push, and only as a lint failure among many. This runs in a second, locally,
# and says which file.
#
# Usage: tools/check_amd_fresh.sh [plugin root]
#
# @copyright 2026 Ralf Erlebach
# @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

set -u
root="${1:-$(cd "$(dirname "$0")/.." && pwd)}"
stale=0

check_dir () {
    local src="$1/amd/src" build="$1/amd/build" name="$2"
    [ -d "$src" ] || return 0

    for file in "$src"/*.js; do
        [ -e "$file" ] || continue
        local base min
        base="$(basename "$file" .js)"
        min="$build/$base.min.js"

        if [ ! -f "$min" ]; then
            echo "MISSING  $name/amd/build/$base.min.js - source exists, build does not"
            stale=1
            continue
        fi
        if [ "$file" -nt "$min" ]; then
            echo "STALE    $name/amd/build/$base.min.js is older than its source"
            stale=1
        fi
    done
}

check_dir "$root" "."
for mode in "$root"/mode/*/; do
    [ -d "$mode" ] || continue
    check_dir "${mode%/}" "mode/$(basename "${mode%/}")"
done

if [ "$stale" -ne 0 ]; then
    cat <<'MSG'

Rebuild before committing:

    npx grunt amd                       # in the plugin root
    (cd mode/<name> && npx grunt amd)   # once per mode subplugin

Grunt determines the component from the directory it runs in, so a single run in the plugin root
rebuilds amd/build/ and leaves every mode/*/amd/build/ untouched and stale.
MSG
    exit 1
fi

echo "AMD builds are current."
