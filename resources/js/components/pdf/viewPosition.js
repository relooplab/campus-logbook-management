// Posisi relatif terhadap halaman tetap valid walau ukuran halaman berubah saat PDF dimuat lagi.
export function capturePdfPosition(viewer) {
  if (!viewer) return null;
  const page = viewer.currentPageNumber;
  const pageView = viewer.getPageView?.(page - 1);
  if (!pageView?.div || !viewer.container || !Number.isInteger(page)) return null;
  return {
    page,
    offsetY: viewer.container.getBoundingClientRect().top - pageView.div.getBoundingClientRect().top,
    scrollLeft: viewer.container.scrollLeft,
    scale: viewer.currentScaleValue,
    spread: viewer.spreadMode,
  };
}

export function restorePdfPosition(viewer, position) {
  if (!viewer || !position) return false;
  const page = Math.min(position.page, viewer.pagesCount);
  const pageView = viewer.getPageView?.(page - 1);
  if (!pageView?.div || !viewer.container) return false;
  const currentOffset = viewer.container.getBoundingClientRect().top - pageView.div.getBoundingClientRect().top;
  viewer.container.scrollTop += position.offsetY - currentOffset;
  viewer.container.scrollLeft = position.scrollLeft;
  return true;
}

// Simpan halaman yang sedang dibaca dan jarak vertikal relatif di dalamnya.
export function captureSpreadAnchor(viewer) {
  const page = viewer?.currentPageNumber;
  const div = viewer?.getPageView?.(page - 1)?.div;
  if (!div || !viewer.container || !Number.isInteger(page)) return null;
  const pageRect = div.getBoundingClientRect();
  const viewportRect = viewer.container.getBoundingClientRect();
  return {
    page,
    offsetFraction: pageRect.height ? (viewportRect.top - pageRect.top) / pageRect.height : 0,
  };
}

// .spread sudah rata tengah secara CSS saat muat di viewport; bila ada scroll
// horizontal, sesuaikan scrollLeft terhadap pasangan halaman, bukan tepi dokumen.
export function centerPdfSpread(viewer, anchor) {
  if (!viewer?.container || !anchor) return false;
  const div = viewer.getPageView?.(anchor.page - 1)?.div;
  const pages = div?.closest?.('.spread')?.querySelectorAll('.page');
  if (!div || !pages?.length) return false;
  const rects = Array.from(pages, (page) => page.getBoundingClientRect());
  const viewport = viewer.container.getBoundingClientRect();
  const left = Math.min(...rects.map((rect) => rect.left));
  const right = Math.max(...rects.map((rect) => rect.right));
  viewer.container.scrollLeft += (left + right) / 2 - (viewport.left + viewer.container.clientWidth / 2);

  if (Number.isFinite(anchor.offsetFraction)) {
    const pageRect = div.getBoundingClientRect();
    viewer.container.scrollTop += pageRect.top + anchor.offsetFraction * pageRect.height - viewport.top;
  }
  return true;
}