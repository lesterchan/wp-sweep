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
 * Markup for one sweep section, matching what admin.php emits.
 *
 * A bare <tr> in innerHTML is discarded by the HTML parser, so the rows are
 * always built inside a full table.
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
		<p>There are a total of
			<strong><span class="sweep-count-type-posts">10</span> Posts</strong> and
			<strong><span class="sweep-count-type-postmeta">2</span> Post Meta</strong>.
		</p>
		<div class="sweep-message"></div>
		<table class="widefat table-sweep">
			<tbody>
				<tr>
					<th scope="row" class="check-column"><input type="checkbox" name="sweep[]" value="${ name }" /></th>
					<td>
						<strong>Revisions</strong>
						<p class="sweep-details" hidden></p>
					</td>
					<td><span class="sweep-count">${ count }</span></td>
					<td><span class="sweep-percentage">${ percentage }</span></td>
					<td class="column-actions">
						<a href="?sweep=${ name }" data-action="sweep" data-sweep-name="${ name }" data-sweep-type="${ type }" data-nonce="NONCE" class="btn-sweep button button-primary">Sweep</a>
						<a href="?sweep_details=${ name }" data-action="sweep_details" data-sweep-name="${ name }" data-sweep-type="${ type }" data-nonce="DNONCE" class="btn-sweep-details button">Details</a>
					</td>
				</tr>
			</tbody>
		</table>
	`;
}
