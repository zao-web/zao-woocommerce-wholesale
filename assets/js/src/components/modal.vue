<template>
	<div id="zwoowh-modal" tabindex="0" style="position: relative;">
		<div class="media-modal wp-core-ui">
			<button type="button" @click="emitClose" class="media-modal-close">
				<span class="media-modal-icon">
					<span class="screen-reader-text">{{ closeBtn }}</span>
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
							<slot name="title">{{ selectProductsTitle }}</slot>
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
									<button type="button" class="button media-button button-primary button-large media-button-insert">{{ insertBtn }}</button>
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
		computed: {
			closeBtn            : () => ZWOOWH.l10n.closeBtn,
			selectProductsTitle : () => ZWOOWH.l10n.selectProductsTitle,
			insertBtn           : () => ZWOOWH.l10n.insertBtn,
		},
		methods : {
			emitClose() {
				ZWOOWH.vEvent.$emit( 'modalClose' );
			}
		}
	}
</script>
