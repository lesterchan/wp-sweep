/**
 * Shared setup for the admin script tests.
 *
 * The script is an IIFE with no exports: it reads its localised strings off
 * `window` as it executes, then attaches delegated listeners to `document`. So
 * the l10n object has to exist before the script is evaluated, and the script
 * can only be evaluated once per test file or every listener fires twice.
 */
import { readFileSync } from 'node:fs';
import { vi } from 'vitest';

/**
 * The strings wp_localize_script() puts on the page.
 */
export const l10n = {
	textCloseWarning:
		"Sweeping is in progress. If you leave now, the process won't be completed.",
	textSweep: 'Sweep',
	textSweeping: 'Sweeping...',
	textNa: 'N/A',
};

/**
 * Evaluate a plugin script in the current jsdom page.
 *
 * @param {string} name File name relative to the plugin root.
 */
export function loadScript( name ) {
	const src = readFileSync( new URL( '../../' + name, import.meta.url ), 'utf8' );

	new Function( src )();
}

/**
 * Put the l10n object and ajaxurl on the page, then load the script.
 */
export function bootScript() {
	window.wpSweepL10n = l10n;
	window.ajaxurl = '/wp-admin/admin-ajax.php';

	loadScript( 'js/wp-sweep-admin.js' );
}

/**
 * Replace fetch with a spy resolving to the given JSON payload.
 *
 * @param {Object|Array} payload Successive responses, or one for every call.
 * @return {Function} The spy.
 */
export function stubFetch( payload ) {
	const queue = Array.isArray( payload ) ? payload.slice() : null;

	const spy = vi.fn( () =>
		Promise.resolve( {
			json: () =>
				Promise.resolve( queue ? queue.shift() ?? { success: false } : payload ),
		} ),
	);

	global.fetch = spy;
	window.fetch = spy;

	return spy;
}

/**
 * A successful sweep response.
 *
 * @param {Object} overrides Fields to override.
 * @return {Object} The payload.
 */
export function sweepResponse( overrides = {} ) {
	return {
		success: true,
		data: {
			sweep: '2 Revisions Processed',
			count: 0,
			total: 10,
			percentage: '0%',
			stats: { posts: 10, postmeta: 4 },
			...overrides,
		},
	};
}

/**
 * A successful details response.
 *
 * @param {Array} items The listed items.
 * @return {Object} The payload.
 */
export function detailsResponse( items ) {
	return { success: true, data: items };
}

/**
 * The query parameters fetch was called with, as a plain object.
 *
 * @param {Function} spy  fetch spy.
 * @param {number}   call Which call to read.
 * @return {Object} Field name to value.
 */
export function sentParams( spy, call = 0 ) {
	const [ url ] = spy.mock.calls[ call ];

	return Object.fromEntries( new URLSearchParams( url.split( '?' )[ 1 ] ) );
}

/**
 * Click an element and let the fetch promise chain settle.
 *
 * @param {Element} el Element to click.
 */
export async function clickAndSettle( el ) {
	el.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );

	// Two turns: request() resolves, then the .then() that updates the DOM.
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
}

/**
 * The id WP_Sweep_Admin::MESSAGE_ID carries, and the Sweep link's
 * aria-controls with it.
 *
 * Not a value this file is free to choose: fixture.test.js reads the constant
 * out of the PHP and fails if the two stop agreeing.
 */
export const MESSAGE_ID = 'wp-sweep-message';

/**
 * The screen, as WP_Sweep_Admin::render_page() and WP_List_Table build it.
 *
 * This is a transcription, not an approximation, and the difference nearly cost
 * the 2.0.0 release: the version that stood here before it put .sweep-message
 * immediately before the table as siblings, with no <form> and no .tablenav
 * between them. Every test passed, and on the real screen -- where the table is
 * inside the form and the message region is outside it -- no sweep had ever
 * reported its result. A fixture a test builds for itself only ever tests the
 * fixture.
 *
 * So the shape below is fixed by the PHP and may not be tidied:
 *
 *   .wrap
 *     table.sweep-totals            <- render_totals(), the running totals
 *     div.sweep-message             <- the region, OUTSIDE the form
 *     ul.subsubsub                  <- $table->views()
 *     form
 *       input[name=_wpnonce]        <- display_tablenav( 'top' ) prints it
 *       div.tablenav.top
 *       table.table-sweep           <- the rows, INSIDE the form
 *       div.tablenav.bottom
 *
 * Trimmed only in ways the script cannot see: one row rather than eighteen, one
 * group filter rather than seven, one totals row rather than six. A bare <tr> in
 * innerHTML is discarded by the parser, so rows are always built inside a table.
 *
 * @param {Object} options            Options.
 * @param {string} options.name       Sweep name.
 * @param {string} options.type       Sweep type.
 * @param {number} options.count      Count shown in the row.
 * @param {string} options.percentage Percentage shown in the row.
 * @return {string} HTML.
 */
