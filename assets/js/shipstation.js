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
		app.$.orderItems  = app.$get( 'woocommerce-order-items' );
		app.$.shipSpinner = $( '.shipstation-spinner' );
		ship.order_id     = app.$get( 'post_ID' ).val();
	};

	ship.init = function() {
		ship.cache();

		app.$.body.on( 'click', '#get_shipstation_rates', ship.getRates );
	};

	ship.setRates = function( evt ) {

		var $this = app.$get( 'shipstation-rates' ).find( ':selected' );
		var price = $this.data( 'price' );
		var value = $this.val();
		ship.block();
		$.post( window.ajaxurl, {
			action : 'set_shipstation_rates',
			order_id : ship.order_id,
			price : price,
			value : value,
			method : $this.text(),
		}, function( response ) {

			app.$get( 'shipstation-rates' ).fadeOut();
			ship.reload_items();

		}, 'json' );

	};

	ship.getRates = function( evt ) {

		ship.spinner();

		$.post( window.ajaxurl, { action : 'get_shipstation_rates', order_id : ship.order_id }, function( response ) {

			app.$get( 'shipstation-rates' ).remove();

			if ( ! response.success ) {
				if ( response.data && response.data.msg ) {
					window.alert( response.data.msg );
				}

				return ship.hideSpinner();
			}

			var $select = $( '<select id="shipstation-rates" />' ).insertBefore( app.$get( 'get_shipstation_rates' ) );

			$select.append( '<option value="">' + app.l10n.selectShipping + '</option>' );

			$.each( response.data, function( i, v ) {
				$select.append( '<option data-price="' + v.shipmentCost.toFixed(2) + '" value="' + v.serviceCode + '">' + v.serviceName + ' - $' + v.shipmentCost.toFixed(2) + '</option>' );
			} );

			var $select2 = $select.select2();

			app.$.body.one( 'change', $select2, ship.setRates );

			ship.hideSpinner();

		}, 'json' );
	};

	ship.block = function() {
		app.$.orderItems.block({
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		});
	};

	ship.unblock = function() {
		app.$.orderItems.unblock();
	};

	ship.spinner = function() {
		app.$.shipSpinner.addClass( 'is-active' );
	};

	ship.hideSpinner = function() {
		app.$.shipSpinner.removeClass( 'is-active' );
	};

	ship.reload_items = function() {

		ship.block();

		$.ajax({
			url:  window.ajaxurl,
			data: {
				order_id: window.woocommerce_admin_meta_boxes.post_id,
				action:   'woocommerce_load_order_items',
				security: window.woocommerce_admin_meta_boxes.order_item_nonce
			},
			type: 'POST',
			success: function( response ) {
				app.$.orderItems.find( '.inside' ).empty();
				app.$.orderItems.find( '.inside' ).append( response );
				ship.unblock();
			}
		});
	};

	$( ship.init );

} )( window, document, jQuery, window.ZWOOWH );
