<style>
	.red {
		color: #f00;
		padding: 100px 250px;
	}
</style>

<template>
	<div id="zwoowh">
		<div class="red"><button @click="modalOpen = ! modalOpen" class="button-secondary">{{btnText}}</button></div>
		<modal v-show="modalOpen" @modalClose="modalOpen = false">
			<template slot="title">Select Products</template>

			Hey, this is some text

			<span slot="addBtn">Add Product</span>
		</modal>
	</div>
</template>

<script>
	var Modal = require( './modal.vue' );

	export default {
		name: 'app',
		components : {
			Modal
		},
		data () {
			return {
				modalOpen : false,
				btnText: 'Click Me'
			}
		},

		methods : {
		}
	}
</script>
