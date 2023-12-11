<template>
	<NcAppNavigationItem
		:icon="loading ? 'icon-loading-small' : 'icon-road'"
		:name="t('maps', 'My tracks')"
		:class="{ 'item-disabled': !enabled }"
		:allow-collapse="true"
		:open="open"
		:force-menu="false"
		@click="onTracksClick"
		@update:open="onUpdateOpen">
		<NcCounterBubble v-show="enabled && tracks.length"
			slot="counter">
			{{ tracks.length > 99 ? '99+' : tracks.length }}
		</NcCounterBubble>
		<template v-if="enabled" slot="actions">
			<NcActionButton
				icon="icon-tag"
				:close-after-click="true"
				@click="onSortByName">
				{{ t('maps', 'Sort by name') }}
			</NcActionButton>
			<NcActionButton
				icon="icon-calendar-dark"
				:close-after-click="true"
				@click="onSortByDate">
				{{ t('maps', 'Sort by date') }}
			</NcActionButton>
		</template>
		<template slot="default">
			<b v-show="false">dummy</b>
			<AppNavigationTrackItem
				v-for="track in sortedTracks"
				:key="track.id"
				:ref="'trackItem' + track.id"
				:track="track"
				:parent-enabled="enabled && tracks.length > 0"
				@click="$emit('track-clicked', $event)"
				@zoom="$emit('zoom', $event)"
				@elevation="$emit('elevation', $event)"
				@color="$emit('color', $event)"
				@add-to-map-track="$emit('add-to-map-track', $event)" />
		</template>
	</NcAppNavigationItem>
</template>

<script>
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import AppNavigationTrackItem from './AppNavigationTrackItem.vue'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'

import optionsController from '../optionsController.js'

export default {
	name: 'AppNavigationTracksItem',

	components: {
		NcAppNavigationItem,
		NcActionButton,
		AppNavigationTrackItem,
		NcCounterBubble,
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
			sortOrder: optionsController.optionValues.tracksSortOrder,
		}
	},

	computed: {
		sortedTracks() {
			if (this.sortOrder === 'name' || this.sortOrder === 'nameAsc') {
				return this.tracks.slice().sort((a, b) => {
					const nameA = a.file_name.toLowerCase()
					const nameB = b.file_name.toLowerCase()
					return nameA.localeCompare(nameB)
				})
			} else if (this.sortOrder === 'nameDesc') {
				return this.tracks.slice().sort((a, b) => {
					const nameA = a.file_name.toLowerCase()
					const nameB = b.file_name.toLowerCase()
					return -nameA.localeCompare(nameB)
				})
			} else if (this.sortOrder === 'date' || this.sortOrder === 'dateDesc') {
				return this.tracks.slice().sort((a, b) => {
					return a.mtime === b.mtime
						? 0
						: a.mtime > b.mtime
							? 1
							: -1
				})
			} else if (this.sortOrder === 'dateAsc') {
				return this.tracks.slice().sort((a, b) => {
					return a.mtime === b.mtime
						? 0
						: a.mtime < b.mtime
							? 1
							: -1
				})
			} else {
				return this.tracks
			}
		},
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
		changeTrackColor(track) {
			console.debug(this.$refs)
			this.$refs['trackItem' + track.id][0].onChangeColorClick()
		},
		onSortByName() {
			if (this.sortOrder === 'name' || this.sortOrder === 'nameAsc') {
				this.sortOrder = 'nameDesc'
			} else {
				this.sortOrder = 'nameAsc'
			}
			optionsController.saveOptionValues({ tracksSortOrder: this.sortOrder })
		},
		onSortByDate() {
			if (this.sortOrder === 'date' || this.sortOrder === 'dateDesc') {
				this.sortOrder = 'dateAsc'
			} else {
				this.sortOrder = 'dateDesc'
			}
			optionsController.saveOptionValues({ tracksSortOrder: this.sortOrder })
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
	background-color: var(--color-primary-element);
}
</style>
