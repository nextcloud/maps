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
		<template #counter>
			<NcCounterBubble v-show="enabled && tracks.length" :count="tracks.length" />
		</template>
		<template v-if="enabled" #actions>
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
		<template #default>
			<b v-show="false">dummy</b>
			<AppNavigationTrackItem
				v-for="track in sortedTracks"
				:key="track.id"
				:ref="(el) => setTrackItemRef(track.id, el)"
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

<script setup>
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcActionButton from '@nextcloud/vue/components/NcActionButton'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import AppNavigationTrackItem from './AppNavigationTrackItem.vue'
import { t } from '@nextcloud/l10n'
import { ref, computed } from 'vue'
import optionsController from '../optionsController.js'

const props = defineProps({
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
})

const emit = defineEmits([
	'tracks-clicked',
	'track-clicked',
	'zoom',
	'elevation',
	'color',
	'add-to-map-track',
])

const open = ref(optionsController.trackListShow)
const sortOrder = ref(optionsController.optionValues.tracksSortOrder)

const trackItemRefs = {}

function setTrackItemRef(id, el) {
	if (el) {
		trackItemRefs[id] = el
	} else {
		delete trackItemRefs[id]
	}
}

const sortedTracks = computed(() => {
	if (sortOrder.value === 'name' || sortOrder.value === 'nameAsc') {
		return props.tracks.slice().sort((a, b) => {
			const nameA = a.file_name.toLowerCase()
			const nameB = b.file_name.toLowerCase()
			return nameA.localeCompare(nameB)
		})
	} else if (sortOrder.value === 'nameDesc') {
		return props.tracks.slice().sort((a, b) => {
			const nameA = a.file_name.toLowerCase()
			const nameB = b.file_name.toLowerCase()
			return -nameA.localeCompare(nameB)
		})
	} else if (sortOrder.value === 'date' || sortOrder.value === 'dateDesc') {
		return props.tracks.slice().sort((a, b) => {
			return a.mtime === b.mtime
				? 0
				: a.mtime > b.mtime
					? 1
					: -1
		})
	} else if (sortOrder.value === 'dateAsc') {
		return props.tracks.slice().sort((a, b) => {
			return a.mtime === b.mtime
				? 0
				: a.mtime < b.mtime
					? 1
					: -1
		})
	} else {
		return props.tracks
	}
})

function onTracksClick() {
	if (!props.enabled && !open.value) {
		open.value = true
		optionsController.saveOptionValues({ trackListShow: 'true' })
	}
	emit('tracks-clicked')
}

function onUpdateOpen(isOpen) {
	open.value = isOpen
	optionsController.saveOptionValues({ trackListShow: isOpen ? 'true' : 'false' })
}

function changeTrackColor(track) {
	console.debug(trackItemRefs)
	trackItemRefs[track.id]?.onChangeColorClick()
}

function onSortByName() {
	if (sortOrder.value === 'name' || sortOrder.value === 'nameAsc') {
		sortOrder.value = 'nameDesc'
	} else {
		sortOrder.value = 'nameAsc'
	}
	optionsController.saveOptionValues({ tracksSortOrder: sortOrder.value })
}

function onSortByDate() {
	if (sortOrder.value === 'date' || sortOrder.value === 'dateDesc') {
		sortOrder.value = 'dateAsc'
	} else {
		sortOrder.value = 'dateDesc'
	}
	optionsController.saveOptionValues({ tracksSortOrder: sortOrder.value })
}

defineExpose({ changeTrackColor })
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
