<?php
/**
 * The list of sweeps.
 *
 * @package WP-Sweep
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists every sweep, what it would remove, and how to remove it.
 *
 * Until 2.0.0 this screen was six hand-written tables carrying the same eleven
 * lines of markup nineteen times, with the column widths set by an inline
 * <style> block. One list table replaces all of it, which is also what makes
 * the bulk action possible: every row on this screen deletes data, and offering
 * that one row at a time was the reason a full clean-up meant nineteen clicks.
 *
 * Every row action is a real, nonced link that works with JavaScript turned
 * off. The script intercepts them so the page does not reload nineteen times.
 */
class WP_Sweep_List_Table extends WP_List_Table {

	/**
	 * Rows per page.
	 *
	 * **It must stay larger than the number of sweeps the plugin ships**, and
	 * that is a requirement rather than a preference. The unfiltered view groups
	 * its rows under headings, and a heading means "everything below me is a
	 * Post Sweep" -- which stops being true the moment a group is split across a
	 * page boundary. The reader would see a heading on page one and its
	 * remaining rows on page two under no heading at all.
	 *
	 * It is also what keeps "select all" meaning all. §4.3 records the same
	 * reasoning for why wp-dbmanager refuses to paginate its tables list:
	 * paging reduces a select-all to select-this-page, silently.
	 *
	 * The sweep list is a fixed set this plugin ships -- nineteen today -- not
	 * user data that grows, so there is no page size a site can outgrow. Fifty
	 * leaves room for a good many more. `test_the_page_size_cannot_split_a_group`
	 * asserts the relationship rather than the number, so adding sweeps past it
	 * fails the suite instead of quietly breaking the headings.
	 *
	 * @var int
	 */
	const PER_PAGE = 50;

	/**
	 * Build the table.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'sweep',
				'plural'   => 'sweeps',
				'ajax'     => false,
				'screen'   => WP_Sweep_Admin::PAGE,
			)
		);
	}

	/**
	 * The table's CSS classes.
	 *
	 * `fixed` is dropped deliberately. It forces every column to the same width
	 * and the details list is far longer than the counts beside it, so keeping
	 * it meant shipping a stylesheet to undo it -- which is how the inline
	 * <style> block got there in the first place.
	 *
	 * @return array
	 */
	protected function get_table_classes() {
		return array( 'widefat', 'striped', $this->_args['plural'], 'table-sweep' );
	}

	/**
	 * The columns, in order.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'name'       => _x( 'Sweep', 'Column heading', 'wp-sweep' ),
			'group'      => __( 'Group', 'wp-sweep' ),
			'count'      => __( 'Count', 'wp-sweep' ),
			'percentage' => __( '%', 'wp-sweep' ),
			'actions'    => __( 'Actions', 'wp-sweep' ),
		);
	}

	/**
	 * The Sweep and Details buttons, in a column of their own.
	 *
	 * Not row actions. WordPress hides those until the row is hovered, which is
	 * right for Edit and Trash on a list of posts, where the row itself is the
	 * subject and the actions are secondary. Here the action *is* the subject:
	 * there is nothing else to do with a row but sweep it, and 1.2.0 put a
	 * visible button on every row. Hiding the only verb on the screen behind a
	 * hover -- and behind nothing at all on a touch screen -- was a real
	 * regression, and this is the column that undoes it.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_actions( $item ) {
		// Strict, so a deferred count (null) keeps its buttons: the row cannot
		// know yet that there is nothing to sweep, and sweeping an empty sweep
		// reports "nothing left" rather than doing any harm. The script swaps
		// the buttons for the dash once a zero arrives.
		if ( 0 === $item['count'] ) {
			return '<span class="sweep-nothing" aria-hidden="true">&mdash;</span><span class="screen-reader-text">'
				. esc_html__( 'Nothing to sweep', 'wp-sweep' ) . '</span>';
		}

		// aria-controls names the region the script reports this sweep's result
		// into. Naming it here is what stops the script having to find it by
		// walking the markup: the region is above the form and the row is
		// inside it, and the walk that used to bridge that gap never once
		// found the region on a real screen.
		return $this->action_link( $item, 'sweep', __( 'Sweep', 'wp-sweep' ), 'button button-primary', array( 'aria-controls' => WP_Sweep_Admin::MESSAGE_ID ) )
			. ' '
			. $this->action_link( $item, 'sweep_details', __( 'Details', 'wp-sweep' ), 'button', array( 'aria-expanded' => 'false' ) );
	}

	/**
	 * The columns a user can sort by.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'name'  => array( 'name', false ),
			'group' => array( 'group', false ),
			'count' => array( 'count', true ),
		);
	}

	/**
	 * The column row actions hang off.
	 *
	 * @return string
	 */
	protected function get_primary_column_name() {
		return 'name';
	}

