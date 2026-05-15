/**
 * Lightweight autocomplete on top of the Beaver Builder Bunny video <select>.
 * No external dependencies — vanilla JS. Runs inside the BB settings iframe.
 */
( function () {
	'use strict';

	function enhance( $select ) {
		if ( $select.dataset.wpbsEnhanced ) return;
		$select.dataset.wpbsEnhanced = '1';

		var options = Array.prototype.slice.call( $select.options ).map( function ( o ) {
			return { value: o.value, label: o.textContent };
		} );

		var wrap = document.createElement( 'div' );
		wrap.className = 'wpbs-picker';
		wrap.style.cssText = 'position:relative;display:block';

		var input = document.createElement( 'input' );
		input.type = 'text';
		input.className = 'fl-form-field-input';
		input.placeholder = 'Type to search videos…';
		input.autocomplete = 'off';
		input.style.cssText = 'width:100%;box-sizing:border-box';

		var menu = document.createElement( 'ul' );
		menu.className = 'wpbs-picker-menu';
		menu.style.cssText = 'position:absolute;top:100%;left:0;right:0;z-index:9999;background:#fff;border:1px solid #ddd;border-top:0;list-style:none;margin:0;padding:0;max-height:240px;overflow:auto;display:none;box-shadow:0 4px 12px rgba(0,0,0,.08)';

		$select.style.display = 'none';
		$select.parentNode.insertBefore( wrap, $select );
		wrap.appendChild( input );
		wrap.appendChild( menu );
		wrap.appendChild( $select );

		// Set initial label from current value.
		var current = options.find( function ( o ) { return o.value === $select.value; } );
		if ( current && current.value ) input.value = current.label;

		function render( filter ) {
			menu.innerHTML = '';
			var q = ( filter || '' ).toLowerCase();
			var matches = options.filter( function ( o ) {
				return o.value !== '' && ( ! q || o.label.toLowerCase().indexOf( q ) !== -1 );
			} ).slice( 0, 50 );

			if ( ! matches.length ) {
				var empty = document.createElement( 'li' );
				empty.textContent = 'No videos found';
				empty.style.cssText = 'padding:8px 10px;color:#888;font-style:italic';
				menu.appendChild( empty );
			} else {
				matches.forEach( function ( o ) {
					var li = document.createElement( 'li' );
					li.textContent = o.label;
					li.dataset.value = o.value;
					li.style.cssText = 'padding:8px 10px;cursor:pointer;border-bottom:1px solid #f0f0f0';
					li.addEventListener( 'mouseenter', function () { li.style.background = '#f0f6fc'; } );
					li.addEventListener( 'mouseleave', function () { li.style.background = ''; } );
					li.addEventListener( 'mousedown', function ( e ) {
						e.preventDefault();
						$select.value = o.value;
						$select.dispatchEvent( new Event( 'change', { bubbles: true } ) );
						input.value = o.label;
						hide();
					} );
					menu.appendChild( li );
				} );
			}
			menu.style.display = 'block';
		}

		function hide() {
			menu.style.display = 'none';
		}

		input.addEventListener( 'focus', function () { render( input.value ); } );
		input.addEventListener( 'input', function () {
			$select.value = '';
			render( input.value );
		} );
		input.addEventListener( 'blur', function () { setTimeout( hide, 150 ); } );
		input.addEventListener( 'keydown', function ( e ) { if ( e.key === 'Escape' ) hide(); } );
	}

	function scan( root ) {
		var doc = root || document;
		doc.querySelectorAll( 'select[name="video_id"]' ).forEach( enhance );
	}

	if ( document.readyState !== 'loading' ) {
		scan();
	} else {
		document.addEventListener( 'DOMContentLoaded', function () { scan(); } );
	}

	// BB rebuilds the settings panel as the user navigates — watch the DOM.
	var observer = new MutationObserver( function ( mutations ) {
		mutations.forEach( function ( m ) {
			m.addedNodes.forEach( function ( node ) {
				if ( node.nodeType === 1 ) {
					if ( node.matches && node.matches( 'select[name="video_id"]' ) ) {
						enhance( node );
					} else if ( node.querySelectorAll ) {
						node.querySelectorAll( 'select[name="video_id"]' ).forEach( enhance );
					}
				}
			} );
		} );
	} );
	observer.observe( document.body, { childList: true, subtree: true } );
} )();
