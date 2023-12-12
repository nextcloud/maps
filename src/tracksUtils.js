import moment from '@nextcloud/moment'

export function processGpx(gpx, overwriteZeroTimpstamp = true) {
	let xmlDoc
	if (window.DOMParser) {
		try {
			const parser = new DOMParser()
			xmlDoc = parser.parseFromString(gpx.replace(/version="1.1"/, 'version="1.0"'), 'text/xml')
		} catch (err) {
			return null
		}
	} else {
		return null
	}
	if (!xmlDoc.documentElement || xmlDoc.documentElement.tagName !== 'gpx') {
		return null
	}
	const gpxx = xmlDoc.documentElement

	const waypoints = []
	const tracks = []
	const routes = []
	gpxx.childNodes.forEach((e) => {
		if (e.tagName === 'wpt') {
			const point = parseWpt(e)
			if (point.lat && point.lng) {
				waypoints.push(point)
			}
		} else if (e.tagName === 'trk') {
			tracks.push(parseTrk(e))
		} else if (e.tagName === 'rte') {
			routes.push(parseRte(e))
		}
	})
	waypoints.sort((a, b) => (a.timestamp || 0) - (b.timestamp || 0))
	return { tracks, routes, waypoints }
}

function parseTrk(e) {
	const trk = {
		segments: [],
	}
	e.childNodes.forEach((c) => {
		if (['name', 'desc'].includes(c.tagName)) {
			trk[c.tagName] = c.textContent
		} else if (c.tagName === 'trkseg') {
			trk.segments.push(parseTrkseg(c))
		}
	})
	return trk
}

function parseTrkseg(e) {
	const seg = {
		points: [],
	}
	e.childNodes.forEach((c) => {
		if (c.tagName === 'trkpt') {
			const point = parseWpt(c)
			if (point.lat && point.lng) {
				seg.points.push(point)
			}
		}
	})
	seg.points.sort((a, b) => (a.timestamp || 0) - (b.timestamp || 0))
	return seg
}

function parseRte(e) {
	const rte = {
		points: [],
	}
	e.childNodes.forEach((c) => {
		if (['name', 'desc'].includes(c.tagName)) {
			rte[c.tagName] = c.textContent
		} else if (c.tagName === 'rtept') {
			const point = parseWpt(c)
			if (point.lat && point.lng) {
				rte.points.push(point)
			}
		}
	})
	return rte
}

function parseWpt(e) {
	const wpt = {
		lat: parseFloat(e.getAttribute('lat')),
		lng: parseFloat(e.getAttribute('lon')),
	}
	e.childNodes.forEach((c) => {
		if (c.tagName === 'ele') {
			wpt.ele = parseFloat(c.textContent)
		} else if (c.tagName === 'time') {
			wpt.timestamp = moment(c.textContent).unix()
		} else if (['name', 'desc', 'cmt', 'sym'].includes(c.tagName)) {
			wpt[c.tagName] = c.textContent
		}
	})
	/*
	linkText = elem.find('link text').text(),
	linkUrl = elem.find('link').getAttribute('href'),
	*/
	return wpt
}

/*
	// const fileDesc = gpxx.find('>metadata>desc').text();

	// var minTrackDate = Math.floor(Date.now() / 1000) + 1000000;
	var wpts = gpxx.find('wpt');
	wpts.each(function() {
		date = that.addWaypoint(id, $(this), coloredTooltipClass);
		minTrackDate = (date < minTrackDate) ? date : minTrackDate;
	});

	var trks = gpxx.find('trk');
	var name, cmt, desc, linkText, linkUrl, popupText, date;
	trks.each(function() {
		name = $(this).find('>name').text();
		cmt = $(this).find('>cmt').text();
		desc = $(this).find('>desc').text();
		linkText = $(this).find('link text').text();
		linkUrl = $(this).find('link').attr('href');
		popupText = that.getLinePopupText(id, name, cmt, desc, linkText, linkUrl);
		$(this).find('trkseg').each(function() {
			date = that.addLine(id, $(this).find('trkpt'), weight, color, name, popupText, coloredTooltipClass);
			minTrackDate = (date < minTrackDate) ? date : minTrackDate;
		});
	});

	var rtes = gpxx.find('rte');
	rtes.each(function() {
		name = $(this).find('>name').text();
		cmt = $(this).find('>cmt').text();
		desc = $(this).find('>desc').text();
		linkText = $(this).find('link text').text();
		linkUrl = $(this).find('link').attr('href');
		popupText = that.getLinePopupText(id, name, cmt, desc, linkText, linkUrl);
		date = that.addLine(id, $(this).find('rtept'), weight, color, name, popupText, coloredTooltipClass);
		minTrackDate = (date < minTrackDate) ? date : minTrackDate;
	});

	this.trackLayers[id].date = minTrackDate;

	// manage track main icon
	// find first point (marker location)
	// then bind tooltip and popup
	var firstWpt = null;
	if (wpts.length > 0) {
		var lat = wpts.first().attr('lat');
		var lon = wpts.first().attr('lon');
		firstWpt = L.latLng(lat, lon);
	}
	var firstLinePoint = null;
	if (trks.length > 0) {
		var trkpt = trks.first().find('trkpt').first();
		if (trkpt) {
			var lat = trkpt.attr('lat');
			var lon = trkpt.attr('lon');
			firstLinePoint = L.latLng(lat, lon);
		}
	}
	if (firstLinePoint === null && rtes.length > 0) {
		var rtept = rtes.first().find('rtept').first();
		if (rtept) {
			var lat = rtept.attr('lat');
			var lon = rtept.attr('lon');
			firstLinePoint = L.latLng(lat, lon);
		}
	}
	var firstPoint = firstLinePoint || firstWpt;

	if (firstPoint) {
		this.tracks[id].marker = L.marker([firstPoint.lat, firstPoint.lng], {
				icon: this.tracks[id].icon
		});
		this.tracks[id].marker.trackid = id;

		this.tracks[id].marker.on('contextmenu', this.trackMouseRightClick);

		// tooltip
		var tooltipText = this.tracks[id].file_name;
		this.tracks[id].marker.bindTooltip(tooltipText, {
			sticky: false,
			className: coloredTooltipClass + ' leaflet-marker-track-tooltip',
			direction: 'top',
			offset: L.point(0, -16)
		});
		// popup
		popupText = that.getLinePopupText(id, '', '', '', '', '');
		this.tracks[id].popupText = popupText;
		this.trackLayers[id].addLayer(this.tracks[id].marker);
	}
}
*/
