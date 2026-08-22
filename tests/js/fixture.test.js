/**
 * The fixture is the screen, or these tests prove nothing.
 *
 * `sweepSection()` is a hand transcription of markup PHP builds, and until the
 * 2.0.0 release was tested in a browser it was a wrong one: it put the message
 * region and the table next to each other as siblings, which is not a shape the
 * plugin has ever rendered. The script was written to walk that DOM, the unit
 * tests agreed with the script, and on the real screen no sweep reported its
 * result.
 *
 * There is no WordPress here to render the real thing against, so these read
 * the PHP as source and pin the few facts the two files must agree on. That is
 * weaker than rendering the screen -- the PHPUnit companion,
 * WP_Sweep_Admin_Test::test_the_sweep_message_region_is_outside_the_form(),
 * asserts the same shape against real output -- but it runs without a container
 * and it fails on exactly the drift that hid the bug.
 */
import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { MESSAGE_ID, sweepSection } from './helpers.js';

/**
 * A plugin PHP file with its comments removed.
 *
 * Assertions about what the code does must not be satisfied by a comment that
 * merely mentions the thing -- the docblock explaining this bug names every
 * class and attribute involved in it. The PHPUnit suite strips comments before
 * asserting on source for the same reason.
 *
 * @param {string} name File name relative to the plugin root.
 * @return {string} The source, comments stripped.
 */
function phpSource( name ) {
	return readFileSync( new URL( '../../' + name, import.meta.url ), 'utf8' )
		.replace( /\/\*[\s\S]*?\*\//g, '' )
		.replace( /^[ \t]*\/\/.*$/gm, '' );
}

const admin = phpSource( 'includes/class-wp-sweep-admin.php' );
const listTable = phpSource( 'includes/class-wp-sweep-list-table.php' );

/**
 * The fixture, parsed.
 *
 * @return {Document} A document holding the rendered screen.
 */
function screen() {
	const parsed = document.implementation.createHTMLDocument( 'Sweep' );
	parsed.body.innerHTML = sweepSection();

	return parsed;
}

describe( 'the id the region and the row actions share', () => {
	it( 'is the one WP_Sweep_Admin::MESSAGE_ID declares', () => {
		const declared = admin.match( /const MESSAGE_ID = '([^']+)'/ );

		expect( declared ).not.toBeNull();
		expect( declared[ 1 ] ).toBe( MESSAGE_ID );
	} );

	it( 'is what the region in the fixture carries', () => {
		const region = screen().querySelector( '.sweep-message' );

		expect( region ).not.toBeNull();
		expect( region.id ).toBe( MESSAGE_ID );
	} );

	it( 'is what every Sweep link points aria-controls at', () => {
		// The script has no other way to find the region, so a link without
		// this reports nothing -- which is the bug, in its new clothes.
		expect( listTable ).toMatch(
			/'aria-controls' => WP_Sweep_Admin::MESSAGE_ID/,
		);

		expect(
			screen().querySelector( '.btn-sweep' ).getAttribute( 'aria-controls' ),
		).toBe( MESSAGE_ID );
	} );
} );

describe( 'where the PHP puts the region', () => {
	it( 'prints it before the form the table is displayed inside', () => {
		const region = admin.indexOf( 'class="sweep-message"' );
		const form = admin.indexOf( '<form method="post"' );
		const table = admin.indexOf( '$table->display()' );

		expect( region ).toBeGreaterThan( -1 );
		expect( form ).toBeGreaterThan( region );
		expect( table ).toBeGreaterThan( form );
	} );

	it( 'is transcribed that way round in the fixture', () => {
		const parsed = screen();
		const region = parsed.querySelector( '.sweep-message' );
		const table = parsed.querySelector( '.table-sweep' );

		expect( region.closest( 'form' ) ).toBeNull();
		expect( table.closest( 'form' ) ).not.toBeNull();
	} );

	it( 'never leaves the two as siblings, which is what hid the bug', () => {
		const parsed = screen();
		const table = parsed.querySelector( '.table-sweep' );

		// The exact assertion the old fixture could not have passed. Nothing
		// the script does may depend on this shape again, and a fixture that
		// quietly drifts back into it is how the bug got here.
		expect( table.previousElementSibling.className ).not.toContain(
			'sweep-message',
		);
		expect( table.parentElement.querySelector( '.sweep-message' ) ).toBeNull();
	} );
} );

