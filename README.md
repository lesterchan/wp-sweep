# WP-Sweep
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: sweep, cleanup, optimize, database, revisions  
Requires at least: 6.8  
Tested up to: 7.0  
Stable tag: 2.0.0  
Requires PHP: 8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WP-Sweep allows you to clean up unused, orphaned and duplicated data in your WordPress. It also optimizes your database tables.

## Description
WP-Sweep finds the rows WordPress leaves behind and removes them. Revisions of posts you edited years ago, meta belonging to a post that was deleted, terms attached to nothing, term relationships pointing at posts that no longer exist, expired transients: none of it is visible anywhere in wp-admin, and all of it is in your database.

It uses the proper WordPress delete functions wherever they can reach the row, rather than running raw delete queries, so the hooks other plugins rely on still fire. Only the rows the API refuses to touch — orphaned meta whose object ID is `0` — are deleted directly.

### Features
* Revisions
* Auto drafts
* Deleted posts
* Unapproved comments
* Spammed comments
* Deleted comments
* Orphaned post meta
* Orphaned comment meta
* Orphaned user meta
* Orphaned term meta
* Orphaned term relationships
* Unused terms
* Duplicated post meta
* Duplicated comment meta
* Duplicated user meta
* Duplicated term meta
* Transient options
* oEmbed caches in post meta
* Optimizes database tables

### Delete functions used
* `wp_delete_post_revision()`
* `wp_delete_post()`
* `wp_delete_comment()`
* `delete_post_meta()`
* `delete_comment_meta()`
* `delete_user_meta()`
* `delete_term_meta()`
* `wp_remove_object_terms()`
* `wp_delete_term()`
* `delete_transient()`
* `delete_site_transient()`

### Known incompatibilities
These plugins keep data in places WP-Sweep reads as orphaned. Protect their meta keys with the filters under Usage before sweeping.

