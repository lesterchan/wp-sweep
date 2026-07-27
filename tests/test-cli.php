<?php
/**
 * Tests for the `wp sweep` WP-CLI command.
 *
 * @package wp-sweep
 */

/**
 * The CLI is a documented interface — the readme advertises `wp sweep --all`
 * and `wp sweep <name>` — and it deletes data without confirmation. It had no
 * test coverage at all before this.
 */
class Test_WP_Sweep_Cli extends WP_Sweep_TestCase {

	/**
	 * Clears the recorded WP-CLI output.
	 */
	public function set_up() {
		parent::set_up();

		WP_CLI::$successes = array();
		WP_CLI::$commands  = array();
	}

	/**
	 * Runs the command the way WP-CLI would.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 * @return array The success messages it reported.
	 */
	protected function run_command( $args = array(), $assoc_args = array() ) {
		$command = new Sweep_Command();
		$command( $args, $assoc_args );

		return WP_CLI::$successes;
	}

	/**
	 * --all sweeps everything that has something to sweep.
	 */
	public function test_all_sweeps_every_populated_item() {
		$revisions = $this->make_revisions( 2 );
		$drafts    = $this->make_posts_with_status( 'auto-draft', 1 );

		$messages = $this->run_command( array(), array( 'all' => true ) );

		$this->assertNull( get_post( $revisions[0] ) );
		$this->assertNull( get_post( $drafts[0] ) );
		$this->assertContains( 'Sweep Complete', $messages );
	}

	/**
	 * --all reports each sweep it actually performed.
	 */
	public function test_all_reports_each_sweep() {
		$this->make_revisions( 2 );

		$messages = $this->run_command( array(), array( 'all' => true ) );

		$this->assertContains( '2 Revisions Processed', $messages );
	}

	/**
	 * A named item sweeps that item and leaves the others alone.
	 */
	public function test_named_item_sweeps_only_that_item() {
		$revisions = $this->make_revisions( 2 );
		$drafts    = $this->make_posts_with_status( 'auto-draft', 1 );

		$messages = $this->run_command( array( 'revisions' ) );

		$this->assertNull( get_post( $revisions[0] ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $drafts[0] ) );
		$this->assertContains( '2 Revisions Processed', $messages );
		$this->assertContains( 'Sweep Complete!', $messages );
	}

	/**
	 * Several names can be passed at once.
	 */
	public function test_several_named_items() {
		$revisions = $this->make_revisions( 1 );
		$drafts    = $this->make_posts_with_status( 'auto-draft', 1 );
		$trashed   = $this->make_posts_with_status( 'trash', 1 );

		$this->run_command( array( 'revisions', 'auto_drafts' ) );

		$this->assertNull( get_post( $revisions[0] ) );
		$this->assertNull( get_post( $drafts[0] ) );
		$this->assertInstanceOf( WP_Post::class, get_post( $trashed[0] ) );
	}

	/**
	 * Items are swept in the canonical order, not the order they were typed.
	 *
	 * That order is deliberate: posts are deleted before the sweeps that hunt
	 * for the meta which deletion has just orphaned.
	 */
	public function test_items_run_in_canonical_order() {
		$this->make_revisions( 1 );
		$this->make_posts_with_status( 'auto-draft', 1 );

		$messages = $this->run_command( array( 'auto_drafts', 'revisions' ) );

		$revision_index = array_search( '1 Revisions Processed', $messages, true );
		$draft_index    = array_search( '1 Auto Drafts Processed', $messages, true );

		$this->assertNotFalse( $revision_index );
		$this->assertNotFalse( $draft_index );
		$this->assertLessThan( $draft_index, $revision_index );
	}

	/**
	 * A name the plugin does not implement sweeps nothing.
	 */
	public function test_unknown_name_sweeps_nothing() {
		$revisions = $this->make_revisions( 2 );

		$messages = $this->run_command( array( 'no_such_sweep' ) );

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ) );
		$this->assertSame( array( 'Sweep Complete!' ), $messages );
	}

	/**
	 * With no arguments at all, nothing is swept. `wp sweep` on its own must
	 * not be a synonym for `wp sweep --all`.
	 */
	public function test_no_arguments_sweeps_nothing() {
		$revisions = $this->make_revisions( 2 );

		$messages = $this->run_command();

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ) );
		$this->assertSame( array( 'Sweep Complete!' ), $messages );
	}

	/**
	 * An item with nothing to sweep is skipped silently rather than reporting
	 * an empty message.
	 */
	public function test_empty_item_is_skipped() {
		$messages = $this->run_command( array( 'revisions' ) );

		$this->assertSame( array( 'Sweep Complete!' ), $messages );
		$this->assertNotContains( '', $messages );
	}

	/**
	 * The run_sweep() method can be driven directly with a list of names.
	 */
	public function test_run_sweep_accepts_a_list() {
		$revisions = $this->make_revisions( 1 );

		$command = new Sweep_Command();
		$command->run_sweep( array( 'revisions' ) );

		$this->assertNull( get_post( $revisions[0] ) );
		$this->assertContains( '1 Revisions Processed', WP_CLI::$successes );
	}

	/**
	 * The command drives the same code path as everything else, so a filter
	 * a site has installed applies to the CLI too.
	 */
	public function test_filters_apply_to_the_cli() {
		add_filter(
			'wp_sweep_postmeta_whitelist',
			static function ( $keys ) {
				$keys[] = 'sweep_protected';
				return $keys;
			}
		);

		$this->make_orphan_meta( 'postmeta', 'sweep_protected' );

		$this->run_command( array( 'orphan_postmeta' ) );

		$this->assertSame( 2, $this->count_meta_rows( 'postmeta', 'sweep_protected' ) );
	}

	/**
	 * The plugin registers the command under the name the readme documents.
	 *
	 * Sweep::init() gates on the WP_CLI constant, which cannot be defined
	 * here without changing what every other test sees, so the registration
	 * is asserted against the source.
	 */
	public function test_command_is_registered_as_sweep() {
		$code = $this->source_without_comments( '/includes/class-sweep.php' );

		$this->assertStringContainsString( "require __DIR__ . '/class-sweep-command.php';", $code );
		$this->assertStringContainsString( "WP_CLI::add_command( 'sweep', 'Sweep_Command' );", $code );
		$this->assertStringContainsString( "defined( 'WP_CLI' )", $code );
	}
}
