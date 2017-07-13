<template>
	<tr :class="rowClass" :title="noStockTitle">
		<td class="img">
			<a :href="editlink"><img :src="imgSrc" :width="imgWidth" :height="imgHeight" :alt="name"/></a>
		</td>
		<td class="sku">{{ sku }}</td>
		<td class="name">
			<a :href="editlink">{{ name }}</a>
			<div v-if="parent">
				Parent: <a class="filter-link" :href="editlink" @click.self.prevent="doParentSearch">{{ parent }}</a>
			</div>
		</td>
		<td class="price">${{ formattedPrice }}</td>
		<td class="qty">
			<template v-if="hasStock">
				<input size="3" @input.self.prevent="updateQty" :id="idAttr" :name="qtyName" :disabled="isDisabled" :value="qty" type="number" step="1" min="0" pattern="[0-9]"/><template v-if="minStock">&nbsp;<span style="inline-block">of {{ minStock }}</span></template>
			</template>
			<template v-else>
			  {{ noStockTitle }} <a @click.self.prevent="removeOutOfStock" href="#" class="remove-out-of-stock-button dashicons dashicons-no filter-link"></a>
			</template>
		</td>
		<td class="type"><a class="filter-link" @click.self.prevent="doTypeSearch" href="#">{{ type }}</a></td>
		<td class="categories">
			<ul v-if="hasCategories">
				<li v-for="category in categories"><a class="filter-link" @click.self.prevent="doCategorySearch" href="#">{{ category }}</a></li>
			</ul>
		</td>
	</tr>
</template>

<script>
	export default {
		props: [ 'id', 'img', 'sku', 'name', 'price', 'parent', 'type', 'qty', 'stock', 'inStock', 'manageStock', 'editlink', 'categories' ],

		computed: {
			hasCategories() {
				return this.categories && this.categories.length;
			},
			imgSrc() {
				return this.img && this.img[0] ? this.img[0] : ZWOOWH.placeholderImgSrc;
			},
			imgWidth() {
				return this.img[1] || 40;
			},
			imgHeight() {
				return this.img[2] || 40;
			},
			rowClass() {
				return 'product-row' + ( this.hasStock ? '' : ' disabled-row' );
			},
			noStockTitle() {
				return this.hasStock ? '' : ZWOOWH.l10n.noStockTitle;
			},
			qtyName() {
				return `quantities[${this.id}]`;
			},

			formattedPrice() {
				return parseFloat( this.price ).toFixed(2);
			},

			hasStock() {
				if ( this.manageStock ) {
					return this.inStock && this.stock > 0;
				}

				return this.inStock ? true : false;
			},

			minStock() {
				if ( this.manageStock ) {
					return this.inStock && this.stock > 0 ? parseInt( this.stock, 10 ) : 0;
				}

				return 0;
			},

			isDisabled() {
				return ! ( this.hasStock ) && ! this.qty;
			},

			idAttr() {
				return 'wholesale-product-' + this.id;
			}
		},

		methods: {
			doTypeSearch() {
				ZWOOWH.vEvent.$emit( 'doSearch', this.type );
			},
			doCategorySearch( evt ) {
				ZWOOWH.vEvent.$emit( 'doSearch', evt.target.innerText );
			},
			doParentSearch() {
				ZWOOWH.vEvent.$emit( 'doSearch', this.parent );
			},
			updateQty( evt ) {
				ZWOOWH.vEvent.$emit( 'updateQty', this.id, evt.target.value );
			},
			removeOutOfStock() {
				ZWOOWH.vEvent.$emit( 'removeOutOfStock' );
			}
		}
	}
</script>
