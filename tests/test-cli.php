<?php
/**
 * Tests for the `wp sweep` WP-CLI command.
 *
 * @package WP-Sweep
 */

/**
 * The CLI is a documented interface — the readme advertises `wp sweep --all`
 * and `wp sweep <name>` — and it deletes data without confirmation. It had no
 * test coverage at all before this.
 */
class WP_Sweep_CLI_Test extends WP_Sweep_TestCase {

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
		$command = new WP_Sweep_Command();
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

		$this->assertNull( get_post( $revisions[0] ), 'wp sweep all removed the revisions.' );
		$this->assertNull( get_post( $drafts[0] ), 'wp sweep all removed the auto drafts too.' );
		$this->assertContains( 'Sweep Complete', $messages, 'The command reports completion.' );
	}

	/**
	 * --all reports each sweep it actually performed.
	 */
	public function test_all_reports_each_sweep() {
		$this->make_revisions( 2 );

		$messages = $this->run_command( array(), array( 'all' => true ) );

		$this->assertContains( '2 Revisions Processed', $messages, 'And reports each sweep it ran, with its count.' );
	}

	/**
	 * A named item sweeps that item and leaves the others alone.
	 */
	public function test_named_item_sweeps_only_that_item() {
		$revisions = $this->make_revisions( 2 );
		$drafts    = $this->make_posts_with_status( 'auto-draft', 1 );

		$messages = $this->run_command( array( 'revisions' ) );

		$this->assertNull( get_post( $revisions[0] ), 'The named item was swept.' );
		$this->assertInstanceOf( WP_Post::class, get_post( $drafts[0] ), 'Only the named item was swept; the drafts survive.' );
		$this->assertContains( '2 Revisions Processed', $messages, 'A named item reports its own count.' );
		$this->assertContains( 'Sweep Complete!', $messages, 'And completion, so the run is not left open-ended.' );
	}

	/**
	 * Several names can be passed at once.
	 */
	public function test_several_named_items() {
		$revisions = $this->make_revisions( 1 );
		$drafts    = $this->make_posts_with_status( 'auto-draft', 1 );
		$trashed   = $this->make_posts_with_status( 'trash', 1 );

		$this->run_command( array( 'revisions', 'auto_drafts' ) );

		$this->assertNull( get_post( $revisions[0] ), 'The first named item was swept.' );
		$this->assertNull( get_post( $drafts[0] ), 'The second named item was swept.' );
		$this->assertInstanceOf( WP_Post::class, get_post( $trashed[0] ), 'An item that was not named survives.' );
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

		$this->assertNotFalse( $revision_index, 'Revisions ran at all, or the ordering assertion below is vacuous.' );
		$this->assertNotFalse( $draft_index, 'Drafts ran at all, or the ordering assertion below is vacuous.' );
		$this->assertLessThan( $draft_index, $revision_index, 'Items run in the canonical order, revisions before drafts.' );
	}

	/**
	 * A name the plugin does not implement sweeps nothing.
	 */
	public function test_unknown_name_sweeps_nothing() {
		$revisions = $this->make_revisions( 2 );

		$messages = $this->run_command( array( 'no_such_sweep' ) );

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ), 'An unknown name sweeps nothing rather than everything.' );
		$this->assertSame( array( 'Sweep Complete!' ), $messages, 'An unknown name reports completion and nothing else.' );
	}

	/**
	 * With no arguments at all, nothing is swept. `wp sweep` on its own must
	 * not be a synonym for `wp sweep --all`.
	 */
	public function test_no_arguments_sweeps_nothing() {
		$revisions = $this->make_revisions( 2 );

		$messages = $this->run_command();

		$this->assertInstanceOf( WP_Post::class, get_post( $revisions[0] ), 'No arguments sweeps nothing rather than everything.' );
		$this->assertSame( array( 'Sweep Complete!' ), $messages, 'No arguments does the same rather than sweeping everything.' );
	}

	/**
	 * An item with nothing to sweep is skipped silently rather than reporting
	 * an empty message.
	 */
	public function test_empty_item_is_skipped() {
		$messages = $this->run_command( array( 'revisions' ) );

		$this->assertSame( array( 'Sweep Complete!' ), $messages, 'An empty item is skipped rather than run.' );
		$this->assertNotContains( '', $messages, 'And produces no empty message line.' );
	}

	/**
	 * The run_sweep() method can be driven directly with a list of names.
	 */
	public function test_run_sweep_accepts_a_list() {
		$revisions = $this->make_revisions( 1 );

		$command = new WP_Sweep_Command();
		$command->run_sweep( array( 'revisions' ) );

		$this->assertNull( get_post( $revisions[0] ), 'run_sweep accepts a list and sweeps what it names.' );
		$this->assertContains( '1 Revisions Processed', WP_CLI::$successes, 'run_sweep accepts a list and reports what it swept.' );
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

		$this->assertSame( 2, $this->count_meta_rows( 'postmeta', 'sweep_protected' ), 'The protection filters apply to the CLI as well as the browser.' );
	}

	/**
	 * The plugin registers the command under the name the readme documents.
	 *
	 * WP_Sweep::init() gates on the WP_CLI constant, which cannot be defined
	 * here without changing what every other test sees, so the registration
	 * is asserted against the source.
	 */
	public function test_command_is_registered_under_the_name_1_2_0_shipped() {
		$code = $this->source_without_comments( '/includes/class-wp-sweep.php' );

		$this->assertStringContainsString( "require_once WP_SWEEP_DIR . 'includes/class-wp-sweep-command.php';", $code, 'The command class is required before it is registered.' );
		$this->assertStringContainsString( "WP_CLI::add_command( 'sweep', 'WP_Sweep_Command' );", $code, 'And registered as the bare noun the released 1.2.0 shipped, not the plugin slug.' );
		$this->assertStringContainsString( "defined( 'WP_CLI' )", $code, 'Guarded on WP_CLI, so a web request never loads it.' );
		$this->assertStringNotContainsString( 'add_command( WP_SWEEP_SLUG', $code, 'The slug constant names the directory, not the command; wp wp-sweep stutters.' );
	}
}
