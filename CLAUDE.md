# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

**Install dev dependencies (PHP coding standards):**
```bash
composer install
```

**Lint PHP against WordPress Coding Standards:**
```bash
./vendor/bin/phpcs --standard=WordPress-Core,WordPress-Docs,WordPress-Extra wp-sweep.php inc/ admin.php
```

**Fix auto-fixable coding standards violations:**
```bash
./vendor/bin/phpcbf --standard=WordPress-Core,WordPress-Docs,WordPress-Extra wp-sweep.php inc/ admin.php
```

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

There is no automated test suite. The JS minified file (`js/wp-sweep.min.js`) must be updated manually when `js/wp-sweep.js` changes.

## Architecture

### Entry point and bootstrapping

`wp-sweep.php` defines `WP_SWEEP_VERSION` and `WP_SWEEP_MAIN_FILE`, then requires the two core class files and instantiates them. `WPSweep` is a singleton; `WPSweep_Api` is instantiated directly in the entry point.

### Core class: `inc/class-wpsweep.php`

`WPSweep` is a singleton accessed via `WPSweep::get_instance()`. All sweep logic lives in three parallel `switch` statements keyed on a string sweep name:

- `count($name)` — returns how many items would be swept
- `details($name)` — returns up to `$limit_details` (500) sample items
- `sweep($name)` — performs the deletion and returns a translated result message
- `total_count($name)` — counts total rows in a given table (used for the "% of" column)

When `post_id`, `comment_id`, `user_id`, or `term_id` is `0` in orphaned meta, a direct SQL `DELETE` is used instead of the WordPress API functions, because the API functions won't act on ID 0.

Two filters control what gets excluded from certain sweeps:
- `wp_sweep_excluded_taxonomies` — taxonomies excluded from orphaned term relationships check (default: `link_category`)
- `wp_sweep_excluded_termids` — term IDs excluded from unused terms sweep (default: default taxonomy terms + terms that are parents of other terms)

### REST API: `inc/class-wpsweep-api.php`

Registers three routes under the `sweep/v1` namespace. All routes require `activate_plugins` capability. The `name` parameter is validated against the hardcoded `$sweeps` array. All routes delegate to the `WPSweep` singleton.

### WP-CLI: `inc/class-wpsweep-command.php`

Loaded and registered only when `WP_CLI` is defined. Iterates the same hardcoded list of sweep names in order, skipping items with a count of 0.

### Admin UI: `admin.php` + `js/wp-sweep.js`

`admin.php` is a template file loaded by `add_management_page` (Tools → Sweep). It calls `count()` and `total_count()` on page load to populate counts. Buttons carry `data-sweep_name`, `data-sweep_type`, and `data-nonce` attributes.

`wp-sweep.js` uses jQuery AJAX against `wp-admin/admin-ajax.php` with actions `sweep` and `sweep_details`. Nonces follow the pattern `wp_sweep_{name}` (sweep) and `wp_sweep_details_{name}` (details). "Sweep All" chains individual sweep promises sequentially using `.reduce()`.

### Adding a new sweep type

1. Add the name string to the `$sweeps` array in `WPSweep_Api` and `$default_items` in `WPSweep_Command`.
2. Add a `case` for the name in `WPSweep::count()`, `details()`, and `sweep()`.
3. Add a `case` for the related table type in `WPSweep::total_count()` if needed.
4. Add the corresponding row to `admin.php` with the correct `data-sweep_type` matching a `total_count()` key.
