# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

**Run the PHP test suite (Docker is the only prerequisite):**
```bash
bin/test.sh                     # starts wp-env, installs deps in the container, runs PHPUnit
bin/test.sh --filter WP_Sweep_Sweep_Test
bin/test-multisite.sh           # the same suite as a network
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
```

**Lint PHP against WordPress Coding Standards:**
```bash
$(composer global config bin-dir --absolute)/phpcs -q .
$(composer global config bin-dir --absolute)/phpcbf -q .
```

`phpcs.xml` is shared wording rather than a per-plugin ruleset. Do not add a
local rule to it and do not add a `phpcs:ignore`: there are currently zero
inline suppressions in this plugin, and a sniff firing in `includes/` is a bug
in the code rather than a rule to silence.

**WP-CLI (requires a running WordPress install):**
```bash
wp sweep --all
wp sweep revisions auto_drafts
```

**REST API (requires the `activate_plugins` capability):**
```
GET    /wp-json/sweep/v1/count/<name>
GET    /wp-json/sweep/v1/details/<name>
DELETE /wp-json/sweep/v1/sweep/<name>
```

wp-env runs on ports 8924 (dev) and 8925 (tests); every other plugin in the
collection has its own pair.

## Architecture

### Entry point

`wp-sweep.php` carries the header, the GPL block, the six constants
(`WP_SWEEP_VERSION`, `WP_SWEEP_DB_VERSION`, `WP_SWEEP_SLUG`,
`WP_SWEEP_MAIN_FILE`, `WP_SWEEP_DIR`, `WP_SWEEP_URL`), one `require_once` and
one call. No logic.

**Never build a path or URL from the literal string `wp-sweep`.** Use
`WP_SWEEP_DIR` and `WP_SWEEP_URL`, which are derived from `__FILE__`. The plugin
has to keep working when it is installed under a different directory name, and a
hardcoded slug fails silently — the script 404s while the markup still looks
well-formed. `WP_Sweep_Admin_Test` asserts on this. `WP_SWEEP_SLUG` is the
literal slug on purpose, and nothing in `includes/` reads it any more — it is
declared for consistency with the other constants rather than because anything
needs it. Do not reach for it to save typing a literal: its last caller was
`add_command()`, and see below for why that stopped.

**The WP-CLI command and the REST namespace are bare nouns, not the slug**:
`wp sweep` and `sweep/v1`, which is what the released 1.2.0 shipped. A `wp-`
prefix is a wordpress.org directory convention rather than a naming rule for
what a plugin registers — `wp wp-sweep` stutters, and the ecosystem norm is the
brand (`wp wc`, `wp yoast`, `wp jetpack`). A bare noun is claimable by another
plugin, and that is the accepted trade. **Do not pass `WP_SWEEP_SLUG` to
`add_command()`**; `WP_Sweep_CLI_Test` fails if you do.

`sweep` is a specific enough noun to claim. Names like `email`, `print` and
`stats` are not, and a plugin registering one of those should qualify it.

### `includes/class-wp-sweep.php` — the engine

`WP_Sweep` is a singleton reached through `WP_Sweep::get_instance()`.

* `get_sweeps()` — the single canonical list: name => label, type, group. The
  REST API, the WP-CLI command, the AJAX handlers and the screen all defer to
  it. **Do not add a second copy.** The declaration order is the order the
  sweeps must run in: posts are deleted before the sweeps that hunt for the meta
  that deleting them just orphaned.
* `count()`, `details()`, `sweep()` — three parallel switches keyed on a sweep
  name. `WP_Sweep_Sweep_Names_Test` fails until all three handle a new name.
* `total_count()` — rows in a table, for the "% of" column.
* `db()` — **every database call in the plugin goes through here.** It is the
  one place that documents why a plugin hunting rows no WordPress API can see
  cannot cache them. Callers build the literal SQL and call `$wpdb->prepare()`
  themselves, so the sniffs still see a literal.

Where a list length varies, use the idiom WPCS recognises rather than
interpolating a pre-built clause:

```php
$wpdb->prepare(
	"SELECT ... WHERE x IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
	$ids
);
```

Meta key exclusions are matched **in PHP**, not with SQL `LIKE`, because `LIKE`
treats an underscore as a single-character wildcard and meta keys are full of
underscores. An empty exclusion list must never reach `NOT IN ()`, which is a
syntax error rather than a match-nothing clause.

### What WP-Sweep stores: nothing

No settings row, no version row, no tables, no capabilities, no scheduled
events. It has no settings and nothing to migrate, so it writes nothing at all —
there is no options class and no upgrade routine. `WP_Sweep_Options_Test` boots
the plugin and fails if any `wp_sweep_*` row appears.

