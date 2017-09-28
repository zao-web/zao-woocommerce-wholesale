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
	var allIds = {};
	var Vue;
	var ENTER = 13;
	var ESCAPE = 27;

	function $get( id ) {
		return $( document.getElementById( id ) );
	}

	app.$get = $get;

	app.modalOpened = false;

	app.cache = function() {
		app.$ = {};
		app.$.body      = $( document.body );
		app.$.select    = $get( 'customer_user' );
		app.$.addItems  = $( '.button.add-line-item' );
		app.$.lineItems = $get( 'order_line_items' );
	};

	app.block = function() {
		$get( 'woocommerce-order-items' ).block({
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6
			}
		});
	};

	app.unblock = function() {
		$get( 'woocommerce-order-items' ).unblock();
	};

	app.triggerStep = () => app[ 'step' + app.whichStep() ]();

	app.whichStep = function() {
		var hasCustomer = app.$.select.val();
		var toAdd = false;
		var hasItems = app.$.lineItems.find( '.item' ).length ? true : false;
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
		app.$.select.select2( 'open' );
	};

	app.step2 = function() {
		app.currStep = 2;
		app.bodyClass( 'build-wholesale-order' );

		app.$.addItems.trigger( 'click' );

		if ( app.currStep > 1 ) {
			app.openModal();
		}
	};

	app.step3 = function() {
		app.currStep = 3;
		app.bodyClass( 'edit-wholesale-order' );
	};

	app.openModal = function() {
		if ( app.vueInstance ) {
			app.vEvent.$emit( 'modalOpen' );
		} else {
			setTimeout( app.openModal, 1000 );
		}
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

		app.vEvent.$on( 'modalOpened', () => app.modalOpened = true );
		app.vEvent.$on( 'modalClosed', () => app.modalOpened = false );
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

		$get( 'woocommerce-order-items' ).on( 'click', 'button.add-order-item', () => app.vEvent.$emit( 'modalOpen' ) );

		app.getProducts( 1 );
	};

	app.initVueModal = function( page ) {
		if ( 1 === page && ! app.vueInstance ) {
			app.vueInstance = app.newVue( {
				el: '#zwoowh',
				render: ( createElement ) => createElement( require( './app.vue' ) )
			} );

			console.warn( 'Vue Modal initiated.' );
			// app.vEvent.$emit( 'modalOpen' );
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

		var url = app.rest_url + 'wc/v2/products/?zwoowh_limit_fields=' + app.productFields.join( ',' ) + '&status=publish&wholesale=1&per_page=100&_wpnonce=' + app.rest_nonce;

		if ( page > 0 ) {
			url += '&page='+ page;
		}

		var params = {
			type: 'GET',
			url: url,
			success: function( response, textStatus, request ) {
				var totalPages = parseInt( request.getResponseHeader( 'X-WP-TotalPages' ), 10 );
				app.maybeSetTermsTitle( request.getResponseHeader( 'X-ZWOOWH-customTaxName' ) );

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
				let err = app.errMessage( jqXHR, textStatus, errorThrown );
				console.warn( 'error', { jqXHR, textStatus, errorThrown } );
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

		if ( ! parentProduct ) {
			return app.vEvent.$emit( 'variationsFetched' );
		}

		if ( parentProduct.variations.length < 1 ) {
			// Fetch the next product.
			return app.getProductVariations();
		}

		var url = app.rest_url + 'wc/v2/products/' + parentProduct.id + '/variations/?zwoowh_limit_fields=' + app.productFields.join( ',' ) + '&status=publish&_wpnonce=' + app.rest_nonce;
		url += '&per_page=' + ( parentProduct.variations.length + 1 ) + '&include[]=' + parentProduct.variations.join( '&include[]=' );

		if ( page > 1 ) {
			url += '&page=' + page;
		}


		var params = {
			type: 'GET',
			url: url,
			success: function( response, textStatus, request ) {
				var totalPages = parseInt( request.getResponseHeader( 'X-WP-TotalPages' ), 10 );

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
			id              : 0,
			img             : [],
			sku             : '',
			parent          : '',
			name            : '',
			price           : 0,
			wholesale_price : 0,
			type            : '',
			custom_tax      : '',
			qty             : '',
			editlink        : '',
			categories      : [],
			manage_stock    : 0,
			in_stock        : 0,
			stock_quantity  : 0,
		} );

		if ( product.id in allIds ) {
			return false;
		}

		allIds[ product.id ] = 1;

		// product.img = product.img ? product.img : 'https://via.placeholder.com/40x40';
		product.stock_quantity  = parseInt( product.stock_quantity, 10 );
		product.price           = product.price ? parseFloat( product.price ) : 0;
		product.wholesale_price = product.wholesale_price ? parseFloat( product.wholesale_price ) : 0;

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

	app.errMessage = function( jqXHR, textStatus, errorThrown ) {
		var msg = app.l10n.somethingWrong;
		var err = jqXHR.responseJSON;

		if ( err && err.code && err.message ) {
			msg = jqXHR.status + ' ' + err.code + ' - ' + err.message;
		}

		if ( errorThrown ) {
			msg += ' ' + app.l10n.msgReceived;
			msg += "\n\n" + errorThrown;
			if ( textStatus ) {
				msg += ' (' + textStatus + ')';
			}
		}

		return msg;
	};

	app.maybeSetTermsTitle = function( title ) {
		if ( ! title ) {
			return;
		}

		// Set the custom tax title.
		app.l10n.customTaxName = title;

		// Allow filtering/search by the custom tax
		app.searchParams.push( 'custom_tax' );

		// Add the header/column for the custom tax.
		app.columns.push( {
			name : 'custom_tax',
			title : app.l10n.customTaxName,
		} );
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

	app.checkAjaxResponseProducts = function( evt, xhr ) {
		var productQtys = xhr.getResponseHeader ? xhr.getResponseHeader( 'X-ZWOOWH-products' ) : '';
		if ( productQtys ) {

			productQtys = JSON.parse( productQtys );

			if ( productQtys ) {
				app.vEvent.$emit( 'updateProductsStock', productQtys );
			}
		}
	};

	app.keyboardActions = function( evt ) {
		var key = evt.keyCode || evt.which;

		if ( ENTER === key && app.modalOpened ) {
			app.vEvent.$emit( 'addProducts' );
		}

		if ( ESCAPE === key ) {
			app.vEvent.$emit( 'modalClose' );
		}
	};

	app.reduceAllStock = function( evt ) {
		evt.preventDefault();

		if ( ! window.confirm( app.l10n.confirmReduceStock ) ) {
			return;
		}

		var url = window.ajaxurl + '?action=zwoowh_reduce_all_stock_levels&order_id=' + $get( 'post_ID' ).val();

		app.lvlsAjax( url );
	};

	app.restoreAllStock = function( evt ) {
		evt.preventDefault();

		if ( ! window.confirm( app.l10n.confirmRestoreStock ) ) {
			return;
		}

		var url = window.ajaxurl + '?action=zwoowh_restore_all_stock_levels&order_id=' + $get( 'post_ID' ).val();

		app.lvlsAjax( url );
	};

	app.lvlsAjax = function( url ) {
		app.block();

		var params = {
			type: 'GET',
			url: url,
			success: function( response ) {
				if ( response.success ) {
					window.location.href = window.location.href;
				}
				app.unblock();
			},
			error: function( jqXHR, textStatus, errorThrown ) {
				app.unblock();

				var msg = app.l10n.msgReceived;
				var err = jqXHR.responseJSON;

				if ( err && err.code && err.message ) {
					msg = jqXHR.status + ' ' + err.code + ' - ' + err.message;
				}

				if ( errorThrown ) {
					msg += ' ' + app.l10n.msgReceived;
					msg += "\n\n" + errorThrown;
					if ( textStatus ) {
						msg += ' (' + textStatus + ')';
					}
				}
				console.error( msg );
			},
		};

		$.ajax( params );
	};

	app.init = function() {
		console.warn('ZWOOWH init');
		app.cache();

		// Pass our wholesale nonce through every ajax call.
		$.ajaxSetup( { data : { is_wholesale: app.is_wholesale } } );

		$( document )
			.ajaxSuccess( app.checkAjaxResponseProducts )
			.on( 'keydown', app.keyboardActions );

		// Replace the WC click event w/ our own later.
		$get( 'woocommerce-order-items' ).off( 'click', 'button.add-order-item' );

		app.initVue();

		app.$.select.on( 'change', app.toggleOrderBoxes );
		app.$.lvls.show();

		// disable mousewheel on a input number field when in focus
		// (to prevent Chromium browsers change the value when scrolling)
		app.$.body
			.on( 'focus', '#quantities-form input[type=number]', function( evt ) {
			  $( this ).on( 'mousewheel.disableScroll', evt => evt.preventDefault() );
			} )
			.on( 'blur', '#quantities-form input[type=number]', function( evt ) {
			  $( this ).off( 'mousewheel.disableScroll' );
			} )
			.on( 'click', '.reduce-all-stock-levels-button', app.reduceAllStock )
			.on( 'click', '.restore-all-stock-levels-button', app.restoreAllStock );

		if ( app.replaceDropdown ) {
			app.$.select.select2();
		}

		app.$.body.on( 'wc_backbone_modal_response', ( evt, target ) => {
			if ( 'wc-modal-add-products' === target ) {
				app.step3();
			}
		} );

		app.triggerStep();
	};

	$( app.init );

} )( window, document, jQuery, window.ZWOOWH );
