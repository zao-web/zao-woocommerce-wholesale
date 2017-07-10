<style>
	.disabled-row {
		opacity: .5;
	}
</style>

<template>
	<tr :class="rowClass" :title="noStockTitle">
		<td class="img"><a :href="editlink"><img v-if="img" :src="img" :alt="name"/></a></td>
		<td class="sku">{{ sku }}</td>
		<td class="parent"><a :href="editlink" @click.self.prevent="doParentSearch">{{ parent }}</a></td>
		<td class="name">{{ name }}</td>
		<td class="price">${{ formattedPrice }}</td>
		<td class="type"><a @click.self.prevent="doTypeSearch" href="#">{{ type }}</a></td>
		<td class="qty">
			<template v-if="minStock">
				<input size="3" @input.self.prevent="updateQty" :id="idAttr" :name="qtyName" :disabled="isDisabled" :value="qty" type="number" step="1" min="0" pattern="[0-9]"/> of {{ minStock }}
			</template>
			<template v-else>
			  {{ noStockTitle }} <a @click.self.prevent="removeOutOfStock" href="#" class="remove-out-of-stock-button dashicons dashicons-no"></a>
			</template>
		</td>
	</tr>
</template>

<script>
	export default {
		props: [ 'id', 'img', 'sku', 'name', 'price', 'parent', 'type', 'qty', 'stock', 'editlink' ],

		computed: {
			rowClass() {
				return 'product-row' + ( this.stock > 1 ? '' : ' disabled-row' );
			},
			noStockTitle() {
				return this.stock > 1 ? '' : ZWOOWH.l10n.noStockTitle;
			},
			qtyName() {
				return `quantities[${this.id}]`;
			},

			formattedPrice() {
				return parseFloat( this.price ).toFixed(2);
			},

			minStock() {
				return this.stock > 1 ? parseInt( this.stock, 10 ) : 0;
			},

			isDisabled() {
				return ! ( this.stock > 1 ) && ! this.qty;
			},

			idAttr() {
				return 'wholesale-product-' + this.id;
			}
		},

		methods: {
			doTypeSearch() {
				ZWOOWH.vEvent.$emit( 'doSearch', this.type );
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