	/**
	 * The nonce action guarding the bulk form.
	 *
	 * WP_List_Table::display_tablenav() prints wp_nonce_field() for this action
	 * itself, in a field named _wpnonce. Printing a second _wpnonce beside it --
	 * which this screen used to do -- does not add a check, it replaces one:
	 * PHP keeps the last field of a repeated name, so whichever the plugin chose
	 * was thrown away and every bulk sweep failed with "The link you followed has
	 * expired". Verify the one core actually emits.
	 *
	 * @return string
	 */
	public function bulk_nonce_action() {
		return 'bulk-' . $this->_args['plural'];
	}

	/**
	 * The actions offered for the checked rows.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array( 'sweep' => __( 'Sweep', 'wp-sweep' ) );
	}

	/**
	 * Whether the rows are shown under group headings.
	 *
	 * Only in the unfiltered view, and only while no column is doing the
	 * ordering. Inside a single group the heading would repeat the filter the
	 * reader just clicked, and under a column sort it would be a lie.
	 *
	 * @return bool
	 */
	private function is_grouped() {
		$args = self::request_args();

		return 'all' === self::current_group()
			&& ! array_key_exists( $args['orderby'], $this->get_sortable_columns() );
	}

	/**
	 * Put the rows in group order, keeping each group's own order intact.
	 *
	 * The order of get_sweep_groups() rather than alphabetical, so the headings
	 * read Post, Comment, User, Term, Option, Database -- the order the groups
	 * are declared in and the order the screen has always listed them. Within a
	 * group the rows keep the order get_sweeps() gave them, which is dependency
	 * order: posts are swept before the sweeps that hunt the meta deleting them
	 * just orphaned.
	 *
	 * @param array $rows Rows to order.
	 * @return array
	 */
	private function order_by_group( $rows ) {
		$ordered = array();

		foreach ( array_keys( WP_Sweep::get_instance()->get_sweep_groups() ) as $group ) {
			foreach ( $rows as $row ) {
				if ( $row['group'] === $group ) {
					$ordered[] = $row;
				}
			}
		}

		// A row whose group is not one of the six would vanish otherwise. There
		// is no such row today and nothing should add one, but silently dropping
		// it would be a worse way to find out.
		foreach ( $rows as $row ) {
			if ( ! array_key_exists( $row['group'], WP_Sweep::get_instance()->get_sweep_groups() ) ) {
				$ordered[] = $row;
			}
		}

		return $ordered;
	}

