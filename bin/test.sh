#!/usr/bin/env bash
#
# Run the WP-Sweep PHPUnit suite against a real MySQL database.
#
# Docker is the only prerequisite. wp-env brings up WordPress plus the test
# library, composer runs *inside* the container so vendor/ never lands in the
# repo, and phpunit runs against the tests-cli service.

set -euo pipefail

cd "$( dirname "${BASH_SOURCE[0]}" )/.."

PLUGIN_CWD="wp-content/plugins/wp-sweep"

echo "==> Starting wp-env"
npx --yes @wordpress/env start

echo "==> Installing dev dependencies inside the container"
npx --yes @wordpress/env run tests-cli --env-cwd="${PLUGIN_CWD}" \
	composer install --no-interaction --no-progress

echo "==> Running PHPUnit"
npx --yes @wordpress/env run tests-cli --env-cwd="${PLUGIN_CWD}" \
	vendor/bin/phpunit "$@"