The one tunable is the `wp_sweep_limit_details` filter, defaulting to
`WP_Sweep::DEFAULT_LIMIT_DETAILS` (500). It was briefly the single field of a
settings screen; one field does not earn a screen, an option row, a
`register_setting()` and a sanitiser.

`uninstall.php` still deletes `wp_sweep_options` and `wp_sweep_version`. Nothing
writes them now and no released version ever did, so that is purely for installs
that ran a 2.0.0 beta.

`wp_sweep_transient_options` and `wp_sweep_details_transient_options` are
**nonce actions** for the sweep named `transient_options`, built as
`'wp_sweep_' . $name`. They are neither options nor transients. No sweep is
named `options` or `version`, and `WP_Sweep_Regressions_Test` keeps it that way.

### `includes/class-wp-sweep-admin.php` and `class-wp-sweep-list-table.php`

One screen, `add_management_page()` at `tools.php?page=wp-sweep`, where the
plugin sat for its whole released life. Sweeping is maintenance against the
installation, which is what Tools is for. **This only works because there is no
settings screen** — Tools has no submenus, so a second screen would have to
become a tab of this one, putting a data screen and a settings form on the same
page. That was tried and reverted; do not propose it again without reading why.

The rows come from `WP_Sweep_List_Table`: pagination at 20, sortable columns,
group views, a bulk sweep and a `no_items()` message. Every sweep carries a
`description` in `get_sweeps()`, rendered under its name.

**Sweep and Details are a column, not row actions.** WordPress hides row actions
until hover, which is right when the row is the subject and the actions are
secondary. Here the action is the whole point, and hover reaches nothing on a
touch screen. Do not move them back.

**Every row has a checkbox, including a row with nothing to sweep.** Withholding
it left gaps down the column and made the header select-all claim rows it did
not select, and it never prevented a no-op anyway — the count is a snapshot from
when the page rendered.

**The bulk form carries exactly one nonce.** `WP_List_Table::display_tablenav()`
prints `wp_nonce_field()` for `bulk-sweeps` itself, in a field named `_wpnonce`.
Printing a second `_wpnonce` beside it does not add a check, it replaces one —
PHP keeps the last field of a repeated name — and every bulk sweep failed with
"The link you followed has expired". Verify `bulk_nonce_action()`, never a
nonce of the screen's own.

**Every path works without JavaScript.** The buttons are real nonced links and
the bulk action is a real form post, both handled in `handle_request()`. The
script intercepts them so the screen updates in place. Do not add a control that
only works with the script running.

**A finished sweep reports into one region, found by id and nothing else.**
`WP_Sweep_Admin::MESSAGE_ID` is printed once, on a `role="status"` div above the
form, and every Sweep row action carries the same id in `aria-controls`; the
script resolves the region through that attribute. It used to find it by walking
back through `.table-sweep`'s previous siblings, which cannot work on this
screen — the table is inside the `<form>` and the region is outside it, so the
walk ran out of siblings inside the form, `showMessage()` returned early, and
**no sweep reported its result at all.** That was true for the whole of the
2.0.0 rewrite and only Playwright caught it. Do not print a second region, and
do not replace the attribute with a walk: the distance between the two elements
is a thing the markup is free to change, and the id is not.

**Details entries are database values.** The script writes them with
`textContent` and the no-JavaScript path escapes them. Comment author names are
supplied by whoever left the comment; building that list with string
concatenation is how the stored XSS fixed in 2.0.0 got there.

`settings_errors()` prints its message unescaped, so anything handed to
`add_settings_error()` is escaped first — sweep results come back through the
public `wp_sweep_sweep` filter.

### Capabilities

The sweep surface takes `activate_plugins` (`update_plugins` returns false when
`DISALLOW_FILE_MODS` is set, which took the screen away from the administrators
who needed it). It resolves through one filter, `wp_sweep_capability`, with a
context: `sweep`, `ajax` or `rest`.

**Under multisite that capability is not what it looks like.** Core's
`map_meta_cap()` adds `manage_network_plugins` to `activate_plugins` unless the
network admin has delegated the Plugins menu, which is not delegated by default,
so an ordinary site administrator cannot sweep. That is correct for something
that deletes data. Tests go through `WP_Sweep_Creates_Admins::create_admin()`,
which grants super admin when `is_multisite()`; do not weaken the gate to make a
test pass.

### Hooks

