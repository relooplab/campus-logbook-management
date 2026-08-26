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
 *
 * Layout halaman:
 *  - Halaman disusun vertikal (continuous scroll) di dalam panel bertingkat
 *    tetap (max-h + overflow-auto) sehingga scrollbar vertikal & horizontal
 *    SELALU terlihat tanpa harus menggulir ke bawah dokumen.
 *  - Canvas responsif (width:100%, height:auto) — halaman landscape ikut
 *    menyempurnakan lebar kontainer, tidak menimpa halaman sebelahnya.
 *  - Overlay anotasi & gambar area memakai koordinat PERSENTASE agar tetap
 *    presisi pada ukuran tampilan apa pun.
 */

const DATA = window.PDF_VIEWER_DATA || {};
const { draftUrl, catatanUrl, hasCatatan, entryId, csrf, commentsUrl, storeUrl, resolveUrl, replyUrl, deleteUrl, burnUrl, buildFeedbackUrl, canReview, canReply, returnUrl } = DATA;

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
  const resolutionStatus = item.resolution_status || (body.resolved ? 'resolved' : 'open');
  return {
    id: item.id,
    page,
    x1, y1, x2, y2,
    comment: body.value || '',
    reply: item.reply || '',
    resolved: resolutionStatus === 'resolved',
    resolutionStatus,
    isDosen: !!item.is_dosen,
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
  const [numPages, setNumPages] = useState(0);
  const [pageSizes, setPageSizes] = useState([]); // ukuran tampilan tiap halaman (px, sesuai skala)
  const [scale, setScale] = useState(1.4);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [areaMode, setAreaMode] = useState(true); // Mode area ON secara default
  const [drawing, setDrawing] = useState(null); // rect sedang digambar (koordinat ternormalisasi 0-1)
  const [modal, setModal] = useState(null); // { geometry, comment, saving }
  const [selected, setSelected] = useState(null);
  const [allResponded, setAllResponded] = useState(false); // semua komentar dosen sudah ditanggapi
  const [showOverview, setShowOverview] = useState(false); // daftar ringkas komentar dosen (overview)

  const pdfRef = useRef(null);
  const canvasRefs = useRef([]);
  const stageRef = useRef(null);
  const renderGenRef = useRef(0); // penanda generasi render (anti race saat ganti skala cepat)

  const pdfUrl = activeType === 'catatan' ? catatanUrl : draftUrl;

  // ---------------------------------------------------------------- load PDF
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);
    setAnnotations([]);
    setAllResponded(false);
    setNumPages(0);
    setPageSizes([]);
    canvasRefs.current = [];
    if (!pdfUrl) {
      setError('Tidak ada file PDF untuk ditampilkan.');
      setLoading(false);
      return;
    }
    pdfjsLib.getDocument(pdfUrl).promise.then(async (doc) => {
      if (cancelled) return;
      pdfRef.current = doc;

      // Kumpulkan dimensi dasar (skala 1) semua halaman untuk menghitung
      // skala awal "pas lebar panel".
      const base = [];
      for (let i = 1; i <= doc.numPages; i++) {
        const page = await doc.getPage(i);
        const v = page.getViewport({ scale: 1 });
        base.push({ width: v.width, height: v.height });
      }
      if (cancelled) return;

      const stageW = (stageRef.current?.clientWidth ?? 600) - 16; // dikurangi padding p-2
      const fit = base.length ? stageW / base[0].width : 1;
      const s = Math.min(1.4, Math.max(0.5, Math.round(fit * 100) / 100));
      setScale(s);

      const sizes = base.map((b) => ({
        width: Math.floor(b.width * s),
        height: Math.floor(b.height * s),
      }));
      setPageSizes(sizes);
      setNumPages(doc.numPages);
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
  // Render canvas setiap kali skala/jumlah halaman berubah. Penanda generasi
  // mencegah dua loop render paralel menulis ke canvas yang sama.
  useEffect(() => {
    const doc = pdfRef.current;
    if (!doc || numPages === 0 || pageSizes.length !== numPages) return;

    const gen = ++renderGenRef.current;

    (async () => {
      // Tunggu React memasang elemen canvas untuk halaman yang baru muncul.
      await new Promise((resolve) => requestAnimationFrame(resolve));
      for (let i = 0; i < numPages; i++) {
        if (gen !== renderGenRef.current) return; // dibatalkan render lebih baru
        const c = canvasRefs.current[i];
        const size = pageSizes[i];
        if (!c || !size) continue;

        const page = await doc.getPage(i + 1);
        if (gen !== renderGenRef.current) return;
        const viewport = page.getViewport({ scale });

        // Resolusi bitmap mengikuti skala (ketajaman), tampilan responsif
        // mengikuti lebar wrapper (CSS) agar tidak meluber/menimpa.
        c.width = Math.floor(viewport.width);
        c.height = Math.floor(viewport.height);
        c.style.width = '100%';
        c.style.height = 'auto';

        const ctx = c.getContext('2d');
        await page.render({ canvasContext: ctx, viewport }).promise;
      }
    })();
  }, [scale, numPages, pageSizes]);

  // ---------------------------------------------------------------- drag draw
  // Koordinat pointer dikonversi ke fraksi 0-1 relatif ukuran TAMPILAN canvas,
  // bukan piksel mentah — agar akurat meski canvas diskalakan oleh CSS.
  function canvasPoint(e, canvas) {
    const rect = canvas.getBoundingClientRect();
    const cx = (e.touches ? e.touches[0].clientX : e.clientX);
    const cy = (e.touches ? e.touches[0].clientY : e.clientY);
    return {
      x: Math.min(Math.max((cx - rect.left) / rect.width, 0), 1),
      y: Math.min(Math.max((cy - rect.top) / rect.height, 0), 1),
      rw: rect.width,
      rh: rect.height,
    };
  }

  function onMouseDown(e, pageIndex) {
    if (!areaMode) return;
    if (e.target.closest('.anno-box')) return;
    const canvas = canvasRefs.current[pageIndex];
    if (!canvas) return;
    const p = canvasPoint(e, canvas);
    setDrawing({ pageIndex, x1: p.x, y1: p.y, x2: p.x, y2: p.y, rw: p.rw, rh: p.rh });
    e.preventDefault();
  }
  function onMouseMove(e, pageIndex) {
    if (!drawing || drawing.pageIndex !== pageIndex) return;
    const canvas = canvasRefs.current[pageIndex];
    if (!canvas) return;
    const p = canvasPoint(e, canvas);
    setDrawing({ ...drawing, x2: p.x, y2: p.y });
  }
  function onMouseUp(e, pageIndex) {
    if (!drawing || drawing.pageIndex !== pageIndex) return;
    const { x1, y1, x2, y2, rw, rh } = drawing;
    const w = Math.abs(x2 - x1) * rw, h = Math.abs(y2 - y1) * rh;
    if (w < 12 || h < 12) { setDrawing(null); return; }
    const norm = {
      page: pageIndex + 1,
      x1: Math.min(x1, x2), y1: Math.min(y1, y2),
      x2: Math.max(x1, x2), y2: Math.max(y1, y2),
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
      if (!res.ok) throw new Error('HTTP ' + res.status);
      const saved = await res.json();
      setAnnotations((a) => [...a, toAnnotation(saved)]);
      setModal(null);
    } catch (e) {
      alert('Gagal menyimpan anotasi.');
      setModal({ ...modal, saving: false });
    }
  }

  // Simpan komentar dengan tekan Enter (tanpa Shift)
  function onCommentKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      saveAnnotation();
    }
  }

  // ---------------------------------------------------------------- resolve/delete
  async function toggleResolve(id) {
    try {
      const res = await fetch(resolveUrl.replace('{id}', id), {
        method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin',
      });
      if (!res.ok) {
        alert('Gagal mengubah status anotasi. Status: ' + res.status);
        return;
      }
      const d = await res.json();
      setAnnotations((a) => a.map((x) => (x.id === id ? {
        ...x,
        resolved: d.resolution_status === 'resolved',
        resolutionStatus: d.resolution_status,
      } : x)));
      // Tutup dialog anotasi setelah status resolve berhasil diperbarui
      setSelected(null);
    } catch (e) {
      alert('Gagal mengubah status anotasi.');
    }
  }
  // --------------------------------------------- skip ke anotasi dosen berikutnya
  function goToNext() {
    const next = annotations
      .filter((x) => x.isDosen && x.resolutionStatus === 'open' && !x.reply && x.id !== (selected?.id))
      .sort((x, y) => (x.page - y.page) || (y.y1 - x.y1) || (y.x1 - x.x1))[0] || null;
    if (next) {
      setSelected(next);
      scrollToAnnotation(next);
    } else {
      setSelected(null);
      setAllResponded(true);
    }
  }

  async function saveReply(id, reply) {
    if (!replyUrl) return;
    if (!reply.trim()) return;
    try {
      const res = await fetch(replyUrl.replace('{id}', id), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ reply }),
      });
      if (!res.ok) {
        alert('Gagal menyimpan balasan. Status: ' + res.status);
        return;
      }
      const d = await res.json();

      // Perbarui anotasi ini (balasan + status from server, biasanya 'addressed').
      setAnnotations((a) => a.map((x) => (x.id === id ? {
        ...x,
        reply: d.reply || '',
        resolutionStatus: d.resolution_status || x.resolutionStatus,
        resolved: (d.resolution_status || x.resolutionStatus) === 'resolved',
      } : x)));

      // Lanjut otomatis ke anotasi dosen berikutnya yang belum ditanggapi.
      const next = annotations
        .filter((x) => x.id !== id)
        .filter((x) => x.isDosen && x.resolutionStatus === 'open' && !x.reply)
        .sort((x, y) => (x.page - y.page) || (y.y1 - x.y1) || (y.x1 - x.x1))[0] || null;

      if (next) {
        setSelected(next);
        scrollToAnnotation(next);
      } else {
        setSelected(null);
        setAllResponded(true);
      }
    } catch (e) {
      alert('Gagal menyimpan balasan.');
    }
  }

  async function removeAnnotation(id) {
    const res = await fetch(deleteUrl.replace('{id}', id), {
      method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' }, credentials: 'same-origin',
    });
    if (!res.ok) {
      alert('Gagal menghapus anotasi. Status: ' + res.status);
      return;
    }
    setAnnotations((a) => a.filter((x) => x.id !== id));
    setSelected(null);
  }

  // Anotasi per halaman
  const annotationsByPage = useMemo(() => {
    const map = {};
    annotations.forEach((a) => {
      if (!map[a.page]) map[a.page] = [];
      map[a.page].push(a);
    });
    return map;
  }, [annotations]);

  // ---------------------------------------------------------------- antrean tanggapan
  // Komentar milik dosen (terurut halaman lalu posisi) yang masih menunggu tanggapan
  // mahasiswa = status 'open' dan belum ada balasan.
  const unrespondedDosen = useMemo(() => (canReply
    ? annotations
        .filter((a) => a.isDosen && a.resolutionStatus === 'open' && !a.reply)
        .sort((x, y) => (x.page - y.page) || (y.y1 - x.y1) || (x.x1 - x.x1))
    : []), [annotations, canReply]);
  const dosenCommentCount = canReply ? annotations.filter((a) => a.isDosen).length : annotations.length;

  // Scroll halaman ke lokasi anotasi tertentu (continuous scroll).
  function scrollToAnnotation(a) {
    requestAnimationFrame(() => {
      const el = document.getElementById('anno-' + a.id);
      if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  }

  // Kembali ke halaman revisi (manual, via tombol di banner sukses).
  function goBackToRevision() {
    if (returnUrl) window.location.href = returnUrl;
  }

  // ---------------------------------------------------------------- build feedback
  // Tombol "Jadikan Feedback": kompilasi komentar yang belum resolve, simpan ke
  // session (di server), lalu langsung pindah ke Quick Review agar feedback
  // sudah terisi otomatis di textarea.
  async function buildFeedback() {
    if (!buildFeedbackUrl) return;
    try {
      const res = await fetch(buildFeedbackUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        credentials: 'same-origin',
      });
      if (!res.ok) {
        console.error('Build feedback error:', res.status);
        alert('Gagal membuat feedback. Status: ' + res.status + '. Pastikan Anda adalah pembimbing entri ini.');
        return;
      }
      const d = await res.json();
      if (!d.feedback) {
        alert('Tidak ada komentar yang belum resolve.');
        return;
      }
      // Berhasil: langsung pindah ke halaman Quick Review.
      window.location.href = '/quick-review';
    } catch (e) {
      console.error(e);
      alert('Gagal membuat feedback. Periksa koneksi atau coba lagi.');
    }
  }

  return (
    <div className="space-y-4">
      {/* Tabs */}
      <div className="border-b border-border flex gap-1 overflow-x-auto">
        <button onClick={() => setActiveType('draft')}
          className={`px-4 py-2 rounded-t-lg text-sm font-semibold whitespace-nowrap ${activeType === 'draft' ? 'bg-bg-surface dark:bg-bg-surface border-b-2 border-brand' : 'bg-bg-panel dark:bg-bg-panel'}`}>
          {TYPE_LABEL.draft}
        </button>
        {hasCatatan && (
          <button onClick={() => setActiveType('catatan')}
            className={`px-4 py-2 rounded-t-lg text-sm font-semibold whitespace-nowrap ${activeType === 'catatan' ? 'bg-bg-surface dark:bg-bg-surface border-b-2 border-brand' : 'bg-bg-panel dark:bg-bg-panel'}`}>
            {TYPE_LABEL.catatan}
          </button>
        )}
      </div>

      {/* Toolbar — panel PDF di bawah menggulir sendiri (max-h + overflow-auto),
          sehingga toolbar ini dan kedua scrollbar panel SELALU terlihat tanpa
          harus menggulir ke bawah dokumen terlebih dulu. */}
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-sm">Total {numPages || '…'} halaman</span>
        <button onClick={() => setScale((s) => Math.max(0.5, Math.round((s - 0.2) * 100) / 100))}
          title="Perkecil"
          className="px-3 py-1.5 rounded-md bg-bg-panel dark:bg-bg-panel text-sm font-bold leading-none">−</button>
        <span className="text-sm font-medium tabular-nums min-w-[3.25rem] text-center px-1.5 py-1 rounded-md bg-bg-panel dark:bg-bg-panel">
          {Math.round(scale * 100)}%
        </span>
        <button onClick={() => setScale((s) => Math.min(4, Math.round((s + 0.2) * 100) / 100))}
          title="Perbesar"
          className="px-3 py-1.5 rounded-md bg-bg-panel dark:bg-bg-panel text-sm font-bold leading-none">+</button>
        <button onClick={() => setAreaMode((m) => !m)}
          className={`px-3 py-1.5 rounded-md text-sm font-semibold ${areaMode ? 'bg-brand text-white' : 'bg-bg-panel dark:bg-bg-panel'}`}>
          {areaMode ? 'Mode Area: ON' : 'Mode Area: OFF'}
        </button>
        {buildFeedbackUrl && (
          <button onClick={buildFeedback}
            className="px-3 py-1.5 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">
            ⚡ Jadikan Feedback
          </button>
        )}
        {burnUrl && (
          <a href={burnUrl.replace('__TYPE__', activeType)} target="_blank" rel="noopener"
            className="ml-auto px-3 py-1.5 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">
            Unduh PDF dengan Anotasi
          </a>
        )}
      </div>

      {/* Overview scope komentar dosen (hanya untuk mahasiswa pemilik TA) */}
      {canReply && (
        <div className="flex flex-wrap items-center gap-3">
          <span className="px-3 py-1.5 rounded-full bg-bg-panel dark:bg-bg-panel border border-border text-sm">
            Komentar dosen: <span className="font-semibold text-text-primary">{dosenCommentCount}</span>
            {' · '}belum ditanggapi <span className="font-semibold text-status-pending">{unrespondedDosen.length}</span>
          </span>
          {unrespondedDosen.length > 0 && (
            <button onClick={() => setShowOverview((s) => !s)}
              className="px-3 py-1.5 rounded-md bg-bg-panel dark:bg-bg-panel border border-border text-sm font-medium hover:bg-bg-hover">
              {showOverview ? 'Sembunyikan daftar' : 'Lihat daftar komentar'}
            </button>
          )}
        </div>
      )}

      {/* Daftar ringkas komentar dosen yang belum ditanggapi → klik untuk lompat */}
      {canReply && showOverview && unrespondedDosen.length > 0 && (
        <div className="rounded-lg border border-border bg-bg-panel p-3 max-h-64 overflow-y-auto">
          <p className="text-xs font-semibold text-text-secondary mb-2">Komentar dosen yang menunggu tanggapan (urut hal. & posisi)</p>
          <div className="space-y-1.5">
            {unrespondedDosen.map((a) => (
              <button key={a.id}
                onClick={() => { setSelected(a); scrollToAnnotation(a); }}
                className="w-full text-left rounded-md bg-bg-surface dark:bg-bg-surface border border-border px-3 py-2 text-sm hover:bg-bg-hover flex items-center gap-2">
                <span className="text-xs text-text-secondary shrink-0">Hal. {a.page}</span>
                <span className="flex-1 truncate">{a.comment}</span>
                <span className="text-[10px] px-1.5 py-0.5 rounded text-white shrink-0" style={{ backgroundColor: '#D97706' }}>Belum ditanggapi</span>
              </button>
            ))}
          </div>
        </div>
      )}

      {/* Selesai menanggapi: banner sukses + auto-redirect ke halaman revisi */}
      {canReply && allResponded && (
        <div className="rounded-lg border border-status-success/40 bg-status-success/10 p-4 flex flex-wrap items-center justify-between gap-3">
          <div>
            <p className="font-semibold text-text-primary">Semua komentar dosen telah Anda tanggapi</p>
            <p className="text-sm text-text-secondary mt-0.5">
              {unrespondedDosen.length === 0
                ? 'Tidak ada lagi komentar dosen yang menunggu tanggapan pada file ini. Anda akan dialihkan ke halaman revisi.'
                : 'Anda dapat menanggapi sisa komentar atau menutup viewer ini.'}
            </p>
          </div>
          <button onClick={goBackToRevision}
            className="px-4 py-2 rounded-xl bg-brand text-[#0b1420] text-sm font-semibold hover:opacity-90">
            Kembali ke Revisi
          </button>
        </div>
      )}

      {/* Stage: panel bertingkat tetap (max-h + overflow-auto) — scrollbar
          vertikal & horizontal selalu terlihat di tepi panel tanpa harus
          menggulir ke bawah dokumen terlebih dulu. Halaman disusun vertikal;
          lebar tiap halaman mengikuti skala dan dibatasi maxWidth agar halaman
          landscape tidak pernah menimpa halaman lain. */}
      <div ref={stageRef}
        className="bg-bg-surface dark:bg-bg-surface rounded-lg border border-border p-2 overflow-auto max-h-[75vh]">
        {error && (
          <div className="flex items-center justify-center p-8 text-center text-sm text-status-danger">
            {error}
          </div>
        )}
        {!error && (
          <div className="space-y-4">
            {Array.from({ length: numPages }, (_, i) => {
              const pageAnnotations = annotationsByPage[i + 1] || [];
              const size = pageSizes[i];
              return (
                <div key={i}
                  className="relative mx-auto"
                  style={{
                    width: size ? size.width + 'px' : undefined,
                    maxWidth: '100%',
                    touchAction: areaMode ? 'none' : 'auto',
                  }}
                  onMouseDown={(e) => onMouseDown(e, i)}
                  onMouseMove={(e) => onMouseMove(e, i)}
                  onMouseUp={(e) => onMouseUp(e, i)}
                  onTouchStart={(e) => onMouseDown(e, i)}
                  onTouchMove={(e) => onMouseMove(e, i)}
                  onTouchEnd={(e) => onMouseUp(e, i)}>
                  <canvas ref={(el) => { canvasRefs.current[i] = el; }} className="block w-full h-auto" />
                  {/* Lapisan overlay anotasi — posisi dalam % agar presisi pada
                      ukuran tampilan apa pun. */}
                  <div className="absolute inset-0">
                    {pageAnnotations.map((a) => (
                      <div key={a.id}
                        id={'anno-' + a.id}
                        className="anno-box absolute border-2 cursor-pointer"
                        onClick={() => setSelected(a)}
                        style={{
                          left: (a.x1 * 100) + '%',
                          top: (a.y1 * 100) + '%',
                          width: ((a.x2 - a.x1) * 100) + '%',
                          height: ((a.y2 - a.y1) * 100) + '%',
                           borderColor: a.resolutionStatus === 'resolved' ? '#7C9473' : a.resolutionStatus === 'addressed' ? '#D97706' : '#C9A97E',
                        }}>
                        <span className="absolute -top-3 -left-1 text-white text-[10px] px-1 rounded whitespace-nowrap"
                           style={{ backgroundColor: a.resolutionStatus === 'resolved' ? '#7C9473' : a.resolutionStatus === 'addressed' ? '#D97706' : '#C9A97E' }}>
                          {a.resolutionStatus === 'addressed' ? 'Diperbaiki' : a.resolutionStatus === 'resolved' ? 'Selesai' : '#' + a.id}
                        </span>
                      </div>
                    ))}
                    {/* Persegi saat menggambar (persen relatif ukuran tampilan) */}
                    {drawing && drawing.pageIndex === i && (
                      <div className="absolute border-2 border-dashed border-sand bg-sand/20"
                        style={{
                          left: (Math.min(drawing.x1, drawing.x2) * 100) + '%',
                          top: (Math.min(drawing.y1, drawing.y2) * 100) + '%',
                          width: (Math.abs(drawing.x2 - drawing.x1) * 100) + '%',
                          height: (Math.abs(drawing.y2 - drawing.y1) * 100) + '%',
                        }} />
                    )}
                  </div>
                  {/* Label halaman */}
                  <div className="absolute bottom-2 right-2 bg-black/60 text-white text-xs px-2 py-0.5 rounded">
                    Hal. {i + 1}
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      <p className="text-xs text-text-secondary">
        {loading ? 'Memuat PDF…' : areaMode ? 'Seret pada halaman untuk menandai area, lalu tekan Enter untuk menyimpan komentar.' : 'Nyalakan Mode Area untuk menandai area, lalu beri komentar.'}
      </p>

      {/* Modal komentar baru */}
      {modal && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-bg-surface dark:bg-bg-surface rounded-lg border border-border p-4 w-full max-w-md">
            <h3 className="font-semibold mb-2">Komentar pada area ini</h3>
            <textarea rows="3" value={modal.comment} onChange={(e) => setModal({ ...modal, comment: e.target.value })}
              onKeyDown={onCommentKeyDown}
              className="w-full rounded-md border border-border bg-bg-surface dark:bg-bg-surface px-3 py-2 text-sm"
              placeholder="Tulis komentar… (Enter untuk simpan)" autoFocus />
            <p className="text-xs text-text-secondary mt-1">Tekan Enter untuk menyimpan, Shift+Enter untuk baris baru.</p>
            <div className="flex justify-end gap-2 mt-3">
              <button onClick={() => setModal(null)} className="px-3 py-2 rounded-md bg-bg-panel dark:bg-bg-panel text-sm">Batal</button>
              <button onClick={saveAnnotation} disabled={modal.saving}
                className="px-3 py-2 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm">
                {modal.saving ? 'Menyimpan…' : 'Simpan'}
              </button>
            </div>
          </div>
        </div>
      )}

      {/* Modal detail komentar */}
      {selected && (
        <div className="fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
          <div className="bg-bg-surface dark:bg-bg-surface rounded-lg border border-border p-4 w-full max-w-md">
            <h3 className="font-semibold mb-2">Anotasi #{selected.id}</h3>
            {canReply && selected.isDosen && (() => {
              const i = unrespondedDosen.findIndex((a) => a.id === selected.id);
              return i >= 0
                ? <p className="text-xs text-text-secondary mb-1">Komentar dosen {i + 1}/{unrespondedDosen.length} · hal. {selected.page}</p>
                : null;
            })()}
            <p className="text-sm mb-1">{selected.user}</p>
            <p className="text-sm mb-3">{selected.comment}</p>
            {selected.reply && (
              <div className="mb-3 rounded-md bg-bg-panel dark:bg-bg-panel p-3">
                <p className="text-xs font-semibold text-text-secondary mb-1">Balasan Mahasiswa</p>
                <p className="text-sm whitespace-pre-wrap">{selected.reply}</p>
              </div>
            )}
            {canReply && (
              <div className="mb-3">
                <textarea key={selected.id} rows="3" defaultValue={selected.reply} autoFocus
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                      e.preventDefault();
                      saveReply(selected.id, e.target.value.trim());
                    }
                  }}
                  className="w-full rounded-md border border-border bg-bg-surface dark:bg-bg-surface px-3 py-2 text-sm"
                  placeholder="Tulis balasan / penjelasan perbaikan… lalu Enter" />
                <p className="text-xs text-text-secondary mt-1">Enter = kirim balasan & lanjut ke anotasi berikutnya · Shift+Enter = baris baru.</p>
                {unrespondedDosen.length > 0 && (
                  <button onClick={goToNext}
                    className="mt-2 px-3 py-1 rounded-md bg-bg-panel dark:bg-bg-panel text-xs font-medium hover:bg-border">
                    Lewati → anotasi dosen berikutnya
                  </button>
                )}
              </div>
            )}
            <div className="flex items-center gap-2">
              <button onClick={() => toggleResolve(selected.id)}
                className="px-3 py-2 rounded-md bg-sand text-white text-sm">
                {selected.resolutionStatus === 'resolved' ? 'Buka kembali' : selected.resolutionStatus === 'addressed' ? 'Buka kembali' : canReview ? 'Tandai Selesai' : 'Tandai Sudah Diperbaiki'}
              </button>
              <button onClick={() => removeAnnotation(selected.id)}
                className="px-3 py-2 rounded-md bg-status-danger text-white text-sm">Hapus</button>
              <button onClick={() => setSelected(null)}
                className="ml-auto px-3 py-2 rounded-md bg-bg-panel dark:bg-bg-panel text-sm">Tutup</button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

const rootEl = document.getElementById('pdf-viewer-root');
if (rootEl) createRoot(rootEl).render(<PdfViewerApp />);
