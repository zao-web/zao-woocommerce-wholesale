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
	var productFields = 'id,img:40,sku,name,price,type,bt_product_type,manage_stock,stock_quantity,in_stock,editlink,category_names';
	var allIds = {};
	var Vue;

	function $get( id ) {
		return $( document.getElementById( id ) );
	}

	app.cache = function() {
		app.$ = {};
		app.$.body = $( document.body );
		app.$.select = $get( app.select_id );
		app.$.addItems = $( '.button.add-line-item' );
		app.$.addItem = $( '.button.add-order-item' );
	};

	app.triggerStep = () => app[ 'step' + app.whichStep() ]();

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

	app.newVue = function( properties ) {
		Vue = Vue || require( 'vue' );
		return new Vue( properties );
	};

	app.initVue = function() {
		if ( app.vEvent ) {
			return;
		}

		var Vue = require( 'vue' );
		app.vEvent = app.newVue();

		app.vEvent.$on( 'modalOpened', app.resizeTable );
		app.vEvent.$on( 'productsSelected', app.addProducts );
		app.vEvent.$on( 'productsFetched', app.initVueModal );
		app.vEvent.$on( 'productsFetched', app.fetchVariations );
		app.vEvent.$on( 'variationsFetched', () => app.vEvent.$emit( 'loading', false ) );
		app.vEvent.$on( 'modalOpen', function() {
			if ( ! app.vueInstance ) {
				window.alert( app.l10n.plsWait );
			}
		} );

		app.$.addItem.on( 'click', () => app.vEvent.$emit( 'modalOpen' ) );

		app.getProducts( 1 );
	};

	app.initVueModal = function( page ) {
		if ( 1 === page && ! app.vueInstance ) {
			app.vueInstance = app.newVue( {
				el: '#zwoowh',
				data() {
					return {
						isLoading        : true,
						modalOpen        : false,
						sortKey          : 'bt_type',
						reverse          : false,
						excludeUnstocked : false,
						search           : '',
						columns          : app.columns,
						searchParams     : app.searchParams,
						products         : app.allProducts,
					};
				},
				// data: data
				render: ( createElement ) => createElement( require( './app.vue' ) )
			} );

			console.warn( 'Vue Modal initiated.' );
			app.vEvent.$emit( 'modalOpen' );
		}
	};

	app.fetchVariations = function( page, done ) {
		if ( done && app.variableProducts.length ) {
			app.getProductVariations();
		}
	};

	app.resizeTable = function() {
		app.$.tHead = app.$.tHead || $( '#zwoowh-modal .zwoowh-content .table-head' );
		app.$.productsTable = app.$.productsTable || $( '#zwoowh-modal .zwoowh-products' );
		app.$.modalContent = app.$.modalContent || $( '#zwoowh-modal .media-frame-content');

		var thH = app.$.tHead.outerHeight();
		var contentH = app.$.modalContent.outerHeight();

		app.$.productsTable.css( { 'max-height': contentH - ( thH * 3 ) } );
	};

	app.getProducts = function( page ) {
		page = page || 0;

		var url = app.rest_url + 'wc/v2/products/?bt_limit_fields=' + productFields + '&status=publish&wholesale=1&per_page=100&_wpnonce=' + app.rest_nonce;

		if ( page > 0 ) {
			url += '&page='+ page;
		}

		// console.warn('getProducts ('+ page +') url', url);

		var params = {
			type: 'GET',
			url: url,
			success: function( response, textStatus, request ) {
				// console.warn('getProducts ('+ page +') response', response);
				var totalPages = parseInt( request.getResponseHeader( 'X-WP-TotalPages' ), 10 );
				var done = true;

				if ( response.length ) {
					for ( var i = 0; i < response.length; i++ ) {
						app.addProduct( response[i] );
					}

					if ( totalPages > 1 && ( page + 1 ) <= totalPages ) {
						done = false;

						// Keep looping to get all products
						app.getProducts( page + 1 );
					}
				}

				app.vEvent.$emit( 'productsFetched', page, done );
			},
			error: ( jqXHR, textStatus, errorThrown ) => {
				let err = app.errMessage( jqXHR );
				console.warn('error', { jqXHR, textStatus, errorThrown } );
				window.alert( err );
			},
		};

		$.ajax( params );
	};

	app.getProductVariations = function( page, parentProduct ) {
		page = page || 1;

		if ( 1 === page ) {
			parentProduct = app.variableProducts.shift();
		}

		// console.warn('parentProduct ('+ page +')', parentProduct);
		if ( ! parentProduct ) {
			return app.vEvent.$emit( 'variationsFetched' );
		}

		var url = app.rest_url + 'wc/v2/products/' + parentProduct.id + '/variations/?bt_limit_fields=' + productFields + '&_wpnonce=' + app.rest_nonce;
		// console.warn('getProductVariations' + parentProduct.id, url);

		if ( page > 1 ) {
			url += '&page=' + page;
		}
		// console.warn('page', page, parentProduct.id);

		var params = {
			type: 'GET',
			url: url,
			success: function( response, textStatus, request ) {
				var totalPages = parseInt( request.getResponseHeader( 'X-WP-TotalPages' ), 10 );
				// console.warn('wc api variant response', response.length);

				if ( response.length ) {
					for ( var i = 0; i < response.length; i++ ) {
						response[i].parent = parentProduct.name;
						app.addProduct( response[i] );
					}

					if ( totalPages > 1 && ( page + 1 ) <= totalPages ) {
						return app.getProductVariations( page + 1, parentProduct );
					}
				}

				app.getProductVariations();
			},
			error: jqXHR => console.error( app.errMessage( jqXHR ) ),
		};

		$.ajax( params );
	};

	app.addProduct = function( product ) {
		product = app.prepareProduct( product );
		if ( product ) {
			app[ 'variable' === product.type ? 'variableProducts' : 'allProducts' ].push( product );
		}
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
			bt_type        : '',
			qty            : '',
			editlink       : '',
			categories     : [],
			manage_stock   : 0,
			in_stock       : 0,
			stock_quantity : 0,
		} );

		if ( product.id in allIds ) {
			return false;
		}

		allIds[ product.id ] = 1;

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

	app.errMessage = function( jqXHR ) {
		var msg = app.l10n.somethingWrong;
		var err = jqXHR.responseJSON;

		if ( err && err.code && err.message ) {
			msg = jqXHR.status + ' ' + err.code + ' - ' + err.message;
		}

		return msg;
	};

	// Because underscore's debounce is whack...
	app.debouce = function( func, wait ) {
		var timeout, args, context;

		return function() {
			context = this;
			args = arguments;

			if ( timeout ) {
				clearTimeout( timeout );
			}

			timeout = setTimeout( () => {
				func.apply(context, args);
				timeout = null;
			}, wait );
		};
	};

	app.init = function() {
		console.warn('ZWOOWH init');
		app.cache();

		// Pass our wholesale nonce through every ajax call.
		$.ajaxSetup( { data : { is_wholesale: app.is_wholesale } } );

		app.$.addItem.removeClass( 'add-order-item' ).addClass( 'add-wholesale-order-items' );

		app.initVue();

		app.$.select.on( 'change', app.toggleOrderBoxes );

		// disable mousewheel on a input number field when in focus
		// (to prevent Chromium browsers change the value when scrolling)
		app.$.body
			.on( 'focus', '#quantities-form input[type=number]', function( evt ) {
			  $( this ).on( 'mousewheel.disableScroll', evt => evt.preventDefault() );
			} )
			.on( 'blur', '#quantities-form input[type=number]', function( evt ) {
			  $( this ).off( 'mousewheel.disableScroll' );
			} );

		if ( 'wholesale_user' === app.select_id ) {
			app.$.select.select2();
		}

		setTimeout( () => app.$.select.select2( 'open' ), 1000 );

		app.$.body.on( 'wc_backbone_modal_response', ( evt, target ) => {
			if ( 'wc-modal-add-products' === target ) {
				app.step3();
			}
		} );

	};

	$( app.init );

} )( window, document, jQuery, window.ZWOOWH );
