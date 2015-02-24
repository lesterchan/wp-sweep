=== WP-Sweep ===
Contributors: GamerZ  
Donate link: http://lesterchan.net/site/donation/  
Tags: sweep, clean, cleanup, clean up, optimize, orphan, unused, duplicated, posts, post meta, comments, comment meta, users, user meta, terms, term relationships, revisions, auto drafts, transient
Requires at least: 4.1  
Tested up to: 4.1  
Stable tag: trunk  
License: GPLv2 or later  
License URI: http://www.gnu.org/licenses/gpl-2.0.html  

WP-Sweep allows you to clean up unused, orphaned and duplicated data in your WordPress.

== Description ==
This plugin cleans up:
* Revisions
* Auto drafts
* Unapproved comments
* Spam comments
* Trashed comments
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

= Build Status =
[![Build Status](https://travis-ci.org/lesterchan/wp-sweep.svg?branch=master)](https://travis-ci.org/lesterchan/wp-sweep)

= Development =
* [https://github.com/lesterchan/wp-sweep](https://github.com/lesterchan/wp-sweep "https://github.com/lesterchan/wp-sweep")

== Changelog ==

= 1.0.0 =
* Initial release

== Installation ==
1. Upload `wp-sweep` folder to the `/wp-content/plugins/` directory
2. Activate the `WP-Sweep` plugin through the 'Plugins' menu in WordPress
3. You can access `WP-Sweep` via `WP-Admin -> Sweep`

== Screenshots ==
1. WP-Sweep Administrator Page

== Frequently Asked Questions ==
Coming soon ...

== Upgrade Notice ==
N/A