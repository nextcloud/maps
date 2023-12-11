<template>
	<NcContent app-name="maps">
		<input id="sharingToken"
			type="hidden"
			name="sharingToken"
			:value="token">
		<MapsNavigation
			@toggle-trackme="onToggleTrackme"
			@toggle-slider="onToggleSlider"
			@toggle-geo-link="onToggleGeoLink">
			<template #items>
				<AppNavigationFavoritesItem
					:enabled="favoritesEnabled"
					:loading="favoritesLoading"
					:favorites="favorites"
					:categories="favoriteCategories"
					:draggable="favoritesDraggable"
					:adding-favorite="addingFavorite"
					@add-favorite="onNavigationAddFavorite"
					@favorites-clicked="onFavoritesClicked"
					@category-clicked="onFavoriteCategoryClicked"
					@rename-category="onRenameFavoriteCategory"
					@zoom-all-categories="onZoomAllFavorites"
					@zoom-category="onZoomFavoriteCategory"
					@export-category="onExportFavoriteCategory"
					@add-to-map-category="onAddFavoriteCategoryToMap"
					@delete-shared-category-from-map="onDeleteFavoriteCategoryFromMap"
					@delete-category="onDeleteFavoriteCategory"
					@category-share-change="onFavoriteCategoryShareChange"
					@toggle-all-categories="onToggleAllFavoriteCategories"
					@export="onExportFavorites"
					@import="onImportFavorites"
					@draggable-clicked="favoritesDraggable = !favoritesDraggable" />
				<AppNavigationContactsItem
					:enabled="contactsEnabled"
					:loading="contactsLoading"
					:contacts="contacts"
					:groups="contactGroups"
					@contacts-clicked="onContactsClicked"
					@group-clicked="onContactGroupClicked"
					@zoom-all-groups="onZoomAllContactGroups"
					@zoom-group="onZoomContactGroup"
					@add-to-map-all-contacts="onAddAllContactsToMap"
					@add-to-map-contact-group="onAddContactsGroupToMap"
					@toggle-all-groups="onToggleAllContactGroups" />
				<AppNavigationPhotosItem
					:enabled="photosEnabled"
					:loading="photosLoading"
					:total-photos="countVisiblePhotos"
					:loaded-photos="loadedPhotos"
					:read-only="photosReadOnly"
					:draggable="photosDraggable"
					:show-suggestions="showPhotoSuggestions"
					@photos-clicked="onPhotosClicked"
					@cancel-clicked="cancelPhotoMove"
					@redo-clicked="redoPhotoMove"
					@draggable-clicked="photosDraggable = !photosDraggable"
					@suggestions-clicked="onPhotoSuggestionsClicked"
					@clear-cache="onPhotosClearCache" />
				<AppNavigationTracksItem
					ref="tracksNavigation"
					:enabled="tracksEnabled"
					:loading="tracksLoading"
					:tracks="tracks"
					@zoom="onTrackZoom"
					@elevation="onTrackElevation"
					@track-clicked="onNavTrackClicked"
					@tracks-clicked="onTracksClicked"
					@color="onChangeTrackColor"
					@add-to-map-track="onAddTrackToMap" />
				<AppNavigationDevicesItem
					v-if="!token"
					ref="devicesNavigation"
					:enabled="devicesEnabled"
					:loading="devicesLoading"
					:devices="devices"
					@zoom="onDeviceZoom"
					@export="onExportDevice"
					@export-all="onExportAllDevices"
					@import="onImportDevices"
					@refresh-positions="onRefreshPositions"
					@delete="onDeleteDevice"
					@add-to-map-device="onAddDeviceToMap"
					@toggle-history="onToggleDeviceHistory"
					@toggle-all="onToggleAllDevices"
					@color="onChangeDeviceColor"
					@device-clicked="onNavDeviceClicked"
					@devices-clicked="onDevicesClicked" />
				<AppNavigationMyMapsItem
					v-if="!token"
					ref="myMapsNavigation"
					:enabled="myMapsEnabled"
					:loading="myMapsLoading"
					:my-maps="myMaps"
					@add="onAddMyMap"
					@rename="onRenameMyMap"
					@delete="onDeleteMyMap"
					@share="onShareMyMap"
					@color="onChangeMyMapColor"
					@my-map-clicked="onMyMapClicked"
					@my-maps-clicked="onMyMapsClicked" />
			</template>
		</MapsNavigation>
		<NcAppContent>
			<div id="app-content-wrapper">
				<Map
					ref="map"
					:active-layer-id-prop="activeLayerId"
					:map-bounds-prop="mapBounds"
					:search-data="searchData"
					:routing-search-data="routingSearchData"
					:favorites="displayedFavorites"
					:favorite-categories="favoriteCategories"
					:favorites-enabled="favoritesEnabled"
					:favorites-draggable="favoritesDraggable"
					:photos="photos"
					:photos-enabled="photosEnabled"
					:photos-draggable="photosDraggable"
					:show-photo-suggestions="showPhotoSuggestions"
					:photo-suggestions="photoSuggestions"
					:photo-suggestions-tracks-and-devices="photoSuggestionsTracksAndDevices"
					:photo-suggestions-selected-indices="photoSuggestionsSelectedIndices"
					:photo-suggestions-hide-photos="photoSuggestionsHidePhotos"
					:contacts="contacts"
					:contact-groups="contactGroups"
					:contacts-enabled="contactsEnabled"
					:tracks="displayedTracks"
					:tracks-enabled="tracksEnabled"
					:devices="displayedDevices"
					:devices-enabled="devicesEnabled"
					:slider-enabled="sliderEnabled"
					:slider-start-timestamp="sliderStart"
					:slider-end-timestamp="sliderEnd"
					:min-data-timestamp="minDataTimestamp"
					:max-data-timestamp="maxDataTimestamp"
					:state="mapState"
					:last-actions="lastActions"
					:last-canceled-actions="lastCanceledActions"
					@add-click="onMapAddClick"
					@click-favorite="onFavoriteClick"
					@edit-favorite="onFavoriteEdit"
					@add-favorite="onFavoriteAdd"
					@add-address-favorite="onAddressFavoriteAdd"
					@add-to-map-favorite="onAddFavoriteToMap"
					@delete-favorite="onFavoriteDelete"
					@delete-favorites="onFavoritesDelete"
					@coords-reset="resetPhotosCoords"
					@address-deleted="onContactAddressDelete"
					@add-to-map-contact="onAddContactToMap"
					@contact-placed="onContactPlace"
					@photo-clusters-loading="onPhotoClustersLoading"
					@photo-clusters-loaded="onPhotoClustersLoaded"
					@add-to-map-photo="onAddPhotoToMap"
					@place-photos="placePhotoFilesOrFolder"
					@photo-moved="onPhotoMoved"
					@photo-suggestion-selected="onPhotoSuggestionSelected"
					@photo-suggestion-moved="onPhotoSuggestionMoved"
					@open-sidebar="openSidebar"
					@click-track="onTrackClick"
					@add-to-map-track="onAddTrackToMap"
					@search-enable-track="onSearchEnableTrack"
					@change-track-color="onChangeTrackColorClicked"
					@track-added="onTrackAdded"
					@add-to-map-device="onAddDeviceToMap"
					@toggle-device-history="onToggleDeviceHistory"
					@change-device-color="onChangeDeviceColorClicked"
					@export-device="onExportDevice"
					@search-enable-device="onSearchEnableDevice"
					@cancel="cancelAction"
					@redo="redoAction"
					@slider-range-changed="sliderStart = $event.start; sliderEnd = $event.end" />
			</div>
		</NcAppContent>
		<Sidebar
			v-if="true"
			ref="Sidebar"
			:show="showSidebar"
			:favorite="selectedFavorite"
			:favorite-categories="favoriteCategories"
			:track="selectedTrack"
			:photo="selectedPhoto"
			:photos-loading="photosLoading"
			:photo-suggestions="photoSuggestions"
			:photo-suggestions-tracks-and-devices="photoSuggestionsTracksAndDevices"
			:photo-suggestions-selected-indices="photoSuggestionsSelectedIndices"
			:photo-suggestions-timezone="photoSuggestionsTimezone"
			:photo-suggestions-hide-photos="photoSuggestionsHidePhotos"
			:my-map="selectedMyMap"
			@edit-favorite="onFavoriteEdit"
			@delete-favorite="onFavoriteDelete"
			@active-changed="onActiveSidebarTabChanged"
			@close="onCloseSidebar"
			@opened="onOpenedSidebar"
			@load-more-photo-suggestions="getPhotoSuggestions"
			@select-all-photo-suggestions="onSelectAllPhotoSuggestions"
			@clear-photo-suggestions-selection="onClearPhotoSuggestionsSelection"
			@cancel-photo-suggestions="onCancelPhotoSuggestions"
			@toggle-photo-suggestions-hide-photo="photoSuggestionsHidePhotos=!photoSuggestionsHidePhotos"
			@save-photo-suggestions-selection="onSavePhotoSuggestionsSelection"
			@photo-suggestion-toggle-track-or-device="onPhotoSuggestionToggleTrackOrDevice"
			@change-photo-suggestions-timezone="onChangePhotoSuggestionsTimezone"
			@zoom-photo-suggestion="onPhotoSuggestionZoom" />
	</NcContent>
</template>

<script>
import NcContent from '@nextcloud/vue/dist/Components/NcContent.js'
import NcAppContent from '@nextcloud/vue/dist/Components/NcAppContent.js'
import NcActions from '@nextcloud/vue/dist/Components/NcActions.js'
import NcActionButton from '@nextcloud/vue/dist/Components/NcActionButton.js'
import { showError, showInfo, showSuccess } from '@nextcloud/dialogs'

import moment from '@nextcloud/moment'

import { emit } from '@nextcloud/event-bus'

import Map from '../components/Map.vue'
import MapsNavigation from '../components/MapsNavigation.vue'
import Sidebar from '../components/Sidebar.vue'
import AppNavigationFavoritesItem from '../components/AppNavigationFavoritesItem.vue'
import AppNavigationPhotosItem from '../components/AppNavigationPhotosItem.vue'
import AppNavigationContactsItem from '../components/AppNavigationContactsItem.vue'
import AppNavigationTracksItem from '../components/AppNavigationTracksItem.vue'
import AppNavigationDevicesItem from '../components/AppNavigationDevicesItem.vue'
import AppNavigationMyMapsItem from '../components/AppNavigationMyMapsItem.vue'
import optionsController from '../optionsController.js'
import { getLetterColor, hslToRgb, Timer, getDeviceInfoFromUserAgent2, isComputer, isPhone, splitByNonEscapedComma } from '../utils.js'
import { binSearch, getToken, isPublic } from '../utils/common.js'
import { poiSearchData } from '../utils/poiData.js'
import { processGpx } from '../tracksUtils.js'
import L from 'leaflet'
import { geoToLatLng, getFormattedADR } from '../utils/mapUtils.js'
import * as network from '../network.js'
import { all as axiosAll, spread as axiosSpread } from 'axios'
import { generateUrl } from '@nextcloud/router'

