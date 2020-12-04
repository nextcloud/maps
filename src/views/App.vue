<template>
	<Content app-name="maps">
		<MapsNavigation
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
					@toggle-all-categories="onToggleAllFavoriteCategories"
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
			</template>
		</MapsNavigation>
		<AppContent>
			<div id="app-content-wrapper">
				<Map
					ref="map"
					:search-data="searchData"
					:favorites="favorites"
					:favorite-categories="favoriteCategories"
					:favorites-enabled="favoritesEnabled"
					:favorites-draggable="favoritesDraggable"
					:photos="photos"
					:photos-enabled="photosEnabled"
					:photos-draggable="photosDraggable"
					:contacts="contacts"
					:contact-groups="contactGroups"
					:contacts-enabled="contactsEnabled"
					:slider-enabled="sliderEnabled"
					:loading="mapLoading"
					@edit-favorite="onFavoriteEdit"
					@add-favorite="onFavoriteAdd"
					@delete-favorite="onFavoriteDelete"
					@delete-favorites="onFavoritesDelete"
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
import { showError, showSuccess } from '@nextcloud/dialogs'

import Map from '../components/Map'
import MapsNavigation from '../components/MapsNavigation'
import AppNavigationFavoritesItem from '../components/AppNavigationFavoritesItem'
import AppNavigationPhotosItem from '../components/AppNavigationPhotosItem'
import AppNavigationContactsItem from '../components/AppNavigationContactsItem'
import optionsController from '../optionsController'
import { getLetterColor, hslToRgb } from '../utils'

import L from 'leaflet'
import { geoToLatLng, getFormattedADR } from '../utils/mapUtils'
import * as network from '../network'

export default {
	name: 'App',

	components: {
		Content,
		AppContent,
		Actions,
		Map,
		MapsNavigation,
		AppNavigationFavoritesItem,
		AppNavigationPhotosItem,
		AppNavigationContactsItem,
	},

	data() {
		return {
			optionValues: optionsController.optionValues,
			sliderEnabled: optionsController.optionValues.displaySlider === 'true',
			// action history
			lastActions: [],
			lastCanceledActions: [],
			// favorites
			favoritesLoading: false,
			favoritesEnabled: optionsController.favoritesEnabled,
			favoritesDraggable: false,
			favorites: {},
			disabledFavoriteCategories: optionsController.disabledFavoriteCategories,
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
		}
	},

	computed: {
		mapLoading() {
			return this.photosLoading || this.contactsLoading || this.favoritesLoading
		},
		searchData() {
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
					const hsl = catid.length < 2
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
		onMainDetailClicked() {
			// this.showSidebar = !this.showSidebar
			// this.activeSidebarTab = 'project-settings'
		},
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
		onExportFavoriteCategory(catid) {
			this.exportFavorites([catid])
		},
		exportFavorites(catIdList) {
			network.exportFavorites(catIdList).then((response) => {
			}).catch((error) => {
				console.error(error)
			})
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
			console.debug(' rename ' + e.old + ' into ' + e.new)
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
