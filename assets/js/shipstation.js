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

	app.ss_cache = function() {
		app.$s                  = {};
		app.$s.body              = $( document.body );
		app.$s.get_rates_button  = $get( 'get_shipstation_rates' );
		app.$s.set_rates_button  = $get( 'set_shipstation_rates' );
		app.$s.spinner           = $( '.shipstation-spinner' );
		app.$s.order_id          = $( '#post_ID' ).val();
	};

	app.init = function() {
		app.ss_cache();

		app.$s.get_rates_button.on( 'click', app.getRates );
		app.$s.set_rates_button.on( 'click', app.setRates );
	};

	app.setRates = function( evt ) {

	};

	app.getRates = function( evt ) {
		app.$s.spinner.addClass( 'is-active' );

		$.post( ajaxurl, { action : get_shipstation_rates, order_id : app.$s.order_id }, function( response ) {
			console.log( response );
		}, 'json' );
	};

	$( app.init );

} )( window, document, jQuery, window.ZWOOWH );
