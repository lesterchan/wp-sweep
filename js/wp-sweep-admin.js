/**
 * WP-Sweep admin screen.
 *
 * Drives the Sweep and Details row actions against admin-ajax.php, keeps the
 * running totals above the table up to date, and warns before the page is
 * closed mid-sweep.
 *
 * The row actions are real, nonced links: with the script turned off they
 * still sweep, they just reload the screen each time. Everything here is an
 * enhancement over that, never a replacement for it.
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
	 * @param {HTMLElement} trigger The row action that was clicked.
	 * @return {Promise<Object>} The decoded JSON response.
	 */
	function request( trigger ) {
		const params = new URLSearchParams();

		// Named on the PHP side, so these stay snake_case. Setting them rather
		// than writing an object literal keeps that out of the identifiers.
		params.set( 'action', trigger.dataset.action );
		params.set( 'sweep_name', trigger.dataset.sweepName );
		params.set( 'sweep_type', trigger.dataset.sweepType );
		params.set( '_wpnonce', trigger.dataset.nonce );

		return fetch( window.ajaxurl + '?' + params.toString(), {
			credentials: 'same-origin',
		} ).then( function( response ) {
			return response.json();
		} );
	}

	/**
	 * Mark a row action as running, or let it go again.
	 *
	 * The triggers are anchors, so they have no disabled property to set --
	 * they are real, nonced links that work with the script turned off. A
	 * click on one that is already running is ignored instead.
	 *
	 * @param {HTMLElement} trigger The row action.
	 * @param {boolean}     busy    Whether it is running.
	 * @param {string}      label   The text to show.
	 */
	function setBusy( trigger, busy, label ) {
		trigger.setAttribute( 'aria-disabled', busy ? 'true' : 'false' );
		trigger.textContent = label;
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
	 * @param {HTMLElement} trigger The Sweep row action that was clicked.
	 * @return {Promise} Resolves once the row has been updated.
	 */
	function sweep( trigger ) {
		const row = trigger.closest( 'tr' );

		document.body.classList.add( 'sweep-active' );
		setBusy( trigger, true, l10n.textSweeping );

		return request( trigger )
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

				// Nothing left to sweep, so the buttons go and the cell says so.
				// The checkbox stays: every row has one, empty or not, or the
				// column gains holes and select-all starts claiming rows it does
				// not select. This mirrors what column_actions() renders on a
				// fresh page load, so a swept row and a reloaded one agree.
				if ( 0 === count ) {
					const actions = row.querySelector( '.column-actions' );

					if ( actions ) {
						actions.textContent = '';

						const dash = document.createElement( 'span' );
						dash.className = 'sweep-nothing';
						dash.setAttribute( 'aria-hidden', 'true' );
						dash.textContent = '\u2014';

						const label = document.createElement( 'span' );
						label.className = 'screen-reader-text';
						label.textContent = l10n.textNothingToSweep;

						actions.append( dash, label );
					}

					return;
				}

				setBusy( trigger, false, l10n.textSweep );
			} )
			.catch( function() {
				document.body.classList.remove( 'sweep-active' );
				setBusy( trigger, false, l10n.textSweep );
			} );
	}

	document.addEventListener( 'click', function( event ) {
		const trigger = event.target.closest(
			'.btn-sweep, .btn-sweep-details, .btn-sweep-all',
		);

		if ( ! trigger || 'true' === trigger.getAttribute( 'aria-disabled' ) ) {
			return;
		}

		event.preventDefault();

		if ( trigger.classList.contains( 'btn-sweep' ) ) {
			sweep( trigger );
			return;
		}

		if ( trigger.classList.contains( 'btn-sweep-details' ) ) {
			request( trigger ).then( function( response ) {
				if ( response && response.success && response.data.length > 0 ) {
					renderDetails( trigger.closest( 'tr' ), response.data );
				}
			} );
			return;
		}

		// Sweep All: one at a time, so the server is never asked to run
		// nineteen deletions at once.
		trigger.disabled = true;
		trigger.textContent = l10n.textSweeping;

		const triggers = Array.prototype.slice.call(
			document.querySelectorAll( '.btn-sweep' ),
		);

		triggers
			.reduce( function( chain, next ) {
				return chain.then( function() {
					return sweep( next );
				} );
			}, Promise.resolve() )
			.then( function() {
				document.body.classList.remove( 'sweep-active' );
				trigger.disabled = false;
				trigger.textContent = l10n.textSweepAll;
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
		event.returnValue = l10n.textCloseWarning;

		return l10n.textCloseWarning;
	} );
}() );
