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
	var productFields = 'id,img:40,sku,name,price,bt_product_type,manage_stock,stock_quantity,in_stock,editlink,category_names';

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
		app.currStep = 1;
		app.bodyClass( 'init-wholesale-order' );
	};

	app.step2 = function() {
		app.currStep = 2;
		app.bodyClass( 'build-wholesale-order' );

		app.$.addItems.trigger( 'click' );

		if ( app.currStep > 1 && app.vueInstance ) {
			app.vEvent.$emit( 'modalOpen' );
		}
	};

	app.step3 = function() {
		app.currStep = 3;
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
		app.vEvent.$on( 'modalOpen', function() {
			if ( ! app.vueInstance ) {
				window.alert( app.l10n.plsWait );
			}
		} );

		app.$.addItem
			.removeClass( 'add-order-item' )
			.addClass( 'add-wholesale-order-items' )
			.on( 'click', function() {
				app.vEvent.$emit( 'modalOpen' );
			} );

		app.prepareProducts( function() {
			if ( app.vueInstance ) {
				return;
			}

			var vueApp = require( './app.vue' );

			app.vueInstance = new Vue( {
				el: '#zwoowh',
				data() {
					return {
						isLoading        : true,
						modalOpen        : false,
						btnText          : 'Click Me',
						sortKey          : 'type',
						reverse          : false,
						excludeUnstocked : false,
						search           : '',
						columns          : app.columns,
						searchParams     : app.searchParams,
						products         : app.allProducts,
					};
				},
				// data: data
				render: function ( createElement ) {
					return createElement( vueApp );
				}
			} );

			// Let's get some more.
			app.getMoreProducts();

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

	app.getMoreProducts = function( page ) {
		page = page || 2;
		var url = app.rest_url + 'wc/v2/products/?bt_limit_fields=' + productFields + '&status=publish&per_page=100&page=' + page + '&type=simple&_wpnonce=' + app.rest_nonce;

		// console.warn('getMoreProducts, page', page);
		var params = {
			type: 'GET',
			url: url,
			success: function( response ) {
				// console.warn('getMoreProducts response', response.length);

				if ( response.length ) {
					for ( var i = 0; i < response.length; i++ ) {
						app.addProduct( response[i] );
					}

					// Keep looping to get all products?
					app.getMoreProducts( page + 1 );
				} else {
					app.vEvent.$emit( 'loading', false );
				}
			},
			error: function( jqXHR, textStatus, errorThrown ) {
				let err = jqXHR.responseJSON;
				if ( err && err.code && err.message ) {
					window.alert( jqXHR.status + ' ' + err.code + ' - ' + err.message );
				} else {
					console.warn('error', { jqXHR, textStatus, errorThrown });
					window.alert( app.l10n.somethingWrong );
				}
			},
		};

		$.ajax( params );
	};

	app.getProductVariations = function( completeCb, page ) {
		page = page || 1;

		var parentProduct = app.toProcess[ app.left - 1 ];
		if ( ! parentProduct ) {
			return completeCb();
		}

		var url = app.rest_url + 'wc/v2/products/' + parentProduct.id + '/variations/?bt_limit_fields=' + productFields + '&_wpnonce=' + app.rest_nonce;

		if ( page > 1 ) {
			url += '&page=' + page;
		}
		// console.warn('page', page, parentProduct.id);

		var params = {
			type: 'GET',
			url: url,
			success: function( response ) {
				// console.warn('wc api variant response', response.length);

				if ( response.length ) {
					for ( var i = 0; i < response.length; i++ ) {
						response[i].parent = parentProduct.name;
						app.addProduct( response[i] );
					}

					app.getProductVariations( completeCb, page + 1 );
				} else {
					app.left--;

					if ( app.left < 1 ) {
						completeCb();
					} else {
						app.getProductVariations( completeCb );
					}
				}

			},
			error: function( jqXHR, textStatus, errorThrown ) {
				app.left--;
				let err = jqXHR.responseJSON;
				if ( err.code && err.message ) {
					console.error( jqXHR.status + ' ' + err.code + ' - ' + err.message );
				} else {
					console.error( app.l10n.somethingWrong );
				}
				// console.error('wc api response error', {
				// 	jqXHR, textStatus, errorThrown
				// } );
			},
		};

		$.ajax( params );
	};

	app.prepareProducts = function( completeCb ) {
		var url = app.rest_url + 'wc/v2/products?status=publish&type=variable&per_page=100&_wpnonce=1&_wpnonce=' + app.rest_nonce;
		if ( app.productCategory > 0 ) {
			url += '&category='+ app.productCategory;
		}
		// if ( 1 === 1 ) {
		// 	return completeCb();
		// }

		var cbCalled = false;
		var params = {
			type: 'GET',
			url: url,
			success: function( response ) {
				// app.allProducts = [];

				if ( response.length ) {
					app.toProcess = response;
					app.left = response.length;
					// console.warn('app.left', app.left);

					app.getProductVariations( completeCb );
				}
				// else {
				// 	completeCb();
				// }

				if ( ! cbCalled ) {
					cbCalled = true;
					completeCb();
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
				} );
			},
		};

		$.ajax( params );
	};

	app.addProduct = function( product ) {
		app.allProducts.push( app.prepareProduct( product ) );
	};

	app.prepareProduct = function( product ) {
		// var getRandom = (min, max) => Math.random() * (max - min) + min;
		_.defaults( product, {
			id             : 0,
			img            : [],
			sku            : '',
			parent         : '',
			name           : '',
			price          : 0,
			type           : '',
			qty            : '',
			editlink       : '',
			categories     : [],
			manage_stock   : 0,
			in_stock       : 0,
			stock_quantity : 0,
		} );

		// product.img = product.img ? product.img : 'https://via.placeholder.com/40x40';
		product.stock_quantity = parseInt( product.stock_quantity, 10 );
		product.price = product.price ? parseFloat( product.price ) : 0;

		return product;
	};

	app.addProducts = function( quantities ) {
		var order_items = {};

		for ( var i = 0; i < quantities.length; i++ ) {
			order_items[ quantities[i].id + ':' + quantities[i].qty ] = quantities[i].id;
		}

		// to add items to Woo items metabox:
		app.$.body.trigger( 'wc_backbone_modal_response', [ 'wc-modal-add-products', {
			add_order_items: order_items
		} ] );

		app.vEvent.$emit( 'modalClose' );
	};

	app.init = function() {
		console.warn('ZWOOWH init');
		app.cache();

		// Pass our wholesale nonce through every ajax call.
		$.ajaxSetup( { data : { is_wholesale: app.is_wholesale } } );

		app.initVue( function() {
			console.warn('Products initiated.');
		} );

		app.$.select.on( 'change', app.toggleOrderBoxes );

		setTimeout( function() {
			app.$.select.select2( 'open' );
		}, 1000 );

		app.$.body.on( 'wc_backbone_modal_response', function( evt, target ) {
			if ( 'wc-modal-add-products' === target ) {
				app.step3();
			}
		} );

	};

	$( app.init );

} )( window, document, jQuery, window.ZWOOWH );
