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

	function $get( id ) {
		return $( document.getElementById( id ) );
	}

	app.shipstationModalOpened = false;

	app.cache = function() {
		app.$                  = {};
		app.$.body             = $( document.body );
		app.$.get_rates_button = $get( 'get_shipstation_rates' );
		app.$.set_rates_button = $get( 'set_shipstation_rates' );
		app.$.spinner          = $( '.shipstation-spinner' );
		app.$.order_id         = $( '#post_ID' ).val();
	};

	app.init = function() {
		app.cache();

		app.$.get_rates_button.on( 'click', app.getRates );
		app.$.set_rates_button.on( 'click', app.setRates );
	};

	app.setRates = function( evt ) {

	};

	app.getRates = function( evt ) {
		app.$.spinner.addClass( 'is-active' );

		$.post( ajaxurl, { action : get_shipstation_rates, order_id : app.$.order_id }, function( response ) {
			console.log( response );
		}, 'json' );
	};

	$( app.init );

} )( window, document, jQuery, window.ZWOOWH );
