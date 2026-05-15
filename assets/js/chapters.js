/**
 * Click handler for chapter links — seeks the Bunny player via postMessage
 * or by appending ?t= to the iframe src as a fallback.
 */
( function () {
	'use strict';

	function init() {
		document.addEventListener( 'click', function ( e ) {
			var a = e.target.closest( 'a[data-wpbs-seek]' );
			if ( ! a ) return;
			e.preventDefault();
			var seconds = parseInt( a.getAttribute( 'data-wpbs-seek' ), 10 ) || 0;

			var wrap = a.closest( '.wpbs-chapters' );
			var prev = wrap ? wrap.previousElementSibling : null;
			var iframe = null;
			if ( prev ) {
				iframe = prev.tagName === 'IFRAME' ? prev : prev.querySelector( 'iframe' );
			}
			if ( ! iframe ) return;

			try {
				iframe.contentWindow.postMessage( { eventType: 'setCurrentTime', currentTime: seconds }, '*' );
			} catch ( err ) {
				// Fallback: reload the iframe with t= param.
				var url = new URL( iframe.src );
				url.searchParams.set( 't', seconds );
				url.searchParams.set( 'autoplay', 'true' );
				iframe.src = url.toString();
			}
		} );
	}

	if ( document.readyState !== 'loading' ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
} )();
