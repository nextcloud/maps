import { UAParser } from 'ua-parser-js';

function basename(str) {
    var base = new String(str).substring(str.lastIndexOf('/') + 1);
    return base;
}

function dirname(path) {
    return path.replace(/\\/g,'/').replace(/\/[^\/]*$/, '');;
}

function Timer(callback, mydelay) {
    var timerId, start, remaining = mydelay;

    this.pause = function() {
        window.clearTimeout(timerId);
        remaining -= new Date() - start;
    };

    this.resume = function() {
        start = new Date();
        window.clearTimeout(timerId);
        timerId = window.setTimeout(callback, remaining);
    };

    this.resume();
}

function getLetterColor(letter1, letter2) {
    var letter1Index = letter1.toLowerCase().charCodeAt(0);
    var letter2Index = letter2.toLowerCase().charCodeAt(0);
    var letterCoef = (letter1Index * letter2Index) % 100 / 100;
    var h = letterCoef * 360;
    var s = 75 + letterCoef * 10;
    var l = 50 + letterCoef * 10;
    return {h: Math.round(h), s: Math.round(s), l: Math.round(l)};
}

function hslToRgb(h, s, l) {
    var r, g, b;

    if(s == 0){
        r = g = b = l; // achromatic
    }else{
        var hue2rgb = function hue2rgb(p, q, t){
            if(t < 0) t += 1;
            if(t > 1) t -= 1;
            if(t < 1/6) return p + (q - p) * 6 * t;
            if(t < 1/2) return q;
            if(t < 2/3) return p + (q - p) * (2/3 - t) * 6;
            return p;
        }

        var q = l < 0.5 ? l * (1 + s) : l + s - l * s;
        var p = 2 * l - q;
        r = hue2rgb(p, q, h + 1/3);
        g = hue2rgb(p, q, h);
        b = hue2rgb(p, q, h - 1/3);
    }
    var rgb = {r: Math.round(r * 255), g: Math.round(g * 255), b: Math.round(b * 255)};
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
    return hexStringR+hexStringG+hexStringB;
}

function hexToRgb(hex) {
    var result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    return result ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16)
    } : null;
}

function pad(num, size) {
    var s = num + '';
    while (s.length < size) s = '0' + s;
    return s;
}

Date.prototype.toIsoString = function() {
    var tzo = -this.getTimezoneOffset(),
        dif = tzo >= 0 ? '+' : '-',
        pad = function(num) {
            var norm = Math.floor(Math.abs(num));
            return (norm < 10 ? '0' : '') + norm;
        };
    return this.getFullYear() +
        '-' + pad(this.getMonth() + 1) +
        '-' + pad(this.getDate()) +
        ' ' + pad(this.getHours()) +
        ':' + pad(this.getMinutes()) +
        ':' + pad(this.getSeconds()) +
        ' GMT'+dif + pad(tzo / 60) +
        ':' + pad(tzo % 60);
}

function brify(str, linesize) {
    var res = '';
    var words = str.split(' ');
    var cpt = 0;
    var toAdd = '';
    for (var i=0; i<words.length; i++) {
        if ((cpt + words[i].length) < linesize) {
            toAdd += words[i] + ' ';
            cpt += words[i].length + 1;
        }
        else{
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
        }
        else{
            return n.toFixed(2) + ' m';
        }
    }
    else if (unit === 'english') {
        var mi = n * METERSTOMILES;
        if (mi < 1) {
            return (n * METERSTOFOOT).toFixed(2) + ' ft';
        }
        else {
            return mi.toFixed(2) + ' mi';
        }
    }
    else if (unit === 'nautical') {
        var nmi = n * METERSTONAUTICALMILES;
        return nmi.toFixed(2) + ' nmi';
    }
}

function metersToElevation(m) {
    var unit = 'metric';
    var n = parseFloat(m);
    if (unit === 'metric' || unit === 'nautical') {
        return n.toFixed(2) + ' m';
    }
    else {
        return (n * METERSTOFOOT).toFixed(2) + ' ft';
    }
}

