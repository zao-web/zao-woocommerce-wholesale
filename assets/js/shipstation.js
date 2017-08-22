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

	app.shipStation = {};
	var ship = app.shipStation;

	// ship.modalOpened = false;

	ship.cache = function() {
		app.$.get_rates_button = app.$get( 'get_shipstation_rates' );
		app.$.set_rates_button = app.$get( 'set_shipstation_rates' );
		app.$.shipSpinner      = $( '.shipstation-spinner' );
		ship.order_id          = app.$get( 'post_ID' ).val();
	};

	ship.init = function() {
		ship.cache();

		app.$.get_rates_button.on( 'click', ship.getRates );
		app.$.set_rates_button.on( 'click', ship.setRates );
	};

	ship.setRates = function( evt ) {

	};

	ship.getRates = function( evt ) {
		app.$.shipSpinner.addClass( 'is-active' );

		$.post( window.ajaxurl, { action : 'get_shipstation_rates', order_id : ship.order_id }, function( response ) {
			console.log( response );
		}, 'json' );
	};

	$( ship.init );

} )( window, document, jQuery, window.ZWOOWH );
