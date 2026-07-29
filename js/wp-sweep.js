/**
 * WP-Sweep admin screen.
 *
 * Drives the Sweep and Details buttons against admin-ajax.php, keeps the
 * running totals at the top of each section up to date, and warns before the
 * page is closed mid-sweep.
 *
 * Listeners are delegated from `document`, so a row added by one of the
 * wp_sweep_admin_*_sweep actions works without re-binding anything.
 */
( function() {
	'use strict';

	const l10n = window.wpSweepL10n || {};

	/**
	 * Ask admin-ajax.php to run a sweep or fetch its details.
	 *
	 * @param {HTMLElement} button The button that was clicked.
	 * @return {Promise<Object>} The decoded JSON response.
	 */
	function request( button ) {
		const params = new URLSearchParams( {
			action: button.dataset.action,
			sweep_name: button.dataset.sweep_name,
			sweep_type: button.dataset.sweep_type,
			_wpnonce: button.dataset.nonce,
		} );

		return fetch( window.ajaxurl + '?' + params.toString(), {
			credentials: 'same-origin',
		} ).then( function( response ) {
			return response.json();
		} );
	}

	/**
	 * Find the message container that belongs to a row's table.
	 *
	 * @param {HTMLElement} row The table row.
	 * @return {HTMLElement|null} The container, if there is one.
	 */
	function messageContainer( row ) {
		const table = row.closest( '.table-sweep' );

		if ( ! table ) {
			return null;
		}

		let previous = table.previousElementSibling;

		while ( previous && ! previous.classList.contains( 'sweep-message' ) ) {
			previous = previous.previousElementSibling;
		}

		return previous;
	}

	/**
	 * Render the list of items a sweep would remove.
	 *
	 * Every entry here comes out of the database — post titles, comment
	 * author names, meta keys, option names. Comment author names in
	 * particular are supplied by whoever left the comment, which is exactly
	 * the sort of person who leaves markup in them. They are written as text
	 * nodes rather than as HTML: before 2.0.0 this list was assembled by
	 * string concatenation and injected with .html(), so a spam comment
	 * signed with a script tag ran that script in the administrator's
	 * browser the moment Details was clicked.
	 *
	 * @param {HTMLElement} row   The row the details belong to.
	 * @param {Array}       items The items to list.
	 */
	function renderDetails( row, items ) {
		const target = row.querySelector( '.sweep-details' );

		if ( ! target ) {
			return;
		}

		const list = document.createElement( 'ol' );

		items.forEach( function( item ) {
			const entry = document.createElement( 'li' );
			entry.textContent = item;
			list.appendChild( entry );
		} );

		target.textContent = '';
		target.appendChild( list );
		target.hidden = false;
	}

	/**
	 * Clear and hide a row's details list.
	 *
	 * @param {HTMLElement} row The table row.
	 */
	function hideDetails( row ) {
		const target = row.querySelector( '.sweep-details' );

		if ( target ) {
			target.textContent = '';
			target.hidden = true;
		}
	}

	/**
	 * Show the result of a sweep above its table.
	 *
	 * @param {HTMLElement} row  The row that was swept.
	 * @param {string}      text The message from the server.
	 */
	function showMessage( row, text ) {
		const container = messageContainer( row );

		if ( ! container ) {
			return;
		}

		const notice = document.createElement( 'div' );
		notice.className = 'updated';

		const paragraph = document.createElement( 'p' );
		paragraph.textContent = text;
		notice.appendChild( paragraph );

		container.textContent = '';
		container.appendChild( notice );
	}

	/**
	 * Run one sweep and fold the result back into the page.
	 *
	 * @param {HTMLElement} button The Sweep button that was clicked.
	 * @return {Promise} Resolves once the row has been updated.
	 */
	function sweep( button ) {
		const row = button.closest( 'tr' );

		document.body.classList.add( 'sweep-active' );
		button.disabled = true;
		button.textContent = l10n.text_sweeping;

		return request( button )
			.then( function( response ) {
				if ( ! response || ! response.success ) {
					return;
				}

				const count = parseInt( response.data.count, 10 );

				const countCell = row.querySelector( '.sweep-count' );
				if ( countCell ) {
					countCell.textContent = count.toLocaleString();
				}

				const percentageCell = row.querySelector( '.sweep-percentage' );
				if ( percentageCell ) {
					percentageCell.textContent = response.data.percentage;
				}

				// Running totals for the whole section.
				Object.keys( response.data.stats || {} ).forEach( function( key ) {
					document
						.querySelectorAll( '.sweep-count-type-' + key )
						.forEach( function( node ) {
							node.textContent = parseInt(
								response.data.stats[ key ],
								10,
							).toLocaleString();
						} );
				} );

				showMessage( row, response.data.sweep );
				hideDetails( row );

				document.body.classList.remove( 'sweep-active' );

				// Nothing left to sweep, so the buttons go with it.
				if ( 0 === count ) {
					button.closest( 'td' ).textContent = l10n.text_na;
					return;
				}

				button.disabled = false;
				button.textContent = l10n.text_sweep;
			} )
			.catch( function() {
				document.body.classList.remove( 'sweep-active' );
				button.disabled = false;
				button.textContent = l10n.text_sweep;
			} );
	}

	document.addEventListener( 'click', function( event ) {
		const button = event.target.closest(
			'.btn-sweep, .btn-sweep-details, .btn-sweep-all',
		);

		if ( ! button ) {
			return;
		}

		event.preventDefault();

		if ( button.classList.contains( 'btn-sweep' ) ) {
			sweep( button );
			return;
		}

		if ( button.classList.contains( 'btn-sweep-details' ) ) {
			request( button ).then( function( response ) {
				if ( response && response.success && response.data.length > 0 ) {
					renderDetails( button.closest( 'tr' ), response.data );
				}
			} );
			return;
		}

		// Sweep All: one at a time, so the server is never asked to run
		// nineteen deletions at once.
		button.disabled = true;
		button.textContent = l10n.text_sweeping;

		const buttons = Array.prototype.slice.call(
			document.querySelectorAll( '.btn-sweep' ),
		);

		buttons
			.reduce( function( chain, next ) {
				return chain.then( function() {
					return sweep( next );
				} );
			}, Promise.resolve() )
			.then( function() {
				document.body.classList.remove( 'sweep-active' );
				button.disabled = false;
				button.textContent = l10n.text_sweep_all;
			} );
	} );

	/*
	 * Page closing confirmation.
	 * https://developer.mozilla.org/en-US/docs/Web/API/Window/beforeunload_event
	 */
	window.addEventListener( 'beforeunload', function( event ) {
		if ( ! document.body.classList.contains( 'sweep-active' ) ) {
			return undefined;
		}

		event.preventDefault();
		event.returnValue = l10n.text_close_warning;

		return l10n.text_close_warning;
	} );
}() );
