<?php
/**
 * Tests for the list of sweeps.
 *
 * @package WP-Sweep
 */

/**
 * The screen was six hand-written tables until 2.0.0. What matters now is that
 * the list table really is one -- pagination, sorting, row actions, a bulk
 * action and a no-items message all come from WP_List_Table rather than from
 * markup that merely looks like it.
 */
class WP_Sweep_List_Table_Test extends WP_Sweep_TestCase {

	/**
	 * Administrator user ID.
	 *
	 * @var int
	 */
	protected static $admin;

	/**
	 * Creates an administrator to build the table as.
	 *
	 * @param WP_UnitTest_Factory $factory Factory.
	 * @return void
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$admin = self::create_admin( $factory );
	}

	/**
	 * A list table reaches WP_Screen, which needs a screen to have been set.
	 */
	public function set_up() {
		parent::set_up();

		wp_set_current_user( self::$admin );
		set_current_screen( 'tools_page_wp-sweep' );

		require_once WP_SWEEP_DIR . 'includes/class-wp-sweep-list-table.php';
	}

	/**
	 * Restores the screen.
	 */
	public function tear_down() {
		$_GET     = array();
		$_REQUEST = array();

		unset( $GLOBALS['current_screen'] );

		parent::tear_down();
	}

	/**
	 * Builds and populates a table for the current request.
	 *
	 * @return WP_Sweep_List_Table
	 */
	private function table() {
		$table = new WP_Sweep_List_Table();
		$table->prepare_items();

		return $table;
	}

	/**
	 * It really is a WP_List_Table.
	 */
	public function test_it_is_a_wp_list_table() {
		$this->assertInstanceOf( WP_List_Table::class, $this->table(), 'The sweep table is not a WP_List_Table.' );
	}

	/**
	 * Every sweep gets a row.
	 */
	public function test_every_sweep_gets_a_row() {
		$table = $this->table();

		$this->assertSame(
			$this->sweep()->get_sweep_names(),
			wp_list_pluck( $table->items, 'name' ),
			'The table does not list every sweep, in order.'
		);
	}

	/**
	 * The columns the standard asks for are all there.
	 */
	public function test_the_columns_include_a_checkbox_and_a_count() {
		$columns = $this->table()->get_columns();

		foreach ( array( 'cb', 'name', 'group', 'count', 'percentage' ) as $column ) {
			$this->assertArrayHasKey( $column, $columns, "The '{$column}' column is missing." );
		}
	}

	/**
	 * Destructive rows mean bulk actions, per the standard.
	 */
	public function test_a_bulk_sweep_is_offered() {
		$table = $this->table();

		ob_start();
		$table->display();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="action"', $html, 'No bulk action control was rendered.' );
		$this->assertStringContainsString( '<option value="sweep"', $html, 'Sweep is not offered as a bulk action.' );
	}

	/**
	 * The empty table says something rather than rendering a void.
	 */
	public function test_it_has_a_no_items_message() {
		ob_start();
		$this->table()->no_items();
		$message = ob_get_clean();

		$this->assertNotEmpty( $message, 'The table has no no_items() message.' );
	}

	/**
	 * Pagination is set to twenty, as the standard requires.
	 */
	public function test_it_paginates_at_twenty() {
		$this->assertSame( 20, WP_Sweep_List_Table::PER_PAGE, 'The page size is not twenty.' );
		$this->assertSame( 20, $this->table()->get_pagination_arg( 'per_page' ), 'The table did not tell WP_List_Table its page size.' );
	}

	/**
	 * The name, group and count columns are sortable.
	 */
	public function test_the_expected_columns_are_sortable() {
		ob_start();
		$this->table()->display();
		$html = ob_get_clean();

		foreach ( array( 'name', 'group', 'count' ) as $column ) {
			$this->assertStringContainsString( "orderby={$column}", $html, "The '{$column}' column is not sortable." );
		}
	}

