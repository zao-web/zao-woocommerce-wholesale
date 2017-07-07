<template>
	<tr class="product-row">
		<td class="img"><img v-if="img" :src="img" :alt="name"/></td>
		<td class="sku">{{ sku }}</td>
		<td class="parent"><a @click.self.prevent="doParentSearch" href="#">{{ parent }}</a></td>
		<td class="name">{{ name }}</td>
		<td class="price">${{ formattedPrice }}</td>
		<td class="type"><a @click.self.prevent="doTypeSearch" href="#">{{ type }}</a></td>
		<td class="qty"><input size="3" @input.self.prevent="updateQty" :id="sku" :name="qtyName" :disabled="isDisabled" :value="qty" type="number" step="1" min="0" pattern="[0-9]"/> of {{ minStock }}</td>
	</tr>
</template>

<script>
	export default {
		props: [ 'img', 'sku', 'name', 'price', 'parent', 'type', 'qty', 'stock' ],

		computed: {
			qtyName: function() {
				return 'quantities['+ this.sku +']';
			},

			formattedPrice: function() {
				return parseFloat( this.price ).toFixed(2);
			},

			minStock: function() {
				return this.stock ? parseInt( this.stock, 10 ) : 0;
			},

			isDisabled: function() {
				return ! this.stock && ! this.qty;
			}
		},

		methods: {
			doTypeSearch: function( evt ) {
				ZWOOWH.vEvent.$emit( 'doSearch', this.type );
			},
			doParentSearch: function( evt ) {
				ZWOOWH.vEvent.$emit( 'doSearch', this.parent );
			},
			updateQty: function( evt ) {
				ZWOOWH.vEvent.$emit( 'updateQty', this.sku, evt.target.value );
			}
		}
	}
</script>
