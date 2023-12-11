<template>
	<div id="photo-suggestions-tab">
		<div v-if="loading" class="icon-loading" />
		<div v-else-if="photoSuggestions.length > 0">
			<div class="oc-dialog-buttonrow">
				<NcButton
					@click="$emit('clear-selection')">
					{{ t('maps', 'Clear selection') }}
				</NcButton>
				<NcButton
					type="primary"
					@click="$emit('select-all')">
					{{ t('maps', 'Select all') }}
				</NcButton>
				<NcActions>
					<NcActionButton
						:icon="selectionLayout==='list'?'icon-toggle-pictures':'icon-toggle-filelist'"
						@click="selectionLayout=selectionLayout==='list'?'grid':'list'">
						{{ t('maps', selectionLayout==='list'?'grid view':'list view') }}
					</NcActionButton>
				</NcActions>
			</div>
			<div v-if="photoSuggestionsSelectedIndices.length > 0 && selectionLayout==='list'">
				<NcListItem v-for="p in photoSuggestionsSelected"
					:key="p.photoSuggestionsIndex"
					:name="p.basename"
					:bold="false"
					:details="getPhotoFormattedDate(p)"
					@click="onListItemClick(p)">
					<template #icon>
						<img
							:src="previewUrl(p)"
							:alt="p.basename"
							width="64"
							height="64">
					</template>
					<template #subname>
						{{ p.path }}
					</template>
					<template #actions>
						<NcActionButton @click="onListItemClick(p)">
							{{ t('maps', 'Display picture') }}
						</NcActionButton>
						<NcActionButton @click="$emit('save',[p.photoSuggestionsIndex])">
							{{ t('maps', 'Save') }}
						</NcActionButton>
						<NcActionButton @click="$emit('zoom', p)">
							{{ t('maps', 'Zoom') }}
						</NcActionButton>
						<NcActionButton @click="$emit('clear-selection',[p.photoSuggestionsIndex])">
							{{ t('maps', 'Remove from selection') }}
						</NcActionButton>
					</template>
				</NcListItem>
			</div>
			<div v-if="photoSuggestionsSelectedIndices.length > 0 && selectionLayout==='grid'"
				class="photo-suggestion-selected-grid">
				<div v-for="p in photoSuggestionsSelected"
					:key="p.photoSuggestionsIndex"
					class="photo-suggestion-selected-grid-item"
					:style="{
						'background-image': 'url('+previewUrl(p)+')',
						'background-size': 'cover'}"
					@click.self="onListItemClick(p)">
					<NcActions class="photo-suggestion-selected-grid-actions">
						<NcActionButton @click="onListItemClick(p)">
							{{ t('maps', 'Display picture') }}
						</NcActionButton>
						<NcActionButton @click="$emit('save',[p.photoSuggestionsIndex])">
							{{ t('maps', 'Save') }}
						</NcActionButton>
						<NcActionButton @click="$emit('zoom', p)">
							{{ t('maps', 'Zoom') }}
						</NcActionButton>
						<NcActionButton @click="$emit('clear-selection',[p.photoSuggestionsIndex])">
							{{ t('maps', 'Remove from selection') }}
						</NcActionButton>
					</NcActions>
				</div>
			</div>
		</div>
		<div v-else>
			<h2> {{ t('maps','No suggestions found') }} </h2>
			{{ t('maps','To get suggestions upload tracks from the trips, when you took your photos.'
				+ 'For future trips you can track your android phone using phonetrack.'
				+ 'This information are then automatically used suggest photo location') }}
		</div>
		<div class="oc-dialog-buttonrow">
			<NcButton
				@click="$emit('cancel')">
				{{ !photoSuggestions.includes(null) ? t('maps', 'Cancel') : t('maps', 'Quit') }}
			</NcButton>
			<NcButton
				@click="$emit('load-more')">
				{{ t('maps', 'Load more') }}
			</NcButton>
			<NcButton
				v-show="photoSuggestions.length > 0"
				type="primary"
				:disabled="photoSuggestionsSelectedIndices.length===0 || readOnly"
				@click="$emit('save')">
				{{ t('maps', 'Save') }}
			</NcButton>
		</div>
		<NcAppNavigationSettings class="footer">
			{{ t('maps', 'Photos default timezone:') }}
			<NcTimezonePicker
				:value="photoSuggestionsTimezone"
				@input="$emit('change-timezone', $event)" />
			{{ t('maps', 'Location sources:') }}
			<NcAppNavigationItem
				:icon="'icon-road'"
				:name="t('maps', 'Tracks')"
				:allow-collapse="true"
				:open="tracksOpen"
				:force-menu="false"
				@click="onTracksClick"
				@update:open="onUpdateTracksOpen">
				<NcCounterBubble v-show="tracks.length"
					slot="counter">
					{{ tracks.length > 99 ? '99+' : tracks.length }}
				</NcCounterBubble>
				<template slot="default">
					<b v-show="false">dummy</b>
					<PhotoSideBarTabTrackItem
						v-for="tr in tracks"
						:key="'track:'.concat(tr.id)"
						:track="tr"
						:sub-tracks="subtracks(tr)"
						@subtrack-click="onSubTrackClick($event)" />
				</template>
			</NcAppNavigationItem>
			<NcAppNavigationItem
				:icon="'icon-phone'"
				:name="t('maps', 'Devices')"
				:allow-collapse="true"
				:open="devicesOpen"
				:force-menu="false"
				@click="onDevicesClick"
				@update:open="onUpdateDevicesOpen">
				<NcCounterBubble v-show="devices.length"
					slot="counter">
					{{ devices.length > 99 ? '99+' : devices.length }}
				</NcCounterBubble>
				<template slot="default">
					<b v-show="false">dummy</b>
					<PhotoSideBarTabDeviceItem
						v-for="d in devices"
						:key="'device:'.concat(d.id)"
						:device="d"
						@device-click="$emit('toggle-track-or-device', d)" />
				</template>
			</NcAppNavigationItem>
			<NcButton
				@click="$emit('toggle-hide-photos')">
				{{ photoSuggestionsHidePhotos ? t('maps', 'Show localized photos'): t('maps', 'Hide localized photos') }}
			</NcButton>
		</NcAppNavigationSettings>
	</div>
