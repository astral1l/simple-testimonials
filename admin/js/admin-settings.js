document.addEventListener( 'DOMContentLoaded', function () {
	if ( typeof window.jQuery !== 'undefined' && typeof window.jQuery.fn.wpColorPicker !== 'undefined' ) {
		window.jQuery( '.st-color-picker' ).wpColorPicker();
	}
} );
