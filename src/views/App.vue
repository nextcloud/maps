<template>
	<Content app-name="maps">
		<MapsNavigation
			@toggle-trackme="onToggleTrackme"
			@toggle-slider="sliderEnabled = $event">
			<template #items>
				<AppNavigationFavoritesItem
					:enabled="favoritesEnabled"
					:loading="favoritesLoading"
					:favorites="favorites"
					:categories="favoriteCategories"
					:draggable="favoritesDraggable"
					@favorites-clicked="onFavoritesClicked"
					@category-clicked="onFavoriteCategoryClicked"
					@rename-category="onRenameFavoriteCategory"
					@zoom-all-categories="onZoomAllFavorites"
					@zoom-category="onZoomFavoriteCategory"
					@export-category="onExportFavoriteCategory"
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
					@toggle-all-groups="onToggleAllContactGroups" />
				<AppNavigationPhotosItem
					:enabled="photosEnabled"
					:loading="photosLoading"
					:photos="photos"
					:draggable="photosDraggable"
					@photos-clicked="onPhotosClicked"
					@cancel-clicked="cancelPhotoMove"
					@redo-clicked="redoPhotoMove"
					@draggable-clicked="photosDraggable = !photosDraggable" />
				<AppNavigationTracksItem
					ref="tracksNavigation"
					:enabled="tracksEnabled"
					:loading="tracksLoading"
					:tracks="tracks"
					@zoom="onTrackZoom"
					@elevation="onTrackElevation"
					@track-clicked="onTrackClicked"
					@tracks-clicked="onTracksClicked"
					@color="onChangeTrackColor" />
				<AppNavigationDevicesItem
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
					@toggle-history="onToggleDeviceHistory"
					@toggle-all="onToggleAllDevices"
					@color="onChangeDeviceColor"
					@device-clicked="onDeviceClicked"
					@devices-clicked="onDevicesClicked" />
			</template>
		</MapsNavigation>
		<AppContent>
			<div id="app-content-wrapper">
				<Map
					ref="map"
					:search-data="searchData"
					:routing-search-data="routingSearchData"
					:favorites="displayedFavorites"
					:favorite-categories="favoriteCategories"
					:favorites-enabled="favoritesEnabled"
					:favorites-draggable="favoritesDraggable"
					:photos="displayedPhotos"
					:photos-enabled="photosEnabled"
					:photos-draggable="photosDraggable"
					:contacts="contacts"
					:contact-groups="contactGroups"
					:contacts-enabled="contactsEnabled"
					:tracks="displayedTracks"
					:tracks-enabled="tracksEnabled"
					:devices="displayedDevices"
					:devices-enabled="devicesEnabled"
					:slider-enabled="sliderEnabled"
					:min-data-timestamp="minDataTimestamp"
					:max-data-timestamp="maxDataTimestamp"
					:loading="mapLoading"
					:last-actions="lastActions"
					:last-canceled-actions="lastCanceledActions"
					@click-favorite="onFavoriteClick"
					@edit-favorite="onFavoriteEdit"
					@add-favorite="onFavoriteAdd"
					@add-address-favorite="onAddressFavoriteAdd"
					@delete-favorite="onFavoriteDelete"
					@delete-favorites="onFavoritesDelete"
					@coords-reset="resetPhotosCoords"
					@address-deleted="onContactAddressDelete"
					@contact-placed="onContactPlace"
					@place-photos="placePhotoFilesOrFolder"
					@photo-moved="onPhotoMoved"
					@click-track="onTrackClick"
					@change-track-color="onChangeTrackColorClicked"
					@toggle-device-history="onToggleDeviceHistory"
					@change-device-color="onChangeDeviceColorClicked"
					@export-device="onExportDevice"
					@cancel="cancelAction"
					@redo="redoAction"
					@slider-range-changed="sliderStart = $event.start; sliderEnd = $event.end" />
			</div>
			<Actions
				class="content-buttons"
				:title="t('maps', 'Details')">
				<ActionButton
					icon="icon-menu-sidebar"
					@click="onMainDetailClicked" />
			</Actions>
		</AppContent>
		<Sidebar
			v-if="true"
			:show="showSidebar"
			:active-tab="activeSidebarTab"
			:favorite="selectedFavorite"
			:favorite-categories="favoriteCategories"
			:track="selectedTrack"
			@edit-favorite="onFavoriteEdit"
			@delete-favorite="onFavoriteDelete"
			@active-changed="onActiveSidebarTabChanged"
			@close="showSidebar = false" />
	</Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import ActionButton from '@nextcloud/vue/dist/Components/ActionButton'
