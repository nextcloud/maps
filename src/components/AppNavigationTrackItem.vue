<template>
	<AppNavigationItem
		:title="track.file_name"
		:class="{ 'subitem-disabled': !track.enabled }"
		:allow-collapse="false"
		:force-menu="false"
		@click="$emit('click', track)">
		<template slot="icon">
			<div v-if="track.loading"
				class="app-navigation-entry-icon icon-loading-small " />
			<div v-else-if="track.color"
				:class="{
					'icon-in-picker': true,
					'icon-road-thin': !track.enabled,
					'icon-road': track.enabled,
				}"
				:style="'background-color: ' + track.color + ';'" />
			<div v-else
				:class="{
					'icon-in-picker': true,
					'icon-road-thin': !track.enabled,
					'icon-road': track.enabled,
					'no-color': true,
				}" />
			<input v-show="false"
				ref="col"
				type="color"
				class="color-inpur"
				:value="track.color || '#0082c9'"
				@change="updateTrackColor"
				@click.stop="">
		</template>
		<template slot="counter">
			&nbsp;
		</template>
		<template slot="actions">
			<ActionButton v-if="parentEnabled && track.enabled"
				icon="icon-search"
				:close-after-click="true"
				@click="$emit('zoom', track)">
				{{ t('maps', 'Zoom') }}
			</ActionButton>
			<ActionButton v-if="parentEnabled && track.enabled"
				icon="icon-category-monitoring"
				:close-after-click="true"
				@click="$emit('elevation', track)">
				{{ t('maps', 'Show track elevation') }}
			</ActionButton>
			<ActionButton v-if="parentEnabled && track.enabled"
				:close-after-click="false"
				@click="onChangeColorClick">
				<template #icon>
					<div class="icon-colorpicker" />
				</template>
				{{ t('maps', 'Change color') }}
			</ActionButton>
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'

export default {
	name: 'AppNavigationTrackItem',

	components: {
		AppNavigationItem,
		ActionButton,
	},

	props: {
		track: {
			type: Object,
			required: true,
		},
		parentEnabled: {
			type: Boolean,
			default: true,
		},
	},

	data() {
		return {
			myTimer: null,
		}
	},

	computed: {
	},

	methods: {
		onChangeColorClick() {
			this.$refs.col.click()
		},
		updateTrackColor(e) {
			this.$emit('color', { track: this.track, color: e.target.value })
			/* clearTimeout(this.myTimer)
			this.mytimer = setTimeout(() => {
				this.$emit('color', { track: this.track, color: e.target.value })
			}, 2000) */
		},
	},
}
</script>

<style lang="scss" scoped>
.subitem-disabled {
	opacity: 0.5;
}

.icon-colorpicker {
	opacity: 1;
	mask: url('../../img/color_picker.svg') no-repeat;
	-webkit-mask: url('../../img/color_picker.svg') no-repeat;
	background-color: var(--color-main-text);
	padding: 0 !important;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
	width: 44px;
	height: 44px;
}
</style>
