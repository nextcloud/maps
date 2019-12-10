import { hslToRgb, getLetterColor } from "./colorUtils";

export const getDefaultCategoryName = () => {
  return t("maps", "Personal");
};

export const getCategoryKey = categoryName =>
  categoryName.replace(" ", "-").toLowerCase();

export const getThemingColorFromCategoryKey = categoryName => {
  let color = "0000EE";

  if (categoryName.length > 1) {
    const hsl = getLetterColor(categoryName[0], categoryName[1]);
    color = hslToRgb(hsl.h / 360, hsl.s / 100, hsl.l / 100);
  }

  if (categoryName === getCategoryKey(getDefaultCategoryName())) {
    color = (OCA.Theming ? OCA.Theming.color : "#0082c9").replace("#", "");
  }

  return `#${color}`;
};
