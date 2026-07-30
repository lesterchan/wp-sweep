/**
 * Tests for the WP-Sweep admin script.
 *
 * The script is evaluated once for the whole file: its listeners live on
 * `document`, so they survive resetting document.body between tests, and a
 * second evaluation would make every handler fire twice.
 */
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest';
import {
	bootScript,
	clickAndSettle,
	detailsResponse,
	l10n,
	sentParams,
	stubFetch,
	sweepResponse,
	sweepSection,
} from './helpers.js';

beforeAll( () => {
	bootScript();
} );

beforeEach( () => {
	document.body.innerHTML = '';
	document.body.className = '';
} );

describe( 'the request it sends', () => {
	it( 'posts the name, type and nonce read off the button', async () => {
		document.body.innerHTML = sweepSection();
		const fetchSpy = stubFetch( sweepResponse() );

		await clickAndSettle( document.querySelector( '.btn-sweep' ) );

		expect( fetchSpy ).toHaveBeenCalledTimes( 1 );
		expect( sentParams( fetchSpy ) ).toEqual( {
			action: 'sweep',
			sweep_name: 'revisions',
			sweep_type: 'posts',
			_wpnonce: 'NONCE',
		} );
	} );

	it( 'sends the details action and its own separate nonce', async () => {
		document.body.innerHTML = sweepSection();
		const fetchSpy = stubFetch( detailsResponse( [ 'Hello world' ] ) );

		await clickAndSettle( document.querySelector( '.btn-sweep-details' ) );

		expect( sentParams( fetchSpy ) ).toMatchObject( {
			action: 'sweep_details',
			_wpnonce: 'DNONCE',
		} );
	} );

	it( 'sends cookies, or admin-ajax would not know who is asking', async () => {
		document.body.innerHTML = sweepSection();
		const fetchSpy = stubFetch( sweepResponse() );

		await clickAndSettle( document.querySelector( '.btn-sweep' ) );

		expect( fetchSpy.mock.calls[ 0 ][ 1 ] ).toMatchObject( {
			credentials: 'same-origin',
		} );
	} );
} );

describe( 'the details list', () => {
	// The reason this file exists. Every entry comes out of the database, and
	// comment author names are written by whoever left the comment.
	it( 'renders entries as text, never as markup', async () => {
		document.body.innerHTML = sweepSection();
		stubFetch(
			detailsResponse( [ '<img src=x onerror="window.pwned = true">' ] ),
		);

		await clickAndSettle( document.querySelector( '.btn-sweep-details' ) );

		const details = document.querySelector( '.sweep-details' );

		expect( details.querySelector( 'img' ) ).toBeNull();
		expect( details.querySelector( 'li' ).textContent ).toBe(
			'<img src=x onerror="window.pwned = true">',
		);
		expect( window.pwned ).toBeUndefined();
	} );

	it( 'does not let a crafted entry close the list and inject siblings', async () => {
		document.body.innerHTML = sweepSection();
		stubFetch( detailsResponse( [ '</li></ol><script>x</script><ol><li>' ] ) );

		await clickAndSettle( document.querySelector( '.btn-sweep-details' ) );

		const details = document.querySelector( '.sweep-details' );

		expect( details.querySelectorAll( 'ol' ) ).toHaveLength( 1 );
		expect( details.querySelectorAll( 'li' ) ).toHaveLength( 1 );
		expect( details.querySelector( 'script' ) ).toBeNull();
	} );

	it( 'lists every item in order and reveals the container', async () => {
		document.body.innerHTML = sweepSection();
		stubFetch( detailsResponse( [ 'first', 'second', 'third' ] ) );

		const details = document.querySelector( '.sweep-details' );
		expect( details.hidden ).toBe( true );

		await clickAndSettle( document.querySelector( '.btn-sweep-details' ) );

		expect(
			[ ...details.querySelectorAll( 'li' ) ].map( ( li ) => li.textContent ),
		).toEqual( [ 'first', 'second', 'third' ] );
		expect( details.hidden ).toBe( false );
	} );

	it( 'stays hidden when there is nothing to list', async () => {
		document.body.innerHTML = sweepSection();
		stubFetch( detailsResponse( [] ) );

		await clickAndSettle( document.querySelector( '.btn-sweep-details' ) );

		expect( document.querySelector( '.sweep-details' ).hidden ).toBe( true );
	} );

	it( 'replaces a previous list rather than appending to it', async () => {
		document.body.innerHTML = sweepSection();
		stubFetch( [
			detailsResponse( [ 'first' ] ),
			detailsResponse( [ 'second' ] ),
		] );

		const button = document.querySelector( '.btn-sweep-details' );
		await clickAndSettle( button );
		await clickAndSettle( button );

		const items = document.querySelectorAll( '.sweep-details li' );
		expect( items ).toHaveLength( 1 );
		expect( items[ 0 ].textContent ).toBe( 'second' );
	} );
} );