* [Custom Fonts](https://wordpress.org/plugins/custom-fonts/)
* [Elementor Popup Builder](https://elementor.com/features/popup-builder/)
* [MailPress](https://wordpress.org/plugins/mailpress/)
* [Meta Slider](https://wordpress.org/support/plugin/ml-slider/)
* [Polylang](https://wordpress.org/plugins/polylang/)
* [Slider Revolution](https://revolution.themepunch.com/)
* [Viba Portfolio](https://codecanyon.net/item/viba-portfolio-wordpress-plugin/9561599)
* [WPML](https://wpml.org/)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Usage
Upload the plugin folder to `/wp-content/plugins/`, activate it through the Plugins menu, and the screen appears at **Tools -> WP-Sweep**. Every sweep is irreversible, so back your database up first.

The screen lists every sweep with a short description of what it removes, how many items that is, and what proportion of the table they are. Tick the ones you want and use the **Sweep** bulk action, or use each row's **Sweep** and **Details** buttons one at a time. Nothing on the screen needs JavaScript: the buttons are ordinary links and the bulk action is an ordinary form.

There is no settings screen. The one thing worth tuning — how many items **Details** lists, 500 by default — is the `wp_sweep_limit_details` filter. The cap exists because the whole sample is held in memory and written into the page.

### WP-CLI
```
wp sweep --all
wp sweep revisions
wp sweep revisions auto_drafts deleted_posts
```

### REST API
All three routes need the `activate_plugins` capability.

```
GET    /wp-json/sweep/v1/count/<name>
GET    /wp-json/sweep/v1/details/<name>
DELETE /wp-json/sweep/v1/sweep/<name>
```

### Item names
`revisions`, `auto_drafts`, `deleted_posts`, `unapproved_comments`, `spam_comments`, `deleted_comments`, `transient_options`, `orphan_postmeta`, `orphan_commentmeta`, `orphan_usermeta`, `orphan_termmeta`, `orphan_term_relationships`, `unused_terms`, `duplicated_postmeta`, `duplicated_commentmeta`, `duplicated_usermeta`, `duplicated_termmeta`, `optimize_database`, `oembed_postmeta`.

### Filters
* `wp_sweep_postmeta_whitelist` (array) — post meta keys that must never be deleted, by the orphaned or the duplicated post meta sweep. `*` matches any run of characters. Default: empty.
* `wp_sweep_commentmeta_whitelist` (array) — the same, for comment meta.
* `wp_sweep_usermeta_whitelist` (array) — the same, for user meta.
* `wp_sweep_termmeta_whitelist` (array) — the same, for term meta.
* `wp_sweep_excluded_taxonomies` (array) — taxonomies left out of the orphaned term relationships sweep. Default: `array( 'link_category' )`.
* `wp_sweep_excluded_termids` (array) — term IDs left out of the unused terms sweep. Default: each taxonomy's default term, plus any term that is the parent of another.
* `wp_sweep_limit_details` (int) — how many items a Details list shows. Default: 500.
* `wp_sweep_capability` (string `$capability`, string `$context`) — the capability required. `$context` is one of `sweep`, `ajax` or `rest`.
* `wp_sweep_total_count` (int `$count`, string `$name`) — the total number of rows a sweep's percentage is measured against.
* `wp_sweep_count` (int `$count`, string `$name`) — how many items a sweep would remove.
* `wp_sweep_details` (array `$details`, string `$name`) — the sample list shown by Details.
* `wp_sweep_sweep` (string `$message`, string `$name`) — the message reported after a sweep has run.

### Actions
`wp_sweep_admin_post_sweep`, `wp_sweep_admin_comment_sweep`, `wp_sweep_admin_user_sweep`, `wp_sweep_admin_term_sweep`, `wp_sweep_admin_option_sweep` and `wp_sweep_admin_database_sweep` all fire below the sweep table, in that order.

Protect specific post meta keys from being swept:

```php
add_filter( 'wp_sweep_postmeta_whitelist', function ( $meta_keys ) {
	$meta_keys[] = '_my_plugin_setting';
	$meta_keys[] = '_acme_*';
	return $meta_keys;
} );
```

Exclude an additional taxonomy from the orphaned term relationships sweep:

```php
add_filter( 'wp_sweep_excluded_taxonomies', function ( $taxonomies ) {
	$taxonomies[] = 'product_type';
	return $taxonomies;
} );
```

## Frequently Asked Questions

### The Tools -> Sweep screen has a new address

It is still under **Tools**, but the address changed from `tools.php?page=wp-sweep/admin.php` to `tools.php?page=wp-sweep`. Update any bookmark.

The old address was the legacy "plugin file as menu slug" form, which put the plugin's installation directory name into the page URL. That is also why the screen used to break for anyone who installed WP-Sweep under a different directory name — renamed by hand, or unzipped as `wp-sweep-2.0.0`. Neither happens now.

### Where did Sweep All go?

Every row has a checkbox and the table has a **Sweep** bulk action, so ticking the header checkbox and applying it does what Sweep All did — and lets you leave out the ones you do not want.

### My snippet calling WPSweep::get_instance() stopped working

The classes are renamed in 2.0.0:

* `WPSweep` is now `WP_Sweep`
* `WPSweep_Api` is now `WP_Sweep_API`
* `WPSweep_Command` is now `WP_Sweep_Command`

So `WPSweep::get_instance()->sweep( 'revisions' )` becomes `WP_Sweep::get_instance()->sweep( 'revisions' )`. There is no compatibility alias, so the old name raises a fatal error rather than failing quietly.

Every filter and every `wp_sweep_admin_*_sweep` action keeps the name it had.

### Which filters can I use to protect data from being swept?

Four per-type filters take a list of meta keys that must never be deleted, and each supports `*` as a wildcard:

```php
add_filter( 'wp_sweep_postmeta_whitelist', function ( $keys ) {
	$keys[] = '_my_plugin_setting';
	$keys[] = '_acme_*';
	return $keys;
} );
```

The same applies to `wp_sweep_commentmeta_whitelist`, `wp_sweep_usermeta_whitelist` and `wp_sweep_termmeta_whitelist`. Terms are protected with `wp_sweep_excluded_termids`, and taxonomies are kept out of the orphaned term relationships sweep with `wp_sweep_excluded_taxonomies`.

In 2.0.0 these lists also protect the **duplicated** meta sweeps, which the documentation always said they did and the code never did.

### A key I protected was swept anyway, or far too much was kept

Before 2.0.0 the exclusion was a SQL `LIKE` clause, and `LIKE` treats an underscore as a single-character wildcard. A pattern of `_my_key` therefore also matched `Xmy!key`, and a good deal else. Matching happens in PHP now, so an underscore is an underscore and only `*` is a wildcard. Check your list if you were relying on the old behaviour.

### Does WP-Sweep leave anything behind when I delete it?

Nothing. WP-Sweep stores no option rows, creates no database tables, registers no capabilities and schedules no events. `uninstall.php` runs anyway and sweeps up after itself across a whole network rather than the first hundred sites.

## Screenshots
1. Tools -> WP-Sweep, listing every sweep with what it removes
2. The same screen after a bulk sweep
3. A Details list, showing a sample of what a sweep would remove

## Changelog
### 2.0.0
* BREAKING: Requires WordPress 6.8 and PHP 8.2, up from 6.0 and 7.4.
* BREAKING: The screen stays under Tools but its address changed, from `tools.php?page=wp-sweep/admin.php` to `tools.php?page=wp-sweep`. The old form put the installation directory name into the URL.
* BREAKING: The classes are renamed `WPSweep` -> `WP_Sweep`, `WPSweep_Api` -> `WP_Sweep_API` and `WPSweep_Command` -> `WP_Sweep_Command`. Every filter and action keeps its name.
* BREAKING: `WP_Sweep::$limit_details` is gone. Read it with `limit_details()` and change it with the `wp_sweep_limit_details` filter.
* BREAKING: The six `wp_sweep_admin_*_sweep` actions fire below the single sweep table, in the order they always had, rather than below six separate ones.
* BREAKING: Sweep All is gone. Tick the header checkbox and apply the `Sweep` bulk action instead, which does the same thing and lets you leave rows out.
* NEW: Bulk sweeping. Tick the sweeps you want and run them in one go instead of one click at a time.
* NEW: Group filters, sortable columns and pagination, from a real `WP_List_Table`.
* NEW: The whole screen works with JavaScript turned off. The row actions are ordinary nonced links and the bulk action is an ordinary form post.
* NEW: The meta key whitelists protect the duplicated meta sweeps as well as the orphaned ones, which the readme always claimed and the code never did.
* NEW: `wp_sweep_capability` and `wp_sweep_limit_details` filters. WP-Sweep has no settings screen: `wp_sweep_limit_details` is how the Details cap is changed.
* NEW: Every sweep carries a description of what it removes, shown under its name.
* NEW: An `uninstall.php` that cleans up across a whole network rather than the first hundred sites. WP-Sweep itself stores nothing: no option rows, no tables, no capabilities and no scheduled events.
* NEW: Restructured into `includes/`, following the Plugin Handbook.
* NEW: PHPUnit and vitest suites, and GitHub Actions CI across six WordPress and PHP combinations, single site and multisite.
* CHANGED: Meta key exclusions are matched in PHP rather than with SQL `LIKE`, so an underscore in a protected key is an underscore rather than a wildcard.
* CHANGED: Every database call goes through one method, and every query is prepared.
* FIXED: A stored XSS on the admin screen. The Details list was built by string concatenation and injected as HTML, so a comment author name containing markup ran as script in the administrator's browser.
* FIXED: The plugin no longer builds paths from its own directory name, so the admin script loads when it is installed under a directory other than `wp-sweep`.
* FIXED: Filtering `wp_sweep_excluded_termids` to an empty array produced invalid SQL, leaving the Unused Terms count blank.
* FIXED: A term was excluded from the Unused Terms sweep whenever its ID matched a `default_<taxonomy>` option, even if that option pointed at a term that no longer exists. WordPress ships `default_link_category` set to 2, so on most sites whatever term held ID 2 quietly refused to sweep.
* FIXED: Sweeping without JavaScript deleted data and displayed nothing; the result is now shown.
* FIXED: The multisite uninstall loop stopped at 100 sites, hydrated whole site objects to read one column, and left the switch stack unwound by one.
* FIXED: Request parameters are sanitized and validated against the plugin's own list of sweeps.

## Upgrade Notice

### 2.0.0

Requires WordPress 6.8 and PHP 8.2.

**The screen's address changed**, though it is still at **Tools -> WP-Sweep**: `tools.php?page=wp-sweep/admin.php` is now `tools.php?page=wp-sweep`. The old form embedded the plugin's folder name, which broke the screen for anyone who installed WP-Sweep under a different one.

**Sweep All is gone.** Every row has a checkbox; tick the one in the header and apply the **Sweep** bulk action for the same effect, with the option of leaving rows out.

**Code holding the singleton needs editing.** `WPSweep::get_instance()` is now `WP_Sweep::get_instance()`, and the `$sweep->limit_details` property is now the `$sweep->limit_details()` method. The old spellings fail rather than falling back. Filter and action names are unchanged, as are the `wp sweep` command and the `/wp-json/sweep/v1/` routes.

**Two sweeps remove less than they did, deliberately.** Meta keys protected by the whitelist filters are honoured by the duplicated meta sweeps as well as the orphaned ones, which is what the documentation always said. Matching also moved out of SQL, so an underscore in a protected key is matched literally rather than as a wildcard: `_my_key` protects that key and nothing else. Review your list if you relied on the old behaviour.
