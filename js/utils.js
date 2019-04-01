function basename(str) {
    var base = new String(str).substring(str.lastIndexOf('/') + 1);
    return base;
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