describe( 'the result of a sweep', () => {
	it( 'writes the new count and percentage into the row', async () => {
		document.body.innerHTML = sweepSection( { count: 2, percentage: '20%' } );
		stubFetch( sweepResponse( { count: 0, percentage: '0%' } ) );

		await clickAndSettle( document.querySelector( '.btn-sweep' ) );

		expect( document.querySelector( '.sweep-count' ).textContent ).toBe( '0' );
		expect( document.querySelector( '.sweep-percentage' ).textContent ).toBe(
			'0%',
		);
	} );

	it( 'updates the running totals for the whole section', async () => {
		document.body.innerHTML = sweepSection();
		stubFetch( sweepResponse( { stats: { posts: 8, postmeta: 3 } } ) );

		await clickAndSettle( document.querySelector( '.btn-sweep' ) );

		expect(
			document.querySelector( '.sweep-count-type-posts' ).textContent,
		).toBe( '8' );
		expect(
			document.querySelector( '.sweep-count-type-postmeta' ).textContent,
		).toBe( '3' );
	} );

	it( 'shows the message above the table it belongs to', async () => {
		document.body.innerHTML = sweepSection();
		stubFetch( sweepResponse( { sweep: '2 Revisions Processed' } ) );

		await clickAndSettle( document.querySelector( '.btn-sweep' ) );

		const message = document.querySelector( '.sweep-message' );
		expect( message.querySelector( '.updated p' ).textContent ).toBe(
			'2 Revisions Processed',
		);
	} );

	it( 'renders the message as text too', async () => {
		document.body.innerHTML = sweepSection();
		stubFetch( sweepResponse( { sweep: '<script>window.pwned = 1</script>' } ) );

		await clickAndSettle( document.querySelector( '.btn-sweep' ) );

		expect( document.querySelector( '.sweep-message script' ) ).toBeNull();
		expect( window.pwned ).toBeUndefined();
	} );

	it( 'takes the buttons away once the count reaches zero', async () => {
		document.body.innerHTML = sweepSection();
		stubFetch( sweepResponse( { count: 0 } ) );

		await clickAndSettle( document.querySelector( '.btn-sweep' ) );

		expect( document.querySelector( '.btn-sweep' ) ).toBeNull();
		expect( document.querySelector( '.btn-sweep-details' ) ).toBeNull();
		expect( document.querySelector( '.sweep-nothing' ) ).not.toBeNull();
	} );

	it( 'keeps the checkbox, so the column does not grow holes', async () => {
		document.body.innerHTML = sweepSection();
		stubFetch( sweepResponse( { count: 0 } ) );

		await clickAndSettle( document.querySelector( '.btn-sweep' ) );

		// A swept row has to look like a reloaded one: column_actions() renders
		// the same dash on a fresh page load, and every row keeps its checkbox
		// whether it has anything to sweep or not.
		expect( document.querySelector( 'input[type="checkbox"]' ) ).not.toBeNull();
	} );

	it( 'leaves the row actions in place when items remain', async () => {
		document.body.innerHTML = sweepSection();
		stubFetch( sweepResponse( { count: 5 } ) );

		await clickAndSettle( document.querySelector( '.btn-sweep' ) );

		const trigger = document.querySelector( '.btn-sweep' );
		expect( trigger ).not.toBeNull();
		expect( trigger.getAttribute( 'aria-disabled' ) ).toBe( 'false' );
		expect( trigger.textContent ).toBe( l10n.textSweep );
	} );

	it( 'clears any details that were on screen', async () => {
		document.body.innerHTML = sweepSection();
		stubFetch( [ detailsResponse( [ 'a revision' ] ), sweepResponse() ] );

		await clickAndSettle( document.querySelector( '.btn-sweep-details' ) );
		expect( document.querySelectorAll( '.sweep-details li' ) ).toHaveLength( 1 );

		await clickAndSettle( document.querySelector( '.btn-sweep' ) );

		const details = document.querySelector( '.sweep-details' );
		expect( details.textContent ).toBe( '' );
		expect( details.hidden ).toBe( true );
	} );

	it( 'ignores a response the server marked unsuccessful', async () => {
		document.body.innerHTML = sweepSection( { count: 2 } );
		stubFetch( { success: false, data: { error: 'Invalid AJAX request.' } } );

		await clickAndSettle( document.querySelector( '.btn-sweep' ) );

		expect( document.querySelector( '.sweep-count' ).textContent ).toBe( '2' );
		expect( document.querySelector( '.sweep-message' ).textContent ).toBe( '' );
	} );
} );

