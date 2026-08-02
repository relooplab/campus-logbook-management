import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import * as pdfjsLib from 'pdfjs-dist';

// Worker PDF.js. Tambah query ?v= (dari import.meta.env / Vite) agar cache
// browser ter-invalidasi saat rebuild — mencegah MIME/cache stale.
const WORKER_VERSION = import.meta.env?.VITE_WORKER_VERSION || '1';
pdfjsLib.GlobalWorkerOptions.workerSrc = '/pdfjs/pdf.worker.min.mjs?v=' + WORKER_VERSION;

/**
 * Arsitektur viewer (pendekatan Google Drive):
 *  - PDF dirender oleh PDF.js ke <canvas>.
 *  - Anotasi digambar sebagai lapisan overlay (DOM) di atas canvas.
 *  - Anotasi disimpan sebagai JSON format W3C Web Annotation di DB,
 *    TERPISAH dari file PDF.
 *  - (Opsional) tombol "Unduh PDF dengan Anotasi" membakar anotasi ke PDF
 *    via endpoint /pdf/burn (server-side, FPDI).
 */

const DATA = window.PDF_VIEWER_DATA || {};
const { draftUrl, catatanUrl, hasCatatan, entryId, csrf, commentsUrl, storeUrl, resolveUrl, deleteUrl, burnUrl } = DATA;

const TYPE_LABEL = { draft: 'File Perbaikan/Draft', catatan: 'Catatan Perbaikan' };

function parseSelector(value) {
  // value = "page=N&xywh=normalized:x1,y1,x2,y2"
  const parts = (value || '').split('&');
  let page = null, coords = null;
  for (const p of parts) {
    if (p.startsWith('page=')) page = parseInt(p.slice(5), 10);
    else if (p.startsWith('xywh=normalized:')) coords = p.slice(17).split(',').map(Number);
  }
  return { page, x1: coords?.[0], y1: coords?.[1], x2: coords?.[2], y2: coords?.[3] };
}

// Ubah anotasi DB (payload Web Annotation) ke bentuk yang bisa dirender.
function toAnnotation(item) {
  const payload = item.payload || {};
  const selector = payload.target?.selector || {};
  const { page, x1, y1, x2, y2 } = parseSelector(selector.value);
  const body = Array.isArray(payload.body) ? payload.body[0] : {};
  return {
    id: item.id,
    page,
    x1, y1, x2, y2,
    comment: body.value || '',
    resolved: Boolean(body.resolved),
    user: item.user?.name || '',
    created: item.created_at,
  };
}

function buildPayload(fileType, page, x1, y1, x2, y2, comment, resolved = false) {
  return {
    '@context': 'http://www.w3.org/ns/anno.jsonld',
    type: 'Annotation',
    motivation: 'commenting',
    body: [{ type: 'TextualBody', value: comment, purpose: 'commenting', resolved }],
    target: {
      type: 'SpecificResource',
      source: `urn:logbook-ta:entry:${entryId}:${fileType}`,
      selector: {
        type: 'FragmentSelector',
        conformsTo: 'http://www.w3.org/TR/media-frags/',
        value: `page=${page}&xywh=normalized:${x1},${y1},${x2},${y2}`,
      },
    },
  };
}

