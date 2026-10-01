/**
 * MouseSelection pada react-pdf-highlighter-plus mengukur drag terhadap
 * container viewer, tetapi mengurangkan page.offsetLeft/Top (relatif terhadap
 * .spread pada mode dua halaman). Koreksi selisih origin parent sebelum
 * menyimpan koordinat halaman; halaman tanpa spread menghasilkan selisih nol.
 */
export function correctAreaSelection(selection, viewer) {
  if (selection?.type !== 'area' || !viewer?.container) return selection;
  const box = selection.position?.boundingRect;
  const page = viewer.getPageView?.(box?.pageNumber - 1)?.div;
  if (!page || !box) return selection;

  const pageRect = page.getBoundingClientRect();
  const containerRect = viewer.container.getBoundingClientRect();
  const originX = pageRect.left - containerRect.left + viewer.container.scrollLeft;
  const originY = pageRect.top - containerRect.top + viewer.container.scrollTop;
  const dx = page.offsetLeft - originX;
  const dy = page.offsetTop - originY;
  const correct = (rect) => ({
    ...rect,
    x1: rect.x1 + dx,
    x2: rect.x2 + dx,
    y1: rect.y1 + dy,
    y2: rect.y2 + dy,
  });

  return {
    ...selection,
    position: {
      ...selection.position,
      boundingRect: correct(box),
      rects: selection.position.rects?.map(correct) || [],
    },
  };
}