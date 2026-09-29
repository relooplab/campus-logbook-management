import assert from 'node:assert/strict';
import test from 'node:test';
import { capturePdfPosition, restorePdfPosition } from '../../resources/js/components/pdf/viewPosition.js';

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