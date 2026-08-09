<?php
/**
 * The WP-CLI command.
 *
 * @package WP-Sweep
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cleans up unused, orphaned and duplicated data from the command line.
 *
 * Registered as `wp sweep` rather than `wp wp-sweep`. A `wp-` prefix is a
 * wordpress.org directory convention for keeping one plugin's download page
 * apart from another's, not a command-naming one, and after the `wp` binary it
 * only stutters; WP-CLI's own ecosystem names commands after the brand -- `wp
 * wc`, `wp yoast`, `wp jetpack`. It is also the name the released 1.2.0
 * shipped, so no script that already calls WP-Sweep has to be edited. WP-CLI
 * gives a collision to whichever plugin registered last and a bare noun is
 * claimable by anyone; that is the accepted trade for a word as distinctive as
 * `sweep`.
 */
class WP_Sweep_Command extends WP_CLI_Command {

	/**
	 * Clean up unused, orphaned and duplicated data in your WordPress.
	 *
	 * ## OPTIONS
	 *
	 * [<name>...]
	 * : One or more items to sweep. Ignored when --all is given.
	 *
	 * [--all]
	 * : Sweep every item, in the order they have to run.
	 *
	 * ## AVAILABLE ITEMS
	 *
	 *  revisions, auto_drafts, deleted_posts, unapproved_comments,
	 *  spam_comments, deleted_comments, transient_options, orphan_postmeta,
	 *  orphan_commentmeta, orphan_usermeta, orphan_termmeta,
	 *  orphan_term_relationships, unused_terms, duplicated_postmeta,
	 *  duplicated_commentmeta, duplicated_usermeta, duplicated_termmeta,
	 *  optimize_database, oembed_postmeta
	 *
	 * ## EXAMPLES
	 *
	 *     # Sweep everything.
	 *     $ wp sweep --all
	 *
	 *     # Sweep one item.
	 *     $ wp sweep revisions
	 *
	 *     # Sweep several items.
	 *     $ wp sweep revisions auto_drafts deleted_posts
	 *
	 * @since 1.0.8
	 *
	 * @param array $args       Item names passed to the command.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function __invoke( $args, $assoc_args ) {
		$sweeps = WP_Sweep::get_instance()->get_sweep_names();

		if ( ! empty( $assoc_args['all'] ) ) {
			$this->run_sweep( $sweeps );

			WP_CLI::success( __( 'Sweep Complete', 'wp-sweep' ) );

			return;
		}

		// Filtered from the canonical list rather than taken as given, so the
		// items run in the order they have to: posts are deleted before the
		// sweeps that hunt for the meta that deleting them just orphaned.
		$this->run_sweep( array_values( array_intersect( $sweeps, (array) $args ) ) );

		WP_CLI::success( __( 'Sweep Complete!', 'wp-sweep' ) );
	}

	/**
	 * Run each named sweep that has anything to remove.
	 *
	 * @since 1.0.8
	 *
	 * @param array $items Sweep names.
	 * @return void
	 */
	public function run_sweep( $items ) {
		$sweep = WP_Sweep::get_instance();

		foreach ( $items as $item ) {
			if ( 0 === (int) $sweep->count( $item ) ) {
				continue;
			}

			WP_CLI::success( $sweep->sweep( $item ) );
		}
	}
}
