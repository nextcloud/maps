export const getPublicShareCategory = () => {
  const el = document.querySelector(".header-appname");

  if (!el) {
    throw new Error("Could not get publis share category");
  }

  return el.textContent;
};
