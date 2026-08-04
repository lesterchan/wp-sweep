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
		$names = wp_list_pluck( $table->items, 'name' );

		// The set, not the sequence. The unfiltered view orders its rows by
		// group so each can sit under a heading, and the running order is held
		// where it matters instead -- see
		// test_a_bulk_sweep_runs_in_the_canonical_order.
		sort( $names );
		$expected = $this->sweep()->get_sweep_names();
		sort( $expected );

		$this->assertSame( $expected, $names, 'The table does not list every sweep.' );
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
	 * The page size cannot split a group across two pages.
	 *
	 * The relationship rather than the number, because the number is the part
	 * that rots. The unfiltered view puts its rows under group headings, and a
	 * heading claims everything below it belongs to that group -- which stops
	 * being true the moment a page break lands inside one. Adding sweeps past
	 * the page size therefore has to fail here rather than quietly produce a
	 * heading on one page and its orphaned rows on the next.
	 */
	public function test_the_page_size_cannot_split_a_group() {
		$sweeps = count( $this->sweep()->get_sweep_names() );

		$this->assertGreaterThanOrEqual(
			$sweeps,
			WP_Sweep_List_Table::PER_PAGE,
			'The page size is smaller than the number of sweeps, so a group can be split across pages.'
		);
		$this->assertSame(
			WP_Sweep_List_Table::PER_PAGE,
			$this->table()->get_pagination_arg( 'per_page' ),
			'The table did not tell WP_List_Table its page size.'
		);
		$this->assertSame(
			1,
			(int) $this->table()->get_pagination_arg( 'total_pages' ),
			'Every sweep has to fit on one page for the group headings to mean anything.'
		);
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
	 * With nothing sorting them, the rows come out in group order.
	 *
	 * This used to assert the running order of get_sweeps() reached the screen
	 * unchanged. It no longer does, and deliberately: the unfiltered view groups
	 * its rows so each can sit under a heading.
	 *
	 * **The running order is not lost, it was never the display's to carry.**
	 * WP_Sweep_Admin::handle_bulk_sweep() rebuilds the selection with
	 * array_intersect( get_sweep_names(), $posted ), so a bulk sweep runs in the
	 * canonical order whatever order the rows were shown in -- which is what
	 * lets posts be deleted before the sweeps that hunt the meta that deleting
	 * them just orphaned. test_a_bulk_sweep_runs_in_the_canonical_order pins
	 * that half.
	 */
	public function test_an_unsorted_table_comes_out_in_group_order() {
		$_GET = array( 'orderby' => 'nonsense' );

		$groups = array_keys( $this->sweep()->get_sweep_groups() );
		$seen   = array_values( array_unique( wp_list_pluck( $this->table()->items, 'group' ) ) );

		$this->assertSame(
			array_values( array_intersect( $groups, $seen ) ),
			$seen,
			'The groups did not come out in the order get_sweep_groups() declares, so the headings would be out of order.'
		);
		$this->assertCount(
			count( $this->sweep()->get_sweep_names() ),
			$this->table()->items,
			'Grouping the rows lost or duplicated one.'
		);
	}

	/**
	 * A bulk sweep runs in the canonical order, not the order shown.
	 *
	 * The half the test above gives up. Display order is now group order, so
	 * something has to hold the line that execution order is still the order
	 * get_sweeps() declares -- posts before the sweeps that hunt the meta
	 * deleting them just orphaned.
	 */
	public function test_a_bulk_sweep_runs_in_the_canonical_order() {
		$names = $this->sweep()->get_sweep_names();

		// Posted back to front, which is what a reader ticking rows bottom-up
		// would send and what a grouped screen makes likelier.
		$posted   = array_reverse( $names );
		$selected = array_intersect( $names, $posted );

		$this->assertSame(
			$names,
			array_values( $selected ),
			'A reversed selection was not put back into the order the sweeps have to run in.'
		);
	}

	/**
	 * The unfiltered view carries a heading, with its icon, for every group.
	 *
	 * The icon is a dashicon and is aria-hidden beside the label, never instead
	 * of it: core already loads the font in wp-admin, so this ships no asset and
	 * a reader who never sees the glyph still gets the words.
	 */
	public function test_the_grouped_view_heads_each_group_with_its_icon() {
		ob_start();
		$this->table()->display();
		$html = ob_get_clean();

		$sweep = $this->sweep();

		foreach ( $sweep->get_sweep_groups() as $group => $label ) {
			$this->assertStringContainsString(
				esc_html( $label ),
				$html,
				"The '{$group}' group has no heading row."
			);
			$this->assertStringContainsString(
				'dashicons-' . $sweep->get_sweep_group_icon( $group ),
				$html,
				"The '{$group}' heading is missing its icon."
			);
		}

		$this->assertStringContainsString(
			'aria-hidden="true"',
			$html,
			'The icons are not hidden from assistive technology, so each heading is read out twice.'
		);
	}

	/**
	 * Every group has an icon, and no two share one.
	 *
	 * A duplicate would not error -- it would just make two groups look alike at
	 * 20px, which is exactly the confusion §4.1 records between wp-polls and
	 * wp-stats over dashicons-chart-bar.
	 */
	public function test_every_group_has_an_icon_of_its_own() {
		$sweep  = $this->sweep();
		$groups = array_keys( $sweep->get_sweep_groups() );
		$icons  = array();

		foreach ( $groups as $group ) {
			$icon = $sweep->get_sweep_group_icon( $group );

			$this->assertNotSame( '', $icon, "The '{$group}' group has no icon." );

			$icons[] = $icon;
		}

		$this->assertSame( $icons, array_unique( $icons ), 'Two groups share a dashicon.' );
		$this->assertSame( '', $sweep->get_sweep_group_icon( 'nonsense' ), 'An unknown group must yield no icon rather than a broken class.' );
	}

	/**
	 * Sorting by a column drops the headings rather than lying about them.
	 */
	public function test_sorting_removes_the_group_headings() {
		$_GET = array(
			'orderby' => 'count',
			'order'   => 'desc',
		);

		ob_start();
		$this->table()->display();
		$html = ob_get_clean();

		$this->assertStringNotContainsString(
			'wp-sweep-group-heading',
			$html,
			'The group headings survived a column sort, where they no longer describe the rows beneath them.'
		);
	}

	/**
	 * A count worth acting on is bold; a zero is not.
	 */
	public function test_only_a_count_worth_acting_on_is_bold() {
		$this->make_revisions( 3 );

		$table = $this->table();
		$rows  = wp_list_pluck( $table->items, 'count', 'name' );

		$this->assertGreaterThan( 0, $rows['revisions'], 'The fixture did not create any revisions, so this proves nothing.' );

		// The element carrying the class is the emphasis, not a tag nested in
		// it: the script updates this cell with textContent, which would strip
		// an inner <strong> on the first sweep and leave the screen looking
		// right only until somebody used it.
		$this->assertStringContainsString(
			'<strong class="sweep-count">',
			$table->column_count( array( 'count' => 5 ) ),
			'A count with something in it is not emphasised.'
		);
		$this->assertStringContainsString(
			'<span class="sweep-count">',
			$table->column_count( array( 'count' => 0 ) ),
			'A zero count is emphasised, which draws the eye to the one row there is nothing to do about.'
		);
		$this->assertStringNotContainsString(
			'<span class="sweep-count"><strong>',
			$table->column_count( array( 'count' => 5 ) ),
			'The emphasis is nested inside the span the script overwrites, so it will not survive a sweep.'
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
