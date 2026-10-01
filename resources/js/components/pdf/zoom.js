export const ZOOM_OPTIONS = [50, 75, 100, 125, 150, 200, 300, 400];

export function parseZoomPercent(value) {
  const text = String(value).trim().replace(/%$/, '').trim();
  if (!/^\d+(?:[.,]\d+)?$/.test(text)) return null;
  const percent = Number(text.replace(',', '.'));
  return percent >= 10 && percent <= 400 ? percent / 100 : null;
}