	/**
	 * Sorting by count really reorders the rows.
	 */
	public function test_sorting_by_count_reorders_the_rows() {
		$this->make_revisions( 3 );

		$_GET = array(
			'orderby' => 'count',
			'order'   => 'desc',
		);

		$counts = wp_list_pluck( $this->table()->items, 'count' );

		$this->assertSame( $counts, wp_list_sort( $counts, '', 'DESC' ), 'The rows were not sorted by count.' );
	}

	/**
	 * An unknown orderby leaves the rows in the order the sweeps must run in.
	 */
	public function test_an_unknown_orderby_leaves_the_running_order_alone() {
		$_GET = array( 'orderby' => 'nonsense' );

		$this->assertSame(
			$this->sweep()->get_sweep_names(),
			wp_list_pluck( $this->table()->items, 'name' ),
			'An unknown orderby disturbed the running order.'
		);
	}

	/**
	 * The group filter narrows the table to that group.
	 */
	public function test_the_group_filter_narrows_the_table() {
		$_GET = array( 'group' => 'users' );

		foreach ( $this->table()->items as $item ) {
			$this->assertSame( 'users', $item['group'], 'A row from another group survived the filter.' );
		}
	}

	/**
	 * A group nobody has heard of falls back to showing everything.
	 */
	public function test_an_unknown_group_shows_everything() {
		$_GET = array( 'group' => 'nonsense' );

		$this->assertSame( 'all', WP_Sweep_List_Table::current_group(), 'An unknown group was not rejected.' );
		$this->assertCount( count( $this->sweep()->get_sweep_names() ), $this->table()->items, 'An unknown group hid rows.' );
	}

	/**
	 * There is a filter link for every group, and one for all of them.
	 */
	public function test_there_is_a_view_for_every_group() {
		ob_start();
		$this->table()->views();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'group=all', $html, 'There is no All view.' );

