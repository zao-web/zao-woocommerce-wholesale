<style>
	/*.red {
		color: #f00;
		padding: 0 190px 10px;
		position: absolute;
		top: 400px;
		left: 0;
	}*/

	.zwoowh-products {
		overflow-y: scroll;
		max-height: 600px;
	}

	.zwoowh-products .widefat {
		border-top: 0;
	}

	#zwoowh td, #zwoowh th {
		width: 14%;
	}

	#zwoowh td.img,
	#zwoowh th.img,
	#zwoowh td.sku,
	#zwoowh th.sku,
	#zwoowh td.price,
	#zwoowh th.price {
		width: 7%;
	}

	.product-row input {
		width: 5em;
	}

	.table-head > thead > tr > th {
		border-bottom: 0;
	}

	.table-foot > tfoot > tr > th {
		border-top: 0;
	}

	.zwoowh-filter-title {
		display: block;
		position: relative;
		padding: 8px 20px;
		margin: 0;
		font-size: 14px;
	}
</style>

<template>
	<div id="zwoowh">
		<!-- <div class="red">
			<p class="description">modalOpen is {{ modalOpen }}</p>
			<button @click="toggleModal" class="button-secondary">{{btnText}}</button>
		</div> -->
		<modal v-show="modalOpen">
			<template slot="title">Select Products</template>
			<template slot="menu">
				<h5 class="zwoowh-filter-title">Variant Products</h5>
				<a v-for="parent in productParents" @click.self.prevent="search = parent" href="#">{{ parent }}</a>
				<div class="separator"></div>
				<h5 class="zwoowh-filter-title">Types</h5>
				<a v-for="type in productTypes" @click.self.prevent="search = type" href="#">{{ type }}</a>
			</template>
			<template slot="router">
				<input v-model="search" class="large-text" type="search" id="search-products" placeholder="Filter products by sku, name, parent, price, etc" required>
			</template>

			<form id="quantities-form">
				<table class="widefat table-head">
					<thead>
						<tr>
						<th v-for="column in columns" :class="column.name">
							<a href="#" @click.self.prevent="sortBy(column.name)" :class="sortKey == column ? 'active' : null">
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
							:img="product.img"
							:sku="product.sku"
							:name="product.name"
							:price="product.price"
							:parent="product.parent"
							:type="product.type"
							:qty="product.qty"
							:stock="product.stock"
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
				<button type="button" class="button media-button button-primary button-large media-button-insert" @click.self.prevent="addProducts()" :disabled="hasSelected()">Add Products</button>
				<button type="reset" class="button media-button button-secondary button-large" @click="clearQuantities()">Clear</button>
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
				.$on( 'toggleOpen', this.toggleModal )
				.$on( 'doSearch', this.doSearch )
				.$on( 'updateQty', this.updateQty );

			var self = this;
			this.products.map( function( product ) {
				// product.img = product.img ? product.img : 'https://via.placeholder.com/50x50';
				product.stock = parseInt( product.stock ? product.stock : self.getRandom( 0, 200 ), 10 );
				product.price = product.price ? parseFloat( product.price ) : 0;
			} );
		},
		data() {
			return {
				modalOpen : false,
				btnText: 'Click Me',
				sortKey: 'type',
				reverse: false,
				search: '',
				columns: ZWOOWH.columns,
				products: ZWOOWH.allProducts,
			}
		},

		computed: {
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
			getRandom( min, max) {
			  return Math.random() * (max - min) + min;
			},
			closeModal() {
				this.modalOpen = false;
			},
			openModal() {
				this.modalOpen = true;
			},
			toggleModal() {
				this.modalOpen = ! this.modalOpen;
			},

			filter() {
				if ( ! this.search ) {
					return this.products;
				}

				return this.searchResults( this.search );
			},

			doSearch( search ) {
				this.search = search;
			},

			searchResults( search ) {
				var self = this;
				var i = 0;
				search = self.toLowerString( search );

				var left = this.products.filter( function( product ) {

					for ( i = 0; i < self.columns.length; i++ ) {
						if ( false !== self.columns[i].filter ) {
							var match = self.toLowerString( product[ self.columns[i].name ] );

							if ( match && match.indexOf( search ) !== -1 ) {
								return true;
							}
						}
					}

					return false;
				} );

				return left;
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

			updateQty( sku, qty ) {
				qty = qty.trim ? qty.trim() : qty;
				qty = parseInt( qty, 10 );
				if ( ! isNaN( qty ) ) {
					var product = this.products.find( function( product ) {
						return sku === product.sku;
					} );
					if ( qty > product.stock ) {
						qty = product.stock;
					}

					product.qty = qty;
				}
			},

			toJSON( data ) {
				return JSON.parse( JSON.stringify( data ) );
			},

			hasSelected() {
				return this.selectedProducts().length ? false : true;
			},

			selectedProducts() {
				return this.products.filter( function( product ) {
					return product.qty;
				} );
			},

			addProducts() {
				var products = this.selectedProducts();

				if ( ! products.length ) {
					return;
				}

				var names = products.map( function( product ) {
					var title = product.name;
					if ( product.parent ) {
						title = product.parent + ' ('+ title +')'
					}
					return product.qty + ' of ' + title;
				} );

				alert( 'Adding ' + names.join( ', ' ) );

				this.search = '';
				this.clearQuantities();
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
