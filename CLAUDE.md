# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

**Run the PHP test suite (Docker is the only prerequisite):**
```bash
bin/test.sh                     # starts wp-env, installs deps in the container, runs PHPUnit
bin/test.sh --filter Test_WP_Sweep_Sweep
```

**Measure coverage** (needs Xdebug, so the environment has to be restarted):
```bash
npx @wordpress/env start --xdebug=coverage
bin/test.sh --coverage-filter includes --coverage-text
npx @wordpress/env start          # back to normal, Xdebug is slow
```

**Run the script tests and linters:**
```bash
npm install
npm run test:js                 # vitest + jsdom
npm run lint:js                 # eslint
npm run lint:js:fix
npm test                        # JS tests, then the PHP suite
```

**Lint PHP against WordPress Coding Standards:**
```bash
phpcs                           # phpcs.xml covers the whole tree; must exit 0
phpcbf                          # auto-fix, formatting only
```

`phpcs.xml` records the reason for every exclusion. Add the reason there rather
than scattering `phpcs:ignore` comments.

**WP-CLI sweep commands (requires a running WordPress install):**
```bash
wp sweep --all                        # Sweep everything
wp sweep revisions auto_drafts        # Sweep specific items
```

**REST API endpoints (requires authentication with `activate_plugins` capability):**
```
GET    /wp-json/sweep/v1/count/<name>
GET    /wp-json/sweep/v1/details/<name>
DELETE /wp-json/sweep/v1/sweep/<name>
```

wp-env runs on ports 8898 (dev) and 8899 (tests); every other plugin in the
collection has its own pair.

## Architecture

### Entry point and bootstrapping

`wp-sweep.php` carries the plugin header, defines `WP_SWEEP_VERSION`,
`WP_SWEEP_MAIN_FILE`, `WP_SWEEP_DIR`, `WP_SWEEP_URL` and `WP_SWEEP_SLUG`, then
requires the two core class files and instantiates them. `Sweep` is a singleton;
`Sweep_Api` is instantiated directly. There is no logic in the main file.

**Never build a path or URL from the literal string `wp-sweep`.** Use the
constants. The plugin has to keep working when it is installed under a different
directory name, and a hardcoded slug fails silently — the script 404s while the
markup still looks perfectly well-formed. `Test_WP_Sweep_Admin` asserts on this.

### Core class: `includes/class-sweep.php`

`Sweep` is a singleton accessed via `Sweep::get_instance()`. All sweep logic lives
in three parallel `switch` statements keyed on a string sweep name:

- `count($name)` — returns how many items would be swept
- `details($name)` — returns up to `$limit_details` (500) sample items
- `sweep($name)` — performs the deletion and returns a translated result message
- `total_count($name)` — counts total rows in a given table (used for the "% of" column)

`get_sweep_names()` is the single canonical list of the nineteen sweeps. The REST
API and the WP-CLI command both defer to it — do not add a second copy.

When `post_id`, `comment_id`, `user_id`, or `term_id` is `0` in orphaned meta, a
direct SQL `DELETE` is used instead of the WordPress API functions, because the
API functions won't act on ID 0.

The hook suffix for the admin screen is recorded from what `add_management_page()`
returns, never hardcoded: `get_plugin_page_hookname()` derives the prefix from
`$admin_page_hooks`, so the same slug produces `tools_page_wp-sweep` on a real
admin request and `admin_page_wp-sweep` where the admin menu has not been built.

Filters that control what gets excluded:
- `wp_sweep_excluded_taxonomies` — taxonomies excluded from orphaned term relationships (default: `link_category`)
- `wp_sweep_excluded_termids` — term IDs excluded from unused terms sweep (default: default taxonomy terms + parent terms)
- `wp_sweep_postmeta_whitelist`, `wp_sweep_commentmeta_whitelist`, `wp_sweep_usermeta_whitelist`, `wp_sweep_termmeta_whitelist` — meta keys never deleted, `*` wildcard supported

An empty exclusion list must produce no SQL clause at all. Interpolating one into
`NOT IN ()` is a syntax error, not a match-nothing clause.

