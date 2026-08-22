/**
 * Tests for the deferred counts the script fills in after the screen renders.
 *
 * The fill runs once, as the script is evaluated -- it is a page-load action,
 * not something a listener can be poked into repeating -- so this file builds
 * the deferred screen first, boots the script over it, lets the whole chain
 * settle, and then reads the result from several angles. A file of its own
 * rather than more cases in wp-sweep-admin.test.js, whose boot happens over an
 * empty page.
 */
import { beforeAll, describe, expect, it } from 'vitest';
import {
	bootScript,
	countResponse,
	l10n,
	stubFetch,
	sweepSection,
	totalsResponse,
} from './helpers.js';

/**
 * The parameters of every request the fill sent, in order.
 *
 * Snapshotted in beforeAll: the config restores mocks before each test, which
 * wipes the spy's own record of its calls, and the fill cannot be re-run --
 * it happens once, as the script is evaluated.
 */
const sent = [];

/**
 * A row's cells, found through its checkbox value.
 *
 * @param {string} name Sweep name.
 * @return {HTMLElement} The row.
 */
function row( name ) {
	return document
		.querySelector( `#the-list input[value="${ name }"]` )
		.closest( 'tr' );
}

beforeAll( async () => {
	document.body.innerHTML = sweepSection( {
		deferred: true,
		rows: [
			{ name: 'auto_drafts', type: 'posts', label: 'Auto Drafts' },
			{ name: 'deleted_posts', type: 'posts', label: 'Deleted Posts' },
		],
	} );

	// The responses in request order: the totals come first, then the rows in
	// document order, because the fill is one request at a time.
	const fetchSpy = stubFetch( [
		totalsResponse( { posts: 10, postmeta: 4 } ),
		countResponse( { count: 2, percentage: '20%' } ),
		countResponse( { count: 0, percentage: '0%' } ),
		{ success: false, data: { error: 'Invalid AJAX request.' } },
	] );

	bootScript();

	// Four sequential fetches, each with a .then() of its own to settle.
	for ( let turn = 0; turn < 12; turn++ ) {
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
	}

	fetchSpy.mock.calls.forEach( ( [ url ] ) => {
		sent.push(
			Object.fromEntries( new URLSearchParams( url.split( '?' )[ 1 ] ) ),
		);
	} );
} );

describe( 'the requests the fill sends', () => {
	it( 'asks for the totals first, with the nonce the table carries', () => {
		expect( sent[ 0 ] ).toEqual( {
			action: 'sweep_totals',
			_wpnonce: 'TNONCE',
		} );
	} );

	it( 'then asks for each row, one at a time and in order', () => {
		expect( sent ).toHaveLength( 4 );

		expect( sent[ 1 ] ).toEqual( {
			action: 'sweep_count',
			sweep_name: 'revisions',
			sweep_type: 'posts',
			_wpnonce: 'C-revisions',
		} );
		expect( sent[ 2 ].sweep_name ).toBe( 'auto_drafts' );
		expect( sent[ 3 ].sweep_name ).toBe( 'deleted_posts' );
	} );
} );

describe( 'a count that came back', () => {
	it( 'replaces the pending cell with the emphasised count', () => {
		const cell = row( 'revisions' ).querySelector( '.sweep-count' );

		expect( cell.tagName ).toBe( 'STRONG' );
		expect( cell.textContent ).toBe( '2' );
		expect( cell.classList.contains( 'sweep-count-pending' ) ).toBe( false );
	} );

	it( 'writes the percentage in beside it', () => {
		expect(
			row( 'revisions' ).querySelector( '.sweep-percentage' ).textContent,
		).toBe( '20%' );
	} );

	it( 'leaves the row actions alone while there is something to sweep', () => {
		expect( row( 'revisions' ).querySelector( '.btn-sweep' ) ).not.toBeNull();
	} );
} );

describe( 'a count that came back zero', () => {
	it( 'renders as an unemphasised span', () => {
		const cell = row( 'auto_drafts' ).querySelector( '.sweep-count' );

		expect( cell.tagName ).toBe( 'SPAN' );
		expect( cell.textContent ).toBe( '0' );
	} );

	it( 'swaps the buttons for the dash a fresh render gives an empty row', () => {
		const actions = row( 'auto_drafts' ).querySelector( '.column-actions' );

		expect( actions.querySelector( '.btn-sweep' ) ).toBeNull();
		expect( actions.querySelector( '.sweep-nothing' ) ).not.toBeNull();
		expect( actions.querySelector( '.screen-reader-text' ).textContent ).toBe(
			l10n.textNothingToSweep,
		);
	} );

	it( 'keeps the checkbox, so the column does not grow holes', () => {
		expect(
			row( 'auto_drafts' ).querySelector( 'input[type="checkbox"]' ),
		).not.toBeNull();
	} );
} );

describe( 'a count the server refused', () => {
	it( 'says N/A rather than pretending to still be loading', () => {
		expect(
			row( 'deleted_posts' ).querySelector( '.sweep-count' ).textContent,
		).toBe( l10n.textNa );
	} );

	it( 'keeps the buttons: the sweep itself may still work', () => {
		expect(
			row( 'deleted_posts' ).querySelector( '.btn-sweep' ),
		).not.toBeNull();
	} );
} );

describe( 'the running totals', () => {
	it( 'are filled from the one totals response, emphasis intact', () => {
		const posts = document.querySelector( '.sweep-count-type-posts' );
		const postmeta = document.querySelector( '.sweep-count-type-postmeta' );

		expect( posts.textContent ).toBe( '10' );
		expect( postmeta.textContent ).toBe( '4' );
		expect( posts.tagName ).toBe( 'STRONG' );
	} );

	it( 'shed the pending marker once written', () => {
		expect( document.querySelector( '.sweep-total-pending' ) ).toBeNull();
	} );
} );
