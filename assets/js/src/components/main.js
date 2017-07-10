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

	app.initVue = function( completeCb ) {
		if ( app.vEvent ) {
			return;
		}

		var Vue = require( 'vue' );
		app.vEvent = new Vue();

		app.vEvent.$on( 'modalOpened', app.resizeTable );
		app.vEvent.$on( 'productsSelected', app.addProducts );

		app.prepareProducts( function() {

			var vueApp = require( './app.vue' );

			app.vueInstance = new Vue( {
				el: '#zwoowh',
				data() {
					return {
						modalOpen        : false,
						btnText          : 'Click Me',
						sortKey          : 'type',
						reverse          : false,
						excludeUnstocked : false,
						search           : '',
						columns          : app.columns,
						products         : app.allProducts,
					};
				},
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

			if ( _.isFunction( completeCb ) ) {
				completeCb();
			}
		} );

	};

	app.resizeTable = function() {
		app.$.tHead = app.$.tHead || $( '#zwoowh-modal .zwoowh-content .table-head' );
		app.$.productsTable = app.$.productsTable || $( '#zwoowh-modal .zwoowh-products' );
		app.$.modalContent = app.$.modalContent || $( '#zwoowh-modal .media-frame-content');

		var thH = app.$.tHead.outerHeight();
		var contentH = app.$.modalContent.outerHeight();

		app.$.productsTable.css( { 'max-height': contentH - ( thH * 3 ) } );
	};

	app.getProductVariations = function( parentProduct, completeCb ) {
		var url = app.rest_url + 'wc/v2/products/' + parentProduct.id +'/variations/?bt_limit_fields=id,img:50,sku,name,price,bt_product_type,stock_quantity,editlink&_wpnonce=' + app.rest_nonce;

		var params = {
			type: 'GET',
			url: url,
			success: function( response ) {
				// console.warn('wc api variant response', response);

				for ( var i = 0; i < response.length; i++ ) {
					response[i].parent = parentProduct.name;
					var product = app.prepareProduct( response[i] );

					// console.warn('product', JSON.parse( JSON.stringify( product ) ));
					app.allProducts.push( product );
				}

				app.toProcess--;

				if ( app.toProcess < 1 ) {
					completeCb();
				}
				// for ( var i = 0; i < response.length; i++ ) {
				// 	console.warn('response['+ i +']', response[i]);
				// }
			},
			error: function( jqXHR, textStatus, errorThrown ) {
				app.toProcess--;
				let err = jqXHR.responseJSON;
				if ( err.code && err.message ) {
					console.error( jqXHR.status + ' ' + err.code + ' - ' + err.message );
				} else {
					console.error( app.l10n.somethingWrong );
				}
				// console.error('wc api response error', {
				// 	jqXHR, textStatus, errorThrown
				// });
			},
		};
		// console.warn('params', params);

		$.ajax( params );
	};

	app.prepareProducts = function( completeCb ) {
		var url = app.rest_url + 'wc/v2/products?status=publish&type=variable&per_page=100&_wpnonce=1&_wpnonce=' + app.rest_nonce;
		if ( app.productCategory > 0 ) {
			url += '&category='+ app.productCategory;
		}

		// $url = '/wc/v2/products';
		// $request = new WP_REST_Request( 'GET', $url );
		// $request['_wpnonce'] = wp_create_nonce( 'wp_rest' );
		// $request['status'] = 'publish';
		// $request['bt_product_type'] = '195';
		// $request['type'] = 'variable';

		var params = {
			type: 'GET',
			url: url,
			success: function( response ) {
				// app.allProducts = [];
				app.toProcess = response.length;

				// console.warn('wc api response', response);
					for ( var i = 0; i < response.length; i++ ) {
						// console.warn('response['+ i +']', response[i]);
						app.getProductVariations( response[i], completeCb );
					}
			},
			error: function( jqXHR, textStatus, errorThrown ) {
				let err = jqXHR.responseJSON;
				if ( err.code && err.message ) {
					window.alert( jqXHR.status + ' ' + err.code + ' - ' + err.message );
				} else {
					window.alert( app.l10n.somethingWrong );
				}
				console.error('wc api response error', {
					jqXHR, textStatus, errorThrown
				});
			},
		};
		// console.warn('params', params);

		$.ajax( params );
	};

	app.prepareProduct = function( product ) {
		// var getRandom = (min, max) => Math.random() * (max - min) + min;
		_.defaults( product, {
			id             : 0,
			img            : '',
			sku            : '',
			parent         : '',
			name           : '',
			price          : 0,
			type           : '',
			qty            : '',
			editlink       : '',
			stock_quantity : 0,
		} );

		// product.img = product.img ? product.img : 'https://via.placeholder.com/50x50';
		product.stock_quantity = parseInt( product.stock_quantity, 10 );
		product.price = product.price ? parseFloat( product.price ) : 0;

		return product;
	};

	app.addProducts = function( products ) {
		var order_items = {};

		// TODO figure out why qty parameter is emtpy.
		//
		//
		//
		//
		//
		//
		var names = products.map( function( product ) {
			console.warn('product', JSON.parse( JSON.stringify( product ) ) );
			var title = product.name;
			if ( product.parent ) {
				title = product.parent + ' ('+ title +')';
			}
			order_items[ product.id + ':' + product.qty ] = product.id;
			return product.qty + ' of ' + title + ' ('+ product.id +')';
		} );

		window.alert( 'Adding ' + names.join( ', ' ) );

		console.warn('order_items', order_items);

		// to add items to Woo items metabox:
		app.$.body.trigger( 'wc_backbone_modal_response', [ 'wc-modal-add-products', {
			add_order_items: order_items
		} ] );

		app.vEvent.$emit( 'modalClose' );
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

		app.initVue( function() {
			console.warn('Products initiated.');
			window.setTimeout( function() {
				app.vEvent.$emit( 'modalOpen' );
			}, 150 );
		});

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
