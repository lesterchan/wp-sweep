#!/bin/bash
#
# Install the toolchain that gates this repository.
#
# One file, byte-identical in the tooling repository and in all nineteen
# plugins, so it names no plugin and branches on what it finds. bin/verify.py
# holds the copies identical; edit _standards/templates and copy it out.
#
# The reason it exists: a fresh session arrives with php and node, and neither
# of the checks that actually decide whether a push is green. `php -l` and
# `bin/verify.py` both pass on code phpcs rejects, and treating them as the gate
# put four red commits on master in one afternoon.
#
#   Node 24       -> what ci.yml pins. The npm that ships with Node 22 resolves
#                    vite's optional `yaml` peer differently and calls every
#                    lockfile here out of sync. That is a false alarm and has
#                    already cost a debugging session; do not regenerate a
#                    lockfile to silence it.
#   phpcs + WPCS  -> the "PHP coding standards" job. Two sniffs this collection
#                    trips repeatedly: a docblock description must start with a
#                    capital letter, and a string with nothing to interpolate
#                    must use single quotes.
#   npm ci        -> the "JS coding standards and tests" job, where there is a
#                    lockfile to install from.
#   Docker        -> installed but not running by default.
#
# PHPUnit and Playwright still will not run, and not for want of Docker: wp-env
# downloads WordPress from *.wordpress.org, which the session's egress policy
# blocks. Those two belong to CI.

set -euo pipefail

if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
	exit 0
fi

cd "${CLAUDE_PROJECT_DIR:-$(cd "$(dirname "$0")/../.." && pwd)}"

log() { printf '==> %s\n' "$1"; }

# --- Node 24, to match ci.yml -----------------------------------------------
# nvm ships as a shell function rather than a binary, so it has to be sourced.
export NVM_DIR="${NVM_DIR:-/opt/nvm}"

if [ -s "$NVM_DIR/nvm.sh" ]; then
	# shellcheck disable=SC1091
	. "$NVM_DIR/nvm.sh"

	if ! nvm which 24 >/dev/null 2>&1; then
		log "Installing Node 24 (ci.yml pins it; npm 10 misreads these lockfiles)"
		nvm install 24 >/dev/null
	fi

	nvm use 24 >/dev/null
	NODE_BIN="$(dirname "$(nvm which 24)")"
	export PATH="$NODE_BIN:$PATH"

	if [ -n "${CLAUDE_ENV_FILE:-}" ]; then
		echo "export NVM_DIR=\"$NVM_DIR\"" >> "$CLAUDE_ENV_FILE"
		echo "export PATH=\"$NODE_BIN:\$PATH\"" >> "$CLAUDE_ENV_FILE"
	fi

	log "Node $(node --version), npm $(npm --version)"
else
	log "nvm not found at $NVM_DIR; leaving Node as it is"
fi

# --- phpcs and WPCS, exactly as ci.yml installs them ------------------------
export COMPOSER_ALLOW_SUPERUSER=1

PHPCS_BIN="$(composer global config bin-dir --absolute 2>/dev/null || true)"

if [ -z "$PHPCS_BIN" ] || [ ! -x "$PHPCS_BIN/phpcs" ]; then
	log "Installing phpcs + WPCS"
	composer global config --no-plugins allow-plugins.dealerdirect/phpcodesniffer-composer-installer true >/dev/null 2>&1
	composer global require --no-interaction --no-progress \
		squizlabs/php_codesniffer wp-coding-standards/wpcs >/dev/null 2>&1
	PHPCS_BIN="$(composer global config bin-dir --absolute 2>/dev/null || true)"
fi

if [ -n "$PHPCS_BIN" ] && [ -x "$PHPCS_BIN/phpcs" ]; then
	export PATH="$PHPCS_BIN:$PATH"

	if [ -n "${CLAUDE_ENV_FILE:-}" ]; then
		echo "export PATH=\"$PHPCS_BIN:\$PATH\"" >> "$CLAUDE_ENV_FILE"
		echo "export COMPOSER_ALLOW_SUPERUSER=1" >> "$CLAUDE_ENV_FILE"
	fi

	log "$("$PHPCS_BIN/phpcs" --version)"
	log "Run phpcs from INSIDE the plugin directory or it misses that plugin's"
	log "phpcs.xml and reports tens of thousands of issues out of vendor/."
else
	log "phpcs did not install; the PHP coding standards job cannot be run here"
fi

# --- JavaScript dependencies, where this repo has any -----------------------
# The tooling repository has no package.json; every plugin has one and a
# lockfile. npm ci rather than npm install, because the lockfile is the thing
# CI installs from and a silent resolution difference is what this is guarding.
if [ -f package.json ] && [ -f package-lock.json ]; then
	log "Installing JavaScript dependencies"

	if npm ci --no-audit --no-fund >/dev/null 2>&1; then
		log "npm ci clean; eslint and any vitest suite are ready"
	else
		log "npm ci failed. If it names a package as missing from the lock file,"
		log "check npm --version is 11.x before touching the lockfile: npm 10"
		log "strips the libc arrays npm 11 writes and reverses the drift it claims"
		log "to fix."
	fi
fi

# --- Docker, for wp-env -----------------------------------------------------
if command -v docker >/dev/null 2>&1; then
	if ! docker info >/dev/null 2>&1; then
		log "Starting the Docker daemon"
		( sudo -n dockerd >/tmp/dockerd.log 2>&1 & ) || true

		for _ in $(seq 1 15); do
			docker info >/dev/null 2>&1 && break
			sleep 1
		done
	fi

	docker info >/dev/null 2>&1 && log "Docker is up" || log "Docker would not start; see /tmp/dockerd.log"
fi

# --- What still will not work, said plainly ---------------------------------
log "PHPUnit and Playwright need wp-env, which downloads WordPress from"
log "*.wordpress.org -- blocked by this session's egress policy. Those two"
log "suites are CI's to run. phpcs, eslint and verify.py cover the rest."
