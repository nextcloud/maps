import {UAParser} from 'ua-parser-js';

function basename(str) {
    var base = new String(str).substring(str.lastIndexOf('/') + 1);
    return base;
}

function dirname(path) {
    return path.replace(/\\/g, '/').replace(/\/[^\/]*$/, '');
}

function splitByNonEscapedComma(str) {
	// As safari doesn't support lookbehind we need to work with reversed strings
	return str.split('')
		.reverse()
		.join('')
		.split(/,(?!(?:(?:\\\\)*\\(?!\\)))/)
		.map((g) => {
				return g.replaceAll(',\\',',')
					.replaceAll('\\\\','\\')
					.split('')
					.reverse()
					.join('')
			}).reverse()
}

function Timer(callback, mydelay) {
    var timerId,
        start,
        remaining = mydelay;

    this.pause = function () {
        window.clearTimeout(timerId);
        remaining -= new Date() - start;
    };

    this.resume = function () {
        start = new Date();
        window.clearTimeout(timerId);
        timerId = window.setTimeout(callback, remaining);
    };

    this.resume();
}

function getLetterColor(letter1, letter2) {
    var letter1Index = letter1.toLowerCase().charCodeAt(0);
    var letter2Index = letter2.toLowerCase().charCodeAt(0);
    var letterCoef = ((letter1Index * letter2Index) % 100) / 100;
    var h = letterCoef * 360;
    var s = 75 + letterCoef * 10;
    var l = 50 + letterCoef * 10;
    return {h: Math.round(h), s: Math.round(s), l: Math.round(l)};
}

function hslToRgb(h, s, l) {
    var r, g, b;

    if (s == 0) {
        r = g = b = l; // achromatic
    } else {
        var hue2rgb = function hue2rgb(p, q, t) {
            if (t < 0) t += 1;
            if (t > 1) t -= 1;
            if (t < 1 / 6) return p + (q - p) * 6 * t;
            if (t < 1 / 2) return q;
            if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
            return p;
        };

        var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        var p = 2 * l - q;
        r = hue2rgb(p, q, h + 1 / 3);
        g = hue2rgb(p, q, h);
        b = hue2rgb(p, q, h - 1 / 3);
    }
    var rgb = {
        r: Math.round(r * 255),
        g: Math.round(g * 255),
        b: Math.round(b * 255)
    };
    var hexStringR = rgb.r.toString(16);
    if (hexStringR.length % 2) {
        hexStringR = '0' + hexStringR;
    }
    var hexStringG = rgb.g.toString(16);
    if (hexStringG.length % 2) {
        hexStringG = '0' + hexStringG;
    }
    var hexStringB = rgb.b.toString(16);
    if (hexStringB.length % 2) {
        hexStringB = '0' + hexStringB;
    }
    return hexStringR + hexStringG + hexStringB;
}

function hexToRgb(hex) {
    var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result
        ? {
            r: parseInt(result[1], 16),
            g: parseInt(result[2], 16),
            b: parseInt(result[3], 16)
        }
        : null;
}

function pad(num, size) {
    var s = num + '';
    while (s.length < size) s = '0' + s;
    return s;
}

Date.prototype.toIsoString = function () {
    var tzo = -this.getTimezoneOffset(),
        dif = tzo >= 0 ? '+' : '-',
        pad = function (num) {
            var norm = Math.floor(Math.abs(num));
            return (norm < 10 ? '0' : '') + norm;
        };
    return (
        this.getFullYear() +
        '-' +
        pad(this.getMonth() + 1) +
        '-' +
        pad(this.getDate()) +
        ' ' +
        pad(this.getHours()) +
        ':' +
        pad(this.getMinutes()) +
        ':' +
        pad(this.getSeconds()) +
        ' GMT' +
        dif +
        pad(tzo / 60) +
        ':' +
        pad(tzo % 60)
    );
};

