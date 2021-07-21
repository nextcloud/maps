export const poiSearchData = [
	{
		type: 'poi',
		subtype: 'amenity',
		label: t('maps', 'Restaurant'),
		value: 'restaurant',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'Fast food'),
		value: 'fast food',
	}, {
		type: 'poi',
		subtype: 'amenity',
		label: t('maps', 'Bar'),
		value: 'bar',
	}, {
		type: 'poi',
		subtype: 'amenity',
		label: t('maps', 'Supermarket'),
		value: 'supermarket',
	}, {
		type: 'poi',
		subtype: 'amenity',
		label: t('maps', 'Cafe'),
		value: 'cafe',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'Library'),
		value: 'library',
	}, {
		type: 'poi',
		subtype: 'amenity',
		label: t('maps', 'School'),
		value: 'school',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'Sports centre'),
		value: 'sports centre',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'Gas station'),
		value: 'fuel',
	}, {
		type: 'poi',
		subtype: 'amenity',
		label: t('maps', 'Parking'),
		value: 'parking',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'Bicycle parking'),
		value: 'bicycle parking',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'Car rental'),
		value: 'car rental',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'ATM'),
		value: 'atm',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'Pharmacy'),
		value: 'pharmacy',
	}, {
		type: 'poi',
		subtype: 'amenity',
		label: t('maps', 'Cinema'),
		value: 'cinema',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'Public toilets'),
		value: 'toilets',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'Drinking water'),
		value: 'water point',
	}, {
		type: 'poi',
		subtype: 'amenity',
		label: t('maps', 'Hospital'),
		value: 'hospital',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'Doctors'),
		value: 'doctors',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'Dentist'),
		value: 'dentist',
	}, {
		type: 'poi',
		subtype: 'q',
		label: t('maps', 'Hotel'),
		value: 'hotel',
	},
]
poiSearchData.forEach((d) => {
	d.icon = 'icon-dot-circle'
	d.id = d.value
})
