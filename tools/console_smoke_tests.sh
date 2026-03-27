#!/usr/bin/env bash
set -euo pipefail

MOODLEROOT="${1:-$(pwd)}"
cd "$MOODLEROOT"

echo "== STACK Math Game smoke checks =="

for path in \
  local/stackmathgame \
  local/stackmathgame/mode/exitgames \
  local/stackmathgame/mode/wisewizzard \
  local/stackmathgame/mode/rpg \
  question/behaviour/stackmathgame
  do
  if [[ -e "$path" ]]; then
    echo "OK  $path"
  else
    echo "MISS $path"
    exit 1
  fi
done

echo "-- Versions --"
grep '\$plugin->version' local/stackmathgame/version.php
grep '\$plugin->version' question/behaviour/stackmathgame/version.php

echo "-- PHP lint --"
find local/stackmathgame -name '*.php' -print0 | xargs -0 -n1 php -l >/tmp/stackmathgame_local_lint.log
find question/behaviour/stackmathgame -name '*.php' -print0 | xargs -0 -n1 php -l >/tmp/stackmathgame_qb_lint.log
echo "PHP lint OK"

echo "-- Subplugin registration --"
php -r 'require "local/stackmathgame/db/subplugins.php"; var_export($subplugins); echo PHP_EOL;'
cat local/stackmathgame/db/subplugins.json

echo "-- Services --"
grep -n 'local_stackmathgame_' local/stackmathgame/db/services.php

echo "Smoke checks completed successfully."