		foreach ( array_keys( $this->sweep()->get_sweep_groups() ) as $group ) {
			$this->assertStringContainsString( "group={$group}", $html, "There is no view for the '{$group}' group." );
		}
	}

	/**
	 * A populated row offers both actions and a checkbox.
	 */
	public function test_a_populated_row_offers_actions_and_a_checkbox() {
		$this->make_revisions( 2 );

		$items = wp_list_filter( $this->table()->items, array( 'name' => 'revisions' ) );
		$item  = reset( $items );

		$actions = $this->table()->column_actions( $item );

		$this->assertStringContainsString( 'btn-sweep', $actions, 'The row offers no Sweep action.' );
		$this->assertStringContainsString( 'btn-sweep-details', $actions, 'The row offers no Details action.' );
		$this->assertStringContainsString( 'name="sweep[]"', $this->table()->column_cb( $item ), 'The row offers no checkbox.' );

		// The buttons are a column of their own, not row actions: WordPress hides
		// those until hover, and sweeping is the only thing this screen does.
		$this->assertStringNotContainsString( 'row-actions', $this->table()->column_name( $item ), 'The actions went back to being hover-only row actions.' );
		$this->assertStringContainsString( 'button', $actions, 'The actions are not drawn as buttons.' );
	}

	/**
	 * Every sweep explains what it removes.
	 *
	 * These rows delete data that does not come back, and a label like "Orphaned
	 * Term Relationships" tells a site owner nothing about whether it is safe to
	 * tick. The description is part of get_sweeps(), so a sweep added without one
	 * fails here.
	 */
	public function test_every_sweep_carries_a_description() {
		foreach ( WP_Sweep::get_instance()->get_sweeps() as $name => $args ) {
			$this->assertArrayHasKey( 'description', $args, "The '{$name}' sweep has no description." );
			$this->assertNotSame( '', trim( (string) $args['description'] ), "The '{$name}' sweep has an empty description." );
		}
	}

	/**
	 * The description is rendered under the name.
	 */
	public function test_the_description_is_rendered_under_the_name() {
		$items = wp_list_filter( $this->table()->items, array( 'name' => 'revisions' ) );
		$item  = reset( $items );

		$html = $this->table()->column_name( $item );

		$this->assertStringContainsString( '<p class="description">', $html, 'The row shows no description.' );
		$this->assertStringContainsString( esc_html( $item['description'] ), $html, 'The description rendered is not the one in get_sweeps().' );
	}

	/**
	 * An empty row keeps its checkbox but offers no Sweep action.
	 *
	 * The checkbox is structural: a gap in that column, and a header select-all
	 * that claims more rows than it selects, is not something any core list table
	 * does. Sweeping an empty sweep removes nothing, and the count is only a
	 * snapshot from when the page rendered, so withholding the checkbox never
	 * really stopped a no-op either.
	 *
	 * The row action is a different matter: a link labelled Sweep on a row with
	 * nothing to sweep is an invitation to nothing, and hiding it costs no
	 * alignment.
	 */
	public function test_an_empty_row_keeps_its_checkbox_but_offers_no_action() {
		$item = array(
			'name'  => 'revisions',
			'label' => 'Revisions',
			'type'  => 'posts',
			'group' => 'posts',
			'count' => 0,
		);

		$this->assertStringContainsString( 'name="sweep[]"', $this->table()->column_cb( $item ), 'An empty row lost its checkbox.' );
		$this->assertStringNotContainsString( 'btn-sweep', $this->table()->column_actions( $item ), 'An empty row offered a Sweep button.' );
	}

	/**
	 * The details a row action asked for are rendered as text, not as markup.
	 *
	 * This is the no-JavaScript half of the stored XSS fixed in 2.0.0: the
	 * script writes text nodes, and this path has to escape for the same
	 * reason. A comment author name is written by whoever left the comment.
	 */
	public function test_requested_details_are_escaped() {
		$comments = $this->make_comments( 'spam', 1 );

		/*
		 * The author name has to be written past core's own sanitisers, or there
		 * is nothing here to escape. wp_update_comment() runs the value through
		 * pre_comment_author_name, where sanitize_text_field() drops a script
		 * element and its contents together, so the column would end up holding
		 * the empty string and the assertions below would pass on a details list
		 * that had rendered nothing. A hostile value gets into that column by a
		 * route that does not go through those filters -- an importer, a direct
		 * write, a comment stored before core hardened this -- and that is the
		 * input this plugin's details list has to survive.
		 */
		remove_all_filters( 'pre_comment_author_name' );

		wp_update_comment(
			array(
				'comment_ID'     => $comments[0],
				'comment_author' => '<script>window.pwned = 1</script>',
			)
		);

		$_GET = array(
			'sweep_details' => 'spam_comments',
			'_wpnonce'      => wp_create_nonce( 'wp_sweep_details_spam_comments' ),
		);

		$html = $this->render_admin_page( $_GET );

		$this->assertStringNotContainsString( '<script>window.pwned', $html, 'A comment author name reached the page as markup.' );
		$this->assertStringContainsString( '&lt;script&gt;', $html, 'The details list was not rendered at all.' );
	}

	/**
	 * The table carries no inline width, style or alignment attribute.
	 */
	public function test_the_table_carries_no_presentational_attributes() {
		$this->make_revisions( 2 );

		ob_start();
		$this->table()->display();
		$html = ob_get_clean();

		foreach ( array( ' style=', ' width=', ' valign=', ' align=' ) as $attribute ) {
			$this->assertStringNotContainsString( $attribute, $html, "The table renders a{$attribute} attribute." );
		}
	}

	/**
	 * The table is not `fixed`, which is what the inline style block undid.
	 */
	public function test_the_table_is_not_fixed_width() {
		ob_start();
		$this->table()->display();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'widefat', $html, 'The table dropped the core widefat class.' );
		$this->assertStringNotContainsString( 'fixed', $html, 'The table is fixed, which needs a stylesheet to undo.' );
	}
}
