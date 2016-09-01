<?php

class WPSweep_Command extends WP_CLI_Command {
	/**
	 * Clean up unused, orphaned and duplicated data in your WordPress
	 *
	 * ## OPTIONS
	 *
	 * [--all]
	 * Sweep all the orphaned data at once.
	 *
	 * Name of the items selected individually
	 * Available Items =
	 * 	revisions
	 * 	auto_drafts
	 * 	deleted_posts
	 * 	unapproved_comments
	 * 	spam_comments
	 * 	deleted_comments
	 * 	transient_options
	 * 	orphan_postmeta
	 * 	orphan_commentmeta
	 * 	orphan_usermeta
	 * 	orphan_termmeta
	 * 	orphan_term_relationships
	 * 	unused_terms
	 * 	duplicated_postmeta
	 * 	duplicated_commentmeta
	 * 	duplicated_usermeta
	 * 	duplicated_termmeta
	 * 	optimize_database
	 * 	oembed_postmet
	 *
	 * ## EXAMPLES
	 *
	 *  1. wp sweep --all
	 *		- Run Sweep for all the items.
	 *  2. wp sweep revisions
	 *		- Sweep only Revision
	 *  3. wp sweep revisions auto_drafts deleted_posts unapproved_comments spam_comments deleted_comments transient_options orphan_postmeta orphan_commentmeta orphan_usermeta orphan_termmeta orphan_term_relationships unused_terms duplicated_postmeta duplicated_commentmeta duplicated_usermeta duplicated_termmeta optimize_database oembed_postmet
	 *		- Sweep the selected items
	 *
	 *
	*/
	public function __invoke( $args, $assoc_args ) {

		$items = array();

		$default_items = array(
			'0'  => 'revisions',
			'1'  => 'auto_drafts',
			'2'  => 'deleted_posts',
			'3'  => 'unapproved_comments',
			'4'  => 'spam_comments',
			'5'  => 'deleted_comments',
			'6'  => 'transient_options',
			'7'  => 'orphan_postmeta',
			'8'  => 'orphan_commentmeta',
			'9'  => 'orphan_usermeta',
			'10' => 'orphan_termmeta',
			'11' => 'orphan_term_relationships',
			'12' => 'unused_terms',
			'13' => 'duplicated_postmeta',
			'14' => 'duplicated_commentmeta',
			'15' => 'duplicated_usermeta',
			'16' => 'duplicated_termmeta',
			'17' => 'optimize_database',
			'18' => 'oembed_postmeta',
		);

		if ( isset( $assoc_args['all'] ) && true == $assoc_args['all'] ) {
			$this->run_sweep( $default_items );
			WP_CLI::success( 'Sweep Complete' );

			return;
		} else {
			foreach ( $default_items as $key => $item ) {
				if ( in_array( $item, $args ) ) {
					array_push( $items, $item );
				}
			}

			$this->run_sweep( $items );
			WP_CLI::success( 'Sweep Complete!' );

			return;
		}

	}

	public function run_sweep( $items ) {

		$sweep = new WPSweep();

		foreach ( $items as $key => $value ) {
			$count = $sweep->count( $value );
			if ( 0 !== $count && '0' !== $count ) {
				$message = $sweep->sweep( $value );
				WP_CLI::success( $message );
			}
		}

	}
}

WP_CLI::add_command( 'sweep', 'WPSweep_Command' );
