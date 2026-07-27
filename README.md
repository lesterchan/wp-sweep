# WP-Sweep
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: sweep, cleanup, optimize, database, revisions  
Requires at least: 6.0  
Tested up to: 7.0  
Stable tag: 2.0.0  
Requires PHP: 7.4  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WP-Sweep allows you to clean up unused, orphaned and duplicated data in your WordPress. It also optimizes your database tables.

## Description
This plugin cleans up: 

* Revisions
* Auto drafts
* Deleted comments
* Unapproved comments
* Spammed comments
* Deleted comments
* Orphaned post meta
* Orphaned comment meta
* Orphaned user meta
* Orphaned term meta
* Orphan term relationships
* Unused terms
* Duplicated post meta
* Duplicated comment meta
* Duplicated user meta
* Duplicated term meta
* Transient options
* Optimizes database tables
* oEmbed caches in post meta

This plugin uses proper WordPress delete functions as much as possible instead of running direct delete MySQL queries.

Following delete functions are used:

* wp_delete_post_revision()
* wp_delete_post()
* wp_delete_comment()
* delete_post_meta()
* delete_comment_meta()
* delete_user_meta()
* delete_term_meta()
* wp_remove_object_terms()
* wp_delete_term()
* delete_transient()
* delete_site_transient()

WP-Sweep WP REST API Endpoints
* `GET /wp-json/sweep/v1/count/<Name>`. Get the number of items that we will be sweeping.
* `GET /wp-json/sweep/v1/details/<Name>`. Get the details of the items that we will be sweeping.
* `DELETE /wp-json/sweep/v1/sweep/<Name>`. Runs sweep for that particular item.

WP-Sweep WP-CLI Commands
* `wp sweep --all`. Runs sweep for all items.
* `wp sweep <Name>`. Runs sweep for that particular item.
* `wp sweep <Name1> <Name2>`. Run sweep for the selected items.

WP-Sweep Available Items:
* revisions
* auto_drafts
* deleted_posts
* unapproved_comments
* spam_comments
* deleted_comments
* transient_options
* orphan_postmeta
* orphan_commentmeta
* orphan_usermeta
* orphan_termmeta
* orphan_term_relationships
* unused_terms
* duplicated_postmeta
* duplicated_commentmeta
* duplicated_usermeta
* duplicated_termmeta
* optimize_database
* oembed_postmeta

WP-Sweep Available Filters:

WP-Sweep exposes a number of filters so you can customise what gets swept and protect data you do not want deleted.

* `wp_sweep_postmeta_whitelist` (array) — Meta keys to exclude when sweeping orphaned and duplicated **post** meta. Return an array of `meta_key` values to keep them from being deleted. Default: empty array.
* `wp_sweep_commentmeta_whitelist` (array) — Meta keys to exclude when sweeping orphaned and duplicated **comment** meta. Default: empty array.
* `wp_sweep_usermeta_whitelist` (array) — Meta keys to exclude when sweeping orphaned and duplicated **user** meta. Default: empty array.
* `wp_sweep_termmeta_whitelist` (array) — Meta keys to exclude when sweeping orphaned and duplicated **term** meta. Default: empty array.
* `wp_sweep_excluded_taxonomies` (array) — Taxonomies to exclude from the orphaned term relationships sweep. Default: `array( 'link_category' )`.
* `wp_sweep_excluded_termids` (array) — Term IDs to exclude from the unused terms sweep. Default: the default taxonomy terms plus any term that is a parent of another term.
* `wp_sweep_total_count` (int `$count`, string `$name`) — Filter the total number of rows reported for a given sweep type (used for the "% of" column).
* `wp_sweep_count` (int `$count`, string `$name`) — Filter the number of items that will be swept for a given sweep name.
* `wp_sweep_details` (array `$details`, string `$name`) — Filter the sample list of items shown in the details view for a given sweep name.
* `wp_sweep_sweep` (string `$message`, string `$name`) — Filter the result message returned after a sweep has run.

Example — protect specific post meta keys from being swept:

```php
add_filter( 'wp_sweep_postmeta_whitelist', function ( $meta_keys ) {
	$meta_keys[] = '_my_plugin_setting';
	$meta_keys[] = '_keep_this_meta';
	return $meta_keys;
} );
```

Example — exclude an additional taxonomy from the orphaned term relationships sweep:

```php
add_filter( 'wp_sweep_excluded_taxonomies', function ( $taxonomies ) {
	$taxonomies[] = 'product_type';
	return $taxonomies;
} );
```

