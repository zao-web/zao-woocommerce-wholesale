<template>
	<tr :class="rowClass" :title="noStockTitle">
		<td class="img">
			<a :href="product.editlink"><img :src="imgSrc" :width="imgWidth" :height="imgHeight" :alt="product.name"/></a>
		</td>
		<td class="sku">{{ product.sku }}</td>
		<td class="name">
			<a :href="product.editlink">{{ product.name }}</a>
			<div v-if="product.parent">
				Parent: <a class="filter-link" :href="product.editlink" @click.self.prevent="doParentSearch">{{ product.parent }}</a>
			</div>
		</td>
		<td class="price"><span :title="originalPriceTitle">${{ formattedWholesalePrice }}</span></td>
		<td class="qty">
			<template v-if="hasStock">
				<input :tabindex="index + 1" size="3" @input="updateQty" :id="idAttr" :name="qtyName" :disabled="isDisabled" :value="product.qty" type="number" step="1" min="0" pattern="[0-9]"/><template v-if="minStock">&nbsp;<span style="inline-block">of {{ minStock }}</span></template>
			</template>
			<template v-else>
			  {{ noStockTitle }} <a @click.self.prevent="removeOutOfStock" href="#" class="remove-out-of-stock-button dashicons dashicons-no filter-link"></a>
			</template>
		</td>
		<td class="categories">
			<ul v-if="hasCategories">
				<li v-for="category in product.categories"><a class="filter-link" @click.self.prevent="doCategorySearch" href="#">{{ category }}</a></li>
			</ul>
		</td>
		<td v-if="customTaxName" class="custom_tax"><a class="filter-link" @click.self.prevent="doCustomTaxSearch" href="#">{{ product.custom_tax }}</a></td>
	</tr>
</template>

<script>
	export default {
		props: [ 'index', 'product' ],

		computed: {
			hasCategories() {
				return this.product.categories && this.product.categories.length;
			},
			customTaxName() {
				return !! ZWOOWH.l10n.customTaxName;
			},
			imgSrc() {
				return this.product.img && this.product.img[0] ? this.product.img[0] : ZWOOWH.placeholderImgSrc;
			},
			imgWidth() {
				return this.product.img[1] || 40;
			},
			imgHeight() {
				return this.product.img[2] || 40;
			},
			rowClass() {
				return 'product-row' + ( this.hasStock ? '' : ' disabled-row' );
			},
			noStockTitle() {
				return this.hasStock ? '' : ZWOOWH.l10n.noStockTitle;
			},
			qtyName() {
				return `quantities[${this.product.id}]`;
			},

			formattedWholesalePrice() {
				return parseFloat( this.product.wholesale_price ).toFixed(2);
			},

			originalPriceTitle() {
				return ZWOOWH.l10n.origPrice.replace( '%d', parseFloat( this.product.price ).toFixed(2) );
			},

			hasStock() {
				if ( this.product.manage_stock ) {
					return this.product.in_stock && this.product.stock_quantity > 0;
				}

				return this.product.in_stock ? true : false;
			},

			minStock() {
				if ( this.product.manage_stock ) {
					return this.product.in_stock && this.product.stock_quantity > 0 ? parseInt( this.product.stock_quantity, 10 ) : 0;
				}

				return 0;
			},

			isDisabled() {
				return ! ( this.hasStock ) && ! this.product.qty;
			},

			idAttr() {
				return 'wholesale-product-' + this.product.id;
			}
		},

		methods: {
			doCustomTaxSearch() {
				ZWOOWH.vEvent.$emit( 'doSearch', this.product.custom_tax );
			},
			doCategorySearch( evt ) {
				ZWOOWH.vEvent.$emit( 'doSearch', evt.target.innerText );
			},
			doParentSearch() {
				ZWOOWH.vEvent.$emit( 'doSearch', this.product.parent );
			},
			// Debounced to give user time to finish input before resorting.
			updateQty: ZWOOWH.debouce( function( evt ) {
				ZWOOWH.vEvent.$emit( 'updateQty', this.product.id, evt.target.value );
			}, 1400 ),
			removeOutOfStock() {
				ZWOOWH.vEvent.$emit( 'removeOutOfStock' );
			}
		}
	}
</script>