function brify(str, linesize) {
    var res = '';
    var words = str.split(' ');
    var cpt = 0;
    var toAdd = '';
    for (var i = 0; i < words.length; i++) {
        if (cpt + words[i].length < linesize) {
            toAdd += words[i] + ' ';
            cpt += words[i].length + 1;
        } else {
            res += toAdd + '<br/>';
            toAdd = words[i] + ' ';
            cpt = words[i].length + 1;
        }
    }
    res += toAdd;
    return res;
}

function metersToDistance(m) {
    var unit = 'metric';
    var n = parseFloat(m);
    if (unit === 'metric') {
        if (n > 1000) {
            return (n / 1000).toFixed(2) + ' km';
        } else {
            return n.toFixed(2) + ' m';
        }
    } else if (unit === 'english') {
        var mi = n * METERSTOMILES;
        if (mi < 1) {
            return (n * METERSTOFOOT).toFixed(2) + ' ft';
        } else {
            return mi.toFixed(2) + ' mi';
        }
    } else if (unit === 'nautical') {
        var nmi = n * METERSTONAUTICALMILES;
        return nmi.toFixed(2) + ' nmi';
    }
}

function metersToElevation(m) {
    var unit = 'metric';
    var n = parseFloat(m);
    if (unit === 'metric' || unit === 'nautical') {
        return n.toFixed(2) + ' m';
    } else {
        return (n * METERSTOFOOT).toFixed(2) + ' ft';
    }
}

function kmphToSpeed(kmph) {
    var unit = 'metric';
    var nkmph = parseFloat(kmph);
    if (unit === 'metric') {
        return nkmph.toFixed(2) + ' km/h';
    } else if (unit === 'english') {
        return (nkmph * 1000 * METERSTOMILES).toFixed(2) + ' mi/h';
    } else if (unit === 'nautical') {
        return (nkmph * 1000 * METERSTONAUTICALMILES).toFixed(2) + ' kt';
    }
}

function minPerKmToPace(minPerKm) {
    var unit = 'metric';
    var nMinPerKm = parseFloat(minPerKm);
    if (unit === 'metric') {
        return nMinPerKm.toFixed(2) + ' min/km';
    } else if (unit === 'english') {
        return (nMinPerKm / 1000 / METERSTOMILES).toFixed(2) + ' min/mi';
    } else if (unit === 'nautical') {
        return (nMinPerKm / 1000 / METERSTONAUTICALMILES).toFixed(2) + ' min/nmi';
    }
}

function formatTimeSeconds(time_s) {
    var minutes = Math.floor(time_s / 60);
    var hours = Math.floor(minutes / 60);

    var ph = pad(hours, 2);
    var pm = pad(minutes % 60, 2);
    var ps = pad(time_s % 60, 2);
    return `${ph}:${pm}:${ps}`;
}

function isComputer(name) {
    return (
        name.match(/windows/i) ||
        name.match(/gnu\/linux/i) ||
        name.match(/mac\s?os/i) ||
        name.match(/chromium\s?os/i) ||
        name.match(/ubuntu/i)
    );
}

function isPhone(name) {
    return (
        name.match(/blackberry/i) ||
        name.match(/symbian/i) ||
        name.match(/phonetrack/i) ||
        name.match(/firefox\s?os/i) ||
        name.match(/android/i) ||
        name.match(/ios/i) ||
        name.match(/windows\s?mobile/i)
    );
}

function getDeviceInfoFromUserAgent2(ua) {
    var res = {
        os: null,
        client: null
    };
    var parser = new UAParser(ua);
    var uap = parser.getResult();
    if (uap.os && uap.os.name) {
        res.os = uap.os.name
            .replace('Linux', 'GNU/Linux')
            .replace('windows', 'Windows');
    }
    if (uap.browser && uap.browser.name) {
        res.client = uap.browser.name.replace('chrome', 'Chrome');
    }
    return res;
}

