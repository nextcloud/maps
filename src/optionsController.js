import * as network from './network.js'
import { showWarning } from '@nextcloud/dialogs'
import { getToken } from './utils/common'

const optionsController = {
	bounds: [
		[40.70081290280357, -74.26963806152345],
		[40.82991732677597, -74.08716201782228],
	],
	nbRouters: 0,
	optionValues: [],
	tileLayer: '',
	locControlEnabled: false,
	photosEnabled: true,
	contactsEnabled: true,
	favoritesEnabled: true,
	tracksEnabled: true,
	trackListShow: true,
	devicesEnabled: true,
	deviceListShow: true,
	myMapId: (window.location.pathname.includes('/apps/maps/m/'))
		? parseInt(window.location.pathname.split('/apps/maps/m/')[1].split('/')[0])
		: null,
	myMapListShow: true,
	myMapsEnabled: true,
	disabledFavoriteCategories: [],
	disabledContactGroups: [],
	enabledTracks: [],
	enabledDevices: [],
	enabledDeviceLines: [],
	saveOptionValues(newOptionValues) {
		for (const k in newOptionValues) {
			this.optionValues[k] = newOptionValues[k]
		}
		if (this.optionValues.isUpdateable) {
			network.saveOptionValues(newOptionValues, this.myMapId, getToken())
		}
	},

	restoreOptions(successCB = null) {
		network.getOptionValues(this.myMapId, getToken())
			.then((response) => {
				this.handleOptionValues(response.data)
				if (successCB) {
					successCB()
				}
			})
	},

	handleOptionValues(response) {
		if (document.location.pathname.includes('/apps/maps/m/')) {
			this.myMapId = parseInt(window.location.pathname.split('/apps/maps/m/')[1].split('/')[0])
		} else {
			this.myMapId = null
		}

		const optionsValues = response.values
		this.optionValues = optionsValues

		if ('mapBounds' in optionsValues) {
			const nsew = optionsValues.mapBounds.split(';')
			if (nsew.length === 4) {
				const n = parseFloat(nsew[0])
				const s = parseFloat(nsew[1])
				const e = parseFloat(nsew[2])
				const w = parseFloat(nsew[3])
				if (n && s && e && w) {
					this.bounds = [
						[n, e],
						[s, w],
					]
				}
			}
		}

		if (document.location.pathname.includes('/apps/maps/openGeoLink/')) {
			const url = decodeURIComponent(window.location.pathname)
			const latLng = url.split('/apps/maps/openGeoLink/geo:')[1].split(',').map(parseFloat)
			const ns = this.bounds[0][0] - this.bounds[1][0]
			const ew = this.bounds[0][1] - this.bounds[1][1]
			this.bounds = [
				[latLng[0] + ns / 2, latLng[1] + ew / 2],
				[latLng[0] - ns / 2, latLng[1] - ew / 2],
			]
		}

		if ('tileLayer' in optionsValues) {
			this.tileLayer = optionsValues.tileLayer
		}

		// check if install scan was done
		if ('installScanDone' in optionsValues && optionsValues.installScanDone === 'no') {
			showWarning(
				t('maps', 'Media scan was not done yet. Wait a few minutes/hours and reload this page to see your photos/tracks.'),
			)
		}

		if ('photosLayer' in optionsValues && optionsValues.photosLayer !== 'true') {
			this.photosEnabled = false
		} else {
			this.photosEnabled = true
		}

		if ('contactLayer' in optionsValues && optionsValues.contactLayer !== 'true') {
			this.contactsEnabled = false
		} else {
			this.contactsEnabled = true
		}

		if ('favoritesEnabled' in optionsValues && optionsValues.favoritesEnabled !== 'true') {
			this.favoritesEnabled = false
		} else {
			this.favoritesEnabled = true
		}
		if ('jsonDisabledFavoriteCategories' in optionsValues
			&& optionsValues.jsonDisabledFavoriteCategories
			&& optionsValues.jsonDisabledFavoriteCategories !== '') {
			try {
				this.disabledFavoriteCategories = JSON.parse(this.optionValues.jsonDisabledFavoriteCategories)
			} catch (error) {
				console.error(error)
			}
		}
		// getUrlParameter('track') ||
		if ('tracksEnabled' in optionsValues && optionsValues.tracksEnabled !== 'true') {
			this.tracksEnabled = false
		} else {
			this.tracksEnabled = true
		}
		if ('trackListShow' in optionsValues && optionsValues.trackListShow !== 'true') {
			this.trackListShow = false
		} else {
			this.trackListShow = true
		}
		if ('enabledTracks' in optionsValues
			&& optionsValues.enabledTracks
			&& optionsValues.enabledTracks !== '') {
			this.enabledTracks = optionsValues.enabledTracks.split('|').map((x) => {
				return parseInt(x)
			})
		}

		// devices
		if ('enabledDevices' in optionsValues
			&& optionsValues.enabledDevices
			&& optionsValues.enabledDevices !== '') {
			this.enabledDevices = optionsValues.enabledDevices.split('|').map((x) => {
				return parseInt(x)
			})
		}
		if ('deviceListShow' in optionsValues && optionsValues.deviceListShow !== 'true') {
			this.deviceListShow = false
		} else {
			this.deviceListShow = true
		}
		if ('enabledDeviceLines' in optionsValues
			&& optionsValues.enabledDeviceLines
			&& optionsValues.enabledDeviceLines !== '') {
			this.enabledDeviceLines = optionsValues.enabledDeviceLines.split('|').map((x) => {
				return parseInt(x)
			})
		}
		if (this.myMapId || ('devicesEnabled' in optionsValues && optionsValues.devicesEnabled !== 'true')) {
			this.devicesEnabled = false
		} else {
			this.devicesEnabled = true
		}

		// my-maps
		if ('myMapListShow' in optionsValues && optionsValues.myMapListShow !== 'true') {
			this.myMapListShow = false
		} else {
			this.myMapListShow = true
		}
		if ('myMapsEnabled' in optionsValues && optionsValues.myMapsEnabled !== 'true') {
			this.myMapsEnabled = false
		} else {
			this.myMapsEnabled = true
		}

		// routing
		if ('osrmCarURL' in optionsValues && optionsValues.osrmCarURL !== '') {
			this.nbRouters++
		}
		if ('osrmBikeURL' in optionsValues && optionsValues.osrmBikeURL !== '') {
			this.nbRouters++
		}
		if ('osrmFootURL' in optionsValues && optionsValues.osrmFootURL !== '') {
			this.nbRouters++
		}
		if ('mapboxAPIKEY' in optionsValues && optionsValues.mapboxAPIKEY !== '') {
			this.nbRouters++
		}
		if (('graphhopperURL' in optionsValues && optionsValues.graphhopperURL !== '')
			|| ('graphhopperAPIKEY' in optionsValues && optionsValues.graphhopperAPIKEY !== '')) {
			this.nbRouters++
		}

		if ('locControlEnabled' in optionsValues && optionsValues.locControlEnabled === 'true') {
			this.locControlEnabled = true
		}
		/*
		if (optionsValues.hasOwnProperty('tracksSortOrder') && optionsValues.tracksSortOrder !== '') {
			tracksController.sortOrder = optionsValues.tracksSortOrder;
		}
		else {
			tracksController.sortOrder = 'date';
		}
		if (optionsValues.hasOwnProperty('trackMe') && optionsValues.trackMe === 'true') {
			$('#track-me').prop('checked', true);
			devicesController.launchTrackLoop();
		}

		*/
	},
}

export default optionsController
