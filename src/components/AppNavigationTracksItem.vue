<template>
	<AppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-road'"
		:title="t('maps', 'My tracks')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onTracksClick"
		@update:open="onUpdateOpen">
		<template slot="counter">
			&nbsp;
			<span v-if="enabled && tracks.length">{{ tracks.length }}</span>
		</template>
		<template v-if="enabled" slot="actions">
			<ActionButton
				icon="icon-search"
				:close-after-click="true"
				@click="$emit('plop')">
				{{ t('maps', 'Zoom') }}
			</ActionButton>
		</template>
		<template #default>
			<AppNavigationTrackItem
				v-for="track in tracks"
				:key="track.id"
				:track="track"
				:parent-enabled="enabled && tracks.length > 0"
				@click="$emit('track-clicked', $event)"
				@zoom="$emit('zoom', track, $event)"
				@elevation="$emit('elevation', track, $event)"
				@color="$emit('color', $event)" />
		</template>
	</AppNavigationItem>
</template>

<script>
import AppNavigationItem from '@nextcloud/vue/dist/Components/AppNavigationItem'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import AppNavigationTrackItem from './AppNavigationTrackItem'
import optionsController from '../optionsController'

export default {
	name: 'AppNavigationTracksItem',

	components: {
		AppNavigationItem,
		ActionButton,
		AppNavigationTrackItem,
	},

	props: {
		enabled: {
			type: Boolean,
			required: true,
		},
		loading: {
			type: Boolean,
			default: false,
		},
		tracks: {
			type: Array,
			required: true,
		},
	},

	data() {
		return {
			open: optionsController.trackListShow,
		}
	},

	computed: {
	},

	methods: {
		onTracksClick() {
			if (!this.enabled && !this.open) {
				this.open = true
				optionsController.saveOptionValues({ trackListShow: 'true' })
			}
			this.$emit('tracks-clicked')
		},
		onUpdateOpen(isOpen) {
			this.open = isOpen
			optionsController.saveOptionValues({ trackListShow: isOpen ? 'true' : 'false' })
		},
	},
}
</script>

<style lang="scss" scoped>
.item-disabled {
	opacity: 0.5;
}

::v-deep .icon-road {
	background-color: var(--color-main-text);
	mask: url('../../img/road.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/road.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}

::v-deep .icon-road-thin {
	background-color: var(--color-main-text);
	mask: url('../../img/road-thin.svg') no-repeat;
	mask-size: 16px auto;
	mask-position: center;
	-webkit-mask: url('../../img/road-thin.svg') no-repeat;
	-webkit-mask-size: 16px auto;
	-webkit-mask-position: center;
}

::v-deep .icon-in-picker {
	margin-bottom: -3px;
}

::v-deep .no-color {
	background-color: var(--color-primary);
}
</style>