	/**
	 * The rows, with a heading before each group.
	 *
	 * WP_List_Table has no notion of row groups, so the heading is emitted as an
	 * ordinary row spanning every column. It carries `role="presentation"` on
	 * the cell's checkbox position rather than an empty `<th>`, so the table's
	 * column count stays honest for assistive technology.
	 *
	 * Deliberately one `<tbody>` and not one per group: core's common.js binds
	 * select-all to `#the-list`, and splitting the body would leave the header
	 * checkbox toggling only whichever group came first.
	 *
	 * @return void
	 */
	public function display_rows() {
		if ( ! $this->is_grouped() ) {
			parent::display_rows();

			return;
		}

		$sweep   = WP_Sweep::get_instance();
		$groups  = $sweep->get_sweep_groups();
		$columns = count( $this->get_columns() );
		$current = null;

		foreach ( $this->items as $item ) {
			if ( $item['group'] !== $current ) {
				$current = $item['group'];
				$icon    = $sweep->get_sweep_group_icon( $current );
				$label   = isset( $groups[ $current ] ) ? $groups[ $current ] : $current;

				printf(
					'<tr class="wp-sweep-group-heading"><td colspan="%1$s"><strong>%2$s%3$s</strong></td></tr>',
					esc_attr( $columns ),
					'' === $icon
						? ''
						: '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span> ',
					esc_html( $label )
				);
			}

			$this->single_row( $item );
		}
	}

	/**
	 * The group filters above the table.
	 *
	 * @return array
	 */
	protected function get_views() {
		$sweeps  = WP_Sweep::get_instance()->get_sweeps();
		$current = self::current_group();

		$views = array(
			'all' => $this->view_link( 'all', __( 'All', 'wp-sweep' ), count( $sweeps ), $current ),
		);

		foreach ( WP_Sweep::get_instance()->get_sweep_groups() as $group => $label ) {
			$views[ $group ] = $this->view_link(
				$group,
				$label,
				count( wp_list_filter( $sweeps, array( 'group' => $group ) ) ),
				$current
			);
		}

		return $views;
	}

