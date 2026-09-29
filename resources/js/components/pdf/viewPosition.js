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