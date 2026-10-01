import assert from 'node:assert/strict';
import test from 'node:test';
import { capturePdfPosition, captureSpreadAnchor, centerPdfSpread, restorePdfPosition } from '../../resources/js/components/pdf/viewPosition.js';

function makeViewer(pageTop, scrollTop, scale = 'page-width') {
  const container = {
    scrollTop,
    scrollLeft: 12,
    getBoundingClientRect: () => ({ top: 100, left: 30 }),
  };
  const page = {
    div: { getBoundingClientRect: () => ({ top: pageTop - container.scrollTop + 100, left: 45 }) },
  };
  return {
    container,
    currentPageNumber: 7,
    currentScaleValue: scale,
    spreadMode: 1,
    pagesCount: 10,
    getPageView: (index) => index === 6 ? page : null,
  };
}

test('restores the same position within a page even when zoom changes its layout', () => {
  const draft = makeViewer(4000, 4285, '1.25');
  const saved = capturePdfPosition(draft);
  assert.deepEqual(saved, { page: 7, offsetY: 285, scrollLeft: 12, scale: '1.25', spread: 1 });

  const reloaded = makeViewer(5200, 5200);
  restorePdfPosition(reloaded, saved);
  assert.equal(reloaded.container.scrollTop, 5485);
  assert.equal(reloaded.container.scrollLeft, 12);
  assert.equal(capturePdfPosition(reloaded).offsetY, 285);
});

test('ignores unavailable viewers and page views', () => {
  assert.equal(capturePdfPosition(null), null);
  assert.equal(capturePdfPosition({ currentPageNumber: 2, getPageView: () => null }), null);
  assert.equal(restorePdfPosition(null, { page: 2 }), false);
  assert.equal(restorePdfPosition(makeViewer(4000, 4000), null), false);
});

test('keeps draft and catatan positions independent across repeated tab switches', () => {
  const draft = makeViewer(4000, 4180);
  const catatan = makeViewer(3000, 3040);
  const saved = { draft: capturePdfPosition(draft), catatan: capturePdfPosition(catatan) };
  const reopenedDraft = makeViewer(5000, 5000);
  restorePdfPosition(reopenedDraft, saved.draft);
  assert.equal(reopenedDraft.container.scrollTop, 5180);
  const reopenedCatatan = makeViewer(6000, 6000);
  restorePdfPosition(reopenedCatatan, saved.catatan);
  assert.equal(reopenedCatatan.container.scrollTop, 6040);
  assert.equal(saved.draft.offsetY, 180);
});

function makeSpreadViewer({ containerWidth, scrollLeft, scrollTop = 0, pageXs, pageWidth, pageTop = 600, pageHeight = 600, pageNumber = 2 }) {
  const container = {
    clientWidth: containerWidth,
    scrollLeft,
    scrollTop,
    getBoundingClientRect: () => ({ left: 50, top: 100 }),
  };
  const spread = { querySelectorAll: () => pageXs.map((x) => ({
    getBoundingClientRect: () => ({ left: 50 + x - container.scrollLeft, right: 50 + x + pageWidth - container.scrollLeft }),
  })) };
  return {
    container,
    currentPageNumber: pageNumber,
    getPageView: (index) => index + 1 === pageNumber ? {
      div: {
        closest: (selector) => selector === '.spread' ? spread : null,
        getBoundingClientRect: () => ({ top: 100 + pageTop - container.scrollTop, height: pageHeight }),
      },
    } : null,
  };
}

test('centers both pages in the PDF viewport despite horizontal scrolling and open panels', () => {
  for (const containerWidth of [900, 580]) {
    const viewer = makeSpreadViewer({ containerWidth, scrollLeft: 270, pageXs: [250, 580], pageWidth: 300, scrollTop: 660 });
    const anchor = captureSpreadAnchor(viewer);
    assert.equal(anchor.page, 2);
    assert.equal(centerPdfSpread(viewer, anchor), true);
    assert.equal(viewer.container.scrollLeft, 250 + 630 / 2 - containerWidth / 2);
    assert.equal(viewer.container.scrollTop, 660);
  }
});

test('centering preserves relative vertical position after spread changes page height', () => {
  const before = makeSpreadViewer({ containerWidth: 700, scrollLeft: 0, scrollTop: 750, pageXs: [100, 420], pageWidth: 300, pageHeight: 600 });
  const anchor = captureSpreadAnchor(before);
  const after = makeSpreadViewer({ containerWidth: 700, scrollLeft: 90, scrollTop: 400, pageXs: [100, 420], pageWidth: 300, pageHeight: 300 });
  centerPdfSpread(after, anchor);
  assert.equal(after.container.scrollTop, 675);
  assert.equal(after.container.scrollLeft, 100 + 620 / 2 - 350);
});

test('a lone page in a spread centers on that page and missing views are ignored', () => {
  const viewer = makeSpreadViewer({ containerWidth: 700, scrollLeft: 75, pageXs: [200], pageWidth: 300, pageNumber: 1 });
  assert.equal(centerPdfSpread(viewer, captureSpreadAnchor(viewer)), true);
  assert.equal(viewer.container.scrollLeft, 0);
  assert.equal(centerPdfSpread(viewer, { page: 9, offsetFraction: 0 }), false);
});