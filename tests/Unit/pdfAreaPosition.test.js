import assert from 'node:assert/strict';
import test from 'node:test';
import { correctAreaSelection } from '../../resources/js/components/pdf/areaPosition.js';
import { buildPayloadFromSelection, toAnnotation } from '../../resources/js/components/pdf/annotationAdapter.js';
import { parseZoomPercent } from '../../resources/js/components/pdf/zoom.js';

function selection(page, x1, y1, width = 200, height = 100) {
  return {
    type: 'area',
    position: { boundingRect: { pageNumber: page, x1, x2: x1 + 40, y1, y2: y1 + 20, width, height }, rects: [] },
  };
}

function viewer(page, { left, top, offsetLeft, offsetTop, scrollLeft = 0, scrollTop = 0 }) {
  return {
    container: { scrollLeft, scrollTop, getBoundingClientRect: () => ({ left: 10, top: 20 }) },
    getPageView: (index) => index === page - 1 ? {
      div: { offsetLeft, offsetTop, getBoundingClientRect: () => ({ left, top }) },
    } : null,
  };
}

test('area selection stays on the right-hand page after switching to a spread', () => {
  const picked = selection(2, 250, 50);
  const spread = viewer(2, { left: 220, top: 20, offsetLeft: 10, offsetTop: 0 });
  const fixed = correctAreaSelection(picked, spread);
  assert.equal(fixed.position.boundingRect.x1, 50);
  assert.equal(picked.position.boundingRect.x1, 250);
  const payload = buildPayloadFromSelection(18, 'draft', fixed, 'Area kanan');
  const annotation = toAnnotation({ id: 27, payload });
  assert.equal(annotation.page, 2);
  assert.equal(annotation.highlight.position.boundingRect.x1, 25);
  assert.equal(annotation.highlight.position.boundingRect.x2, 45);
});

test('single page and scrolled area coordinates are not moved', () => {
  const picked = selection(1, 40, 25);
  const single = viewer(1, { left: -20, top: -80, offsetLeft: 10, offsetTop: 0, scrollLeft: 40, scrollTop: 100 });
  assert.deepEqual(correctAreaSelection(picked, single).position, picked.position);
  assert.deepEqual(correctAreaSelection({ ...picked, type: 'text' }, single).position, picked.position);
});

test('left page and scrolled right page use their own origin in a spread', () => {
  const leftPage = selection(1, 45, 35);
  const spreadLeft = viewer(1, { left: 20, top: 20, offsetLeft: 10, offsetTop: 0 });
  assert.deepEqual(correctAreaSelection(leftPage, spreadLeft).position, leftPage.position);

  const rightPage = selection(2, 250, 230, 400, 600);
  // Viewer scrolled horizontally and vertically; the page still starts at
  // document coordinate 210, and the user's drag is 50px inside the page.
  const spreadRight = viewer(2, { left: 180, top: 20, offsetLeft: 10, offsetTop: 0, scrollLeft: 40, scrollTop: 200 });
  const fixed = correctAreaSelection(rightPage, spreadRight);
  assert.equal(fixed.position.boundingRect.x1, 50);
  assert.equal(fixed.position.boundingRect.y1, 30);
  assert.equal(toAnnotation({ id: 28, payload: buildPayloadFromSelection(18, 'draft', fixed, 'Area') }).highlight.position.boundingRect.x1, 12.5);
});

test('zoom accepts presets and custom percentages within existing limits', () => {
  assert.equal(parseZoomPercent('125%'), 1.25);
  assert.equal(parseZoomPercent('137,5'), 1.375);
  for (const input of ['9', '401', '', 'abc', '1e2', '-50']) assert.equal(parseZoomPercent(input), null);
});