### REST API: `includes/class-sweep-api.php`

Registers three routes under the `sweep/v1` namespace. All routes require
`activate_plugins`. The `name` parameter is validated against
`Sweep::get_sweep_names()`.

### WP-CLI: `includes/class-sweep-command.php`

Loaded and registered only when `WP_CLI` is defined. Iterates
`Sweep::get_sweep_names()` in order, skipping items with a count of 0. The order
matters: posts are deleted before the sweeps that hunt for the meta that deletion
just orphaned.

### Admin UI: `includes/admin.php` + `js/wp-sweep.js`

`includes/admin.php` is a template rendered by `Sweep::admin_page()`, the callback
registered with `add_management_page()` under the menu slug `wp-sweep`. It calls
`count()` and `total_count()` on page load. Buttons carry `data-sweep_name`,
`data-sweep_type`, and `data-nonce`.

`js/wp-sweep.js` is vanilla JavaScript — no jQuery, no minified twin, no build
step. It uses `fetch` against `admin-ajax.php` with actions `sweep` and
`sweep_details`. Nonces follow `wp_sweep_{name}` and `wp_sweep_details_{name}`.
Strings are localised as `wpSweepL10n`.

**Details entries are database values and must be written with `textContent`.**
Comment author names are supplied by whoever left the comment. Building that list
with string concatenation is how the stored XSS fixed in 2.0.0 got there.

### Adding a new sweep type

1. Add the name to `Sweep::get_sweep_names()`.
2. Add a `case` for it in `count()`, `details()`, and `sweep()`.
3. Add a `case` for the related table type in `total_count()` if needed.
4. Add the row to `includes/admin.php` with a `data-sweep_type` matching a `total_count()` key.
5. `Test_WP_Sweep_Sweep_Names` fails until all three switches handle it.

## Testing

`tests/` holds 296 PHPUnit tests against a real MySQL database via wp-env, plus 27
vitest/jsdom tests for the script. Line coverage of `includes/` is ~95%; the
remainder is `__construct`, `get_instance`, `init` and `add_hooks`, which all run
during bootstrap before the coverage driver starts. CI runs phpcs, eslint, vitest and PHPUnit on
WP 6.0/PHP 7.4 and WP latest/PHP 8.3.

Notes that will save time:

- Tests reach the plugin through the `sweep()` accessor on `WP_Sweep_TestCase`
  rather than naming the class, so a rename is one line.
- Counts are asserted as deltas against a baseline captured per test, because the
  database is shared and may already hold a stray orphan row.
- `source_without_comments()` strips comments before any assertion about the
  source. A docblock explaining why something was removed otherwise reads as the
  thing itself.
- `$wp_scripts` survives the transaction rollback between tests and has to be
  reset, or a handle enqueued by one test is still enqueued in the next.
- The AJAX tests go through `_handleAjax()`, not the handler directly: the test
  case's die handler is what closes the buffer `_handleAjax()` opened. Catch
  `WPDieException`, the parent — `wp_die()` only throws the `WPAjaxDie*`
  subclasses while `wp_doing_ajax()` is true, and the plain parent escapes a
  catch list naming only the subclasses.
- **`_handleAjax()` fires `admin_init`, and core hangs its update checks off
  that hook.** They call api.wordpress.org, which the container cannot reach,
  and `convertWarningsToExceptions` turns core's complaint into a test error.
  `Test_WP_Sweep_Ajax::set_up()` removes `_maybe_update_core`,
  `_maybe_update_plugins` and `_maybe_update_themes` for this reason. Without
  it the suite fails perhaps one run in four, always in a different AJAX test,
  never the same one twice.
- **Run the suite in random order when chasing an intermittent failure.** It is
  what turned that one into a reproducible bug:
  `bin/test.sh --order-by=random --random-order-seed=5714`. PHPUnit prints the
  seed on every run, so a failure can be replayed exactly.
- Playground is not used here. It is SQLite, and this plugin is almost entirely
  `SHOW TABLES`, `OPTIMIZE TABLE`, `GROUP_CONCAT`, `HAVING` and correlated
  `NOT IN` subqueries.
