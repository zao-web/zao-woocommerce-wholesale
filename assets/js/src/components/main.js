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
		app.$.body = $( document.body );
		app.$.select = $( document.getElementById( 'customer_user' ) );
		app.$.addItems = $( '.button.add-line-item' );
		app.$.addItem = $( '.button.add-order-item' );
	};

	app.bodyClass = function( toRemove, toAdd ) {
		toRemove = true === toRemove ? 'init-wholesale-order build-wholesale-order' : toRemove;
		console.warn('toRemove', toRemove);
		console.warn('toAdd', toAdd);
		if ( toRemove ) {
			app.$.body.removeClass( toRemove );
		}

		if ( toAdd ) {
			app.$.body.addClass( toAdd );
		}
	};

	app.toggleOrderBoxes = function( evt ) {
		var hasVal = $( this ).val();
		var toAdd = false;
		var hasItems = $( '#order_line_items .item' ).length ? true : false;
		if ( ! hasItems ) {
			toAdd = hasVal ? 'build-wholesale-order' : 'init-wholesale-order';
		} else if ( ! hasVal ) {
			toAdd = 'init-wholesale-order';
		}

		app.bodyClass( true, toAdd );

		if ( hasVal && ! hasItems ) {
			app.$.addItems.trigger( 'click' );
			window.setTimeout( function() {
				app.$.addItem.trigger( 'click' );
			}, 150 );
		}

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

		app.$.select.on( 'change', app.toggleOrderBoxes );
		setTimeout( function() {
			app.$.select.select2( 'open' );
		}, 1000 );

		$.ajaxSetup( { data : { is_wholesale: app.is_wholesale } } );
	};

	$( app.init );


} )( window, document, jQuery, window.ZWOOWH );