export function sweepSection( {
	name = 'revisions',
	type = 'posts',
	count = 2,
	percentage = '20%',
} = {} ) {
	return `
		<div class="wrap">
			<h1>Sweep</h1>
			<div class="notice notice-warning"><p>Before you do any sweep, please <a href="https://wordpress.org/plugins/wp-dbmanager/">backup your database</a> first, because any sweep done is irreversible.</p></div>
			<p class="description">Details lists a sample of up to 500 items. Filter wp_sweep_limit_details to change it.</p>
			<table class="widefat striped sweep-totals">
				<thead><tr><th scope="col">Group</th><th scope="col">Currently in your database</th></tr></thead>
				<tbody>
					<tr>
						<th scope="row">Posts</th>
						<td><strong class="sweep-count-type-posts">10</strong> Posts, <strong class="sweep-count-type-postmeta">2</strong> Post Meta</td>
					</tr>
				</tbody>
			</table>
			<div class="sweep-message" id="${ MESSAGE_ID }" role="status"></div>
			<ul class="subsubsub">
				<li class="all"><a href="tools.php?page=wp-sweep&group=all" class="current" aria-current="page">All <span class="count">(18)</span></a></li>
			</ul>
			<form method="post" action="tools.php?page=wp-sweep&group=all">
				<input type="hidden" id="_wpnonce" name="_wpnonce" value="BULKNONCE" />
				<div class="tablenav top">
					<div class="alignleft actions bulkactions">
						<label for="bulk-action-selector-top" class="screen-reader-text">Select bulk action</label>
						<select name="action" id="bulk-action-selector-top">
							<option value="-1">Bulk actions</option>
							<option value="sweep">Sweep</option>
						</select>
						<input type="submit" id="doaction" class="button action" value="Apply" />
					</div>
					<br class="clear" />
				</div>
				<table class="wp-list-table widefat striped sweeps table-sweep">
					<thead>
						<tr>
							<td id="cb" class="manage-column column-cb check-column"><input id="cb-select-all-1" type="checkbox" /></td>
							<th scope="col" id="name" class="manage-column column-name column-primary sortable desc">Sweep</th>
							<th scope="col" id="group" class="manage-column column-group sortable desc">Group</th>
							<th scope="col" id="count" class="manage-column column-count sortable asc">Count</th>
							<th scope="col" id="percentage" class="manage-column column-percentage">%</th>
							<th scope="col" id="actions" class="manage-column column-actions">Actions</th>
						</tr>
					</thead>
					<tbody id="the-list" data-wp-lists="list:sweep">
						<tr>
							<th scope="row" class="check-column">
								<label class="screen-reader-text" for="sweep_${ name }">Select Revisions</label>
								<input type="checkbox" id="sweep_${ name }" name="sweep[]" value="${ name }" />
							</th>
							<td class="name column-name has-row-actions column-primary" data-colname="Sweep">
								<strong>Revisions</strong>
								<p class="description">Older copies of posts and pages, kept every time one is saved.</p>
								<div class="sweep-details" hidden></div>
								<button type="button" class="toggle-row"><span class="screen-reader-text">Show more details</span></button>
							</td>
							<td class="group column-group" data-colname="Group">Post Sweep</td>
							<td class="count column-count" data-colname="Count">${
	// The emphasis is the element itself, matching column_count():
	// a <strong> while there is something to sweep, a <span>
	// once there is not.
	count > 0
		? `<strong class="sweep-count">${ count }</strong>`
		: `<span class="sweep-count">${ count }</span>`
}</td>
							<td class="percentage column-percentage" data-colname="%"><span class="sweep-percentage">${ percentage }</span></td>
							<td class="actions column-actions" data-colname="Actions">
								<a href="tools.php?page=wp-sweep&sweep=${ name }&_wpnonce=abc123" class="btn-sweep button button-primary" data-action="sweep" data-sweep-name="${ name }" data-sweep-type="${ type }" data-nonce="NONCE" aria-controls="${ MESSAGE_ID }">Sweep</a>
								<a href="tools.php?page=wp-sweep&sweep_details=${ name }&_wpnonce=def456" class="btn-sweep-details button" data-action="sweep_details" data-sweep-name="${ name }" data-sweep-type="${ type }" data-nonce="DNONCE" aria-expanded="false">Details</a>
							</td>
						</tr>
					</tbody>
					<tfoot>
						<tr>
							<td class="manage-column column-cb check-column"><input id="cb-select-all-2" type="checkbox" /></td>
							<th scope="col" class="manage-column column-name column-primary sortable desc">Sweep</th>
							<th scope="col" class="manage-column column-group sortable desc">Group</th>
							<th scope="col" class="manage-column column-count sortable asc">Count</th>
							<th scope="col" class="manage-column column-percentage">%</th>
							<th scope="col" class="manage-column column-actions">Actions</th>
						</tr>
					</tfoot>
				</table>
				<div class="tablenav bottom">
					<br class="clear" />
				</div>
			</form>
		</div>
	`;
}