import { showError, showInfo, showSuccess } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'

import Map from '../components/Map'
import MapsNavigation from '../components/MapsNavigation'
import Sidebar from '../components/Sidebar'
import AppNavigationFavoritesItem from '../components/AppNavigationFavoritesItem'
import AppNavigationPhotosItem from '../components/AppNavigationPhotosItem'
import AppNavigationContactsItem from '../components/AppNavigationContactsItem'
import AppNavigationTracksItem from '../components/AppNavigationTracksItem'
import AppNavigationDevicesItem from '../components/AppNavigationDevicesItem'
import optionsController from '../optionsController'
import { getLetterColor, hslToRgb, Timer, getDeviceInfoFromUserAgent2, isComputer, isPhone } from '../utils'
import { poiSearchData } from '../utils/poiData'
import { processGpx } from '../tracksUtils'

import L from 'leaflet'
import { geoToLatLng, getFormattedADR } from '../utils/mapUtils'
import * as network from '../network'

export default {
	name: 'App',

	components: {
		Content,
		AppContent,
		Actions,
		ActionButton,
		Map,
		MapsNavigation,
		Sidebar,
		AppNavigationFavoritesItem,
		AppNavigationPhotosItem,
		AppNavigationContactsItem,
		AppNavigationTracksItem,
		AppNavigationDevicesItem,
	},

	data() {
		return {
			optionValues: optionsController.optionValues,
			sendPositionTimer: null,
			showSidebar: false,
			activeSidebarTab: 'sharing',
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
			// photos
			photosLoading: false,
			photosEnabled: optionsController.photosEnabled,
			photosDraggable: false,
			photos: [],
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
			devicesEnabled: optionsController.devicesEnabled,
			exportingDevices: false,
		}
	},

	computed: {
		mapLoading() {
			return this.photosLoading || this.contactsLoading || this.favoritesLoading || this.tracksLoading || this.devicesLoading
				|| this.exportingDevices || this.importingDevices
		},
		// slider
		minDataTimestamp() {
			return Math.min(
				this.minPhotoTimestamp,
				this.minFavoriteTimestamp
			) || 0
		},
		maxDataTimestamp() {
			return Math.max(
				this.maxPhotoTimestamp,
				this.maxFavoriteTimestamp
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
		// displayed data
		displayedTracks() {
			return this.sliderEnabled
				? this.tracks.filter((p) => {
					return true
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
		displayedPhotos() {
			return this.sliderEnabled
				? this.photos.filter((p) => {
					return p.dateTaken === null || (p.dateTaken >= this.sliderStart && p.dateTaken <= this.sliderEnd)
				})
				: this.photos
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
			return [
				...this.contactSearchData,
				...this.favoriteSearchData,
				...poiSearchData,
			]
		},
		routingSearchData() {
			return [
				...this.contactSearchData,
				...this.favoriteSearchData,
			]
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
		if (optionsController.optionValues.trackMe === 'true') {
			this.sendPositionLoop()
		}

		document.onkeyup = (e) => {
			if (e.ctrlKey) {
				if (e.key === 'z' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
					this.cancelAction()
				} else if (e.key === 'Z' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
					this.redoAction()
				}
			}
		}
	},
	mounted() {
		// subscribe('nextcloud:unified-search.search', this.filter)
		// subscribe('nextcloud:unified-search.reset', this.cleanSearch)
	},
	beforeDestroy() {
		// unsubscribe('nextcloud:unified-search.search', this.filter)
		// unsubscribe('nextcloud:unified-search.reset', this.cleanSearch)
	},
	methods: {
		onActiveSidebarTabChanged(newActive) {
			this.activeSidebarTab = newActive
		},
		onMainDetailClicked() {
			this.showSidebar = !this.showSidebar
			this.activeSidebarTab = 'main'
		},
		onToggleTrackme(enabled) {
			if (enabled) {
				this.sendPositionLoop()
			} else {
				this.stopTrackLoop()
			}
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
			network.sendMyPosition(lat, lng, name, acc, ts).then((response) => {
				// TODO get new positions
			}).catch((error) => {
				showError(t('maps', 'Failed to send current position') + ' ' + error)
			})
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
		onPhotosClicked() {
			this.photosEnabled = !this.photosEnabled
			// get photos if we don't have them yet
			if (this.photosEnabled && this.photos.length === 0) {
				this.getPhotos()
			}
			optionsController.saveOptionValues({ photosLayer: this.photosEnabled ? 'true' : 'false' })
		},
		getPhotos() {
			if (!this.photosEnabled) {
				return
			}
			this.photosLoading = true
			network.getPhotos().then((response) => {
				this.photos = response.data.sort((a, b) => {
					if (a.dateTaken < b.dateTaken) {
						return -1
					} else if (a.dateTaken > b.dateTaken) {
						return 1
					}
					return 0
				})
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.photosLoading = false
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
				true
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
				true
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
				true
			)
		},
		placePhotos(paths, lats, lngs, directory = false, save = true, reload = true) {
			network.placePhotos(paths, lats, lngs, directory).then((response) => {
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
			this.placePhotos([photo.path], [latLng.lat], [latLng.lng], false, true, false)
		},
		resetPhotosCoords(paths, save = true) {
			network.resetPhotosCoords(paths).then((response) => {
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

			network.getContacts().then((response) => {
				this.contacts = response.data
				this.buildContactGroups()
			}).catch((error) => {
				showError(
					t('maps', 'Failed to load contacts')
					+ ': ' + error.response?.request?.responseText
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
						const cGroups = c.GROUPS.split(/[^\\],/).map((name) => {
							return name.replace('\\,', ',')
						})
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
			network.deleteContactAddress(contact.BOOKID, contact.URI, contact.UID, contact.ADR).then((response) => {
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
				e.address, e.addressType
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
			if (!this.favoritesEnabled) {
				return
			}
			this.favoritesLoading = true
			network.getSharedFavoriteCategories().then((response) => {
				this.favoriteCategoryTokens = {}
				response.data.forEach((s) => {
					this.favoriteCategoryTokens[s.category] = s.token
				})
			})
			network.getFavorites().then((response) => {
				this.favorites = {}
				response.data.forEach((f) => {
					if (!f.category) {
						f.category = t('maps', 'Personal')
					}
					this.$set(this.favorites, f.id, f)
				})
			}).catch((error) => {
				console.error(error)
			}).then(() => {
				this.favoritesLoading = false
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
				network.shareFavoriteCategory(catid).then((response) => {
					this.$set(this.favoriteCategoryTokens, catid, response.data.token)
				}).catch((error) => {
					console.error(error)
				})
			} else {
				network.unshareFavoriteCategory(catid).then((response) => {
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
			this.showSidebar = true
			this.activeSidebarTab = 'favorite'
			this.selectedFavorite = f
		},
		onFavoriteEdit(f, save = true) {
			network.editFavorite(f.id, f.name, f.category, f.comment, f.lat, f.lng).then((response) => {
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
			}).catch((error) => {
				console.error(error)
			})
		},
		onFavoriteDelete(favid, save = true) {
			network.deleteFavorite(favid).then((response) => {
				if (save) {
					this.saveAction({
						type: 'favoriteDelete',
						favorites: [{ ...this.favorites[favid] }],
					})
				}
				this.selectedFavorite = null
				this.$delete(this.favorites, favid)
			}).catch((error) => {
				console.error(error)
			})
		},
		onFavoritesDelete(favids, save = true) {
			network.deleteFavorites(favids).then((response) => {
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
			network.exportFavorites(catIdList).then((response) => {
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
				true
			)
		},
		importFavorites(path) {
			network.importFavorites(path).then((response) => {
				this.getFavorites()
			}).catch((error) => {
				console.error(error)
			})
		},
		onAddressFavoriteAdd(obj) {
			const name = obj.address
				? obj.address.attraction
				|| obj.address.road
				|| obj.address.city_district
				: null
			this.addFavorite(obj.latLng, name, null, obj.formattedAddress || null)
		},
		onFavoriteAdd(latLng) {
			this.addFavorite(latLng)
		},
		addFavorite(latLng, name = null, category = null, comment = null, extensions = null, save = true) {
			return network.addFavorite(latLng.lat, latLng.lng, name, category, comment, extensions).then((response) => {
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
				return fav.id
			}).catch((error) => {
				console.error(error)
			})
		},
		onRenameFavoriteCategory(e, save = true) {
			network.renameFavoriteCategory([e.old], e.new).then((response) => {
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
			if (!this.tracksEnabled) {
				return
			}
			this.tracksLoading = true
			network.getTracks().then((response) => {
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
		onTrackClicked(track) {
			if (track.enabled) {
				track.enabled = false
				this.saveEnabledTracks()
			} else if (track.metadata && track.content) {
				track.enabled = true
				this.saveEnabledTracks()
			} else {
				this.getTrack(track, true, true)
			}
		},
		getTrack(track, enable = false, save = true) {
			track.loading = true
			network.getTrack(track.id).then((response) => {
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
			network.editTrack(e.track.id, e.color).then((response) => {
				console.debug(response.data)
			}).catch((error) => {
				console.error(error)
			})
		},
		onTrackZoom(track) {
			const meta = track.metadata
			this.$refs.map.fitBounds(L.latLngBounds([meta.s, meta.w], [meta.n, meta.e]), { padding: [30, 30] })
		},
		onTrackElevation(track) {
			this.$refs.map.displayElevation(track)
		},
		onTrackClick(track) {
			this.showSidebar = true
			this.activeSidebarTab = 'track'
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
			if (!this.devicesEnabled) {
				return
			}
			this.devicesLoading = true
			network.getDevices().then((response) => {
				this.devices = response.data.map((device) => {
					return {
						...device,
						loading: false,
						enabled: false,
						historyEnabled: optionsController.enabledDeviceLines.includes(device.id),
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
		onDeviceClicked(device) {
			if (device.enabled) {
				device.enabled = false
				this.saveEnabledDevices()
			} else if (device.points) {
				device.enabled = true
				this.saveEnabledDevices()
			} else {
				this.getDevice(device, true, true)
			}
		},
		getDevice(device, enable = false, save = true) {
			device.loading = true
			network.getDevice(device.id).then((response) => {
				device.points = response.data
				if (enable) {
					device.enabled = true
				}
				if (save) {
					this.saveEnabledDevices()
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
			network.editDevice(e.device.id, null, e.color).then((response) => {
				console.debug(response.data)
			}).catch((error) => {
				console.error(error)
			})
		},
		onDeviceZoom(device) {
			this.$refs.map.zoomOnDevice(device)
		},
		onExportDevice(device) {
			this.exportingDevices = true
			network.exportDevices([device.id], false).then((response) => {
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
		onToggleDeviceHistory(device) {
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
				true
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
	-webkit-mask: url('../../img/star-circle.svg') no-repeat 50% 50%;
	-webkit-mask-size: 18px;
	mask: url('../../img/star-circle.svg') no-repeat 50% 50%;
	mask-size: 18px;
	background: url('../../img/star-white.svg') no-repeat 50% 50%;
	background-size: 18px 18px;
	margin: auto;
}

::v-deep .favoriteClusterMarker {
	height: 27px !important;
	width: 27px !important;
	-webkit-mask: url('../../img/star-circle.svg') no-repeat 50% 50%;
	-webkit-mask-size: 27px;
	mask: url('../../img/star-circle.svg') no-repeat 50% 50%;
	mask-size: 27px;
	background: url('../../img/star-white.svg') no-repeat 50% 50%;
	background-size: 27px 27px;
	margin: auto;
}

::v-deep .favoriteMarkerDark {
	background: url('../../img/star-black.svg') no-repeat 50% 50%;
}
</style>
