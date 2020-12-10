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
	disabledFavoriteCategories: [],
	disabledContactGroups: [],
	enabledTracks: [],
	enabledDevices: [],
	enabledDeviceLines: [],
	saveOptionValues(newOptionValues) {
		for (const k in newOptionValues) {
			this.optionValues[k] = newOptionValues[k]
		}
		network.saveOptionValues(newOptionValues)
	},

	restoreOptions(successCB) {
		network.getOptionValues()
			.then((response) => {
				this.handleOptionValues(response.data)
				successCB()
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
		if (!optionsValues.hasOwnProperty('trackListShow') || optionsValues.trackListShow === 'true') {
			tracksController.toggleTrackList();
		}
		if (optionsValues.hasOwnProperty('enabledTracks')
			&& optionsValues.enabledTracks
			&& optionsValues.enabledTracks !== '')
		{
			that.enabledTracks = optionsValues.enabledTracks.split('|').map(function (x) {
				return parseInt(x);
			});
		}
		if (optionsValues.hasOwnProperty('tracksSortOrder') && optionsValues.tracksSortOrder !== '') {
			tracksController.sortOrder = optionsValues.tracksSortOrder;
		}
		else {
			tracksController.sortOrder = 'date';
		}
		if (getUrlParameter('track') || !optionsValues.hasOwnProperty('tracksEnabled') || optionsValues.tracksEnabled === 'true') {
			tracksController.toggleTracks();
		}
		if (!optionsValues.hasOwnProperty('deviceListShow') || optionsValues.deviceListShow === 'true') {
			devicesController.toggleDeviceList();
		}
		if (optionsValues.hasOwnProperty('enabledDevices')
			&& optionsValues.enabledDevices
			&& optionsValues.enabledDevices !== '')
		{
			that.enabledDevices = optionsValues.enabledDevices.split('|').map(function (x) {
				return parseInt(x);
			});
		}
		if (optionsValues.hasOwnProperty('enabledDeviceLines')
			&& optionsValues.enabledDeviceLines
			&& optionsValues.enabledDeviceLines !== '')
		{
			that.enabledDeviceLines = optionsValues.enabledDeviceLines.split('|').map(function (x) {
				return parseInt(x);
			});
		}
		if (!optionsValues.hasOwnProperty('devicesEnabled') || optionsValues.devicesEnabled === 'true') {
			devicesController.toggleDevices();
		}
		if (optionsValues.hasOwnProperty('trackMe') && optionsValues.trackMe === 'true') {
			$('#track-me').prop('checked', true);
			devicesController.launchTrackLoop();
		}

		*/
	},
}

export default optionsController
