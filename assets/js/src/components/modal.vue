<style>
	.zwoowh-content {
		padding: 16px;
	}
</style>

<template>
	<div id="zwoowh-modal" tabindex="0" style="position: relative;">
		<div class="media-modal wp-core-ui">
			<button type="button" @click="emitClose" class="media-modal-close">
				<span class="media-modal-icon">
					<span class="screen-reader-text">Close product selector</span>
				</span>
			</button>
			<div class="media-modal-content">
				<div class="media-frame mode-select wp-core-ui">
					<div class="media-frame-menu">
						<div class="media-menu">
							<slot name="menu"></slot>
						</div>
					</div>
					<div class="media-frame-title">
						<h1>
							<slot name="title">Select Products</slot>
						</h1>
					</div>
					<div class="media-frame-router">
						<div class="media-router">
							<slot name="router"></slot>
						</div>
					</div>
					<div class="media-frame-content">
						<div class="zwoowh-content">
							<slot></slot>
						</div>
					</div>
					<div class="media-frame-toolbar">
						<div class="media-toolbar">
							<div class="media-toolbar-primary search-form">
								<slot name="addBtn">
									<button type="button" class="button media-button button-primary button-large media-button-insert">Insert</button>
								</slot>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<div class="media-modal-backdrop"></div>
	</div>
</template>

<script>
	export default {
		methods : {
			emitClose() {
				ZWOOWH.vEvent.$emit( 'modalClose' );
			}
		}
	}
</script>
