#!/usr/bin/env bash
#
# Run the Playwright end-to-end suite against this plugin's wp-env.
#
# Docker and Node are the only prerequisites, as with bin/test.sh. The browser
# is downloaded on first run and cached by Playwright afterwards.
#
#   bin/test-e2e.sh                       # the whole suite, headless
#   bin/test-e2e.sh --headed              # watch it happen
#   bin/test-e2e.sh admin.spec.js         # one file
#   bin/test-e2e.sh --debug               # step through it
#
set -euo pipefail

cd "$(dirname "$0")/.."

# The tests environment, not the development one: the suite creates posts and
# changes settings, and doing that to the environment somebody is looking at is
# rude. The port comes from .wp-env.json, so a second checkout on other ports
# needs no edit here.
PORT=$(node -p "require('./.wp-env.json').testsPort")
export WP_BASE_URL="${WP_BASE_URL:-http://localhost:${PORT}}"

echo "==> Starting wp-env"
npx --yes @wordpress/env start

# wp-env installs the plugin into both environments but only activates it in the
# development one, so the tests environment PHPUnit uses has it switched off --
# which is fine for PHPUnit, because the WordPress test bootstrap loads the
# plugin itself. A browser gets the site as it really is: no plugin, no menu,
# and "Sorry, you are not allowed to access this page" on every screen.
#
# The directory name, not the literal slug, so this keeps working in a checkout
# cloned under a different name.
SLUG=$(basename "$PWD")

echo "==> Activating ${SLUG} in the tests environment"
npx --yes @wordpress/env run tests-cli wp plugin activate "$SLUG"

# And a theme, for the same reason. The tests environment ships with every
# bundled theme installed and none of them active, because PHPUnit does not
# render a page -- so the front end answers 200 with an empty body, and a
# browser test looking for anything on it waits for something that was never
# going to arrive.
#
# A classic theme on purpose: it puts post content in one predictable
# .entry-content rather than through a block template.
echo "==> Activating a theme in the tests environment"
npx --yes @wordpress/env run tests-cli wp theme activate twentytwentyone

# Running PHPUnit reinstalls this same database, which takes the plugin, the
# theme and the logged-in session with it. Both steps above are therefore run
# every time rather than once, so the suite repairs whatever the last command
# left behind.
echo "==> Installing the browser if it is missing"
npx playwright install chromium

echo "==> Running Playwright against ${WP_BASE_URL}"
exec npx playwright test "$@"