export default {
	name: 'App',

	components: {
		NcContent,
		NcAppContent,
		NcActions,
		NcActionButton,
		Map,
		MapsNavigation,
		Sidebar,
		AppNavigationFavoritesItem,
		AppNavigationPhotosItem,
		AppNavigationContactsItem,
		AppNavigationTracksItem,
		AppNavigationDevicesItem,
		AppNavigationMyMapsItem,
	},

	data() {
		return {
		    // Map Options
		    activeLayerId: optionsController.tileLayer,
			mapBounds: optionsController.bounds,
			optionValues: optionsController.optionValues,
			sendPositionTimer: null,
			showSidebar: false,
			activeSidebarTab: '',
			// slider
			sliderEnabled: optionsController.optionValues.displaySlider === 'true',
			sliderStart: 0,
			sliderEnd: moment().unix(),
			// action history
			lastActions: [],
			lastCanceledActions: [],
			// favorites
			favoritesLoading: false,
			favoritesEnabled: optionsController.favoritesEnabled,
			favoritesDraggable: false,
			favorites: {},
			disabledFavoriteCategories: optionsController.disabledFavoriteCategories,
			favoriteCategoryTokens: {},
			selectedFavorite: null,
			addingFavorite: false,
			lastUsedFavoriteCategory: null,
			// photos
			photosLoading: false,
			photosEnabled: optionsController.photosEnabled,
			photosDraggable: false,
			photos: [],
			loadedPhotos: 0,
			selectedPhoto: null,
			showPhotoSuggestions: false,
			photoSuggestions: [],
			photoSuggestionsTracksAndDevices: {},
			photoSuggestionsSelectedIndices: [],
			photoSuggestionsHidePhotos: false,
			photoSuggestionsTimezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
			photoSuggestionsLimit: 250,
			photoSuggestionsOffset: 0,
			// contacts
			contactsLoading: false,
			contactsEnabled: optionsController.contactsEnabled,
			contacts: [],
			contactGroups: {},
			disabledContactGroups: [],
			// tracks
			tracksLoading: false,
			tracks: [],
			tracksEnabled: optionsController.tracksEnabled,
			selectedTrack: null,
			// devices
			devicesLoading: false,
			devices: [],
			devicesEnabled: optionsController.devicesEnabled && !isPublic(),
			exportingDevices: false,
			importingDevices: false,
			// myMaps
			myMapsLoading: false,
			myMaps: [],
			myMapsEnabled: optionsController.myMapsEnabled && !isPublic(),
			myMapId: optionsController.myMapId,
			selectedMyMap: null,
			// PublicPage
			token: (window.location.pathname.includes('/apps/maps/s/')
				? window.location.pathname.split('/apps/maps/s/')[1].split('/')[0]
				: null),
		}
	},

	computed: {
		mapLoading() {
			return this.photosLoading || this.contactsLoading || this.favoritesLoading || this.tracksLoading || this.devicesLoading || this.myMapsLoading
				|| this.exportingDevices || this.importingDevices
		},
		mapState() {
			if (this.addingFavorite) {
				return 'adding'
			} else if (this.mapLoading) {
				return 'loading'
			}
			return ''
		},
		// slider
		minDataTimestamp() {
			return Math.min(
				this.minPhotoTimestamp,
				this.minFavoriteTimestamp,
				this.minTrackTimestamp,
				this.minDevicesTimestamp,
			) || 0
		},
		maxDataTimestamp() {
			return Math.max(
				this.maxPhotoTimestamp,
				this.maxFavoriteTimestamp,
				this.maxTrackTimestamp,
			) || moment().unix()
		},
		photoDates() {
			return this.photos.filter(p => { return p.dateTaken !== null }).map(p => p.dateTaken)
		},
		minPhotoTimestamp() {
			return this.photoDates.length >= 2
				? this.photoDates[0]
				: this.photoDates.length === 1
					? this.photoDates[0] - 100
					: moment().unix() - 100
		},
		maxPhotoTimestamp() {
			return this.photoDates.length >= 2
				? this.photoDates[this.photoDates.length - 1]
				: this.photoDates.length === 1
					? this.photoDates[0] + 100
					: moment().unix() + 100
		},
		favoritesDates() {
			return Object.keys(this.favorites).filter((fid) => {
				return this.favorites[fid].date_created !== null
			}).map((fid) => {
				return this.favorites[fid].date_created
			})
		},
		minFavoriteTimestamp() {
			return this.favoritesDates.length >= 2
				? Math.min(...this.favoritesDates)
				: this.favoritesDates.length === 1
					? this.favoritesDates[0] - 100
					: moment().unix() - 100
		},
		maxFavoriteTimestamp() {
			return this.favoritesDates.length >= 2
				? Math.max(...this.favoritesDates)
				: this.favoritesDates.length === 1
					? this.favoritesDates[0] + 100
					: moment().unix() + 100
		},
		trackDates() {
			return [
				...this.tracks.filter((t) => !!t.metadata?.begin && t.metadata?.begin >= 0).map((t) => t.metadata?.begin),
				...this.tracks.filter((t) => !!t.metadata?.end && t.metadata?.end >= 0).map((t) => t.metadata?.end),
			]
		},
		minTrackTimestamp() {
			return this.trackDates.length >= 2
				? Math.min(...this.trackDates)
				: this.trackDates.length === 1
					? this.trackDates[0] - 100
					: moment().unix() - 100
		},
		maxTrackTimestamp() {
			return this.trackDates.length >= 2
				? Math.max(...this.trackDates)
				: this.trackDates.length === 1
					? this.trackDates[0] + 100
					: moment().unix() + 100
		},
		minDevicesTimestamp() {
			return this.devices.reduce((min, device) => {
				if (device.enabled && device.historyEnabled) {
					const lastNullIndex = binSearch(device.points, (p) => !p.timestamp)
					min = Math.min(min, device.points[lastNullIndex + 1].timestamp)
				}
				return min
			}, moment().unix() + 100)
		},
		// displayed data
		displayedTracks() {
			return this.sliderEnabled
				? this.tracks.filter((t) => {
					return !(t.metadata?.begin >= this.sliderEnd || (t.metadata?.end >= 0 && t.metadata?.end <= this.sliderStart))
				})
				: this.tracks
		},
		displayedDevices() {
			return this.sliderEnabled
				? this.devices.filter((p) => {
					return true
				})
				: this.devices
		},
		photosLastNullIndex() {
			return this.sliderEnabled ? binSearch(this.photos, (p) => !p.dateTaken) : -1
		},
		photosFirstShownIndex() {
			return this.sliderEnabled ? binSearch(this.photos, (p) => (p.dateTaken || 0) < this.sliderStart) + 1 : 0
		},
		photosLastShownIndex() {
			return this.sliderEnabled ? binSearch(this.photos, (p) => (p.dateTaken || 0) < this.sliderEnd) : this.photos.length - 1
		},
		countVisiblePhotos() {
			return 1 + this.photosLastNullIndex + Math.min(this.photos.length - 1 ?? 0, this.photosLastShownIndex) - this.photosFirstShownIndex + 1
		},
		photosReadOnly() {
			return this.photos.every((p) => !p.isUpdateable)
				|| (this.photos.length === 0 && !(optionsController.optionValues?.isCreatable && !optionsController.optionValues?.isUpdateable))
		},
		displayedFavorites() {
			return this.sliderEnabled
				? Object.keys(this.favorites).filter((fid) => {
					return this.favorites[fid].date_created === null
						|| (this.favorites[fid].date_created >= this.sliderStart && this.favorites[fid].date_created <= this.sliderEnd)
				}).reduce((res, fid) => { res[fid] = this.favorites[fid]; return res }, {})
				: this.favorites
		},
		// search
		searchData() {
			const data = [
				...this.contactSearchData,
				...this.favoriteSearchData,
				...this.trackSearchData,
				...this.deviceSearchData,
				...poiSearchData,
			]
			if (navigator.geolocation && window.isSecureContext) {
				data.unshift({
					type: 'mylocation',
					icon: 'icon-address',
					id: 'dummyID',
					label: t('maps', 'My location'),
					value: t('maps', 'My location'),
				})
			}
			return data
		},
		routingSearchData() {
			const data = [
				...this.contactSearchData,
				...this.favoriteSearchData,
				...this.trackRoutingSearchData,
				...this.deviceRoutingSearchData,
			]
			if (navigator.geolocation && window.isSecureContext) {
				data.unshift({
					type: 'mylocation',
					icon: 'icon-address',
					id: 'dummyID',
					label: t('maps', 'My location'),
					value: t('maps', 'My location'),
				})
			}
			return data
		},
		deviceSearchData() {
			return this.devicesEnabled
				? this.devices.map((d) => {
					return {
						type: 'device',
						icon: isComputer(d.user_agent) ? 'icon-desktop' : 'icon-phone',
						id: d.id,
						label: d.user_agent,
						device: d,
					}
				})
				: []
		},
		deviceRoutingSearchData() {
			return this.devicesEnabled
				? this.devices.filter((d) => {
					return d.points
				}).map((d) => {
					const lastPoint = d.points[d.points.length - 1]
					const ll = L.latLng([lastPoint.lat, lastPoint.lng])
					return {
						type: 'device',
						icon: isComputer(d.user_agent) ? 'icon-desktop' : 'icon-phone',
						id: d.id,
						label: d.user_agent,
						latLng: ll,
					}
				})
				: []
		},
		trackSearchData() {
			return this.tracksEnabled
				? this.tracks.map((t) => {
					return {
						type: 'track',
						icon: 'icon-road',
						id: t.id,
						label: t.file_name,
						track: t,
					}
				})
				: []
		},
		trackRoutingSearchData() {
			return this.tracksEnabled
				? this.tracks.filter((t) => {
					return t.metadata
				}).map((t) => {
					const ll = L.latLng([t.metadata.lat, t.metadata.lng])
					return {
						type: 'track',
						icon: 'icon-road',
						id: t.id,
						label: t.file_name,
						latLng: ll,
					}
				})
				: []
		},
		contactSearchData() {
			return this.contactsEnabled
				? this.contacts.map((c) => {
					return {
						type: 'contact',
						icon: 'icon-contacts-dark',
						id: c.UID + c.GEO,
						label: c.FN + ' - ' + getFormattedADR(c.ADR),
						latLng: L.latLng(geoToLatLng(c.GEO)),
					}
				})
				: []
		},
		favoriteSearchData() {
			return this.favoritesEnabled
				? Object.keys(this.favorites).filter((favid) => {
					const f = this.favorites[favid]
					return f.name
				}).map((favid) => {
					const f = this.favorites[favid]
					return {
						type: 'favorite',
						icon: 'icon-favorite',
						id: favid,
						label: f.name,
						latLng: L.latLng(f.lat, f.lng),
					}
				})
				: []
		},
		favoriteCategories() {
			const categories = {}
			const noCategoryId = t('maps', 'Personal')
			Object.keys(this.favorites).forEach((fid) => {
				const f = this.favorites[fid]
				const catid = f.category || noCategoryId
				if (categories[catid]) {
					categories[catid].counter++
					if (!f.isUpdateable) {
						categories[catid].isUpdateable = false
					}
				} else {
					const hsl = catid.length < 1
						? getLetterColor('a', 'a')
						: catid.length === 1
							? getLetterColor(catid[0], 'a')
							: getLetterColor(catid[0], catid[1])
					const color = hslToRgb(hsl.h / 360, hsl.s / 100, hsl.l / 100)
					categories[catid] = {
						name: catid,
						color,
						counter: 1,
						isShareable: this.myMapId === null || this.myMapId === '',
						isUpdateable: f.isUpdateable,
						enabled: !this.disabledFavoriteCategories.includes(catid),
						token: this.favoriteCategoryTokens[catid],
					}
				}
			})
			return categories
		},
	},

	created() {
		this.getContacts()
		this.getPhotos()
		this.getFavorites()
		this.getTracks()
		this.getDevices()
		this.getMyMaps()
		if (optionsController.optionValues.trackMe === 'true') {
			this.sendPositionLoop()
		}
		// Register sidebar to be callable from viewer, possibly nicer in main.js but I failed to but it there
		window.OCA.Files.Sidebar.open = this.openSidebar
		window.OCA.Files.Sidebar.close = this.closeSidebar
		window.OCA.Files.Sidebar.setFullScreenMode = this.sidebarSetFullScreenMode

		document.onkeyup = (e) => {
			if (e.ctrlKey) {
				if (e.key === 'z' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
					this.cancelAction()
				} else if (e.key === 'Z' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
					this.redoAction()
				}
			} else if (e.key === 'Escape') {
				this.addingFavorite = false
			}
		}
	},
	mounted() {
		// subscribe('nextcloud:unified-search.search', this.filter)
		// subscribe('nextcloud:unified-search.reset', this.cleanSearch)
		setTimeout(() => { emit('files:sidebar:closed') }, 1000)
	},
	beforeDestroy() {
		// unsubscribe('nextcloud:unified-search.search', this.filter)
		// unsubscribe('nextcloud:unified-search.reset', this.cleanSearch)
	},
	methods: {
		onActiveSidebarTabChanged(newActive) {
			window.OCA.Files.Sidebar.setActiveTab(newActive)
		},
		onMainDetailClicked() {
			this.showSidebar ? this.closeSidebar() : this.openSidebar()
		},
		onCloseSidebar() {
			this.closeSidebar()
			this.deselectAll()
			// Make shure that the active photo suggestions tab stays there if photo suggestions are loaded
			if (this.showPhotoSuggestions) {
				this.openSidebar()
				window.OCA.Files.Sidebar.setActiveTab('photo-suggestion')
			}
		},
		closeSidebar() {
			this.$refs.Sidebar.close()
			emit('files:sidebar:closed')
			window.OCA.Files.Sidebar.setActiveTab('')
			this.showSidebar = false
		},
		openSidebar(path = null, type = null, title = null) {
			this.showSidebar = true
			this.$refs.Sidebar.open(path, type, title)
			if (!path && this.showPhotoSuggestions) {
				window.OCA.Files.Sidebar.setActiveTab('photo-suggestion')
			}
			/*
			const myMap = path ? this.myMaps.find((m) => m.path === path) : false
			if (myMap) {
				window.OCA.Files.state.activeTab = 'myMaps'
				this.selectedMyMap = myMap
				this.sidebarFileInfo = myMap.fileInfo
				window.OCA.Files.Sidebar.state.file = path
			} else {
				const photo = this.photos.find((p) => p.path === path)
				if (photo) {
					window.OCA.Files.state.activeTab = 'photo'
					this.selectedPhoto = photo
					this.sidebarFileInfo = photo
					window.OCA.Files.Sidebar.state.file = path
				} else {
					window.OCA.Files.Sidebar.state.file = true
				}
			}
			emit('files:sidebar:opening')
			 */
		},
		/**
		 * Allow to set the Sidebar as fullscreen from OCA.Files.Sidebar
		 *
		 * @param {boolean} isFullScreen - Wether or not to render the Sidebar in fullscreen.
		 */
		sidebarSetFullScreenMode(isFullScreen) {
			if (this.$refs.Sidebar) {
				this.$refs.Sidebar.setFullScreenMode(isFullScreen)
			}
		},
		onOpenedSidebar() {
			// opened is emitted when the sidebar is mounted, but not actually shown
			if (this.showSidebar) {
				emit('files:sidebar:opened')
				console.info('files:sidebar:opened')
			}
		},
		deselectAll() {
			if (this.selectedFavorite) {
				this.selectedFavorite.selected = false
				this.selectedFavorite = null
			}
			if (this.selectedTrack) {
				this.selectedTrack.selected = false
				this.selectedTrack = null
			}
			if (!isPublic()) {
				window.OCA.Files.Sidebar.state.file = ''
			}
		},
		onToggleTrackme(enabled) {
			if (enabled) {
				this.sendPositionLoop()
			} else {
				this.stopTrackLoop()
			}
		},
		onToggleGeoLink(enabled) {
			if (enabled) {
				if (window.navigator.registerProtocolHandler) {
					window.navigator.registerProtocolHandler('geo', generateUrl('/apps/maps/openGeoLink/') + '%s', 'Nextcloud Maps')
				}
			} else {
				if (window.navigator.unregisterProtocolHandler) {
					window.navigator.unregisterProtocolHandler('geo', generateUrl('/apps/maps/openGeoLink/') + '%s')
				}
			}
		},
		onToggleSlider(enabled) {
			this.sliderEnabled = enabled
			this.sliderStart = 0
			this.sliderEnd = moment().unix()
		},
		sendPositionLoop() {
			// start a loop which get and send my position
			if (navigator.geolocation && window.isSecureContext) {
				navigator.geolocation.getCurrentPosition((position) => {
					const lat = position.coords.latitude
					const lng = position.coords.longitude
					const acc = position.coords.accuracy
					this.sendMyPosition(lat, lng, acc)
					// loop
					this.stopTrackLoop()
					this.sendPositionTimer = new Timer(() => {
						this.sendPositionLoop()
					}, 10 * 1000)
				})
			} else {
				showInfo(t('maps', 'Impossible to get current location'))
			}
		},
		stopTrackLoop() {
			if (this.sendPositionTimer) {
				this.sendPositionTimer.pause()
				delete this.sendPositionTimer
				this.sendPositionTimer = null
			}
		},
		sendMyPosition(lat, lng, acc) {
			const uaString = navigator.userAgent
			const info = getDeviceInfoFromUserAgent2(uaString)
			let name = uaString
			if (info.client && info.os) {
				if (isPhone(info.os)) {
					name = t('maps', 'Phone')
				} else if (isComputer(info.os)) {
					name = t('maps', 'Computer')
				} else {
					name = t('maps', 'Unknown device type')
				}
				name += ' (' + info.client
				name += '/' + info.os
				name += ')'
			}
			const ts = Math.floor(Date.now() / 1000)
			network.sendMyPosition(lat, lng, name, acc, ts, this.myMapId).then((response) => {
				// TODO get new positions
			}).catch((error) => {
				showError(t('maps', 'Failed to send current position') + ' ' + error)
			})
		},
		onMapAddClick(e) {
			if (this.mapState === 'adding' && this.addingFavorite) {
				this.addFavorite({ lat: e.latlng.lat, lng: e.latlng.lng }, null, null, null, null, true, true)
				this.addingFavorite = false
			}
		},
		// action history
		saveAction(action) {
			this.lastActions.push(action)
			this.lastCanceledActions = []
		},
		cancelAction() {
			if (this.lastActions.length === 0 || this.mapLoading) {
				return
			}
			const lastAction = this.lastActions.pop()
			this.lastCanceledActions.push(lastAction)
			if (lastAction.type === 'photoMove') {
				this.cancelPhotoMove(lastAction.content)
			} else if (lastAction.type === 'favoriteAdd') {
				this.cancelFavoriteAdd(lastAction)
			} else if (lastAction.type === 'favoriteEdit') {
				this.cancelFavoriteEdit(lastAction)
			} else if (lastAction.type === 'favoriteDelete') {
				this.cancelFavoriteDelete(lastAction)
			} else if (lastAction.type === 'favoriteRenameCategory') {
				this.cancelFavoriteRenameCategory(lastAction)
			} else if (lastAction.type === 'contactDelete') {
				this.cancelContactDelete(lastAction)
			} else if (lastAction.type === 'contactPlace') {
				this.cancelContactPlace(lastAction)
			}
		},
		redoAction() {
			if (this.lastCanceledActions.length === 0 || this.mapLoading) {
				return
			}
			const lastCanceledAction = this.lastCanceledActions.pop()
			this.lastActions.push(lastCanceledAction)
			if (lastCanceledAction.type === 'photoMove') {
				this.redoPhotoMove(lastCanceledAction.content)
			} else if (lastCanceledAction.type === 'favoriteAdd') {
				this.redoFavoriteAdd(lastCanceledAction)
			} else if (lastCanceledAction.type === 'favoriteEdit') {
				this.redoFavoriteEdit(lastCanceledAction)
			} else if (lastCanceledAction.type === 'favoriteDelete') {
				this.redoFavoriteDelete(lastCanceledAction)
			} else if (lastCanceledAction.type === 'favoriteRenameCategory') {
				this.redoFavoriteRenameCategory(lastCanceledAction)
			} else if (lastCanceledAction.type === 'contactDelete') {
				this.redoContactDelete(lastCanceledAction)
			} else if (lastCanceledAction.type === 'contactPlace') {
				this.redoContactPlace(lastCanceledAction)
			}
		},
		// ================ PHOTOS =================
		onPhotoClustersLoading(processed, total) {
			this.photosLoading = true
			this.loadedPhotos = processed
		},
		onPhotoClustersLoaded() {
			if (this.loadedPhotos > 0) {
				this.photosLoading = false
			}
		},
		onPhotosClicked() {
			this.photosEnabled = !this.photosEnabled

			// get photos if we don't have them yet
			if (this.photosEnabled && this.photos.length === 0) {
				this.getPhotos()
			}
			optionsController.saveOptionValues({ photosLayer: this.photosEnabled ? 'true' : 'false' })
		},
		onAddPhotoToMap(photo) {
			this.chooseMyMap((map) => {
				try {
					network.copyByPath(photo.path, map.path + '/' + photo.basename)
					showSuccess(t('maps', 'Photo {photoName} added to map {mapName}', {
						photoName: photo.basename ?? '',
						mapName: map.name ?? '',
					}))
				} catch (error) {
					console.error(error)
					showError(t('maps', 'Failed to save photo {photoName} to map {mapName}', {
						trackName: photo.basename ?? '',
						mapName: map.name ?? '',
					}))
				}
			})
		},
		getPhotos() {
			this.showPhotoBackgroundJobInfo()
			this.photos = []
			if (!this.photosEnabled) {
				return
			}
			this.photosLoading = true
			network.getPhotos(this.myMapId, getToken()).then(
				/* async (response) => {
					for (let i = 0; i * 500 < response.data.length; i++) {
						this.photos.push(...response.data.slice(i * 500, (i + 1) * 500))
						await sleep(i * 1000 + 500)
					}
				// this.photos = response.data.sort((p1, p2) => (p1.dateTaken || 0) - (p2.dateTaken || 0))
				} */
				async (response) => {
					// this.photos = response.data.sort((p1, p2) => (p1.dateTaken || 0) - (p2.dateTaken || 0))
					this.photos = response.data
				},
			).catch((error) => {
				console.error(error)
			}).then(() => {
				this.photosLoading = false
			})
		},
		async showPhotoBackgroundJobInfo() {
			network.getBackgroundJobStatus().then((response) => {
				const status = response.data
				if (status.addJobsRemainingForUser > 0) {
					showInfo(t(
						'maps',
						'A background job added {current} from {total} new photos. This might take a while.',
						{
							current: status.recentlyAdded,
							total: status.recentlyAdded + status.addJobsRemainingForUser,
						}))
				}
				if (status.updateJobsRemainingForUser > 0) {
					showInfo(t(
						'maps',
						'A background job updated {current} from {total} changed photos. This might take a while.',
						{
							current: status.recentlyUpdated,
							total: status.recentlyUpdated + status.updateJobsRemainingForUser,
						}))
				}
			}).catch((error) => {
				console.error(error)
			})
		},
		placePhotoFilesOrFolder(latlng) {
			OC.dialogs.confirmDestructive(
				'',
				t('maps', 'What do you want to place?'),
				{
					type: OC.dialogs.YES_NO_BUTTONS,
					confirm: t('maps', 'Photo files'),
					confirmClasses: '',
					cancel: t('maps', 'Photo folders'),
				},
				(result) => {
					if (result) {
						this.placePhotoFiles(latlng)
					} else {
						this.placePhotoFolder(latlng)
					}
				},
				true,
			)
		},
		placePhotoFiles(latlng) {
			OC.dialogs.filepicker(
				t('maps', 'Choose pictures to place'),
				(targetPath) => {
					this.placePhotos(targetPath, [latlng.lat], [latlng.lng])
				},
				true,
				['image/jpeg', 'image/tiff'],
				true,
			)
		},
		placePhotoFolder(latlng) {
			OC.dialogs.filepicker(
				t('maps', 'Choose directory of pictures to place'),
				(targetPath) => {
					if (targetPath === '') {
						targetPath = '/'
					}
					this.placePhotos([targetPath], [latlng.lat], [latlng.lng], true)
				},
				false,
				'httpd/unix-directory',
				true,
			)
		},
		placePhotos(paths, lats, lngs, directory = false, save = true, reload = true) {
			network.placePhotos(paths, lats, lngs, directory, this.myMapId).then((response) => {
				if (reload) {
					this.getPhotos()
				}
				if (save) {
					this.saveAction({
						type: 'photoMove',
						content: response.data,
					})
					if (paths.length === 1) {
						showSuccess(t('maps', '"{path}" successfully moved', { path: paths[0] }))
					} else {
						showSuccess(t('maps', '{nb} photos moved', { nb: paths.length }))
					}
				}
			}).catch((error) => {
				console.error(error)
			})
		},
		onPhotoMoved(photo, latLng) {
			photo.lat = latLng.lat
			photo.lng = latLng.lng
			this.placePhotos([photo.path], [latLng.lat], [latLng.lng], false, true, false)
		},
		resetPhotosCoords(paths, save = true) {
			network.resetPhotosCoords(paths, this.myMapId).then((response) => {
				this.getPhotos()
				if (save) {
					this.saveAction({
						type: 'photoMove',
						content: response.data,
					})
				}
			}).catch((error) => {
				console.error(error)
			}).then(() => {
			})
		},
		cancelPhotoMove(actionContent) {
			// place the photos that previously had coordinates
			const toPlace = actionContent.filter((action) => {
				return (action.oldLat && action.oldLng)
			})
			// reset the photos that previously had NO coordinates and have new ones
			const toReset = actionContent.filter((action) => {
				return (action.lat && !action.oldLat)
			})
			if (toPlace.length > 0) {
				const paths = toPlace.map((a) => { return a.path })
				const lats = toPlace.map((a) => { return a.oldLat })
				const lngs = toPlace.map((a) => { return a.oldLng })
				this.placePhotos(paths, lats, lngs, false, false)
			}
			if (toReset.length > 0) {
				const paths = toReset.map((a) => { return a.path })
				this.resetPhotosCoords(paths, false)
			}
		},
		redoPhotoMove(actionContent) {
			// redo placement action
			const toPlace = actionContent.filter((action) => {
				return (action.lat && action.lng)
			})
			// redo reset action
			const toReset = actionContent.filter((action) => {
				return (!action.lat && action.oldLat)
			})
			if (toPlace.length > 0) {
				const paths = toPlace.map((a) => { return a.path })
				const lats = toPlace.map((a) => { return a.lat })
				const lngs = toPlace.map((a) => { return a.lng })
				this.placePhotos(paths, lats, lngs, false, false)
			}
			if (toReset.length > 0) {
				const paths = toReset.map((a) => { return a.path })
				this.resetPhotosCoords(paths, false)
			}
		},
		onPhotosClearCache() {
			this.photosLoading = true
			network.clearPhotoCache(getToken()).then(() => {
				showSuccess(t('maps', 'Cleared photo cache'))
			}).catch((error) => {
				console.error(error)
				showError(t('maps', 'Failed to clear photos cache'))
			}).then(() => {
				this.photosLoading = false
			})
		},
		// ================ PHOTOSUGGESTIONS ========
		onPhotoSuggestionsClicked() {
			this.showPhotoSuggestions = !this.showPhotoSuggestions
			if (this.photosEnabled && this.showPhotoSuggestions && this.photoSuggestions.length === 0) {
				this.getPhotoSuggestions()
			}
			window.OCA.Files.Sidebar.setActiveTab('photo-suggestion')
			this.showPhotoSuggestions ? this.openSidebar() : this.closeSidebar()
		},
		onPhotoSuggestionSelected(index) {
			const newIndices = this.photoSuggestionsSelectedIndices.filter((e) => { return index !== e })
			if (newIndices.length === this.photoSuggestionsSelectedIndices.length) {
				newIndices.push(index)
			}
			this.photoSuggestionsSelectedIndices = newIndices
		},
		onPhotoSuggestionMoved(index, latLng) {
			this.photoSuggestions[index].lat = latLng.lat
			this.photoSuggestions[index].lng = latLng.lng
		},
		onPhotoSuggestionZoom(photo) {
			this.$refs.map.zoomOnPhotoSuggestion(photo)
		},
		cancelPhotoSuggestions() {
			this.photoSuggestionsSelectedIndices = []
			this.showPhotoSuggestions = false
			this.photoSuggestionsHidePhotos = false
			this.photoSuggestionsOffset = 0
			this.photoSuggestionsTracksAndDevices = {}
			this.closeSidebar()
			this.photoSuggestions = []
		},
		getPhotoSuggestions() {
			if (!this.photosEnabled || this.photosLoading) {
				return
			}
			this.photosLoading = true
			network.getPhotoSuggestions(this.myMapId, getToken(), this.photoSuggestionsTimezone, this.photoSuggestionsLimit, this.photoSuggestionsOffset).then((response) => {
				const photoSuggestions = []
				Object.entries(response.data).forEach(([i, v]) => {
					if (!this.photoSuggestionsTracksAndDevices[i]) {
						const isplit = i.split(':')
						if (isplit[0] === 'track') {
							const track = this.tracks.find((t) => {
								return t.id === parseInt(isplit[1])
							})
							if (track) {
								this.$set(this.photoSuggestionsTracksAndDevices, i, {
									key: i,
									enabled: true,
									visible: true,
									color: track.color || '#0082c9',
									name: track.file_name,
									id: track.id,
									suggestionCount: v.length,
								})
								photoSuggestions.push(...v)
							} else {
								this.$set(this.photoSuggestionsTracksAndDevices, i, {
									key: i,
									enabled: false,
									visible: false,
								})
							}
						} else if (isplit[0] === 'device') {
							const device = this.devices.find((d) => {
								return d.id === parseInt(isplit[1])
							})
							if (device) {
								photoSuggestions.push(...v)
								this.$set(this.photoSuggestionsTracksAndDevices, i, {
									key: i,
									enabled: true,
									visible: true,
									color: device.color || '#0082c9',
									name: device.user_agent,
									id: device.id,
									suggestionCount: v.length,
								})
							} else {
								this.$set(this.photoSuggestionsTracksAndDevices, i, {
									key: i,
									enabled: false,
									visible: false,
								})
							}
						}
					}
				})
				this.photoSuggestions.unshift(...photoSuggestions.sort((a, b) => {
					if (a.dateTaken < b.dateTaken) {
						return -1
					} else if (a.dateTaken > b.dateTaken) {
						return 1
					}
					return 0
				}))
				this.photoSuggestionsOffset += this.photoSuggestionsLimit
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.photosLoading = false
			})
		},
		onSelectAllPhotoSuggestions() {
			this.photoSuggestionsSelectedIndices = []
			const indices = [...this.photoSuggestions.keys()]
			this.photoSuggestionsSelectedIndices = indices.filter((i) => {
				const tod = this.photoSuggestionsTracksAndDevices[this.photoSuggestions[i].trackOrDeviceId]
				return tod.visible && tod.enabled
			})
		},
		onClearPhotoSuggestionsSelection(indices) {
			if (indices) {
				this.photoSuggestionsSelectedIndices = this.photoSuggestionsSelectedIndices.filter((e) => { return !indices.includes(e) })
			} else {
				this.photoSuggestionsSelectedIndices = []
			}
		},
		onCancelPhotoSuggestions() {
			this.cancelPhotoSuggestions()
		},
		onPhotoSuggestionToggleTrackOrDevice(t) {
			this.photoSuggestionsTracksAndDevices[t.key].enabled = !this.photoSuggestionsTracksAndDevices[t.key].enabled
		},
		onChangePhotoSuggestionsTimezone(tz) {
			this.photoSuggestionsTimezone = tz
			this.photoSuggestionsSelectedIndices = []
			this.photoSuggestions = []
			this.photoSuggestionsTracksAndDevices = {}
			this.photoSuggestionsOffset = 0
			this.getPhotoSuggestions()
		},
		onSavePhotoSuggestionsSelection(indices = null) {
			const toSave = indices || this.photoSuggestionsSelectedIndices
			const paths = toSave.map((i) => { return this.photoSuggestions[i].path })
			const lats = toSave.map((i) => { return this.photoSuggestions[i].lat })
			const lngs = toSave.map((i) => { return this.photoSuggestions[i].lng })
			network.placePhotos(paths, lats, lngs).then((response) => {
				toSave.forEach((i) => {
					this.photos.push(this.photoSuggestions[i])
					this.photoSuggestionsTracksAndDevices[this.photoSuggestions[i].trackOrDeviceId].length -= 1
					this.$set(this.photoSuggestions, i, null)
				})
				this.photoSuggestionsSelectedIndices = this.photoSuggestionsSelectedIndices.filter((e) => {
					return !toSave.includes(e)
				})
				response.data.length === toSave.length
					? showSuccess(n('maps', 'Saved location', 'Saved all %n locations', toSave.length))
					: showInfo(t('maps', 'Saved {r} from {i} locations', { r: response.data.length, i: toSave.length }))
			}).catch((error) => {
				showError(t('maps', 'Failed to save locations'))
				console.error(error)
			})
		},
		// ================ CONTACTS =================
		onContactsClicked() {
			this.contactsEnabled = !this.contactsEnabled
			// get contacts if we don't have them yet
			if (this.contactsEnabled && this.contacts.length === 0) {
				this.getContacts()
			}
			optionsController.saveOptionValues({ contactLayer: this.contactsEnabled ? 'true' : 'false' })
		},
		onContactGroupClicked(groupId) {
			this.contactGroups[groupId].enabled = !this.contactGroups[groupId].enabled
			this.saveContactGroupStates()
		},
		onToggleAllContactGroups() {
			let allEnabled = true
			for (const gid in this.contactGroups) {
				if (!this.contactGroups[gid].enabled) {
					allEnabled = false
					break
				}
			}

			// disable all except if at least one is disabled
			for (const gid in this.contactGroups) {
				this.contactGroups[gid].enabled = !allEnabled
			}
			this.saveContactGroupStates()
		},
		saveContactGroupStates() {
			const newDisabledContactGroups = []
			for (const gid in this.contactGroups) {
				if (!this.contactGroups[gid].enabled) {
					newDisabledContactGroups.push(gid)
				}
			}
			optionsController.saveOptionValues({ jsonDisabledContactGroups: JSON.stringify(newDisabledContactGroups) })
		},
		onZoomAllContactGroups() {
			this.zoomOnContacts(this.contacts)
		},
		onZoomContactGroup(gid) {
			const contactsOfGroup = this.contacts.filter((c) => {
				return ((gid === '0' && c.groupList.length === 0)
					|| c.groupList.includes(gid))
			})
			this.zoomOnContacts(contactsOfGroup)
		},
		onAddContactToMap(c) {
			this.chooseMyMap((map) => {
				network.addContactToMap(c.BOOKID, c.URI, c.UID, map.id, c.FILEID).then((response) => {
					showSuccess(t('maps', 'Contact {contactName} added to map {mapName}', { contactName: c.FN ?? '', mapName: map.name ?? '' }))
				}).catch((error) => {
					console.error(error)
					showError(t('maps', 'Failed to save Contact {contactName} to map {mapName}', { contactName: c.FN ?? '', mapName: map.name ?? '' }))
				})
			})
		},
		onAddAllContactsToMap() {
			this.chooseMyMap((map) => {
				axiosAll(this.contacts.map((c) => {
					return network.addContactToMap(c.BOOKID, c.URI, c.UID, map.id, c.FILEID)
				})).then(axiosSpread((...responses) => {
					showSuccess(t('maps', 'All Contacts added to map {mapName}', { mapName: map.name ?? '' }))
				})).catch((error) => {
					console.error(error)
					showError(t('maps', 'Failed to save all Contacts to map {mapName}', { mapName: map.name ?? '' }))
				})
			})
		},
		onAddContactsGroupToMap(group) {
			const contactsInGroup = this.contacts.filter((c) => {
				if (c.GROUPS) {
					try {
						const cGroups = splitByNonEscapedComma(c.GROUPS)
						for (let i = 0; i < cGroups.length; i++) {
							// if at least in one enabled group
							if (cGroups[i] === group) {
								return true
							}
						}
					} catch (error) {
						console.error(error)
					}
				} else if (!group) {
					// or not grouped and this is enabled
					return true
				}
				return false
			})
			this.chooseMyMap((map) => {
				axiosAll(contactsInGroup.map((c) => {
					return network.addContactToMap(c.BOOKID, c.URI, c.UID, map.id, c.FILEID)
				})).then(axiosSpread((...responses) => {
					showSuccess(t('maps', 'All Contacts added to map {mapName}', { mapName: map.name ?? '' }))
				})).catch((error) => {
					console.error(error)
					showError(t('maps', 'Failed to save all Contacts to map {mapName}', { mapName: map.name ?? '' }))
				})
			})
		},
		zoomOnContacts(contacts) {
			const lats = contacts.map((c) => {
				return geoToLatLng(c.GEO)[0]
			})
			const lons = contacts.map((c) => {
				return geoToLatLng(c.GEO)[1]
			})
			if (lats && lons) {
				const minLat = Math.min(...lats)
				const maxLat = Math.max(...lats)
				const minLon = Math.min(...lons)
				const maxLon = Math.max(...lons)
				this.$refs.map.fitBounds(L.latLngBounds([minLat, minLon], [maxLat, maxLon]), { padding: [30, 30] })
			}
		},
		getContacts() {
			this.contacts = []
			if (!this.contactsEnabled) {
				return
			}
			this.contactsLoading = true
			this.disabledContactGroups = []
			if ('jsonDisabledContactGroups' in this.optionValues) {
				try {
					this.disabledContactGroups = JSON.parse(this.optionValues.jsonDisabledContactGroups)
				} catch (error) {
					console.error(error)
				}
			}

			network.getContacts(this.myMapId, this.token).then((response) => {
				this.contacts = response.data
				this.buildContactGroups()
			}).catch((error) => {
				showError(
					t('maps', 'Failed to load contacts')
					+ ': ' + error.response?.request?.responseText,
				)
			}).then(() => {
				this.contactsLoading = false
			})
		},
		buildContactGroups() {
			this.contactGroups = {}
			const notGroupedId = '0'
			this.$set(this.contactGroups, notGroupedId, {
				name: t('maps', 'Not grouped'),
				counter: 0,
				enabled: !this.disabledContactGroups.includes(notGroupedId),
			})
			this.contacts.forEach((c) => {
				c.groupList = []
				if (c.GROUPS) {
					try {
						const cGroups = splitByNonEscapedComma(c.GROUPS)
						if (cGroups.length > 0) {
							cGroups.forEach((g) => {
								c.groupList.push(g)
								if (this.contactGroups[g]) {
									this.contactGroups[g].counter++
								} else {
									this.$set(this.contactGroups, g, {
										name: g,
										counter: 1,
										enabled: !this.disabledContactGroups.includes(g),
									})
								}
							})
						} else {
							this.contactGroups[notGroupedId].counter++
						}
					} catch (error) {
						console.error(error)
					}
				} else {
					this.contactGroups[notGroupedId].counter++
				}
			})
		},
		onContactAddressDelete(contact, save = true) {
			network.deleteContactAddress(contact.BOOKID, contact.URI, contact.UID, contact.ADR, contact.GEO, contact.FILEID || null, this.myMapId).then((response) => {
				if (save) {
					this.saveAction({
						type: 'contactDelete',
						content: contact,
					})
				}
				this.getContacts()
			}).catch((error) => {
				console.error(error)
			})
		},
		onContactPlace(e, save = true) {
			network.placeContact(e.contact.BOOKID, e.contact.URI,
				e.contact.UID, e.latLng.lat, e.latLng.lng,
				e.address || null, e.addressType, e.FILEID || null, this.myMapId,
			).then((response) => {
				if (save) {
					this.saveAction({
						type: 'contactPlace',
						content: e,
					})
				}
				this.getContacts()
			}).catch((error) => {
				console.error(error)
			})
		},
		cancelContactDelete(action) {
			// here we have to generate all placement parameters
			const latLng = geoToLatLng(action.content.GEO)
			const split = action.content.ADR.split(';')
			const address = {
				attraction: '',
				house_number: '',
				road: split[2],
				postcode: split[5],
				city: split[3],
				state: split[4],
				country: split[6],
			}
			this.onContactPlace({
				contact: action.content,
				latLng: { lat: latLng[0], lng: latLng[1] },
				address,
				addressType: action.content.ADRTYPE,
			}, false)
		},
		redoContactDelete(action) {
			this.onContactAddressDelete(action.content, false)
		},
		cancelContactPlace(action) {
			const a = action.content.address
			let city = a.village || a.town || a.city || ''
			city = city.replace(/\s+/g, ' ').trim()
			action.content.contact.ADR = ';;'
				+ (a.road || '') + ';'
				+ (city || '') + ';'
				+ (a.state || '') + ';'
				+ (a.postcode || '') + ';'
				+ (a.country || '')
			this.onContactAddressDelete(action.content.contact, false)
		},
		redoContactPlace(action) {
			this.onContactPlace(action.content, false)
		},
		// ================ FAVORITES =================
		onFavoritesClicked() {
			this.favoritesEnabled = !this.favoritesEnabled
			// get favorites if we don't have them yet
			if (this.favoritesEnabled && Object.keys(this.favorites).length === 0) {
				this.getFavorites()
			}
			optionsController.saveOptionValues({ favoritesEnabled: this.favoritesEnabled ? 'true' : 'false' })
		},
		getFavorites() {
			this.favorites = {}
			if (!this.favoritesEnabled) {
				return
			}
			this.favoritesLoading = true
			this.favorites = {}
			this.favoriteCategoryTokens = {}
			network.getFavorites(this.myMapId, getToken()).then((response) => {
				response.data.forEach((f) => {
					if (!f.category) {
						f.category = t('maps', 'Personal')
					}
					f.selected = false
					this.$set(this.favorites, f.id, f)
				})
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.favoritesLoading = false
			})
			network.getSharedFavoriteCategories(this.myMapId, getToken()).then((response) => {
				this.favoriteCategoryTokens = {}
				response.data.forEach((s) => {
					this.favoriteCategoryTokens[s.category] = s.token
					if (!(this.myMapId === null || this.myMapId === '')) {
						network.getFavoritesByToken(s.token).then((response) => {
							response.data.favorites.forEach((f) => {
								f.id = s.token + f.id
								f.selected = false
								this.$set(this.favorites, f.id, f)
							})
						}).catch((error) => {
							console.error(error)
						}).then(() => {
							this.favoritesLoading = false
						})
					}
				})
			})
		},
		onFavoriteCategoryClicked(catid) {
			if (this.disabledFavoriteCategories.includes(catid)) {
				const i = this.disabledFavoriteCategories.indexOf(catid)
				this.disabledFavoriteCategories.splice(i, 1)
			} else {
				this.disabledFavoriteCategories.push(catid)
			}
			optionsController.saveOptionValues({ jsonDisabledFavoriteCategories: JSON.stringify(this.disabledFavoriteCategories) })
		},
		onToggleAllFavoriteCategories() {
			let allEnabled = true
			for (const catid in this.favoriteCategories) {
				if (this.disabledFavoriteCategories.includes(catid)) {
					allEnabled = false
					break
				}
			}

			if (allEnabled) {
				for (const catid in this.favoriteCategories) {
					this.disabledFavoriteCategories.push(catid)
				}
			} else {
				for (const catid in this.favoriteCategories) {
					if (this.disabledFavoriteCategories.includes(catid)) {
						const i = this.disabledFavoriteCategories.indexOf(catid)
						this.disabledFavoriteCategories.splice(i, 1)
					}
				}
			}
			optionsController.saveOptionValues({ jsonDisabledFavoriteCategories: JSON.stringify(this.disabledFavoriteCategories) })
		},
		onFavoriteCategoryShareChange(catid, checked) {
			if (checked) {
				network.shareFavoriteCategory(catid, this.myMapId).then((response) => {
					this.$set(this.favoriteCategoryTokens, catid, response.data.token)
				}).catch((error) => {
					console.error(error)
				})
			} else {
				network.unshareFavoriteCategory(catid, this.myMapId).then((response) => {
					this.$delete(this.favoriteCategoryTokens, catid)
				}).catch((error) => {
					console.error(error)
				})
			}
		},
		onZoomAllFavorites() {
			this.zoomOnFavorites(Object.values(this.favorites))
		},
		onZoomFavoriteCategory(catid) {
			const favoritesOfCategory = Object.values(this.favorites).filter((f) => {
				return f.category === catid
			})
			this.zoomOnFavorites(favoritesOfCategory)
		},
		zoomOnFavorites(favorites) {
			const lats = favorites.map((f) => {
				return f.lat
			})
			const lons = favorites.map((f) => {
				return f.lng
			})
			if (lats && lons) {
				const minLat = Math.min(...lats)
				const maxLat = Math.max(...lats)
				const minLon = Math.min(...lons)
				const maxLon = Math.max(...lons)
				this.$refs.map.fitBounds(L.latLngBounds([minLat, minLon], [maxLat, maxLon]), { padding: [30, 30] })
			}
		},
		onFavoriteClick(f) {
			this.deselectAll()
			// select
			this.favorites[f.id].selected = true
			this.openSidebar(null, 'favorite', f.name)
			window.OCA.Files.Sidebar.setActiveTab('favorite')
			this.selectedFavorite = f
		},
		onFavoriteEdit(f, save = true) {
			network.editFavorite(f.id, f.name, f.category, f.comment, f.lat, f.lng, this.myMapId, getToken()).then((response) => {
				if (save) {
					this.saveAction({
						type: 'favoriteEdit',
						before: { ...this.favorites[f.id] },
						after: f,
					})
				}
				this.favorites[f.id].name = f.name
				this.favorites[f.id].category = f.category
				this.favorites[f.id].comment = f.comment
				this.favorites[f.id].lat = f.lat
				this.favorites[f.id].lng = f.lng
				this.lastUsedFavoriteCategory = f.category
				showSuccess(t('maps', 'Favorite {name} was saved', { name: f.name ? f.name : '' }))
			}).catch((error) => {
				console.error(error)
			})
		},
		onFavoriteDelete(favid, save = true) {
			network.deleteFavorite(favid, this.myMapId, getToken()).then((response) => {
				if (save) {
					this.saveAction({
						type: 'favoriteDelete',
						favorites: [{ ...this.favorites[favid] }],
					})
				}
				this.selectedFavorite = null
				this.closeSidebar()
				showError(t('maps', 'Favorite was deleted'))
				this.$delete(this.favorites, favid)
			}).catch((error) => {
				console.error(error)
			})
		},
		onFavoritesDelete(favids, save = true) {
			network.deleteFavorites(favids, this.myMapId, getToken()).then((response) => {
				if (save) {
					const deleted = favids.map((favid) => {
						return { ...this.favorites[favid] }
					})
					this.saveAction({
						type: 'favoriteDelete',
						favorites: deleted,
					})
				}
				favids.forEach((favid) => {
					this.$delete(this.favorites, favid)
				})
			}).catch((error) => {
				console.error(error)
			})
		},
		onDeleteFavoriteCategory(catid) {
			const favIds = Object.keys(this.favorites).filter((favid) => {
				return this.favorites[favid].category === catid
			})
			this.onFavoritesDelete(favIds)
		},
		onExportFavorites() {
			const catIds = Object.keys(this.favoriteCategories).filter((catid) => {
				return this.favoriteCategories[catid].enabled
			})
			this.exportFavorites(catIds)
		},
		onExportFavoriteCategory(catid) {
			this.exportFavorites([catid])
		},
		exportFavorites(catIdList) {
			network.exportFavorites(catIdList, this.myMapId).then((response) => {
				showSuccess(t('maps', 'Favorites exported in {path}', { path: response.data }))
			}).catch((error) => {
				console.error(error)
			})
		},
		onImportFavorites() {
			OC.dialogs.filepicker(
				t('maps', 'Import favorites from GeoJSON (Google Maps), gpx (OsmAnd, Nextcloud Maps) or kmz/kml (F-Droid Maps, Maps.me, Marble)'),
				(targetPath) => {
					this.importFavorites(targetPath)
				},
				false,
				['application/gpx+xml', 'application/vnd.google-earth.kmz', 'application/vnd.google-earth.kml+xml', 'application/json', 'application/geo+json'],
				true,
			)
		},
		importFavorites(path) {
			network.importFavorites(path, this.myMapId).then((response) => {
				this.getFavorites()
			}).catch((error) => {
				console.error(error)
			})
		},
		onAddressFavoriteAdd(obj) {
			const name = obj.name
				? obj.name
				: obj.address
					? obj.address.attraction
					|| obj.address.road
					|| obj.address.city_district
					: null
			this.addFavorite(obj.latLng, name, null, obj.formattedAddress || null)
		},
		onAddFavoriteToMap(f) {
			this.chooseMyMap((map) => {
				network.addFavorite(f.lat, f.lng, f.name, f.category, f.comment, f.extensions, map.id).then((response) => {
					showSuccess(t('maps', 'Favorite {favoriteName} added to map {mapName}', { favoriteName: f.name ?? '', mapName: map.name ?? '' }))
				}).catch((error) => {
					console.error(error)
					showError(t('maps', 'Failed to save Favorite {favoriteName} to map {mapName}', { favoriteName: f.name ?? '', mapName: map.name ?? '' }))
				})
			})
		},
		onAddFavoriteCategoryToMap(catid) {
			if (this.favoriteCategories[catid].token) {
				this.chooseMyMap((map) => {
					network.addSharedFavoriteCategoryToMap(catid, map.id, optionsController.myMapId).then((response) => {
						showSuccess(t('maps', 'Favorite category {favoriteName} linked to map {mapName}', { favoriteName: this.favoriteCategories[catid].category ?? '', mapName: map.name ?? '' }))
					}).catch((error) => {
						console.error(error)
						showError(t('maps', 'Failed to link Favorite category {favoriteName} to map {mapName}', { favoriteName: this.favoriteCategories[catid].category ?? '', mapName: map.name ?? '' }))
					})
				})
			} else {
				this.chooseMyMap((map) => {
					network.addFavorites(Object.values(this.favorites).filter((f) => f.category === catid), map.id).then((responses) => {
						showSuccess(t('maps', 'Favorite category {favoriteName} copied to map {mapName}', { favoriteName: this.favoriteCategories[catid].category ?? '', mapName: map.name ?? '' }))
					}).catch(errors => {
						showError(t('maps', 'Failed to copy Favorite category {favoriteName} to map {mapName}', { favoriteName: this.favoriteCategories[catid].category ?? '', mapName: map.name ?? '' }))
					})
				})
			}
		},
		onDeleteFavoriteCategoryFromMap(catid) {
			network.deleteSharedFavoriteCategoryFromMap(catid, optionsController.myMapId).then((response) => {
				const favIds = Object.keys(this.favorites).filter((favid) => {
					return this.favorites[favid].category === catid
				})
				favIds.forEach((favid) => {
					this.$delete(this.favorites, favid)
				})
				showSuccess(t('maps', 'Favorite category {favoriteName} unlinked from map', { favoriteName: catid ?? '' }))
			}).catch((error) => {
				console.error(error)
				showError(t('maps', 'Failed to remove Favorite category {favoriteName} from map', { favoriteName: catid ?? '' }))
			})
		},
		onFavoriteAdd(latLng) {
			this.addFavorite(latLng, null, null, null, null, true, true)
		},
		addFavorite(latLng, name = null, category = null, comment = null, extensions = null, save = true, openSidebar = false) {
			this.favoritesLoading = true
			if (category === null) {
				category = this.lastUsedFavoriteCategory
			}
			return network.addFavorite(latLng.lat, latLng.lng, name, category, comment, extensions, this.myMapId, getToken()).then((response) => {
				const fav = response.data
				if (!fav.category) {
					fav.category = t('maps', 'Personal')
				}
				if (save) {
					this.saveAction({
						type: 'favoriteAdd',
						favorite: { ...fav },
					})
				}
				this.$set(this.favorites, fav.id, fav)
				if (openSidebar) {
					this.selectedFavorite = this.favorites[fav.id]
					window.OCA.Files.Sidebar.setActiveTab('favorite')
					this.openSidebar(null, 'favorite', fav.name)
				}
				if (this.sliderEnabled) {
					if (this.sliderStart > fav.date_created) {
						this.sliderStart = this.minDataTimestamp
					}
					if (this.sliderEnd < fav.date_created) {
						this.sliderEnd = this.maxDataTimestamp
					}
				}
				this.favoritesLoading = false
				return fav.id
			}).catch((error) => {
				this.favoritesLoading = false
				console.error(error)
			})
		},
		onRenameFavoriteCategory(e, save = true) {
			network.renameFavoriteCategory([e.old], e.new, this.myMapId, getToken()).then((response) => {
				if (save) {
					this.saveAction({
						type: 'favoriteRenameCategory',
						old: e.old,
						new: e.new,
					})
				}
				Object.keys(this.favorites).forEach((favid) => {
					if (this.favorites[favid].category === e.old) {
						this.favorites[favid].category = e.new
					}
				})
				// update share token
				if (this.favoriteCategoryTokens[e.old]) {
					this.favoriteCategoryTokens[e.new] = this.favoriteCategoryTokens[e.old]
					delete this.favoriteCategoryTokens[e.old]
				}
			}).catch((error) => {
				console.error(error)
			})
		},
		cancelFavoriteAdd(action) {
			this.onFavoritesDelete([action.favorite.id], false)
		},
		cancelFavoriteEdit(action) {
			this.onFavoriteEdit(action.before, false)
		},
		async cancelFavoriteDelete(action) {
			for (let i = 0; i < action.favorites.length; i++) {
				const f = action.favorites[i]
				const newFavId = await this.addFavorite(L.latLng(f.lat, f.lng), f.name, f.category, f.comment, f.extensions, false)
				this.updateActionFavoriteId(f.id, newFavId)
			}
		},
		cancelFavoriteRenameCategory(action) {
			this.onRenameFavoriteCategory({ old: action.new, new: action.old }, false)
		},
		async redoFavoriteAdd(action) {
			const f = action.favorite
			const newFavId = await this.addFavorite(L.latLng(f.lat, f.lng), f.name, f.category, f.comment, f.extensions, false)
			this.updateActionFavoriteId(action.favorite.id, newFavId)
		},
		redoFavoriteEdit(action) {
			this.onFavoriteEdit(action.after, false)
		},
		redoFavoriteDelete(action) {
			const favIds = action.favorites.map((f) => {
				return f.id
			})
			this.onFavoritesDelete(favIds, false)
		},
		redoFavoriteRenameCategory(action) {
			this.onRenameFavoriteCategory({ old: action.old, new: action.new }, false)
		},
		onNavigationAddFavorite(category = null) {
			if (category !== null) {
				this.lastUsedFavoriteCategory = category
			}
			this.addingFavorite = !this.addingFavorite
			if (this.addingFavorite) {
				showInfo(t('maps', 'Click on the map to add a favorite, press Esc to cancel'))
			}
		},
		// when a favorite is created by canceling deletion or redoing creation
		// we need to update the ids in the action history
		updateActionFavoriteId(oldId, newId) {
			const allActions = this.lastCanceledActions.concat(this.lastActions)
			allActions.forEach((a) => {
				if (a.type === 'favoriteAdd' && a.favorite.id === oldId) {
					a.favorite.id = newId
				} else if (a.type === 'favoriteEdit' && a.before.id === oldId) {
					a.before.id = newId
					a.after.id = newId
				} else if (a.type === 'favoriteDelete') {
					a.favorites.forEach((f) => {
						if (f.id === oldId) {
							f.id = newId
						}
					})
				}
			})
		},
		// tracks
		onTracksClicked() {
			this.tracksEnabled = !this.tracksEnabled
			// get tracks if we don't have them yet
			if (this.tracksEnabled && this.tracks.length === 0) {
				this.getTracks()
			}
			optionsController.saveOptionValues({ tracksEnabled: this.tracksEnabled ? 'true' : 'false' })
		},
		getTracks() {
			this.tracks = []
			if (!this.tracksEnabled) {
				return
			}
			this.tracksLoading = true
			network.getTracks(this.myMapId, this.token).then((response) => {
				this.tracks = response.data.map((track) => {
					if (track.metadata) {
						try {
							track.metadata = JSON.parse(track.metadata)
						} catch (error) {
							console.error('Failed to parse track metadata')
						}
					}
					return {
						...track,
						loading: false,
						enabled: false,
						selected: false,
					}
				})
				this.tracks.forEach((track) => {
					if (optionsController.enabledTracks.includes(track.id)) {
						this.getTrack(track, true, false)
					}
				})
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.tracksLoading = false
			})
		},
		onTrackAdded(track) {
			if (track.metadata) {
				try {
					track.metadata = JSON.parse(track.metadata)
				} catch (error) {
					console.error('Failed to parse track metadata')
				}
			}
			track = this.tracks.push({
				...track,
				loading: false,
				enabled: false,
				selected: false,
			}) - 1
			this.getTrack(this.tracks[track], true, true, false)
		},
		onNavTrackClicked(track) {
			if (track.enabled) {
				this.disableTrack(track)
			} else {
				this.enableTrack(track, true)
			}
		},
		onSearchEnableTrack(track) {
			this.enableTrack(track, true)
		},
		enableTrack(track, zoom = false) {
			if (track.metadata && track.data) {
				track.enabled = true
				this.saveEnabledTracks()
				if (zoom) {
					this.$refs.map.zoomOnTrack(track)
				}
			} else {
				this.getTrack(track, true, true, zoom)
			}
		},
		disableTrack(track) {
			track.enabled = false
			this.saveEnabledTracks()
		},
		getTrack(track, enable = false, save = true, zoom = false) {
			track.loading = true
			network.getTrack(track.id, this.myMapId, false, this.token).then((response) => {
				if (!track.metadata) {
					try {
						track.metadata = JSON.parse(response.data.metadata)
					} catch (error) {
						console.error('Failed to parse track metadata')
					}
				}
				track.data = processGpx(response.data.content)
				if (enable) {
					track.enabled = true
				}
				if (save) {
					this.saveEnabledTracks()
				}
				if (zoom) {
					this.$refs.map.zoomOnTrack(track)
				}
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				track.loading = false
			})
		},
		saveEnabledTracks() {
			const trackStringList = this.tracks
				.filter((track) => { return track.enabled })
				.map((track) => { return track.id })
				.join('|')
			optionsController.saveOptionValues({ enabledTracks: trackStringList })
		},
		onChangeTrackColorClicked(track) {
			console.debug('chchchc')
			console.debug(track)
			this.$refs.tracksNavigation.changeTrackColor(track)
		},
		onChangeTrackColor(e) {
			e.track.color = e.color
			network.editTrack(e.track.id, e.color, this.myMapId, this.token).then((response) => {
				console.debug(response.data)
			}).catch((error) => {
				console.error(error)
			})
		},
		onTrackZoom(track) {
			this.$refs.map.zoomOnTrack(track)
			if (track.metadata && this.sliderEnabled) {
				if (track.metadata.begin) {
					this.sliderStart = track.metadata.begin
				}
				if (track.metadata.end) {
					this.sliderEnd = track.metadata.end
				}
			}
		},
		onTrackElevation(track) {
			this.$refs.map.displayElevation(track)
		},
		onTrackClick(track) {
			this.deselectAll()
			// select
			track.selected = true
			this.openSidebar(track.path, 'track', track.name)
			window.OCA.Files.Sidebar.setActiveTab('maps-track-metadata')
			this.selectedTrack = track
		},
		// devices
		onDevicesClicked() {
			this.devicesEnabled = !this.devicesEnabled
			// get devices if we don't have them yet
			if (this.devicesEnabled && this.devices.length === 0) {
				this.getDevices()
			}
			optionsController.saveOptionValues({ devicesEnabled: this.devicesEnabled ? 'true' : 'false' })
		},
		getDevices() {
			this.devices = []
			if (!this.devicesEnabled) {
				return
			}
			this.devicesLoading = true
			network.getDevices(this.myMapId).then((response) => {
				this.devices = response.data.map((device) => {
					return {
						...device,
						loading: false,
						enabled: false,
						historyEnabled: false, // optionsController.enabledDeviceLines.includes(device.id),
					}
				})
				this.devices.forEach((device) => {
					if (optionsController.enabledDevices.includes(device.id)) {
						this.getDevice(device, true, false)
					}
				})
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.devicesLoading = false
			})
		},
		onNavDeviceClicked(device) {
			if (device.enabled) {
				this.disableDevice(device)
			} else {
				this.enableDevice(device, false)
			}
		},
		onSearchEnableDevice(device) {
			this.enableDevice(device, true)
		},
		enableDevice(device, zoom = false) {
			if (device.points) {
				device.enabled = true
				this.saveEnabledDevices()
				if (zoom) {
					this.$refs.map.zoomOnDevice(device)
				}
			} else {
				this.getDevice(device, true, true, zoom)
			}
		},
		disableDevice(device) {
			device.enabled = false
			device.historyEnabled = false
			this.saveEnabledDevices()
		},
		getDevice(device, enable = false, save = true, zoom = false) {
			device.loading = true
			network.getDevice(device.id, this.myMapId, 100000, device.points?.length || 0, device.tokens).then((response) => {
				// There are too many points making it responsiv crashes most browsers
				// this.$set(device, 'points', response.data /* .sort((p1, p2) => (p1.timestamp || 0) - (p2.timestamp || 0)) */)
				if (device.points) {
					device.points = response.data.concat(device.points)
				} else {
					device.points = response.data
				}
				if (response.data.length >= 100000) {
					this.getDevice(device, false, false, false)
				}
				if (enable) {
					device.enabled = true
				}
				if (save) {
					this.saveEnabledDevices()
				}
				if (zoom) {
					this.$refs.map.zoomOnDevice(device)
				}
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				device.loading = false
			})
		},
		saveEnabledDevices() {
			const deviceStringList = this.devices
				.filter((d) => { return d.enabled })
				.map((d) => { return d.id })
				.join('|')
			optionsController.saveOptionValues({ enabledDevices: deviceStringList })
		},
		saveEnabledDeviceLines() {
			const stringList = this.devices
				.filter(d => d.historyEnabled)
				.map(d => d.id)
				.join('|')
			optionsController.saveOptionValues({ enabledDeviceLines: stringList })
		},
		onChangeDeviceColorClicked(device) {
			this.$refs.devicesNavigation.changeDeviceColor(device)
		},
		onChangeDeviceColor(e) {
			e.device.color = e.color
			network.editDevice(e.device.id, null, e.color, this.myMapId).then((response) => {
				console.debug(response.data)
			}).catch((error) => {
				console.error(error)
			})
		},
		onAddTrackToMap(track) {
			this.chooseMyMap((map) => {
				try {
					network.copyByPath(track.path, (map.path + '/' + track.file_name).replace('//', '/'))
					showSuccess(t('maps', 'Track {trackName} added to map {mapName}',
						{
							trackName: track.name ?? '',
							mapName: map.name ?? '',
						}))

				} catch (error) {
					console.error(error)
					showError(
						t('maps', 'Failed to save track {trackName} to map {mapName}', {
							trackName: track.name ?? '',
							mapName: map.name ?? '',
						}))

				}
			})
		},
		onDeviceZoom(device) {
			this.$refs.map.zoomOnDevice(device)
		},
		onExportDevice(device) {
			this.exportingDevices = true
			network.exportDevices(
				[device.id],
				false,
				this.sliderEnabled ? this.sliderStart : null,
				this.sliderEnabled ? this.sliderEnd : null,
			).then((response) => {
				showSuccess(t('maps', 'Devices exported in {path}', { path: response.data }))
			}).catch((error) => {
				console.error(error)
				showError(t('maps', 'Failed to export devices') + ': ' + error.data)
			}).then(() => {
				this.exportingDevices = false
			})
		},
		onExportAllDevices() {
			this.exportingDevices = true
			const deviceIds = this.devices.map(d => d.id)
			network.exportDevices(deviceIds, true).then((response) => {
				showSuccess(t('maps', 'Devices exported in {path}', { path: response.data }))
			}).catch((error) => {
				console.error(error)
				showError(t('maps', 'Failed to export devices') + ': ' + error.data)
			}).then(() => {
				this.exportingDevices = false
			})
		},
		onDeleteDevice(device) {
			network.deleteDevice(device.id).then((response) => {
				const i = this.devices.findIndex(d => d.id === device.id)
				if (i !== -1) {
					this.devices.splice(i, 1)
				}
			}).catch((error) => {
				console.error(error)
				showError(t('maps', 'Failed to delete device') + ': ' + error.data)
			})
		},
		async onAddDeviceToMap(device) {
			const timestampFrom = this.sliderEnabled ? this.sliderStart : Number.MIN_SAFE_INTEGER
			const timestampTo = this.sliderEnabled ? this.sliderEnd : Number.MAX_SAFE_INTEGER
			device.loading = true
			await network.shareDevice(device.id, timestampFrom, timestampTo).then((response) => {
				const share = response.data
				this.chooseMyMap((map) => {
					network.addSharedDeviceToMap(share.token, map.id).then((response) => {
						showSuccess(t('maps', 'Device {deviceName} linked to map {mapName}', { favoriteName: device.name ?? '', mapName: map.name ?? '' }))
					}).catch((error) => {
						console.error(error)
						showError(t('maps', 'Failed to link Device to map {mapName}', { mapName: map.name ?? '' }))
					})
				})
			}).catch((error) => {
				console.error(error)
				showError(t('maps', 'Failed to share Device'))
			})
			device.loading = false

		},
		async onToggleDeviceHistory(device) {
			device.historyEnabled = !device.historyEnabled
			this.saveEnabledDeviceLines()
		},
		onRefreshPositions() {
			this.devices.filter(d => d.enabled).forEach((d) => {
				this.refreshPositions(d)
			})
		},
		refreshPositions(device) {
			network.updateDevicePositions(device).then((response) => {
				if (device.points) {
					device.points.push(...response.data)
				} else {
					device.points = response.data
				}
			}).catch((error) => {
				console.error(error)
			})
		},
		onToggleAllDevices() {
			// hide all only if all are enabled
			// otherwise, show all
			const oneDisabled = this.devices.find(d => d.enabled === false)
			if (oneDisabled) {
				this.devices.forEach((device) => {
					if (device.points) {
						device.enabled = true
					} else {
						this.getDevice(device, true, true)
					}
				})
			} else {
				this.devices.forEach((d) => {
					d.enabled = false
				})
			}
			this.saveEnabledDevices()
		},
		onImportDevices() {
			OC.dialogs.filepicker(
				t('maps', 'Import devices from gpx (Nextcloud Maps) or kml/kmz (Google Timeline) file'),
				(targetPath) => {
					this.importDevices(targetPath)
				},
				false,
				['application/gpx+xml', 'application/vnd.google-earth.kmz', 'application/vnd.google-earth.kml+xml'],
				true,
			)
		},
		importDevices(path) {
			this.importingDevices = true
			network.importDevices(path).then((response) => {
				showSuccess(t('maps', '{nb} devices imported from {path}', { nb: response.data, path }))
				this.getDevices()
			}).catch((error) => {
				console.error(error)
				showError(t('maps', 'Failed to import devices') + ': ' + error.data)
			}).then(() => {
				this.importingDevices = false
			})
		},
		// MyMaps
		getMyMaps() {
			if (!this.myMapsEnabled) {
				return
			}
			this.myMapsLoading = true
			this.myMaps = [
				{
					name: t('maps', 'Default'),
					id: null,
					enabled: this.myMapId === null,
					isDeletable: false,
					isShareable: false,
				},
			]
			network.getMyMaps().then((response) => {
				this.myMaps = [
					{
						name: t('maps', 'Default'),
						id: null,
						enabled: this.myMapId === null,
						isDeletable: false,
						isShareable: false,
					},
				]
			    this.myMaps.push(
				    ...response.data.map((myMap) => {
				        return {
				            ...myMap,
							enabled: this.myMapId === myMap.id,
						}
				    }))
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.myMapsLoading = false
			})
		},
		chooseMyMap(callback) {
			const that = this
			const firstCallBack = (path) => {
				const p = (path === '' ? '/' : path)
				const map = that.myMaps.find((m) => { return m.path === p })
				if (map) {
					callback(map)
				} else {
					showInfo(t('maps', 'Folder is not a map'))
					const fileClient = OC.Files.getClient()
					fileClient.getFileInfo(path).then((status, fileInfo) => {
						const map = {
							id: fileInfo.id,
							path: fileInfo.name,
							name: fileInfo.basename,
							fileInfo,
						}
						callback(map)
					})
				}
			}
			OC.dialogs.filepicker(
				t('maps', 'Choose directory of pictures to place'),
				(t) => firstCallBack(t),
				false,
				'httpd/unix-directory',
				true,
				OC.dialogs.FILEPICKER_TYPE_CHOOSE,
			)
		},
		onMyMapsClicked() {
			this.myMapsEnabled = !this.myMapsEnabled
			// get tracks if we don't have them yet
			if (this.myMapsEnabled && this.myMaps.length === 0) {
				this.getMyMaps()
			}
			optionsController.saveOptionValues({ myMapsEnabled: this.myMapsEnabled ? 'true' : 'false' })
		},
		onMyMapClicked(myMap) {
			if (this.showSidebar) {
				this.openSidebar(myMap.path, 'maps', myMap.name)
			}
		    if (!this.myMapsLoading) {
				this.myMapsLoading = true
		        this.loadMap(myMap)
			}
			this.myMapsLoading = false
		},
		onAddMyMap(name) {
		    this.myMapsLoading = true
		    network.addMyMap(name).then((response) => {
				this.myMaps.push({
					...response.data,
					enabled: this.myMapId === response.data.id,
				})
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.myMapsLoading = false
			})
		},
		onChangeMyMapColor(myMap) {

		},
		onRenameMyMap({ id, newName }) {
			this.myMapsLoading = true
			network.renameMyMap(id, newName).then((response) => {
				const index = this.myMaps.findIndex((myMap) => myMap.id === id)
				this.myMaps[index] = response.data
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.myMapsLoading = false
			})
		},
		onDeleteMyMap(id) {
			this.myMapsLoading = true
			network.deleteMyMap(id).then((response) => {
				const index = this.myMaps.findIndex((myMap) => myMap.id === id)
				this.myMaps.splice(index, 1)
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.myMapsLoading = false
			})
		},
		onShareMyMap(myMap) {
			window.OCA.Files.Sidebar.setActiveTab('sharing')
			this.openSidebar(myMap.path, 'maps', myMap.name)
		},
		loadMap(myMap) {
			if (this.photoSuggestions) {
				this.cancelPhotoSuggestions()
			}
			this.myMapId = myMap.id
			optionsController.myMapId = myMap.id
			const that = this
			optionsController.restoreOptions(function() {
				that.activeLayerId = optionsController.tileLayer
				that.mapBounds = optionsController.bounds
				if (!that.myMapId) {
					that.devicesEnabled = optionsController.devicesEnabled
				}
			})
			// fixme set new tilelayer and mapbounds in the map component
			let newurl
			if (this.myMapId === null) {
				newurl = window.location.href.split('/apps/maps')[0].concat('/apps/maps/')

			} else {
				newurl = window.location.href.split('/apps/maps')[0].concat('/apps/maps/m/', this.myMapId)
			}
			window.history.pushState({ id: this.myMapId }, myMap.name, newurl)
			this.getContacts()
			this.getPhotos()

			this.getFavorites()
			this.getTracks()
			this.getDevices()
			this.getMyMaps()
			if (optionsController.optionValues.trackMe === 'true') {
				this.sendPositionLoop()
			}
		},
	},
}
</script>

<style lang="scss" scoped>
.content-buttons {
	z-index: 99999;
	position: absolute !important;
	top: 8px;
	right: 8px;
}

#app-content-wrapper {
	display: flex;
	height: 100%;
}

::v-deep .favoriteMarker {
	height: 18px !important;
	width: 18px !important;
	background-size: 18px 18px;
	margin: auto;
}

::v-deep .favoriteClusterMarker {
	height: 27px !important;
	width: 27px !important;
	background-size: 27px 27px;
	margin: auto;
}

::v-deep .navigationFavoriteMarker {
	background: url('../../img/star-white.svg') no-repeat 50% 50%;
	border-radius: 50%;
}

::v-deep .navigationFavoriteMarkerDark {
	background: url('../../img/star-black.svg') no-repeat 50% 50%;
	border-radius: 50%;
}

</style>
