#!/usr/bin/env bash
#
# Run the PHPUnit suite as a network. Everything else is bin/test.sh -- the
# only difference is the config file, which carries WP_MULTISITE=1.
#
#   bash bin/test-multisite.sh
#   bash bin/test-multisite.sh --filter Uninstall

set -euo pipefail

cd "$(dirname "$0")/.."

PHPUNIT_CONFIG=phpunit-multisite.xml.dist exec bash bin/test.sh "$@"