function PdfViewerApp() {
  const [activeType, setActiveType] = useState('draft');
  const [annotations, setAnnotations] = useState([]);
  const [pageNum, setPageNum] = useState(1);
  const [numPages, setNumPages] = useState(0);
  const [scale, setScale] = useState(1.4);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [areaMode, setAreaMode] = useState(false);
  const [drawing, setDrawing] = useState(null); // rect sedang digambar (pixel)
  const [modal, setModal] = useState(null); // { geometry, comment, saving }
  const [selected, setSelected] = useState(null);

  const pdfRef = useRef(null);
  const canvasRef = useRef(null);
  const ctxRef = useRef(null);
  const stageRef = useRef(null);
  const pageSizeRef = useRef({ width: 1, height: 1 });

  const pdfUrl = activeType === 'catatan' ? catatanUrl : draftUrl;

  // ---------------------------------------------------------------- load PDF
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    setAnnotations([]);
    setPageNum(1);
    setNumPages(0);
    if (!pdfUrl) {
      setError('Tidak ada file PDF untuk ditampilkan.');
      setLoading(false);
      return;
    }
    pdfjsLib.getDocument(pdfUrl).promise.then(async (doc) => {
      if (cancelled) return;
      pdfRef.current = doc;
      setNumPages(doc.numPages);
      await renderPage(doc, 1, scale, canvasRef, pageSizeRef);
      setLoading(false);
    }).catch((e) => {
      console.error(e);
      setError('Gagal memuat PDF. Pastikan file tersedia dan aset frontend ter-build (lihat README).');
      setLoading(false);
    });
    // muat anotasi
    fetch(commentsUrl + '?type=' + activeType, { credentials: 'same-origin' })
      .then((r) => r.json())
      .then((list) => setAnnotations(list.map(toAnnotation)));
    return () => { cancelled = true; };
  }, [activeType, pdfUrl]);

  // ---------------------------------------------------------------- render
  async function renderPage(doc, pageNumber, scl, canvas, sizeRef) {
    const page = await doc.getPage(pageNumber);
    const viewport = page.getViewport({ scale: scl });
    const c = canvas.current;
    if (!c) return;
    c.width = Math.floor(viewport.width);
    c.height = Math.floor(viewport.height);
    c.style.width = viewport.width + 'px';
    c.style.height = viewport.height + 'px';
    const ctx = c.getContext('2d');
    await page.render({ canvasContext: ctx, viewport }).promise;
    sizeRef.current = { width: c.width, height: c.height };
    ctxRef.current = ctx;
  }

  useEffect(() => {
    if (pdfRef.current) renderPage(pdfRef.current, pageNum, scale, canvasRef, pageSizeRef);
  }, [pageNum, scale]);

  // ---------------------------------------------------------------- nav/zoom
  const goPage = (delta) => {
    const next = pageNum + delta;
    if (next >= 1 && next <= numPages) setPageNum(next);
  };

  // ---------------------------------------------------------------- drag draw
  function canvasPoint(e) {
    const rect = canvasRef.current.getBoundingClientRect();
    const cx = (e.touches ? e.touches[0].clientX : e.clientX);
    const cy = (e.touches ? e.touches[0].clientY : e.clientY);
    return { x: cx - rect.left, y: cy - rect.top };
  }

  function onMouseDown(e) {
    if (!areaMode) return;
    if (e.target.closest('.anno-box')) return;
    const p = canvasPoint(e);
    setDrawing({ x1: p.x, y1: p.y, x2: p.x, y2: p.y });
    e.preventDefault();
  }
  function onMouseMove(e) {
    if (!drawing) return;
    const p = canvasPoint(e);
    setDrawing({ ...drawing, x2: p.x, y2: p.y });
  }
  function onMouseUp() {
    if (!drawing) return;
    const { x1, y1, x2, y2 } = drawing;
    const w = Math.abs(x2 - x1), h = Math.abs(y2 - y1);
    if (w < 12 || h < 12) { setDrawing(null); return; }
    const left = Math.min(x1, x2), top = Math.min(y1, y2);
    const cw = pageSizeRef.current.width, ch = pageSizeRef.current.height;
    // normalisasi (top-origin)
    const norm = {
      page: pageNum,
      x1: left / cw, y1: top / ch, x2: (left + w) / cw, y2: (top + h) / ch,
    };
    setModal({ geometry: norm, comment: '', saving: false });
    setDrawing(null);
  }

  // ---------------------------------------------------------------- save
  async function saveAnnotation() {
    if (!modal) return;
    const { geometry, comment } = modal;
    setModal({ ...modal, saving: true });
    const payload = buildPayload(activeType, geometry.page, geometry.x1, geometry.y1, geometry.x2, geometry.y2, comment.trim() || 'Tandai area');
    try {
      const res = await fetch(storeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ file_type: activeType, payload }),
      });
      const saved = await res.json();
      setAnnotations((a) => [...a, toAnnotation(saved)]);
      setModal(null);
    } catch (e) {
      alert('Gagal menyimpan anotasi.');
      setModal({ ...modal, saving: false });
    }
  }

  // ---------------------------------------------------------------- resolve/delete
  async function toggleResolve(id) {
    await fetch(resolveUrl.replace('{id}', id), {
      method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin',
    });
    setAnnotations((a) => a.map((x) => (x.id === id ? { ...x, resolved: !x.resolved } : x)));
  }
  async function removeAnnotation(id) {
    await fetch(deleteUrl.replace('{id}', id), {
      method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin',
    });
    setAnnotations((a) => a.filter((x) => x.id !== id));
    setSelected(null);
  }

  // Anotasi untuk halaman aktif
  const pageAnnotations = useMemo(
    () => annotations.filter((a) => a.page === pageNum),
    [annotations, pageNum]
  );

  return (
    <div className="space-y-4">
      {/* Tabs */}
      <div className="border-b border-slate-200 dark:border-slate-700 flex gap-1">
        <button onClick={() => setActiveType('draft')}
          className={`px-4 py-2 rounded-t-lg text-sm font-semibold ${activeType === 'draft' ? 'bg-white dark:bg-slate-800 border-b-2 border-emerald-500' : 'bg-slate-100 dark:bg-slate-900'}`}>
          {TYPE_LABEL.draft}
        </button>
        {hasCatatan && (
          <button onClick={() => setActiveType('catatan')}
            className={`px-4 py-2 rounded-t-lg text-sm font-semibold ${activeType === 'catatan' ? 'bg-white dark:bg-slate-800 border-b-2 border-emerald-500' : 'bg-slate-100 dark:bg-slate-900'}`}>
            {TYPE_LABEL.catatan}
          </button>
        )}
      </div>

      {/* Toolbar */}
      <div className="flex flex-wrap items-center gap-2">
        <button onClick={() => goPage(-1)} disabled={pageNum <= 1} className="px-3 py-1.5 rounded-md bg-slate-200 dark:bg-slate-700 text-sm disabled:opacity-40">‹ Prev</button>
        <span className="text-sm">Halaman {pageNum} / {numPages || '…'}</span>
        <button onClick={() => goPage(1)} disabled={pageNum >= numPages} className="px-3 py-1.5 rounded-md bg-slate-200 dark:bg-slate-700 text-sm disabled:opacity-40">Next ›</button>
        <button onClick={() => setScale((s) => Math.min(4, s + 0.2))} className="px-3 py-1.5 rounded-md bg-slate-200 dark:bg-slate-700 text-sm">+</button>
        <button onClick={() => setScale((s) => Math.max(0.5, s - 0.2))} className="px-3 py-1.5 rounded-md bg-slate-200 dark:bg-slate-700 text-sm">−</button>
        <button onClick={() => setAreaMode((m) => !m)}
          className={`px-3 py-1.5 rounded-md text-sm font-semibold ${areaMode ? 'bg-emerald-600 text-white' : 'bg-slate-200 dark:bg-slate-700'}`}>
          {areaMode ? 'Mode Area: ON' : 'Mode Area: OFF'}
        </button>
        {burnUrl && (
          <a href={burnUrl.replace('__TYPE__', activeType)} target="_blank" rel="noopener"
            className="ml-auto px-3 py-1.5 rounded-md bg-indigo-600 hover:bg-indigo-700 text-white text-sm">
            Unduh PDF dengan Anotasi
          </a>
        )}
      </div>

      {/* Stage */}
      <div className="relative inline-block bg-white dark:bg-slate-900 rounded-lg border border-slate-200 dark:border-slate-700 p-2"
        ref={stageRef}
        onMouseDown={onMouseDown} onMouseMove={onMouseMove} onMouseUp={onMouseUp}
        onTouchStart={onMouseDown} onTouchMove={onMouseMove} onTouchEnd={onMouseUp}
        style={{ touchAction: areaMode ? 'none' : 'auto' }}>
        <canvas ref={canvasRef} />
        {error && (
          <div className="absolute inset-0 flex items-center justify-center p-8 text-center text-sm text-red-600 dark:text-red-400">
            {error}
          </div>
        )}
        {/* Lapisan overlay anotasi */}
        <div className="absolute inset-0" style={{ left: '8px', top: '8px' }}>
          {pageAnnotations.map((a) => (
            <div key={a.id}
              className="anno-box absolute border-2 cursor-pointer"
              onClick={() => setSelected(a)}
              style={{
                left: (a.x1 * pageSizeRef.current.width) + 'px',
                top: (a.y1 * pageSizeRef.current.height) + 'px',
                width: ((a.x2 - a.x1) * pageSizeRef.current.width) + 'px',
                height: ((a.y2 - a.y1) * pageSizeRef.current.height) + 'px',
                borderColor: a.resolved ? '#10b981' : '#f59e0b',
                backgroundColor: (a.resolved ? 'rgba(16,185,129,.15)' : 'rgba(245,158,11,.15)'),
              }}>
              <span className="absolute -top-3 -left-1 bg-amber-500 dark:bg-amber-500 text-white text-[10px] px-1 rounded"
                style={{ backgroundColor: a.resolved ? '#10b981' : '#f59e0b' }}>
                {a.id}
              </span>
            </div>
          ))}
          {/* Persegi saat menggambar */}
          {drawing && (
            <div className="absolute border-2 border-dashed border-amber-500 bg-amber-500/20"
              style={{
                left: Math.min(drawing.x1, drawing.x2) + 'px',
                top: Math.min(drawing.y1, drawing.y2) + 'px',
                width: Math.abs(drawing.x2 - drawing.x1) + 'px',
                height: Math.abs(drawing.y2 - drawing.y1) + 'px',
              }} />
          )}
        </div>
      </div>

      <p className="text-xs text-slate-500 dark:text-slate-400">
        {loading ? 'Memuat PDF…' : areaMode ? 'Aktifkan lalu seret untuk menandai area.' : 'Nyalakan Mode Area untuk menandai area, lalu beri komentar.'}
      </p>

      {/* Modal komentar baru */}
      {modal && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-lg border dark:border-slate-700 p-4 w-full max-w-md">
            <h3 className="font-semibold mb-2">Komentar pada area ini</h3>
            <textarea rows="3" value={modal.comment} onChange={(e) => setModal({ ...modal, comment: e.target.value })}
              className="w-full rounded-md border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-2 text-sm"
              placeholder="Tulis komentar…" autoFocus />
            <div className="flex justify-end gap-2 mt-3">
              <button onClick={() => setModal(null)} className="px-3 py-2 rounded-md bg-slate-200 dark:bg-slate-700 text-sm">Batal</button>
              <button onClick={saveAnnotation} disabled={modal.saving}
                className="px-3 py-2 rounded-md bg-emerald-600 hover:bg-emerald-700 text-white text-sm">
                {modal.saving ? 'Menyimpan…' : 'Simpan'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal detail komentar */}
      {selected && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-white dark:bg-slate-900 rounded-lg border dark:border-slate-700 p-4 w-full max-w-md">
            <h3 className="font-semibold mb-2">Anotasi #{selected.id}</h3>
            <p className="text-sm mb-1">{selected.user}</p>
            <p className="text-sm mb-3">{selected.comment}</p>
            <div className="flex items-center gap-2">
              <button onClick={() => toggleResolve(selected.id)}
                className="px-3 py-2 rounded-md bg-amber-500 text-white text-sm">
                {selected.resolved ? 'Buka' : 'Tandai Selesai'}
              </button>
              <button onClick={() => removeAnnotation(selected.id)}
                className="px-3 py-2 rounded-md bg-red-600 text-white text-sm">Hapus</button>
              <button onClick={() => setSelected(null)}
                className="ml-auto px-3 py-2 rounded-md bg-slate-200 dark:bg-slate-700 text-sm">Tutup</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

const rootEl = document.getElementById('pdf-viewer-root');
if (rootEl) createRoot(rootEl).render(<PdfViewerApp />);
