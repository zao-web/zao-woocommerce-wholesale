/**
 * Zao WooCommerce Wholesale
 * https://zao.is
 *
 * Copyright (c) 2017 Zao
 * Licensed under the GPL-2.0+ license.
 */

window.ZWOOWH = window.ZWOOWH || {};

( function( window, document, $, app, undefined ) {
	'use strict';

	app.cache = function() {
		app.$ = {};
	};

	app.toggleOrderBoxes = function( evt ) {
		console.warn('this', $( this ).val() );
		var hasVal = $( this ).val();
		$( document.body )[ hasVal ? 'removeClass' : 'addClass' ]( 'fresh-wholesale-order' );

		if ( window.postboxes ) {
			postboxes._mark_area();
		}

	};

	app.init = function() {
		app.cache();

		var Vue = require( 'vue' );
		app.vue = require( './app.vue' );

		new Vue( {
			el: '#zwoowh',
			render: function ( createElement ) {
				return createElement( app.vue );
			}
		} );

		$( document.getElementById( 'customer_user' ) ).on( 'change', app.toggleOrderBoxes );

		$.ajaxSetup( { data : { is_wholesale: app.is_wholesale } } );
	};

	$( app.init );


} )( window, document, jQuery, window.ZWOOWH );
