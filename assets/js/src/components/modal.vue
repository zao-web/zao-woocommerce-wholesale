<style>
	.red {
		color: #f00;
		padding: 100px 250px;
	}
</style>

<template>
	<div id="wc-backbone-modal-dialog" tabindex="0" v-show="isVisible">
		<div class="wc-backbone-modal">
			<div class="wc-backbone-modal-content">
				<section class="wc-backbone-modal-main" role="main">
					<header class="wc-backbone-modal-header">
						<h1><slot name="title"></slot></h1>
						<button @click="emitClose" class="modal-close modal-close-link dashicons dashicons-no-alt">
							<span class="screen-reader-text">Close modal panel</span>
						</button>
					</header>
					<article>
						<form action="" method="post">
							<slot></slot>
						</form>
					</article>
					<footer>
						<div class="inner">
							<button id="btn-ok" class="button button-primary button-large"><slot name="addBtn"></slot></button>
						</div>
					</footer>
				</section>
			</div>
		</div>
		<div class="wc-backbone-modal-backdrop modal-close"></div>
	</div>
</template>

<script>
	export default {
		data () {
			return {
				isVisible : false,
			}
		},

		methods : {
			emitClose() {
				this.$emit( 'modalClose' );
			}
		}
	}
</script>