</template>

<script>

import { generateUrl } from '@nextcloud/router'
import moment from '@nextcloud/moment'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcListItem from '@nextcloud/vue/dist/Components/NcListItem.js'
import NcCounterBubble from '@nextcloud/vue/dist/Components/NcCounterBubble.js'
import NcAppNavigationItem from '@nextcloud/vue/dist/Components/NcAppNavigationItem.js'
import NcAppNavigationSettings from '@nextcloud/vue/dist/Components/NcAppNavigationSettings.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'

import { getToken } from '../../utils/common.js'
import NcTimezonePicker from '@nextcloud/vue/dist/Components/NcTimezonePicker.js'
import PhotoSideBarTabTrackItem from './PhotoSideBarTabTrackItem.vue'
import PhotoSideBarTabDeviceItem from './PhotoSideBarTabDeviceItem.vue'

export default {
	name: 'PhotoSuggestionsSidebarTab',

	components: {
		NcButton,
		NcActions,
		NcActionButton,
		NcListItem,
		NcTimezonePicker,
		NcCounterBubble,
		PhotoSideBarTabTrackItem,
		PhotoSideBarTabDeviceItem,
		NcAppNavigationItem,
		NcAppNavigationSettings,
	},

	props: {
		photoSuggestions: {
			required: true,
			type: Array,
		},
		photoSuggestionsTracksAndDevices: {
			required: true,
			type: Object,
		},
		photoSuggestionsSelectedIndices: {
			required: true,
			type: Array,
		},
		photoSuggestionsHidePhotos: {
			type: Boolean,
			default: false,
		},
		photoSuggestionsTimezone: {
			required: true,
			type: String,
		},
		loading: {
			required: true,
			type: Boolean,
		},
	},

	data() {
		return {
			selectionLayout: 'list',
			tracksOpen: false,
			devicesOpen: false,
			openTracks: {},
		}
	},

	computed: {
		photoSuggestionsSelected() {
			return this.photoSuggestionsSelectedIndices.reduce((filtered, i) => {
				const p = this.photoSuggestions[i]
				if (p) {
					p.photoSuggestionsIndex = i
					filtered.push(p)
				}
				return filtered
			}, [])
		},
		readOnly() {
			return !this.photoSuggestions.some((f) => (f.isUpdateable))
		},
		tracks() {
			const f = Object.values(this.photoSuggestionsTracksAndDevices).reduce((filtered, v) => {
				if (v.key.startsWith('track') && v.visible && !filtered[v.id]) {
					if (!filtered[v.id]) {
						v.open = !!this.openTracks[v.id]
						filtered[v.id] = v
					} else {
						filtered[v.id].suggestionCount += v.suggestionCount
					}
				}
				return filtered
			}, {})
			return Object.values(f)
		},
		devices() {
			return Object.values(this.photoSuggestionsTracksAndDevices).filter((v) => { return v.key.startsWith('device') && v.visible })
		},
	},

	methods: {
		onTracksClick() {
			this.tracksOpen = !this.tracksOpen
			this.$emit('tracks-clicked')
		},
		onUpdateTracksOpen(isOpen) {
			this.tracksOpen = isOpen
		},
		onSubTrackClick(t) {
			this.$emit('toggle-track-or-device', t)
		},
		onDevicesClick() {
			this.devicesOpen = !this.devicesOpen
			this.$emit('devices-clicked')
		},
		onUpdateDevicesOpen(isOpen) {
			this.devicesOpen = isOpen
		},
		previewUrl(photo) {
			if (photo && photo.hasPreview) {
				const token = getToken()
				return token
					? generateUrl('apps/files_sharing/publicpreview/') + token + '?file=' + encodeURIComponent(photo.path) + '&x=341&y=256&a=1'
					: generateUrl('core') + '/preview?fileId=' + photo.fileId + '&x=341&y=256&a=1'
			} else {
				return generateUrl('/apps/theming/img/core/filetypes') + '/image.svg?v=2'
			}
		},
		getPhotoFormattedDate(photo) {
			return moment.unix(photo.dateTaken).format('L')
		},
		onListItemClick(photo) {
			if (OCA.Viewer && OCA.Viewer.open) {
				OCA.Viewer.open({ path: photo.path, list: this.photoSuggestionsSelected })
			}
		},
		subtracks(t) {
			return Object.values(this.photoSuggestionsTracksAndDevices).filter((v) => { return v.key.startsWith('track:'.concat(t.id)) && v.visible })
		},
	},
}
</script>

