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
	 * Find the region a row action reports its result into.
	 *
	 * The screen prints one, above the form, and every Sweep link names it in
	 * aria-controls -- so the id is written once, in PHP
	 * (WP_Sweep_Admin::MESSAGE_ID), and this follows the association instead of
	 * guessing at the markup between the two.
	 *
	 * That guess is what this used to be, and it had never once worked: it took
	 * the row's .table-sweep and walked backwards through its previous siblings
	 * looking for .sweep-message. Since the screen became one list table the
	 * table is inside the <form> and the region is outside it, so the walk ran
	 * out of siblings inside the form and returned null, showMessage() took its
	 * early return, and no sweep told anyone what it had done. The sweeps
	 * themselves ran correctly throughout, which is why nothing looked wrong
	 * beyond a count quietly changing.
	 *
	 * It reached nobody only because 2.0.0 has not shipped. Nothing in the
	 * plugin's own tests would have stopped it: the vitest fixture had been
	 * written to suit the walk -- region and table as adjacent siblings, no
	 * form -- so the assertion and the code agreed with each other and with
	 * nothing else. It took a browser to find it.
	 *
	 * @param {HTMLElement} trigger The row action that was clicked.
	 * @return {HTMLElement|null} The region, if the page has one.
	 */
	function messageContainer( trigger ) {
		const id = trigger.getAttribute( 'aria-controls' );

		return id ? document.getElementById( id ) : null;
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
	 * Write a count into its cell, emphasised only when it is worth acting on.
	 *
	 * The emphasis is the element itself -- a <strong> while there is
	 * something to sweep, a <span> once there is not -- so the element is
	 * replaced rather than restyled, exactly as a reload would redraw it.
	 * Replacing it also sheds the pending marker and the data attributes a
	 * deferred cell was rendered with, which is what stops a slow count
	 * response overwriting a row that has since been swept: the fetch checks
	 * its cell is still connected before writing.
	 *
	 * @param {HTMLElement} cell  The current .sweep-count element.
	 * @param {number}      count The count to show.
	 */
	function writeCount( cell, count ) {
		const replacement = document.createElement( count > 0 ? 'strong' : 'span' );

		replacement.className = 'sweep-count';
		replacement.textContent = count.toLocaleString();

		cell.replaceWith( replacement );
	}

	/**
	 * Write the running totals above the table.
	 *
	 * @param {Object} stats Row counts keyed by table type.
	 */
	function writeStats( stats ) {
		Object.keys( stats || {} ).forEach( function( key ) {
			document
				.querySelectorAll( '.sweep-count-type-' + key )
				.forEach( function( node ) {
					node.classList.remove( 'sweep-total-pending' );
					node.textContent = parseInt( stats[ key ], 10 ).toLocaleString();
				} );
		} );
	}

	/**
	 * Swap a row's buttons for the dash a fresh render gives an empty row.
	 *
	 * The checkbox stays: every row has one, empty or not, or the column gains
	 * holes and select-all starts claiming rows it does not select. This
	 * mirrors what column_actions() renders on a fresh page load, so a swept
	 * row and a reloaded one agree.
	 *
	 * @param {HTMLElement} row The table row.
	 */
	function markRowEmpty( row ) {
		const actions = row.querySelector( '.column-actions' );

		if ( ! actions ) {
			return;
		}

		actions.textContent = '';

		const dash = document.createElement( 'span' );
		dash.className = 'sweep-nothing';
		dash.setAttribute( 'aria-hidden', 'true' );
		dash.textContent = '—';

		const label = document.createElement( 'span' );
		label.className = 'screen-reader-text';
		label.textContent = l10n.textNothingToSweep;

		actions.append( dash, label );
	}

	/**
	 * Show the result of a sweep in the region the trigger names.
	 *
	 * @param {HTMLElement} trigger The Sweep row action that was clicked.
	 * @param {string}      text    The message from the server.
	 */
	function showMessage( trigger, text ) {
		const container = messageContainer( trigger );

		if ( ! container ) {
			return;
		}

		// The same classes settings_errors() emits for the reload path, so the
		// two messages are one message in two code paths rather than two
		// different-looking ones. `updated` is the pre-4.1 vocabulary and the
		// standard names notice-success instead; hand-rolling it here was also
		// the one place the "no hand-rolled div.updated" rule was being broken,
		// because it is JavaScript and the checker only reads PHP.
		const notice = document.createElement( 'div' );
		notice.className = 'notice notice-success';

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
					writeCount( countCell, count );
				}

				const percentageCell = row.querySelector( '.sweep-percentage' );
				if ( percentageCell ) {
					percentageCell.textContent = response.data.percentage;
				}

				// Running totals for the whole section.
				writeStats( response.data.stats );

				showMessage( trigger, response.data.sweep );
				hideDetails( row );

				document.body.classList.remove( 'sweep-active' );

				// Nothing left to sweep, so the buttons go and the cell says so.
				if ( 0 === count ) {
					markRowEmpty( row );

					return;
				}

				setBusy( trigger, false, l10n.textSweep );
			} )
			.catch( function() {
				document.body.classList.remove( 'sweep-active' );
				setBusy( trigger, false, l10n.textSweep );
			} );
	}

	/**
	 * Fetch one deferred count and fold it into its row.
	 *
	 * The cell was rendered pending, carrying the same action/name/type/nonce
	 * vocabulary the row actions carry, so request() reads it the same way.
	 *
	 * @param {HTMLElement} cell The pending .sweep-count element.
	 * @return {Promise} Resolves once the row has been updated.
	 */
	function fillCount( cell ) {
		return request( cell )
			.then( function( response ) {
				// Replaced meanwhile -- the row was swept before its count
				// arrived, and the sweep's answer is the fresher one.
				if ( ! cell.isConnected ) {
					return;
				}

				if ( ! response || ! response.success ) {
					cell.textContent = l10n.textNa;
					return;
				}

				const row = cell.closest( 'tr' );
				const count = parseInt( response.data.count, 10 );

				writeCount( cell, count );

				const percentageCell = row.querySelector( '.sweep-percentage' );
				if ( percentageCell ) {
					percentageCell.textContent = response.data.percentage;
				}

				if ( 0 === count ) {
					markRowEmpty( row );
				}
			} )
			.catch( function() {
				cell.textContent = l10n.textNa;
			} );
	}

	/**
	 * Fetch the running totals, which were also deferred.
	 *
	 * One request for the whole table: its nonce rides on the table element,
	 * since twelve cells share the answer.
	 *
	 * @return {Promise} Resolves once the totals have been written.
	 */
	function fillTotals() {
		const table = document.querySelector( '.sweep-totals[data-nonce]' );

		if ( ! table || ! table.querySelector( '.sweep-total-pending' ) ) {
			return Promise.resolve();
		}

		const params = new URLSearchParams();

		params.set( 'action', 'sweep_totals' );
		params.set( '_wpnonce', table.dataset.nonce );

		const fail = function() {
			table.querySelectorAll( '.sweep-total-pending' ).forEach( function( node ) {
				node.textContent = l10n.textNa;
			} );
		};

		return fetch( window.ajaxurl + '?' + params.toString(), {
			credentials: 'same-origin',
		} )
			.then( function( response ) {
				return response.json();
			} )
			.then( function( response ) {
				if ( ! response || ! response.success ) {
					fail();
					return;
				}

				writeStats( response.data.stats );
			} )
			.catch( fail );
	}

	/**
	 * Fill in everything the screen rendered without.
	 *
	 * The screen defers its counts so it can render before the queries run --
	 * they are the expensive half of the page, and computing all of them
	 * before printing a byte is what used to time the screen out on large
	 * databases. Totals first, then one row at a time: these queries scan the
	 * same handful of tables, and nineteen of them at once would hand the
	 * database the very spike the deferral exists to avoid.
	 */
	function fillDeferredCounts() {
		const cells = Array.prototype.slice.call(
			document.querySelectorAll( '.sweep-count-pending' ),
		);

		cells.reduce( function( chain, cell ) {
			return chain.then( function() {
				return fillCount( cell );
			} );
		}, fillTotals() );
	}

	document.addEventListener( 'click', function( event ) {
		const trigger = event.target.closest(
			'.btn-sweep, .btn-sweep-details',
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
			const row = trigger.closest( 'tr' );
			const shown = row.querySelector( '.sweep-details' );

			// A toggle, not a one-way door. The list can be long, and the only
			// way to put it away used to be reloading the screen.
			if ( shown && ! shown.hidden ) {
				hideDetails( row );
				trigger.setAttribute( 'aria-expanded', 'false' );
				return;
			}

			request( trigger ).then( function( response ) {
				if ( response && response.success && response.data.length > 0 ) {
					renderDetails( row, response.data );
					trigger.setAttribute( 'aria-expanded', 'true' );
				}
			} );
		}
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

	// The script is enqueued in the footer, so the cells it fills are already
	// on the page by the time this runs.
	fillDeferredCounts();
}() );
