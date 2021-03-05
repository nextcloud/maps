import * as network from './network'
import { showWarning } from '@nextcloud/dialogs'

const optionsController = {
	bounds: [
		[40.70081290280357, -74.26963806152345],
		[40.82991732677597, -74.08716201782228],
	],
	nbRouters: 0,
	optionValues: {},
	locControlEnabled: false,
	photosEnabled: true,
	contactsEnabled: true,
	favoritesEnabled: true,
	tracksEnabled: true,
	trackListShow: true,
	devicesEnabled: true,
	deviceListShow: true,
	myMapId: null,
	myMapListShow: null,
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
		network.saveOptionValues(newOptionValues, this.myMapId)
	},

	restoreOptions(successCB = null) {
		network.getOptionValues(this.myMapId)
			.then((response) => {
				this.handleOptionValues(response.data)
				if (successCB) {
					successCB()
				}
			})
	},

	handleOptionValues(response) {
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

		// check if install scan was done
		if ('installScanDone' in optionsValues && optionsValues.installScanDone === 'no') {
			showWarning(
				t('maps', 'Media scan was not done yet. Wait a few minutes/hours and reload this page to see your photos/tracks.')
			)
		}

		if ('photosLayer' in optionsValues && optionsValues.photosLayer !== 'true') {
			this.photosEnabled = false
		}

		if ('contactLayer' in optionsValues && optionsValues.contactLayer !== 'true') {
			this.contactsEnabled = false
		}

		if ('favoritesEnabled' in optionsValues && optionsValues.favoritesEnabled !== 'true') {
			this.favoritesEnabled = false
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
		}
		if ('trackListShow' in optionsValues && optionsValues.trackListShow !== 'true') {
			this.trackListShow = false
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
		}
		if ('enabledDeviceLines' in optionsValues
			&& optionsValues.enabledDeviceLines
			&& optionsValues.enabledDeviceLines !== '') {
			this.enabledDeviceLines = optionsValues.enabledDeviceLines.split('|').map((x) => {
				return parseInt(x)
			})
		}
		if ('devicesEnabled' in optionsValues && optionsValues.devicesEnabled !== 'true') {
			this.devicesEnabled = false
		}

		// my-maps
		if ('myMapListShow' in optionsValues && optionsValues.myMapListShow !== 'true') {
			this.myMapListShow = false
		}
		if ('myMapsEnabled' in optionsValues && optionsValues.myMapsEnabled !== 'true') {
			this.myMapsEnabled = false
		}
		if (document.location.pathname.includes('/apps/maps/m/')) {
			this.myMapId = parseInt(window.location.pathname.split('/apps/maps/m/')[1].split('/')[0])
		} else {
			this.myMapId = null
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