<style lang="scss" scoped>
#photo-tab {
	padding: 0 10px 0 10px;
	.thumbnail {
		opacity: 1;
		background-color: var(--color-main-text);
		padding: 0 !important;
		mask-size: 64px auto;
		mask-position: center;
		-webkit-mask-size: 64px auto;
		-webkit-mask-position: center;
	}
}
.photo-suggestion-selected-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(128px, 1fr));
	grid-auto-rows: 1fr;
	gap: 5px;
	padding-left: 5px;
}

.photo-suggestion-selected-grid-item {
	padding-bottom: calc(100% - 44px);
}

.photo-suggestion-selected-grid-actions {
	position: absolute;
	top:      0;
	left:     calc(100% - 44px);
	bottom:   0;
	right:    0;
	opacity: 0;
}

.photo-suggestion-selected-grid-item:hover .photo-suggestion-selected-grid-actions {
	opacity: 1 !important;
}

.footer {
	position: absolute;
	bottom: 0;
	width: 100%;
	padding: 0;
	margin: 0;
	background-color: var(--color-main-background);
	box-shadow: none;
	border: 0;
	text-align: left;
	font-weight: normal;
	font-size: 100%;
	color: var(--color-main-text);
	line-height: 44px
}

.item-disabled {
	opacity: 0.5;
}

</style>