WP-Sweep is not compatible with the following plugins:
* [Custom Fonts](https://wordpress.org/plugins/custom-fonts/)
* [Elementor Popup Builder](https://elementor.com/features/popup-builder/)
* [MailPress](https://wordpress.org/plugins/mailpress/)
* [Meta Slider](https://wordpress.org/support/plugin/ml-slider/)
* [Polylang](https://wordpress.org/plugins/polylang/)
* [Slider Revolution](https://revolution.themepunch.com/)
* [Viba Portfolio](https://codecanyon.net/item/viba-portfolio-wordpress-plugin/9561599)
* [WPML](https://wpml.org/)

### Development
* [https://github.com/lesterchan/wp-sweep](https://github.com/lesterchan/wp-sweep "https://github.com/lesterchan/wp-sweep")

### Credits
* Plugin icon by [Freepik](https://www.freepik.com) from [Flaticon](https://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog
### 2.0.0
* IMPORTANT: The admin screen moved from `tools.php?page=wp-sweep/admin.php` to `tools.php?page=wp-sweep`. The Tools -> Sweep menu link is unaffected; update any bookmark you have. See the FAQ.
* IMPORTANT: The classes are renamed `WPSweep` -> `Sweep`, `WPSweep_Api` -> `Sweep_Api` and `WPSweep_Command` -> `Sweep_Command`. Code calling `WPSweep::get_instance()` must be updated. Every filter, action, REST route and WP-CLI command is unchanged. See the FAQ.
* IMPORTANT: Requires WordPress 6.0 and PHP 7.4.
* SECURITY: Fixed a stored XSS on the admin screen. The Details list was built by string concatenation and injected as HTML, so a comment author name containing markup ran as script in the administrator's browser.
* FIXED: The plugin no longer builds paths from its own directory name, so the admin script loads when it is installed under a directory other than `wp-sweep`.
* FIXED: Filtering `wp_sweep_excluded_termids` to an empty array produced invalid SQL, leaving the Unused Terms count blank.
* FIXED: A term was excluded from the Unused Terms sweep whenever its ID matched a `default_<taxonomy>` option, even if that option pointed at a term that no longer exists. WordPress ships `default_link_category` set to 2, so on most sites whatever term held ID 2 quietly refused to sweep.
* FIXED: Sweeping without JavaScript deleted data and displayed nothing; the result is now shown.
* FIXED: Removed activation, deactivation and uninstall routines that looped over a multisite network to call empty functions, capped at 100 sites and leaving the switch stack unwound.
* NEW: Rewrote the admin script in vanilla JavaScript. jQuery is no longer loaded, and `js/wp-sweep.min.js` is gone.
* NEW: Request parameters are sanitized and validated against the plugin's own list of sweeps.
* NEW: Restructured into `includes/`, following the Plugin Handbook.
* NEW: 299 PHPUnit tests, 27 script tests and GitHub Actions CI.

### 1.2.0
* NEW: Per-type meta key filters (`wp_sweep_postmeta_whitelist`, `wp_sweep_commentmeta_whitelist`, `wp_sweep_usermeta_whitelist`, `wp_sweep_termmeta_whitelist`) to protect metadata from accidental deletion
* NEW: Documented all available filters in README

### 1.1.9
* NEW: Bump WordPress 7.0
* NEW: Add CLAUDE.md

### 1.1.8
* FIXED: Added current_user_can() Check For AJAX Calls

### 1.1.7
* FIXED: Pass in default blank string to fix fatal error

### 1.1.6
* NEW: Re-org wp-sweep.php to inc/class-wpsweep.php
* NEW: Bump to WordPress 6.2

### 1.1.5
* NEW: Bump to WordPress 5.8

### 1.1.4
* FIXED: Replaced %\_transient\_% with %\\\_transient\\\_%. Escape _ in MySQL if not it is being used as a wildcard character. Props @janrenn.

### 1.1.3
* FIXED: Changed permissions check to `activate_plugins` because `update_plugins` will return false when DISALLOW_FILE_MODS=true.

### 1.1.2
* NEW: Changed permission check to `update_plugins` for better MultiSite compatibility.
* NEW: Bump min PHP version to 5.6.

### 1.1.1
* NEW: `wp_sweep_excluded_termids` filter.

### 1.1.0
* NEW: Added WP Rest API Endpoint support, `sweep/v1/count/<Name>`, `sweep/v1/details/<Name>`, and `sweep/v1/sweep/<Name>`
* FIXED: Follow as close as possible to WordPress Coding Standards

### 1.0.12
* NEW: Bump to WordPress 4.9
* NEW: Update README to incompatible plugins

### 1.0.10
* FIXED: Invalid plugin head 'This plugin has an invalid header.'

### 1.0.9
* NEW: Support for Codeclimate
* FIXES: Uses `get_sites()` on WordPress 4.6. This should fix deprecated notices.
* FIXES: Fixes translation placeholder count. Props @pedro-mendonca.
* FIXES: Use `manage_options` capability as it conflicts with Admin Menu Editor on multisite installs. Props @EusebiuOprinoiu.

### 1.0.8
* NEW: Added wp_sweep_excluded_taxonomies filter to allow more than just link_category taxonomy
* NEW: Support for WP-CLI `wp sweep`

### 1.0.7
* FIXES: Use custom query to delete Orphaned Term Relationship if wp_remove_object_terms() fails

### 1.0.6
* NEW: Delete 'languages' folder from the plugin
* NEW: Use translate.wordpress.org to translate the plugin
* FIXED: Works only with WordPress 4.4 because of new term meta

### 1.0.5
* FIXED: apply_filters() wrong arguments

### 1.0.4
* NEW: oEmbed caches in post meta Sweep
* NEW: Add POT file for translators

### 1.0.3
* NEW: AJAX Sweep All
* NEW: AJAX Sweeping
* NEW: View details of sweep
* NEW: Optimize DB sweep
* NEW: User hint and confirmation. Props @SiamKreative
* FIXED: Division by zero. Pros @barisunver

### 1.0.2
* FIXED: Use term_id for wp_remove_object_terms()
* FIXED: number_format_i18n() issues after sweeping

### 1.0.1
* NEW: Moved plugin location to WP-Admin -> Tools -> Sweep
* NEW: Add Deleted Post Sweep
* FIXED: Use forced_delete for wp_delete_post() and wp_delete_comment();
* FIXED: If orphaned meta has an object id of 0, use SQL query to delete 

### 1.0.0
* Initial release

## Installation
1. Upload the plugin folder to the `/wp-content/plugins/` directory
2. Activate the `WP-Sweep` plugin through the 'Plugins' menu in WordPress
3. You can access `WP-Sweep` via `WP-Admin -> Tools -> Sweep`

## Screenshots
1. WP-Sweep Administrator Page (Before Sweeping)
2. WP-Sweep Administrator Page (Swept)

## Frequently Asked Questions

### My bookmark to the sweep screen is a 404

The admin screen moved in 2.0.0, from `tools.php?page=wp-sweep/admin.php` to
`tools.php?page=wp-sweep`. Update the bookmark, or reach it from Tools ->
Sweep as usual.

The old address was the legacy "plugin file as menu slug" form, which put the
plugin's installation directory name into the page URL. That is also why the
screen broke for anyone who installed WP-Sweep under a different directory
name — renamed by hand, or unzipped as `wp-sweep-2.0.0`. Neither happens now.

### My snippet calling WPSweep::get_instance() stopped working

The classes are renamed in 2.0.0:

* `WPSweep` is now `Sweep`
* `WPSweep_Api` is now `Sweep_Api`
* `WPSweep_Command` is now `Sweep_Command`

So `WPSweep::get_instance()->sweep( 'revisions' )` becomes
`Sweep::get_instance()->sweep( 'revisions' )`. There is no compatibility
alias, so the old name raises a fatal error rather than failing quietly.

Nothing else moved. Every filter, every `wp_sweep_admin_*_sweep` action, all
three REST routes and all the WP-CLI commands keep the names they had.

### The Sweep and Details buttons do nothing when I click them

The admin script is not loading. In 2.0.0 the most common cause of this was
fixed: the plugin used to build the script URL from the literal directory
name `wp-sweep`, so it returned a 404 for any other directory name.

If it still happens, check the browser console. The script has no
dependencies and does not use jQuery, so a jQuery conflict is not the cause.

### Which filters can I use to protect data from being swept?

Four per-type filters take a list of meta keys that must never be deleted, and
each supports `*` as a wildcard:

    add_filter( 'wp_sweep_postmeta_whitelist', function ( $keys ) {
        $keys[] = '_my_plugin_setting';
        $keys[] = '_acme_*';
        return $keys;
    } );

The same applies to `wp_sweep_commentmeta_whitelist`,
`wp_sweep_usermeta_whitelist` and `wp_sweep_termmeta_whitelist`. Terms are
protected with `wp_sweep_excluded_termids`, and taxonomies are kept out of the
orphaned term relationships sweep with `wp_sweep_excluded_taxonomies`.

### Does WP-Sweep leave anything behind when I delete it?

No. It stores no options, creates no database tables, registers no
capabilities and schedules no events. Everything it does is a deletion carried
out at the moment you ask for it, which is why `uninstall.php` has nothing to
do.

## Upgrade Notice
### 2.0.0
Major release. The classes are renamed, so any code calling `WPSweep::get_instance()` must be updated to `Sweep::get_instance()`. The Tools -> Sweep screen has a new URL. Requires WordPress 6.0 and PHP 7.4. Also fixes a stored XSS on the admin screen.
