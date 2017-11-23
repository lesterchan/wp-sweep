# WP-Sweep
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: sweep, clean, cleanup, clean up, optimize, orphan, unused, duplicated, posts, post meta, comments, comment meta, users, user meta, terms, term meta, term relationships, revisions, auto drafts, transient, database, tables, oembed
Requires at least: 4.4  
Tested up to: 4.9  
Stable tag: 1.0.12  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

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

WP-Sweep is not compatible with the following plugins:
* [Meta Slider](https://wordpress.org/support/plugin/ml-slider/)

### Build Status
[![Build Status](https://travis-ci.org/lesterchan/wp-sweep.svg?branch=master)](https://travis-ci.org/lesterchan/wp-sweep) [![Code Climate](https://codeclimate.com/github/lesterchan/wp-sweep/badges/gpa.svg)](https://codeclimate.com/github/lesterchan/wp-sweep) [![Issue Count](https://codeclimate.com/github/lesterchan/wp-sweep/badges/issue_count.svg)](https://codeclimate.com/github/lesterchan/wp-sweep)

### Development
* [https://github.com/lesterchan/wp-sweep](https://github.com/lesterchan/wp-sweep "https://github.com/lesterchan/wp-sweep")

### Credits
* Plugin icon by [Freepik](http://www.freepik.com) from [Flaticon](http://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog
### 1.0.12
* NEw: Bump to WordPress 4.9
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
1. Upload `wp-sweep` folder to the `/wp-content/plugins/` directory
2. Activate the `WP-Sweep` plugin through the 'Plugins' menu in WordPress
3. You can access `WP-Sweep` via `WP-Admin -> Tools -> Sweep`

## Screenshots
1. WP-Sweep Administrator Page (Before Sweeping)
2. WP-Sweep Administrator Page (Swept)

## Frequently Asked Questions
Coming soon ...

## Upgrade Notice
N/A