	/**
	 * One group filter link.
	 *
	 * @param string $group   Group name, or 'all'.
	 * @param string $label   Translated label.
	 * @param int    $total   Number of sweeps in the group.
	 * @param string $current The group currently being shown.
	 * @return string
	 */
	private function view_link( $group, $label, $total, $current ) {
		$url = add_query_arg(
			array(
				'page'  => WP_Sweep_Admin::PAGE,
				'group' => $group,
			),
			admin_url( 'tools.php' )
		);

		return sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
			esc_url( $url ),
			$group === $current ? ' class="current" aria-current="page"' : '',
			esc_html( $label ),
			esc_html( number_format_i18n( $total ) )
		);
	}

	/**
	 * The four navigation parameters this screen reads off the URL.
	 *
	 * Every read of the query string is here, so there is one place to look.
	 * None of them is form data: they choose which rows are shown and in what
	 * order, and they change nothing. Core builds its own sortable column
	 * headers by swapping orderby and order on the current URL, so the link
	 * carries no nonce and there is nothing to verify -- which is why phpcs.xml
	 * excuses the sniff for *-table.php across the whole collection.
	 *
	 * @return array The group, orderby, order and counts, each already sanitised.
	 */
	private static function request_args() {
		$query = wp_unslash( $_GET );

		return array(
			'group'   => isset( $query['group'] ) ? sanitize_key( $query['group'] ) : 'all',
			'orderby' => isset( $query['orderby'] ) ? sanitize_key( $query['orderby'] ) : '',
			'order'   => isset( $query['order'] ) && 'desc' === strtolower( sanitize_key( $query['order'] ) ) ? 'desc' : 'asc',
			'counts'  => isset( $query['counts'] ) ? sanitize_key( $query['counts'] ) : '',
		);
	}

	/**
	 * Whether the request asked for the counts to be computed with the page.
	 *
	 * This is the no-JavaScript path to the numbers: the counts are normally
	 * left out of the render and fetched afterwards, and a real link carrying
	 * counts=now is what a reader without the script clicks instead.
	 *
	 * @return bool
	 */
	public static function counts_requested_now() {
		return 'now' === self::request_args()['counts'];
	}

	/**
	 * Whether the counts are left out of the render and fetched afterwards.
	 *
	 * The counts are the expensive half of this screen: every one is a query
	 * against a table WordPress cannot answer for from cache, and 2.0.0
	 * computing all of them before printing a byte is what turned the screen
	 * into a white page on sites whose meta tables have grown into the
	 * millions of rows. Deferred, the screen renders at once and the script
	 * fills the numbers in one sweep at a time.
	 *
	 * Two requests still compute them with the page, because the render cannot
	 * be right without them: counts=now (the no-JavaScript link), and a sort
	 * on the Count column, which cannot order rows by numbers it does not
	 * have.
	 *
	 * @return bool
	 */
	public static function defer_counts() {
		if ( self::counts_requested_now() || 'count' === self::request_args()['orderby'] ) {
			return false;
		}

		/**
		 * Filters whether the Sweep screen defers its counts to the script.
		 *
		 * @since 2.0.1
		 *
		 * @param bool $defer Whether to defer. Default true.
		 */
		return (bool) apply_filters( 'wp_sweep_defer_counts', true );
	}

	/**
	 * The group the request asked for.
	 *
	 * @return string Group name, or 'all'.
	 */
	public static function current_group() {
		$group = self::request_args()['group'];

		return array_key_exists( $group, WP_Sweep::get_instance()->get_sweep_groups() ) ? $group : 'all';
	}

	/**
	 * Gather, filter, sort and paginate the rows.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$sweep = WP_Sweep::get_instance();
		$group = self::current_group();
		$defer = self::defer_counts();

		$rows = array();

		// One total per table, not one per row. Three postmeta sweeps share a
		// denominator, and total_count() is a COUNT(*) that InnoDB answers by
		// scanning an index -- asking three times was a third of what made
		// loading this screen time out on large sites.
		$totals = array();

		foreach ( $sweep->get_sweeps() as $name => $args ) {
			if ( 'all' !== $group && $args['group'] !== $group ) {
				continue;
			}

			if ( $defer ) {
				$count      = null;
				$percentage = null;
			} else {
				$count = (int) $sweep->count( $name );

				if ( ! array_key_exists( $args['type'], $totals ) ) {
					$totals[ $args['type'] ] = $sweep->total_count( $args['type'] );
				}

				$percentage = $sweep->format_percentage( $count, $totals[ $args['type'] ] );
			}

			$rows[] = array(
				'name'        => $name,
				'label'       => $args['label'],
				'description' => isset( $args['description'] ) ? (string) $args['description'] : '',
				'type'        => $args['type'],
				'group'       => $args['group'],
				'count'       => $count,
				'percentage'  => $percentage,
			);
		}

		$rows = $this->sort( $rows );

		// Group the rows only when nothing else is ordering them. A reader who
		// has clicked Count wants the biggest sweep first, and headings under
		// that order would claim a grouping the rows no longer have.
		if ( $this->is_grouped() ) {
			$rows = $this->order_by_group( $rows );
		}

		$total = count( $rows );
		$page  = $this->get_pagenum();

		$this->items = array_slice( $rows, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'name' );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => self::PER_PAGE,
				'total_pages' => (int) ceil( $total / self::PER_PAGE ),
			)
		);
	}

	/**
	 * Order the rows by whatever the column headers were clicked for.
	 *
	 * The default is the order the sweeps have to run in, which is the order
	 * get_sweeps() declares them: posts are deleted before the sweeps that hunt
	 * for the meta that deleting them just orphaned.
	 *
	 * @param array $rows Rows to sort.
	 * @return array
	 */
	private function sort( $rows ) {
		$args    = self::request_args();
		$orderby = $args['orderby'];
		$order   = $args['order'];

		if ( ! array_key_exists( $orderby, $this->get_sortable_columns() ) ) {
			return $rows;
		}

		usort(
			$rows,
			static function ( $a, $b ) use ( $orderby ) {
				if ( 'count' === $orderby ) {
					return $a['count'] <=> $b['count'];
				}

				if ( 'group' === $orderby ) {
					return strcmp( $a['group'], $b['group'] );
				}

				return strcmp( $a['label'], $b['label'] );
			}
		);

		return 'desc' === $order ? array_reverse( $rows ) : $rows;
	}

	/**
	 * The message shown when a filter leaves nothing to show.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No sweeps to show. Nothing here needs cleaning up.', 'wp-sweep' );
	}

	/**
	 * The row's checkbox.
	 *
	 * A sweep with nothing to remove gets no checkbox, so "select all" cannot
	 * queue up eighteen no-ops to run one at a time.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_cb( $item ) {
		/*
		 * Every row gets one, including a row with nothing to sweep.
		 *
		 * This used to return an empty string for an empty row, on the reasoning
		 * that a bulk sweep should not be able to queue a no-op. It never
		 * actually prevented one -- the count is a snapshot taken when the page
		 * rendered, and anything that empties or fills a sweep between then and
		 * the form post leaves the checkbox saying the wrong thing either way --
		 * and sweeping an empty sweep removes nothing regardless. What it did do
		 * was leave gaps down the checkbox column and make the header's select-all
		 * claim more rows than it selects, which no core list table does.
		 */
		return sprintf(
			'<label class="screen-reader-text" for="sweep_%1$s">%2$s</label><input type="checkbox" id="sweep_%1$s" name="sweep[]" value="%1$s" />',
			esc_attr( $item['name'] ),
			/* translators: %s is the name of a sweep. */
			esc_html( sprintf( __( 'Select %s', 'wp-sweep' ), $item['label'] ) )
		);
	}

	/**
	 * The name column, with its row actions and its details container.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_name( $item ) {
		$name = '<strong>' . esc_html( $item['label'] ) . '</strong>';

		// Every sweep says what it removes. These screens delete data that does
		// not come back, and "Orphaned Term Relationships" tells a site owner
		// nothing about whether it is safe to tick.
		if ( ! empty( $item['description'] ) ) {
			$name .= '<p class="description">' . esc_html( $item['description'] ) . '</p>';
		}

		$details = WP_Sweep_Admin::requested_details();

		if ( isset( $details[ $item['name'] ] ) && ! empty( $details[ $item['name'] ] ) ) {
			$list = '';

			foreach ( $details[ $item['name'] ] as $detail ) {
				// Every entry here came out of the database -- post titles,
				// comment author names, meta keys. A comment author name is
				// supplied by whoever left the comment.
				$list .= '<li>' . esc_html( $detail ) . '</li>';
			}

			// A div rather than the p this used to be. An <ol> inside a <p> is
			// not valid, and a browser does not merely tolerate it: the parser
			// closes the paragraph before the list, so .sweep-details ended up
			// empty and the <ol> became its sibling. Anyone with JavaScript
			// never saw the bug, because renderDetails() builds the same list
			// through the DOM and gets what it asked for -- so only visitors
			// without it lost the details entirely.
			//
			// The <ol> stays inside rather than becoming .sweep-details itself,
			// so that this markup and the one renderDetails() produces are the
			// same shape. The two paths disagreeing is what the bug was.
			$name .= '<div class="sweep-details"><ol>' . $list . '</ol></div>';
		} else {
			$name .= '<div class="sweep-details" hidden></div>';
		}

		if ( 0 === $item['count'] ) {
			return $name;
		}

		// No row_actions() here: the buttons live in their own always-visible
		// column. See column_actions().
		return $name;
	}

	/**
	 * One row action.
	 *
	 * The href is a working, nonced request. The script reads the same name,
	 * type and nonce back off the data attributes and calls admin-ajax.php
	 * instead, so the row updates in place rather than reloading the screen.
	 *
	 * @param array  $item    Row.
	 * @param string $action  Either sweep or sweep_details.
	 * @param string $label   Translated link text.
	 * @param string $classes    Extra CSS classes, so the same link can be drawn
	 *                            as a button.
	 * @param array  $attributes Extra attributes, already-escaped values keyed
	 *                            by attribute name.
	 * @return string
	 */
	private function action_link( $item, $action, $label, $classes = '', $attributes = array() ) {
		$nonce = 'sweep' === $action ? 'wp_sweep_' . $item['name'] : 'wp_sweep_details_' . $item['name'];

		$args = array(
			'page'  => WP_Sweep_Admin::PAGE,
			'group' => self::current_group(),
			$action => $item['name'],
		);

		// A reader on the counts=now view has no script following these links,
		// so the reload the link causes has to stay on that view -- dropping
		// the parameter would sweep correctly and then land them back on a
		// screen of ellipses.
		if ( self::counts_requested_now() ) {
			$args['counts'] = 'now';
		}

		$url = wp_nonce_url( add_query_arg( $args, admin_url( 'tools.php' ) ), $nonce );

		$extra = '';

		foreach ( $attributes as $attribute => $value ) {
			$extra .= sprintf( ' %1$s="%2$s"', esc_attr( $attribute ), esc_attr( $value ) );
		}

		return sprintf(
			'<a href="%1$s" class="%2$s" data-action="%3$s" data-sweep-name="%4$s" data-sweep-type="%5$s" data-nonce="%6$s"%7$s>%8$s</a>',
			esc_url( $url ),
			trim( ( 'sweep' === $action ? 'btn-sweep' : 'btn-sweep-details' ) . ' ' . $classes ),
			esc_attr( $action ),
			esc_attr( $item['name'] ),
			esc_attr( $item['type'] ),
			esc_attr( wp_create_nonce( $nonce ) ),
			$extra,
			esc_html( $label )
		);
	}

	/**
	 * The count column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_count( $item ) {
		// A count that was deferred rather than computed. The cell carries
		// everything the script needs to fetch the number -- the same
		// name/type/nonce vocabulary the row actions use -- and an ellipsis for
		// anyone reading before it lands. The no-JavaScript path to a real
		// number is the counts=now link the screen prints in a <noscript>.
		if ( null === $item['count'] ) {
			return sprintf(
				'<span class="sweep-count sweep-count-pending" data-action="sweep_count" data-sweep-name="%1$s" data-sweep-type="%2$s" data-nonce="%3$s">&hellip;</span>',
				esc_attr( $item['name'] ),
				esc_attr( $item['type'] ),
				esc_attr( wp_create_nonce( 'wp_sweep_count_' . $item['name'] ) )
			);
		}

		// Bold only a count there is something to do about. A run of bold zeroes
		// reads as emphasis on nothing, and the whole point of this column is to
		// show at a glance which rows are worth ticking. <strong> rather than a
		// stylesheet: this plugin ships no CSS, and one rule does not earn the
		// first file.
		//
		// The emphasis is the element carrying the class, never a tag nested
		// inside it. js/wp-sweep-admin.js updates this cell after a sweep with
		// `.textContent =`, which replaces everything between the tags -- so a
		// <strong> *within* the span would survive exactly until the first sweep
		// and then vanish, on a screen nobody reloads. The script demotes the
		// element to a <span> itself once the count reaches zero.
		$tag = $item['count'] > 0 ? 'strong' : 'span';

		return sprintf(
			'<%1$s class="sweep-count">%2$s</%1$s>',
			$tag,
			esc_html( number_format_i18n( $item['count'] ) )
		);
	}

	/**
	 * The percentage column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_percentage( $item ) {
		// Empty while the count is deferred: the script writes the percentage
		// in beside the count, and an ellipsis in two columns would say
		// "loading" twice about one fetch.
		return '<span class="sweep-percentage">' . ( null === $item['percentage'] ? '' : esc_html( $item['percentage'] ) ) . '</span>';
	}

	/**
	 * Anything without a column method of its own.
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column being rendered.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		if ( 'group' === $column_name ) {
			$groups = WP_Sweep::get_instance()->get_sweep_groups();

			return esc_html( isset( $groups[ $item['group'] ] ) ? $groups[ $item['group'] ] : $item['group'] );
		}

		return isset( $item[ $column_name ] ) ? esc_html( $item[ $column_name ] ) : '';
	}
}
