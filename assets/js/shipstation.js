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

	ship.cache = function() {
		app.$.get_rates_button = app.$get( 'get_shipstation_rates' );
		app.$.shipSpinner      = $( '.shipstation-spinner' );
		ship.order_id          = $( '#post_ID' ).val();
	};

	ship.init = function() {
		ship.cache();

		app.$.get_rates_button.on( 'click', ship.getRates );
	};

	ship.setRates = function( evt ) {
		app.$.shipSpinner.addClass( 'is-active' );

		var $this = $( this );
		var price = $this.data( 'price' );
		var value = $this.val();

		console.log( evt );
		console.log( price );
		console.log( value );
	};

	ship.getRates = function( evt ) {
		app.$.shipSpinner.addClass( 'is-active' );

		if ( $( '#shipstation-rates' ).length ) {
			$( '#shipstation-rates' ).remove();
		}

		$.post( window.ajaxurl, { action : 'get_shipstation_rates', order_id : ship.order_id }, function( response ) {
			console.log( response );

			var $select = $( '<select id="shipstation-rates" />' ).insertBefore( app.$.get_rates_button );
			$select.append( '<option value="">' + app.l10n.selectShipping + '</option>' );

			$.each( response.data, function( i, v ) {
				$select.append( '<option data-price="' + v.shipmentCost.toFixed(2) + '" value="' + v.serviceCode + '">' + v.serviceName + ' - $' + v.shipmentCost.toFixed(2) + '</option>' );
			} );

			var $select2 = $select.select2();

			app.$.body.on( 'select2:select', $select2, ship.setRates );

			app.$.shipSpinner.removeClass( 'is-active' );

		}, 'json' );
	};

	$( ship.init );

} )( window, document, jQuery, window.ZWOOWH );
