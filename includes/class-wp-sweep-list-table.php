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
	 * @var int
	 */
	const PER_PAGE = 20;

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
			'percentage' => __( '% Of', 'wp-sweep' ),
		);
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
	 * The actions offered for the checked rows.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array( 'sweep' => __( 'Sweep', 'wp-sweep' ) );
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
			admin_url( 'admin.php' )
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
	 * The three navigation parameters this screen reads off the URL.
	 *
	 * Every read of the query string is here, so there is one place to look.
	 * None of them is form data: they choose which rows are shown and in what
	 * order, they change nothing, and a nonce on them would only mean a
	 * bookmarked, sorted URL stopped working. That is why the one suppression
	 * in this plugin is on the line below rather than scattered over the four
	 * methods that want these values.
	 *
	 * @return array The group, orderby and order, each already sanitised.
	 */
	private static function request_args() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list table navigation; nothing here changes state, and a nonce would break a bookmarked URL.
		$query = wp_unslash( $_GET );

		return array(
			'group'   => isset( $query['group'] ) ? sanitize_key( $query['group'] ) : 'all',
			'orderby' => isset( $query['orderby'] ) ? sanitize_key( $query['orderby'] ) : '',
			'order'   => isset( $query['order'] ) && 'desc' === strtolower( sanitize_key( $query['order'] ) ) ? 'desc' : 'asc',
		);
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

		$rows = array();

		foreach ( $sweep->get_sweeps() as $name => $args ) {
			if ( 'all' !== $group && $args['group'] !== $group ) {
				continue;
			}

			$count = (int) $sweep->count( $name );

			$rows[] = array(
				'name'       => $name,
				'label'      => $args['label'],
				'type'       => $args['type'],
				'group'      => $args['group'],
				'count'      => $count,
				'percentage' => $sweep->format_percentage( $count, $sweep->total_count( $args['type'] ) ),
			);
		}

		$rows = $this->sort( $rows );

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
		if ( 0 === $item['count'] ) {
			return '';
		}

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

		if ( 'unused_terms' === $item['name'] ) {
			$name .= '<p class="description">' . esc_html__( 'Some unused terms belong to drafts that have not been published yet. Only sweep this when you have no draft posts.', 'wp-sweep' ) . '</p>';
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

			$name .= '<p class="sweep-details"><ol>' . $list . '</ol></p>';
		} else {
			$name .= '<p class="sweep-details" hidden></p>';
		}

		if ( 0 === $item['count'] ) {
			return $name;
		}

		return $name . $this->row_actions(
			array(
				'sweep'   => $this->action_link( $item, 'sweep', __( 'Sweep', 'wp-sweep' ) ),
				'details' => $this->action_link( $item, 'sweep_details', __( 'Details', 'wp-sweep' ) ),
			)
		);
	}

	/**
	 * One row action.
	 *
	 * The href is a working, nonced request. The script reads the same name,
	 * type and nonce back off the data attributes and calls admin-ajax.php
	 * instead, so the row updates in place rather than reloading the screen.
	 *
	 * @param array  $item   Row.
	 * @param string $action Either sweep or sweep_details.
	 * @param string $label  Translated link text.
	 * @return string
	 */
	private function action_link( $item, $action, $label ) {
		$nonce = 'sweep' === $action ? 'wp_sweep_' . $item['name'] : 'wp_sweep_details_' . $item['name'];

		$url = wp_nonce_url(
			add_query_arg(
				array(
					'page'  => WP_Sweep_Admin::PAGE,
					'group' => self::current_group(),
					$action => $item['name'],
				),
				admin_url( 'admin.php' )
			),
			$nonce
		);

		return sprintf(
			'<a href="%1$s" class="%2$s" data-action="%3$s" data-sweep-name="%4$s" data-sweep-type="%5$s" data-nonce="%6$s">%7$s</a>',
			esc_url( $url ),
			'sweep' === $action ? 'btn-sweep' : 'btn-sweep-details',
			esc_attr( $action ),
			esc_attr( $item['name'] ),
			esc_attr( $item['type'] ),
			esc_attr( wp_create_nonce( $nonce ) ),
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
		return '<span class="sweep-count">' . esc_html( number_format_i18n( $item['count'] ) ) . '</span>';
	}

	/**
	 * The percentage column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public function column_percentage( $item ) {
		return '<span class="sweep-percentage">' . esc_html( $item['percentage'] ) . '</span>';
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
