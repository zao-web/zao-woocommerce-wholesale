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

	var stepClasses = 'init-wholesale-order build-wholesale-order edit-wholesale-order';

	function $get( id ) {
		return $( document.getElementById( id ) );
	}

	app.cache = function() {
		app.$ = {};
		app.$.body = $( document.body );
		app.$.select = $get( 'customer_user' );
		app.$.addItems = $( '.button.add-line-item' );
		app.$.addItem = $( '.button.add-order-item' );
	};

	app.triggerStep = function() {
		app[ 'step' + app.whichStep() ]();
	};

	app.whichStep = function() {
		var hasCustomer = app.$.select.val();
		var toAdd = false;
		var hasItems = $( '#order_line_items .item' ).length ? true : false;
		var step = 3;
		if ( ! hasItems ) {
			step = hasCustomer ? 2 : 1;
		} else if ( ! hasCustomer ) {
			step = 1;
		}

		return step;
	};

	app.step1 = function() {
		app.bodyClass( 'init-wholesale-order' );
	};

	app.step2 = function() {
		app.bodyClass( 'build-wholesale-order' );

		app.$.addItems.trigger( 'click' );

		app.initVue();

		window.setTimeout( function() {
			app.$.addItem.trigger( 'click' );
		}, 150 );
	};

	app.step3 = function() {
		app.bodyClass( 'edit-wholesale-order' );
	};

	app.bodyClass = function( toAdd ) {
		app.$.body.removeClass( stepClasses );

		if ( toAdd ) {
			app.$.body.addClass( toAdd );
		}
	};

	app.toggleOrderBoxes = function( evt ) {
		app.triggerStep();
		if ( window.postboxes ) {
			postboxes._mark_area();
		}
	};

	app.initVue = function() {
		if ( app.vEvent ) {
			return;
		}

		var Vue = require( 'vue' );
		var vueApp = require( './app.vue' );

		app.vEvent = new Vue();

		app.vueInstance = new Vue( {
			el: '#zwoowh',
			// data: data
			render: function ( createElement ) {
				return createElement( vueApp );
			}
		} );

		app.$.addItem
			.removeClass( 'add-order-item' )
			.addClass( 'add-wholesale-order-items' )
			.on( 'click', function() {
				app.vEvent.$emit( 'modalOpen' );
			} );

	};

	app.init = function() {
		app.cache();

		app.$.select.on( 'change', app.toggleOrderBoxes );
		setTimeout( function() {
			app.$.select.select2( 'open' );
		}, 1000 );

		$.ajaxSetup( { data : { is_wholesale: app.is_wholesale } } );

		app.$.body.on( 'wc_backbone_modal_response', function( evt, target, data ) {
			if ( 'wc-modal-add-products' === target ) {
				app.step3();
			}
		} );

		// to add items to Woo items metabox:
		// app.$.body.trigger( 'wc_backbone_modal_response', [ 'wc-modal-add-products', {
		// 	add_order_items: [ '63779' ]
		// } ] );
		// app.$.body.trigger( 'wc_backbone_modal_response', [ 'wc-modal-add-products', {
		// 	add_order_items: { '63779:2' : '63779' }
		// } ] );
	};

	$( app.init );

} )( window, document, jQuery, window.ZWOOWH );