function kmphToSpeed(kmph) {
    var unit = 'metric';
    var nkmph = parseFloat(kmph);
    if (unit === 'metric') {
        return nkmph.toFixed(2) + ' km/h';
    }
    else if (unit === 'english') {
        return (nkmph * 1000 * METERSTOMILES).toFixed(2) + ' mi/h';
    }
    else if (unit === 'nautical') {
        return (nkmph * 1000 * METERSTONAUTICALMILES).toFixed(2) + ' kt';
    }
}

function minPerKmToPace(minPerKm) {
    var unit = 'metric';
    var nMinPerKm = parseFloat(minPerKm);
    if (unit === 'metric') {
        return nMinPerKm.toFixed(2) + ' min/km';
    }
    else if (unit === 'english') {
        return (nMinPerKm / 1000 / METERSTOMILES).toFixed(2) + ' min/mi';
    }
    else if (unit === 'nautical') {
        return (nMinPerKm / 1000 / METERSTONAUTICALMILES).toFixed(2) + ' min/nmi';
    }
}

function formatTimeSeconds(time_s){
    var minutes = Math.floor(time_s / 60);
    var hours = Math.floor(minutes / 60);

    var ph = pad(hours, 2);
    var pm = pad(minutes % 60, 2);
    var ps = pad(time_s % 60, 2);
    return `${ph}:${pm}:${ps}`;
}

function isComputer(name) {
    return (   name.match(/windows/i)
            || name.match(/gnu\/linux/i)
            || name.match(/mac\s?os/i)
            || name.match(/chromium\s?os/i)
            || name.match(/ubuntu/i)
    );
}

function isPhone(name) {
    return (name.match(/blackberry/i)
         || name.match(/symbian/i)
         || name.match(/phonetrack/i)
         || name.match(/firefox\s?os/i)
         || name.match(/android/i)
         || name.match(/ios/i)
         || name.match(/windows\s?mobile/i)
    );
}

function getDeviceInfoFromUserAgent2(ua) {
    var res = {
        os: null,
        client: null,
    };
    var parser = new UAParser(ua);
    var uap = parser.getResult();
    if (uap.os && uap.os.name) {
        res.os = uap.os.name.replace('Linux', 'GNU/Linux').replace('windows', 'Windows');
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
    }
    else if (ua.match(/android/i)) {
        res.os = 'Android';
    }
    else if (ua.match(/windows/i)) {
        res.os = 'Windows';
    }
    else if (ua.match(/iphone/i)) {
        res.os = 'IOS';
    }
    else if (ua.match(/macintosh/i) || ua.match(/darwin/i)) {
        res.os = 'MacOS';
    }
    // BROWSER
    if (ua.match(/firefox\//i) && !ua.match(/seamonkey\//i)) {
        res.client = 'Firefox';
        m = ua.match(/firefox\/([0-9.]*)/i);
        if (m.length > 1) {
            res.clientVersion = m[1];
        }
    }
    else if (ua.match(/safari\//i) && !ua.match(/chrome\//i) && !ua.match(/chromium\//i)) {
        res.client = 'Safari';
        m = ua.match(/safari\/([0-9.]*)/i);
        if (m.length > 1) {
            res.clientVersion = m[1];
        }
    }
    else if (ua.match(/chrome\//i) && !ua.match(/chromium\//i)) {
        res.client = 'Chrome';
        m = ua.match(/chrome\/([0-9.]*)/i);
        if (m.length > 1) {
            res.clientVersion = m[1];
        }
    }
    else if (ua.match(/chromium\//i)) {
        res.client = 'Chromium';
        m = ua.match(/chromium\/([0-9.]*)/i);
        if (m.length > 1) {
            res.clientVersion = m[1];
        }
    }
    else if (ua.match(/opr\//i)) {
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
    var strAddress =
        (address.attraction || '')+' '+
        (address.house_number || '')+' '+
        (address.road || '')+' '+
        (address.pedestrian || '')+' '+
        (address.suburb || '')+' '+
        (address.city_district || '')+' '+
        (address.postcode || '')+' '+
        (address.village || address.town || address.city || '')+' '+
        (address.state || '')+' '+
        (address.country || '');
    return strAddress.replace(/\s+/g, ' ').trim();
}

export {
    basename,
    dirname,
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
    formatAddress
}
