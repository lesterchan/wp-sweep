# WP-Sweep
Contributors: GamerZ  
Donate link: http://lesterchan.net/site/donation/  
Tags: sweep, clean, cleanup, clean up, optimize, orphan, unused, duplicated, posts, post meta, comments, comment meta, users, user meta, terms, term relationships, revisions, auto drafts, transient  
Requires at least: 4.1  
Tested up to: 4.1  
Stable tag: trunk  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

WP-Sweep allows you to clean up unused, orphaned and duplicated data in your WordPress.

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
* Orphan term relationships
* Unused terms
* Duplicated post meta
* Duplicated comment meta
* Duplicated user meta
* Transient options

This plugin uses proper WordPress delete functions as much as possible instead of running direct delete MySQL queries.

Following delete functions are used:

* wp_delete_post_revision()
* wp_delete_post()
* wp_delete_comment()
* delete_post_meta()
* delete_comment_meta()
* delete_user_meta()
* wp_remove_object_terms()
* wp_delete_term()
* delete_transient()
* delete_site_transient()

### Build Status
[![Build Status](https://travis-ci.org/lesterchan/wp-sweep.svg?branch=master)](https://travis-ci.org/lesterchan/wp-sweep)

### Development
* [https://github.com/lesterchan/wp-sweep](https://github.com/lesterchan/wp-sweep "https://github.com/lesterchan/wp-sweep")

### Credits
* Plugin icon by [Freepik](http://www.freepik.com) from [Flaticon](http://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog
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