describe( 'the close warning', () => {
	it( 'marks the page busy while a sweep is in flight', async () => {
		document.body.innerHTML = sweepSection();

		let resolveFetch;
		const spy = vi.fn(
			() =>
				new Promise( ( resolve ) => {
					resolveFetch = () =>
						resolve( { json: () => Promise.resolve( sweepResponse() ) } );
				} ),
		);
		global.fetch = spy;
		window.fetch = spy;

		const button = document.querySelector( '.btn-sweep' );
		button.dispatchEvent( new window.MouseEvent( 'click', { bubbles: true } ) );

		expect( document.body.classList.contains( 'sweep-active' ) ).toBe( true );
		expect( button.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
		expect( button.textContent ).toBe( l10n.textSweeping );

		resolveFetch();
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
		await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );

		expect( document.body.classList.contains( 'sweep-active' ) ).toBe( false );
	} );

	it( 'warns on beforeunload only while a sweep is running', () => {
		// jsdom's Event.returnValue is the spec boolean, not the string slot
		// BeforeUnloadEvent carries, so the assertion is on preventDefault()
		// having been called — which is what every current browser acts on.
		const fire = () => {
			const event = new window.Event( 'beforeunload', { cancelable: true } );
			window.dispatchEvent( event );
			return event.defaultPrevented;
		};

		expect( fire() ).toBe( false );

		document.body.classList.add( 'sweep-active' );
		expect( fire() ).toBe( true );
	} );

	it( 'supplies the warning text for browsers that still show one', () => {
		// The legacy string is returned from the handler, so a browser that
		// reads it gets the translated sentence rather than a blank dialog.
		expect( l10n.textCloseWarning ).toContain( 'Sweeping is in progress' );

		let captured;
		const handler = ( event ) => {
			captured = event;
		};
		window.addEventListener( 'beforeunload', handler );

		document.body.classList.add( 'sweep-active' );
		window.dispatchEvent(
			new window.Event( 'beforeunload', { cancelable: true } ),
		);
		window.removeEventListener( 'beforeunload', handler );

		expect( captured.defaultPrevented ).toBe( true );
	} );
} );


describe( 'when things go wrong', () => {
	it( 'recovers the button and the page state if the request fails', async () => {
		document.body.innerHTML = sweepSection();

		const spy = vi.fn( () => Promise.reject( new Error( 'network down' ) ) );
		global.fetch = spy;
		window.fetch = spy;

		const button = document.querySelector( '.btn-sweep' );
		await clickAndSettle( button );

		// Without the catch, the page stays flagged busy for ever and the
		// beforeunload warning fires on every subsequent navigation.
		expect( document.body.classList.contains( 'sweep-active' ) ).toBe( false );
		expect( button.getAttribute( 'aria-disabled' ) ).toBe( 'false' );
		expect( button.textContent ).toBe( l10n.textSweep );
	} );

	it( 'does not throw when a row has no details container', async () => {
		document.body.innerHTML = `
			<div class="sweep-message"></div>
			<table class="widefat table-sweep"><tbody><tr>
				<td><strong>Revisions</strong></td>
				<td><span class="sweep-count">2</span></td>
				<td><span class="sweep-percentage">20%</span></td>
				<td><button data-action="sweep_details" data-sweep_name="revisions" data-sweep_type="posts" data-nonce="D" class="btn-sweep-details">Details</button></td>
			</tr></tbody></table>
		`;
		stubFetch( detailsResponse( [ 'an item' ] ) );

		await clickAndSettle( document.querySelector( '.btn-sweep-details' ) );

		expect( document.querySelector( '.btn-sweep-details' ) ).not.toBeNull();
	} );

	it( 'survives a row that is not inside a table', async () => {
		document.body.innerHTML =
			'<button data-action="sweep" data-sweep_name="revisions" data-sweep_type="posts" data-nonce="N" class="btn-sweep">Sweep</button>';
		stubFetch( sweepResponse( { count: 3 } ) );

		await clickAndSettle( document.querySelector( '.btn-sweep' ) );

		expect( document.body.classList.contains( 'sweep-active' ) ).toBe( false );
	} );

	it( 'ignores clicks that are not on one of its buttons', async () => {
		document.body.innerHTML =
			sweepSection() + '<a href="#" id="unrelated">Something else</a>';
		const spy = stubFetch( sweepResponse() );

		await clickAndSettle( document.querySelector( '#unrelated' ) );

		expect( spy ).not.toHaveBeenCalled();
	} );

	it( 'treats a click on text inside the button as a click on the button', async () => {
		document.body.innerHTML = sweepSection();
		const button = document.querySelector( '.btn-sweep' );
		button.innerHTML = '<span id="label">Sweep</span>';
		const spy = stubFetch( sweepResponse() );

		await clickAndSettle( document.querySelector( '#label' ) );

		expect( spy ).toHaveBeenCalledTimes( 1 );
		expect( sentParams( spy ).sweep_name ).toBe( 'revisions' );
	} );
} );