function getDeviceInfoFromUserAgent(ua) {
    var res = {
        os: null,
        client: null,
        clientVersion: null
    };
    var m;
    // OS
    if (ua.match(/x11/i) || ua.match(/linux/i)) {
        res.os = 'GNU/Linux';
    } else if (ua.match(/android/i)) {
        res.os = 'Android';
    } else if (ua.match(/windows/i)) {
        res.os = 'Windows';
    } else if (ua.match(/iphone/i)) {
        res.os = 'IOS';
    } else if (ua.match(/macintosh/i) || ua.match(/darwin/i)) {
        res.os = 'MacOS';
    }
    // BROWSER
    if (ua.match(/firefox\//i) && !ua.match(/seamonkey\//i)) {
        res.client = 'Firefox';
        m = ua.match(/firefox\/([0-9.]*)/i);
        if (m.length > 1) {
            res.clientVersion = m[1];
        }
    } else if (
        ua.match(/safari\//i) &&
        !ua.match(/chrome\//i) &&
        !ua.match(/chromium\//i)
    ) {
        res.client = 'Safari';
        m = ua.match(/safari\/([0-9.]*)/i);
        if (m.length > 1) {
            res.clientVersion = m[1];
        }
    } else if (ua.match(/chrome\//i) && !ua.match(/chromium\//i)) {
        res.client = 'Chrome';
        m = ua.match(/chrome\/([0-9.]*)/i);
        if (m.length > 1) {
            res.clientVersion = m[1];
        }
    } else if (ua.match(/chromium\//i)) {
        res.client = 'Chromium';
        m = ua.match(/chromium\/([0-9.]*)/i);
        if (m.length > 1) {
            res.clientVersion = m[1];
        }
    } else if (ua.match(/opr\//i)) {
        res.client = 'Opera';
        m = ua.match(/opr\/([0-9.]*)/i);
        if (m.length > 1) {
            res.clientVersion = m[1];
        }
    }
    return res;
}

function getUrlParameter(sParam) {
    var sPageURL = window.location.search.substring(1);
    var sURLVariables = sPageURL.split('&');
    for (var i = 0; i < sURLVariables.length; i++) {
        var sParameterName = sURLVariables[i].split('=');
        if (sParameterName[0] === sParam) {
            return decodeURIComponent(sParameterName[1]);
        }
    }
}

function formatAddress(address) {
    if (!address) {
        return ''
    }
    var strAddress =
        (address.attraction || '') +
        ' ' +
        (address.house_number || '') +
        ' ' +
        (address.road || '') +
        ' ' +
        (address.pedestrian || '') +
        ' ' +
        (address.suburb || '') +
        ' ' +
        (address.city_district || '') +
        ' ' +
        (address.postcode || '') +
        ' ' +
        (address.village || address.town || address.city || '') +
        ' ' +
        (address.state || '') +
        ' ' +
        (address.country || '');
    return strAddress.replace(/\s+/g, ' ').trim();
}

function sleep(ms) {
	return new Promise(function (resolve) {
		setTimeout(resolve, ms);
	});
}

export const accented = {
	'A': '[Aa\xaa\xc0-\xc5\xe0-\xe5\u0100-\u0105\u01cd\u01ce\u0200-\u0203\u0226\u0227\u1d2c\u1d43\u1e00\u1e01\u1e9a\u1ea0-\u1ea3\u2090\u2100\u2101\u213b\u249c\u24b6\u24d0\u3371-\u3374\u3380-\u3384\u3388\u3389\u33a9-\u33af\u33c2\u33ca\u33df\u33ff\uff21\uff41]',
	'B': '[Bb\u1d2e\u1d47\u1e02-\u1e07\u212c\u249d\u24b7\u24d1\u3374\u3385-\u3387\u33c3\u33c8\u33d4\u33dd\uff22\uff42]',
	'C': '[Cc\xc7\xe7\u0106-\u010d\u1d9c\u2100\u2102\u2103\u2105\u2106\u212d\u216d\u217d\u249e\u24b8\u24d2\u3376\u3388\u3389\u339d\u33a0\u33a4\u33c4-\u33c7\uff23\uff43]',
	'D': '[Dd\u010e\u010f\u01c4-\u01c6\u01f1-\u01f3\u1d30\u1d48\u1e0a-\u1e13\u2145\u2146\u216e\u217e\u249f\u24b9\u24d3\u32cf\u3372\u3377-\u3379\u3397\u33ad-\u33af\u33c5\u33c8\uff24\uff44]',
	'E': '[Ee\xc8-\xcb\xe8-\xeb\u0112-\u011b\u0204-\u0207\u0228\u0229\u1d31\u1d49\u1e18-\u1e1b\u1eb8-\u1ebd\u2091\u2121\u212f\u2130\u2147\u24a0\u24ba\u24d4\u3250\u32cd\u32ce\uff25\uff45]',
	'F': '[Ff\u1da0\u1e1e\u1e1f\u2109\u2131\u213b\u24a1\u24bb\u24d5\u338a-\u338c\u3399\ufb00-\ufb04\uff26\uff46]',
	'G': '[Gg\u011c-\u0123\u01e6\u01e7\u01f4\u01f5\u1d33\u1d4d\u1e20\u1e21\u210a\u24a2\u24bc\u24d6\u32cc\u32cd\u3387\u338d-\u338f\u3393\u33ac\u33c6\u33c9\u33d2\u33ff\uff27\uff47]',
	'H': '[Hh\u0124\u0125\u021e\u021f\u02b0\u1d34\u1e22-\u1e2b\u1e96\u210b-\u210e\u24a3\u24bd\u24d7\u32cc\u3371\u3390-\u3394\u33ca\u33cb\u33d7\uff28\uff48]',
	'I': '[Ii\xcc-\xcf\xec-\xef\u0128-\u0130\u0132\u0133\u01cf\u01d0\u0208-\u020b\u1d35\u1d62\u1e2c\u1e2d\u1ec8-\u1ecb\u2071\u2110\u2111\u2139\u2148\u2160-\u2163\u2165-\u2168\u216a\u216b\u2170-\u2173\u2175-\u2178\u217a\u217b\u24a4\u24be\u24d8\u337a\u33cc\u33d5\ufb01\ufb03\uff29\uff49]',
	'J': '[Jj\u0132-\u0135\u01c7-\u01cc\u01f0\u02b2\u1d36\u2149\u24a5\u24bf\u24d9\u2c7c\uff2a\uff4a]',
	'K': '[Kk\u0136\u0137\u01e8\u01e9\u1d37\u1d4f\u1e30-\u1e35\u212a\u24a6\u24c0\u24da\u3384\u3385\u3389\u338f\u3391\u3398\u339e\u33a2\u33a6\u33aa\u33b8\u33be\u33c0\u33c6\u33cd-\u33cf\uff2b\uff4b]',
	'L': '[Ll\u0139-\u0140\u01c7-\u01c9\u02e1\u1d38\u1e36\u1e37\u1e3a-\u1e3d\u2112\u2113\u2121\u216c\u217c\u24a7\u24c1\u24db\u32cf\u3388\u3389\u33d0-\u33d3\u33d5\u33d6\u33ff\ufb02\ufb04\uff2c\uff4c]',
	'M': '[Mm\u1d39\u1d50\u1e3e-\u1e43\u2120\u2122\u2133\u216f\u217f\u24a8\u24c2\u24dc\u3377-\u3379\u3383\u3386\u338e\u3392\u3396\u3399-\u33a8\u33ab\u33b3\u33b7\u33b9\u33bd\u33bf\u33c1\u33c2\u33ce\u33d0\u33d4-\u33d6\u33d8\u33d9\u33de\u33df\uff2d\uff4d]',
	'N': '[Nn\xd1\xf1\u0143-\u0149\u01ca-\u01cc\u01f8\u01f9\u1d3a\u1e44-\u1e4b\u207f\u2115\u2116\u24a9\u24c3\u24dd\u3381\u338b\u339a\u33b1\u33b5\u33bb\u33cc\u33d1\uff2e\uff4e]',
	'O': '[Oo\xba\xd2-\xd6\xf2-\xf6\u014c-\u0151\u01a0\u01a1\u01d1\u01d2\u01ea\u01eb\u020c-\u020f\u022e\u022f\u1d3c\u1d52\u1ecc-\u1ecf\u2092\u2105\u2116\u2134\u24aa\u24c4\u24de\u3375\u33c7\u33d2\u33d6\uff2f\uff4f]',
	'P': '[Pp\u1d3e\u1d56\u1e54-\u1e57\u2119\u24ab\u24c5\u24df\u3250\u3371\u3376\u3380\u338a\u33a9-\u33ac\u33b0\u33b4\u33ba\u33cb\u33d7-\u33da\uff30\uff50]',
	'Q': '[Qq\u211a\u24ac\u24c6\u24e0\u33c3\uff31\uff51]',
	'R': '[Rr\u0154-\u0159\u0210-\u0213\u02b3\u1d3f\u1d63\u1e58-\u1e5b\u1e5e\u1e5f\u20a8\u211b-\u211d\u24ad\u24c7\u24e1\u32cd\u3374\u33ad-\u33af\u33da\u33db\uff32\uff52]',
	'S': '[Ss\u015a-\u0161\u017f\u0218\u0219\u02e2\u1e60-\u1e63\u20a8\u2101\u2120\u24ae\u24c8\u24e2\u33a7\u33a8\u33ae-\u33b3\u33db\u33dc\ufb06\uff33\uff53]',
	'T': '[Tt\u0162-\u0165\u021a\u021b\u1d40\u1d57\u1e6a-\u1e71\u1e97\u2121\u2122\u24af\u24c9\u24e3\u3250\u32cf\u3394\u33cf\ufb05\ufb06\uff34\uff54]',
	'U': '[Uu\xd9-\xdc\xf9-\xfc\u0168-\u0173\u01af\u01b0\u01d3\u01d4\u0214-\u0217\u1d41\u1d58\u1d64\u1e72-\u1e77\u1ee4-\u1ee7\u2106\u24b0\u24ca\u24e4\u3373\u337a\uff35\uff55]',
	'V': '[Vv\u1d5b\u1d65\u1e7c-\u1e7f\u2163-\u2167\u2173-\u2177\u24b1\u24cb\u24e5\u2c7d\u32ce\u3375\u33b4-\u33b9\u33dc\u33de\uff36\uff56]',
	'W': '[Ww\u0174\u0175\u02b7\u1d42\u1e80-\u1e89\u1e98\u24b2\u24cc\u24e6\u33ba-\u33bf\u33dd\uff37\uff57]',
	'X': '[Xx\u02e3\u1e8a-\u1e8d\u2093\u213b\u2168-\u216b\u2178-\u217b\u24b3\u24cd\u24e7\u33d3\uff38\uff58]',
	'Y': '[Yy\xdd\xfd\xff\u0176-\u0178\u0232\u0233\u02b8\u1e8e\u1e8f\u1e99\u1ef2-\u1ef9\u24b4\u24ce\u24e8\u33c9\uff39\uff59]',
	'Z': '[Zz\u0179-\u017e\u01f1-\u01f3\u1dbb\u1e90-\u1e95\u2124\u2128\u24b5\u24cf\u24e9\u3390-\u3394\uff3a\uff5a]',
}

export {
	basename,
	dirname,
	splitByNonEscapedComma,
	Timer,
	getLetterColor,
	hslToRgb,
	hexToRgb,
    pad,
    brify,
    metersToDistance,
    metersToElevation,
    kmphToSpeed,
    minPerKmToPace,
    formatTimeSeconds,
    isComputer,
    isPhone,
    getDeviceInfoFromUserAgent2,
    getDeviceInfoFromUserAgent,
    getUrlParameter,
    formatAddress,
	sleep
}
