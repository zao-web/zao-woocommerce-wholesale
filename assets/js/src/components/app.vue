<style>
	/*.test {
		color: #f00;
		padding: 0 190px 10px;
		position: absolute;
		top: 400px;
		left: 0;
	}*/
</style>

<template>
	<div id="zwoowh">
		<!-- <div class="test">
			<p class="description">modalOpen is {{ modalOpen }}</p>
			<button @click="openModal" class="button-secondary">{{btnText}}</button>
		</div> -->
		<modal v-show="modalOpen">
			<template slot="title"><span v-if="isLoading" class="spinner is-active zwoowh-loading-spinner"></span>{{ selectProductsTitle }}</template>
			<template slot="menu">
				<h5 class="zwoowh-filter-title filter-link">{{ variantProductsTitle }}</h5>
				<a v-for="parent in productParents" @click.self.prevent="search = parent" href="#">{{ parent }}</a>
				<div class="separator"></div>
				<h5 class="zwoowh-filter-title filter-link">{{ typesTitle }}</h5>
				<a v-for="type in productTypes" @click.self.prevent="search = type" href="#">{{ type }}</a>
				<div class="separator"></div>
				<h5 class="zwoowh-filter-title filter-link">{{ categoryTitle }}</h5>
				<a v-for="category in allCategories" @click.self.prevent="search = category" href="#">{{ category }}</a>
			</template>
			<template slot="router">
				<input v-model="search" class="large-text" type="search" id="search-products" :placeholder="searchPlaceholder" required>
			</template>

			<form id="quantities-form">
				<table class="widefat table-head">
					<thead>
						<tr>
						<th v-for="column in columns" :class="column.name">
							<a href="#" @click.self.prevent="sortBy(column.name)" :class="sortKey == column ? 'active filter-link' : 'filter-link'">
								{{ column.title }}
							</a>
						</th>
						</tr>
					</thead>
				</table>
				<div class="zwoowh-products">
					<table class="widefat striped">
						<tbody>
							<tr
							is="product-row"
							v-for="(product, index) in orderedProducts"
							:index="index"
							:id="product.id"
							:img="product.img"
							:sku="product.sku"
							:name="product.name"
							:price="product.price"
							:parent="product.parent"
							:type="product.type"
							:qty="product.qty"
							:editlink="product.editlink"
							:categories="product.categories"
							:stock="product.stock_quantity"
							:inStock="product.in_stock"
							:manageStock="product.manage_stock"
							></tr>
						</tbody>
					</table>

				</div>
				<table class="widefat table-foot">
					<tfoot>
						<tr>
						<th v-for="column in columns" :class="column.name">
							<a href="#" @click.self.prevent="sortBy(column.name)" :class="sortKey == column ? 'active' : null">
								{{ column.title }}
							</a>
						</th>
						</tr>
					</tfoot>
				</table>
			</form>

			<template slot="addBtn">
				<button type="button" class="button media-button button-primary button-large media-button-insert" @click.self.prevent="addProducts()" :disabled="hasSelected()">{{ btnText }}</button>
				<button type="reset" class="button media-button button-secondary button-large" @click="clearQuantities()">{{ clearBtn }}</button>
			</template>
		</modal>
	</div>
</template>