describe( 'what anchors the two ways a sweep reports', () => {
	it( 'prints the marker between the heading and the region', () => {
		// Core's common.js re-inserts every notice directly after
		// hr.wp-header-end, so the marker is what settings_errors() ends up
		// underneath and the region has to be the next thing along.
		const heading = admin.indexOf( '<h1>' );
		const marker = admin.indexOf( '<hr class="wp-header-end">' );
		const errors = admin.indexOf( "settings_errors( 'wp_sweep' )" );
		const region = admin.indexOf( 'class="sweep-message"' );

		expect( marker ).toBeGreaterThan( heading );
		expect( errors ).toBeGreaterThan( marker );
		expect( region ).toBeGreaterThan( errors );
	} );

	it( 'keeps the permanent warning out of that gathering', () => {
		// Without `inline` the warning is hoisted to the marker like any other
		// notice, which lands it between the reload path's message and the
		// region -- the exact split moving the region up here was meant to end.
		expect( admin ).toContain( '<div class="notice notice-warning inline">' );

		const warning = admin.indexOf( 'notice notice-warning' );

		expect( warning ).toBeGreaterThan( admin.indexOf( 'class="sweep-message"' ) );
	} );

	it( 'is transcribed that way round in the fixture', () => {
		const region = screen().querySelector( '.sweep-message' );

		expect( region.previousElementSibling.className ).toBe( 'wp-header-end' );
		expect( region.nextElementSibling.className ).toContain( 'inline' );
	} );
} );

describe( 'the rest of the screen the script reaches for', () => {
	it( 'carries the running totals as spans inside the totals table', () => {
		const parsed = screen();

		expect( listTable ).not.toContain( 'sweep-count-type-' );
		expect( admin ).toContain( 'sweep-count-type-%1$s' );
		expect(
			parsed.querySelector( '.sweep-totals .sweep-count-type-posts' ),
		).not.toBeNull();
	} );

	it( 'renders a deferred count cell the way column_count() writes it', () => {
		// The pending cell is how the script knows a count is missing and how
		// it fetches the number: the same action/name/type/nonce vocabulary
		// the row actions carry, on the cell itself.
		expect( listTable ).toContain( 'sweep-count sweep-count-pending' );
		expect( listTable ).toContain( 'data-action="sweep_count"' );
		expect( listTable ).toContain(
			"wp_create_nonce( 'wp_sweep_count_' . $item['name'] )",
		);

		const parsed = document.implementation.createHTMLDocument( 'Sweep' );
		parsed.body.innerHTML = sweepSection( { deferred: true } );

		const cell = parsed.querySelector( '.sweep-count-pending' );

		expect( cell ).not.toBeNull();
		expect( cell.dataset.action ).toBe( 'sweep_count' );
		expect( cell.dataset.sweepName ).toBe( 'revisions' );
		expect( cell.dataset.sweepType ).toBe( 'posts' );
		expect( cell.dataset.nonce ).toBeTruthy();
	} );

	it( 'puts the totals nonce on the totals table, as render_totals() does', () => {
		expect( admin ).toContain( "wp_create_nonce( 'wp_sweep_totals' )" );
		expect( admin ).toContain( 'sweep-total-pending' );

		const parsed = document.implementation.createHTMLDocument( 'Sweep' );
		parsed.body.innerHTML = sweepSection( { deferred: true } );

		const table = parsed.querySelector( '.sweep-totals' );

		expect( table.dataset.nonce ).toBeTruthy();
		expect( table.querySelector( '.sweep-total-pending' ) ).not.toBeNull();
	} );

	it( 'registers the AJAX actions the fill calls', () => {
		expect( admin ).toContain( "add_action( 'wp_ajax_sweep_count'" );
		expect( admin ).toContain( "add_action( 'wp_ajax_sweep_totals'" );
	} );

	it( 'gives each row a details container that is a div, not a p', () => {
		// An <ol> inside a <p> does not survive the parser: it closes the
		// paragraph and becomes its sibling. column_name() learned that once
		// already, and a fixture using a <p> would let it be relearnt.
		expect( listTable ).toContain( '<div class="sweep-details" hidden>' );
		expect( screen().querySelector( '.sweep-details' ).tagName ).toBe( 'DIV' );
	} );
} );
