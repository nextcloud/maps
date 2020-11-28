<template>
	<Content app-name="maps">
		<MapsNavigation
			@toggle-slider="sliderEnabled = $event">
			<template #items>
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
					:can-cancel="lastPhotoMoves.length > 0"
					:can-redo="lastPhotoCanceledMoves.length > 0"
					@photos-clicked="onPhotosClicked"
					@cancel-clicked="cancelPhotoMove"
					@redo-clicked="redoPhotoMove"
					@draggable-clicked="photosDraggable = !photosDraggable" />
			</template>
		</MapsNavigation>
		<AppContent>
			<div id="app-content-wrapper">
				<Map
					ref="map"
					:search-data="searchData"
					:photos="photos"
					:photos-enabled="photosEnabled"
					:photos-draggable="photosDraggable"
					:contacts="contacts"
					:contact-groups="contactGroups"
					:contacts-enabled="contactsEnabled"
					:slider-enabled="sliderEnabled"
					:loading="mapLoading"
					@coords-reset="resetPhotosCoords"
					@address-deleted="getContacts"
					@contact-placed="getContacts"
					@place-photos="placePhotoFilesOrFolder"
					@photo-moved="onPhotoMoved" />
			</div>
			<Actions
				class="content-buttons"
				:title="t('maps', 'Details')">
				<ActionButton
					icon="icon-menu-sidebar"
					@click="onMainDetailClicked" />
			</Actions>
		</AppContent>
		<!--Sidebar
			v-if="currentProjectId"
			:show="showSidebar"
			:active-tab="activeSidebarTab"
			@active-changed="onActiveSidebarTabChanged"
			@close="showSidebar = false" /-->
	</Content>
</template>

<script>
import Content from '@nextcloud/vue/dist/Components/Content'
import AppContent from '@nextcloud/vue/dist/Components/AppContent'
import Actions from '@nextcloud/vue/dist/Components/Actions'
import Map from '../components/Map'
import MapsNavigation from '../components/MapsNavigation'
import AppNavigationPhotosItem from '../components/AppNavigationPhotosItem'
import AppNavigationContactsItem from '../components/AppNavigationContactsItem'
import optionsController from '../optionsController'
import L from 'leaflet'
import { geoToLatLng, getFormattedADR } from '../utils/mapUtils'
import * as network from '../network'
import { showError, showSuccess } from '@nextcloud/dialogs'

export default {
	name: 'App',

	components: {
		Content,
		AppContent,
		Actions,
		Map,
		MapsNavigation,
		AppNavigationPhotosItem,
		AppNavigationContactsItem,
	},

	data() {
		return {
			optionValues: optionsController.optionValues,
			sliderEnabled: optionsController.optionValues.displaySlider === 'true',
			// photos
			photosLoading: false,
			photosEnabled: optionsController.photosEnabled,
			photosDraggable: false,
			photos: [],
			lastPhotoMoves: [],
			lastPhotoCanceledMoves: [],
			// contacts
			contactsLoading: false,
			contactsEnabled: optionsController.contactsEnabled,
			contacts: [],
			contactGroups: {},
			disabledContactGroups: [],
		}
	},

	computed: {
		mapLoading() {
			return this.photosLoading || this.contactsLoading
		},
		searchData() {
			return [...this.contactSearchData]
		},
		contactSearchData() {
			return this.contacts.map((c) => {
				return {
					type: 'contact',
					icon: 'icon-contacts-dark',
					id: c.UID + c.GEO,
					label: c.FN + ' - ' + getFormattedADR(c.ADR),
					latLng: geoToLatLng(c.GEO),
				}
			})
		},
	},

	created() {
		this.getContacts()
		this.getPhotos()

		document.onkeyup = (e) => {
			if (e.ctrlKey) {
				if (e.key === 'z' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
					this.cancelPhotoMove()
				} else if (e.key === 'Z' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
					this.redoPhotoMove()
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
		onMainDetailClicked() {
			// this.showSidebar = !this.showSidebar
			// this.activeSidebarTab = 'project-settings'
		},
		// ================ PHOTOS =================
		onPhotosClicked() {
			this.photosEnabled = !this.photosEnabled
			// get contacts if we don't have them yet
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
				this.photos = response.data
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
		placePhotos(paths, lats, lngs, directory = false, save = true) {
			network.placePhotos(paths, lats, lngs, directory).then((response) => {
				this.getPhotos()
				if (save) {
					this.lastPhotoMoves.push(response.data)
					this.lastPhotoCanceledMoves = []
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
			this.placePhotos([photo.path], [latLng.lat], [latLng.lng])
		},
		resetPhotosCoords(paths, save = true) {
			network.resetPhotosCoords(paths).then((response) => {
				this.getPhotos()
				if (save) {
					this.lastPhotoMoves.push(response.data)
					this.lastPhotoCanceledMoves = []
				}
			}).catch((error) => {
				console.error(error)
			}).then(() => {
			})
		},
		cancelPhotoMove() {
			if (this.lastPhotoMoves.length === 0 || this.photosLoading) {
				return
			}
			const lastPhotoMove = this.lastPhotoMoves.pop()
			this.lastPhotoCanceledMoves.push(lastPhotoMove)
			// place the photos that previously had coordinates
			const toPlace = lastPhotoMove.filter((action) => {
				return (action.oldLat && action.oldLng)
			})
			// reset the photos that previously had NO coordinates and have new ones
			const toReset = lastPhotoMove.filter((action) => {
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
		redoPhotoMove() {
			if (this.lastPhotoCanceledMoves.length === 0 || this.photosLoading) {
				return
			}
			const lastPhotoCanceledMove = this.lastPhotoCanceledMoves.pop()
			this.lastPhotoMoves.push(lastPhotoCanceledMove)
			// redo placement action
			const toPlace = lastPhotoCanceledMove.filter((action) => {
				return (action.lat && action.lng)
			})
			// redo reset action
			const toReset = lastPhotoCanceledMove.filter((action) => {
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
			const lats = this.contacts.map((c) => {
				return geoToLatLng(c.GEO)[0]
			})
			const lons = this.contacts.map((c) => {
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
		onZoomContactGroup(gid) {
			const contactsOfGroup = this.contacts.filter((c) => {
				return ((gid === '0' && c.groupList.length === 0)
					|| c.groupList.includes(gid))
			})
			const lats = contactsOfGroup.map((c) => {
				return geoToLatLng(c.GEO)[0]
			})
			const lons = contactsOfGroup.map((c) => {
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
	},
}
</script>

<style lang="scss" scoped>
.content-buttons {
	position: absolute !important;
	top: 0px;
	right: 8px;
}

#app-content-wrapper {
	display: flex;
	height: 100%;
}
</style>
