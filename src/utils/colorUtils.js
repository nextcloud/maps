export const getLetterColor = (letter1, letter2) => {
  const letter1Index = letter1.toLowerCase().charCodeAt(0);
  const letter2Index = letter2.toLowerCase().charCodeAt(0);
  const letterCoefficient = ((letter1Index * letter2Index) % 100) / 100;
  const h = letterCoefficient * 360;
  const s = 75 + letterCoefficient * 10;
  const l = 50 + letterCoefficient * 10;

  return { h: Math.round(h), s: Math.round(s), l: Math.round(l) };
};

export const hue2Rgb = (p, q, t) => {
  if (t < 0) t += 1;
  if (t > 1) t -= 1;
  if (t < 1 / 6) return p + (q - p) * 6 * t;
  if (t < 1 / 2) return q;
  if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
  return p;
};

export const hslToRgb = (h, s, l) => {
  let r, g, b;

  if (s === 0) {
    r = g = b = l; // achromatic
  } else {
    const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
    const p = 2 * l - q;
    r = hue2Rgb(p, q, h + 1 / 3);
    g = hue2Rgb(p, q, h);
    b = hue2Rgb(p, q, h - 1 / 3);
  }
  const rgb = {
    r: Math.round(r * 255),
    g: Math.round(g * 255),
    b: Math.round(b * 255)
  };

  let hexStringR = rgb.r.toString(16);
  if (hexStringR.length % 2) {
    hexStringR = "0" + hexStringR;
  }

  let hexStringG = rgb.g.toString(16);
  if (hexStringG.length % 2) {
    hexStringG = "0" + hexStringG;
  }

  let hexStringB = rgb.b.toString(16);
  if (hexStringB.length % 2) {
    hexStringB = "0" + hexStringB;
  }

  return hexStringR + hexStringG + hexStringB;
};
