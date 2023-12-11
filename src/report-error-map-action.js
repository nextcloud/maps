OCA.Maps?.registerMapsAction({
	label: t('maps', 'Report Error'),
	icon: 'icon-alert-outline',
	callback: (location) => {
		const url = `https://www.openstreetmap.org/note/new?lat=${location.latitude}&lon=${location.longitude}#map=18/${location.latitude}/${location.longitude}`
		window.open(url, '_blank')?.focus()
	},
})