Eighteen, all prefixed `wp_sweep_`, all carrying a `@since`. Sixteen shipped
before 2.0.0 and are public API — **do not rename them.**
`WP_Sweep_Filters_Test` pins the exact set.

### Adding a new sweep

1. Add an entry to `WP_Sweep::get_sweeps()`, in the position it must run in,
   **with a `description`** saying what it removes. These rows delete data that
   does not come back, and a label like "Orphaned Term Relationships" tells a
   site owner nothing about whether it is safe to tick.
2. Add a `case` for it in `count()`, `details()` and `sweep()`.
3. Add its table type to `get_sweep_types()` and `total_count()` if it is new.
4. `WP_Sweep_Sweep_Names_Test` fails until all three switches handle it, and
   `test_every_sweep_carries_a_description` until it has one. The screen needs
   no edit — the rows are generated.

## Testing

`tests/` holds the PHPUnit suite against a real MySQL database via wp-env, plus
vitest/jsdom tests for the script. Test files are `test-*.php` — **discovery is
by that prefix, so a misnamed file is silently not run.** Every other file in
`tests/` is `helper-*.php`. Classes are `WP_Sweep_<Area>_Test` extending
`WP_Sweep_TestCase` in `helper-testcase.php`.

Notes that will save time:

- Tests reach the plugin through the `sweep()` accessor on `WP_Sweep_TestCase`
  rather than naming the class, so a rename is one line.
- Counts are asserted as deltas against a baseline captured per test, because
  the database is shared and may already hold a stray orphan row.
- `source_without_comments()` strips comments before any assertion about the
  source. A docblock explaining why something was removed otherwise reads as the
  thing itself.
- `$wp_scripts` survives the transaction rollback between tests and has to be
  reset, or a handle enqueued by one test is still enqueued in the next. So does
  `$wp_settings_errors`; `render_admin_page()` clears it.
- A `WP_List_Table` reaches `WP_Screen::get()`, so a test touching one has to
  call `set_current_screen()` first.
- The AJAX tests go through `_handleAjax()`, not the handler directly: the test
  case's die handler is what closes the buffer `_handleAjax()` opened. Catch
  `WPDieException`, the parent — `wp_die()` only throws the `WPAjaxDie*`
  subclasses while `wp_doing_ajax()` is true, and the plain parent escapes a
  catch list naming only the subclasses.
- **`_handleAjax()` fires `admin_init`, and core hangs its update checks off
  that hook.** They call api.wordpress.org, which the container cannot reach,
  and `convertWarningsToExceptions` turns core's complaint into a test error.
  `WP_Sweep_Ajax_Test::set_up()` removes `_maybe_update_core`,
  `_maybe_update_plugins` and `_maybe_update_themes` for this reason. Without it
  the suite fails perhaps one run in four, always in a different AJAX test,
  never the same one twice.
- **Run the suite in random order when chasing an intermittent failure.** It is
  what turned that one into a reproducible bug:
  `bin/test.sh --order-by=random --random-order-seed=5714`. PHPUnit prints the
  seed on every run, so a failure can be replayed exactly.
- `phpunit.xml.dist` turns on `beStrictAboutTestsThatDoNotTestAnything`,
  `failOnWarning` and `failOnRisky`. A test without an assertion is fatal.
- **`tests/js/helpers.js` is a transcription of what the screen renders, not a
  convenient DOM.** `sweepSection()` must stay the shape `render_page()` and
  `WP_List_Table::display()` actually produce, down to the form, the tablenav
  and the table's own select-all checkboxes — an unscoped
  `querySelector( 'input[type="checkbox"]' )` finds the header's, not the row's.
  The fixture that hid the message-container bug was a plausible two-element DOM
  the plugin has never emitted: the tests agreed with the script, the script
  agreed with the tests, and neither agreed with the screen.
  `tests/js/fixture.test.js` now pins the fixture to the PHP that produces it —
  the id, the `aria-controls`, the region outside the form and the table inside
  — and `WP_Sweep_Admin_Test::test_the_sweep_message_region_is_outside_the_form()`
  asserts the same shape against real rendered output. Change the screen's
  markup and those two are what tell you the fixture has gone stale.
- Playground is not used here. It is SQLite, and this plugin is almost entirely
  `SHOW TABLES`, `OPTIMIZE TABLE`, `GROUP_CONCAT`, `HAVING` and correlated
  `NOT IN` subqueries.

## Known gap

Roughly 290 of the suite's 1,100-odd assertions still carry no failure message.
Every assertion should carry one — not only the ones PHPUnit would report
opaquely — and new or rewritten tests here do. The older ones are the backlog.
