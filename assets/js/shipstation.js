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
		app.$.set_rates_button = $( 'set_shipstation_rates' );
		app.$.shipSpinner      = $( '.shipstation-spinner' );
		ship.order_id          = $( '#post_ID' ).val();
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

		if ( $( '#shipstation-rates' ).length ) {
			$( '#shipstation-rates' ).remove();
		}

		$.post( window.ajaxurl, { action : 'get_shipstation_rates', order_id : ship.order_id }, function( response ) {
			console.log( response );

			var $select = $( '<select id="shipstation-rates" />' ).prependTo( app.$.get_rates_button );

			$.each( response.data, function( i, v ) {
				$select.append( '<option data-price="' + v.shipmentCost.toFixed(2) + '" value="' + v.serviceCode + '">' + v.serviceName + ' - $' + v.shipmentCost.toFixed(2) + '</option>' );
			} );

			$select.select2();

		}, 'json' );
	};

	$( ship.init );

} )( window, document, jQuery, window.ZWOOWH );