<script>

	var Modal = require( './modal.vue' );
	var ProductRow = require( './product-row.vue' );

	export default {
		name: 'app',
		components : {
			Modal,
			ProductRow
		},
		created() {
			ZWOOWH.vEvent
				.$on( 'modalClose', this.closeModal )
				.$on( 'modalOpen', this.openModal )
				.$on( 'doSearch', this.doSearch )
				.$on( 'updateQty', this.updateQty )
				.$on( 'removeOutOfStock', this.removeOutOfStock )
				.$on( 'loading', this.setLoading );
		},
		data() {
			return {
				isLoading            : true,
				modalOpen            : false,
				sortKey              : 'sku',
				reverse              : false,
				excludeUnstocked     : true,
				search               : '',
				columns              : ZWOOWH.columns,
				searchParams         : ZWOOWH.searchParams,
				products             : ZWOOWH.allProducts,
				allCategories        : ZWOOWH.allCategories,
				btnText              : ZWOOWH.l10n.addProductsBtn,
				clearBtn             : ZWOOWH.l10n.clearBtn,
				variantProductsTitle : ZWOOWH.l10n.variantProductsTitle,
				typesTitle           : ZWOOWH.l10n.typesTitle,
				categoryTitle        : ZWOOWH.l10n.categoryTitle,
			}
		},

		computed: {
			doneLoading() {
				return this.isLoading ? false : true;
			},
			selectProductsTitle() {
				return ZWOOWH.l10n.selectProductsTitle + ' ('+ this.products.length +' found)';
			},
			searchPlaceholder() {
				return ZWOOWH.l10n.searchPlaceholder
			},
			productParents() {
				var cats = {};
				for (var i = 0; i < this.products.length; i++) {
					if ( this.products[i].parent ) {
						cats[ this.products[i].parent ] = 1;
					}
				}
				return Object.keys( cats );
			},
			productTypes() {
				var types = {};
				for (var i = 0; i < this.products.length; i++) {
					if ( this.products[i].type ) {
						types[ this.products[i].type ] = 1;
					}
				}
				return Object.keys( types );
			},
			orderedProducts() {
				var sk = this.sortKey;
				var results = _.sortBy( this.filter(), function( p ) {
					return p[ sk ] && p[ sk ].toLowerCase ? p[ sk ].toLowerCase() : p[ sk ];
				} );

				if ( this.reverse ) {
					results.reverse();
				}

				return results;
			},
		},

		methods : {
			closeModal() {
				this.modalOpen = false;
				setTimeout( () => ZWOOWH.vEvent.$emit( 'modalClosed' ), 100 );
			},
			openModal() {
				this.modalOpen = true;
				setTimeout( () => ZWOOWH.vEvent.$emit( 'modalOpened' ), 100 );
			},
			hasStock( product ) {
				// manage_stock   : 0,
				// in_stock       : 0,
				// stock_quantity : 0,
				if ( product.manage_stock ) {
					return product.in_stock && product.stock_quantity > 0;
				}

				return product.in_stock ? true : false;
			},
			hasQty : ( product ) => product.qty > 0,

			filter() {
				var results = this.searchResults( this.search );

				if ( this.excludeUnstocked ) {
					results = results.filter( this.hasStock );;
				}

				return results;
			},

			doSearch( search ) {
				this.search = search;
			},

			searchResults( search ) {
				if ( ! search || search.length < 2 ) {
					return this.products;
				}

				var self = this;
				var i = 0
				var x = 0;
				search = self.toLowerString( search );

				return this.products.filter( function( product ) {

					return _.find( self.searchParams, function( col ) {

						var prodVal = self.toLowerString( product[ col ] );

						if ( 'categories' === col && _.isArray( prodVal ) ) {
							var foundCat = _.find( prodVal, ( cat ) => cat.indexOf( search ) !== -1 );

							if ( foundCat ) {
								return true;
							}
						} else {
							if ( prodVal && prodVal.indexOf( search ) !== -1 ) {
								return true;
							}
						}

						return false;

					} );

					return false;
				} );
			},

			toLowerString( val ) {
				if ( ! val ) {
					return val;
				}

				if ( val.toString ) {
					val = val.toString();
				}

				if ( val.toLowerCase ) {
					val = val.toLowerCase();
				}

				return val;
			},

			sortBy(sortKey) {
				this.reverse = (this.sortKey == sortKey) ? ! this.reverse : false;
				this.sortKey = sortKey;
			},

			updateQty( id, qty ) {
				qty = qty.trim ? qty.trim() : qty;
				qty = parseInt( qty, 10 );
				if ( ! isNaN( qty ) ) {
					var product = this.products.find( function( product ) {
						return id === product.id;
					} );
					if ( product.manage_stock && qty > product.stock_quantity ) {
						qty = product.stock_quantity;
					}

					product.qty = qty;
				}
			},

			removeOutOfStock() {
				this.excludeUnstocked = true;
			},

			setLoading( loading ) {
				this.isLoading = loading ? true : false;
			},

			toJSON: ( data ) => JSON.parse( JSON.stringify( data ) ),

			hasSelected() {
				return this.selectedProducts().length ? false : true;
			},

			selectedProducts() {
				return this.products.filter( this.hasQty );
			},

			addProducts() {
				var products = this.selectedProducts();

				if ( ! products.length ) {
					return;
				}

				var quantities = products.map( function( product ) {
					return { id: product.id, qty: product.qty };
				} );

				this.search = '';
				this.clearQuantities();

				ZWOOWH.vEvent.$emit( 'productsSelected', quantities );
			},

			clearQuantities() {
				for (var i = 0; i < this.products.length; i++) {
					if ( this.products[i].qty ) {
						this.products[i].qty = '';
					}
				}

				document.getElementById( 'quantities-form' ).reset();
			},

		}
	}
</script>
