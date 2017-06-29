/**
 * Zao WooCommerce Wholesale
 * https://zao.is
 *
 * Copyright (c) 2017 Zao
 * Licensed under the GPL-2.0+ license.
 */

window.ZWOOWH = window.ZWOOWH || {};

( function( window, document, app, undefined ) {
	'use strict';

	app.cache = function() {
		app.$ = {};
	};

	app.init = function() {
		app.cache();

		var Vue = require( 'vue' );
		var VueApp = require( './app.vue' );
		console.warn('VueApp', VueApp);

		app.vue = new Vue( {
			el: '#zwoowh',
			render: function ( createElement ) {
				return createElement( VueApp );
			}
		} );
	};

	app.init();

} )( window, document, window.ZWOOWH );

