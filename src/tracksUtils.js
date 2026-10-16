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
	return wpt
}
