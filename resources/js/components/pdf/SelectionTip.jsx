import React, { useLayoutEffect, useRef, useState } from 'react';
import { Plus } from 'lucide-react';
import { usePdfHighlighterContext } from 'react-pdf-highlighter-plus';

/**
 * Tip mengambang ala example-app: muncul tepat di posisi seleksi.
 * Keadaan ringkas: tombol "+ Tambah komentar".
 * Keadaan bentang: textarea + Simpan/Batal.
 *
 * onSave(selection, comment) -> Promise<boolean>: true bila tersimpan,
 * tip ditutup dan ghost dibersihkan; false bila gagal (tip tetap terbuka).
 */
export default function SelectionTip({ onSave }) {
  const [expanded, setExpanded] = useState(false);
  const [comment, setComment] = useState('');
  const [saving, setSaving] = useState(false);
  const selectionRef = useRef(null);

  const { getCurrentSelection, removeGhostHighlight, setTip, updateTipPosition } =
    usePdfHighlighterContext();

  // Posisikan ulang tip setelah bentang (textarea menambah tinggi).
  useLayoutEffect(() => {
    try {
      updateTipPosition?.();
    } catch (e) {
      /* abaikan */
    }
  }, [expanded, updateTipPosition]);

  function close() {
    setExpanded(false);
    setComment('');
    setSaving(false);
    selectionRef.current = null;
    try {
      removeGhostHighlight();
    } catch (e) {
      /* abaikan */
    }
    try {
      setTip(null);
    } catch (e) {
      /* abaikan */
    }
  }

  async function handleSubmit(e) {
    e?.preventDefault();
    const selection = selectionRef.current;
    if (!selection || saving) return;
    setSaving(true);
    const ok = await onSave(selection, comment.trim());
    if (ok) close();
    else setSaving(false);
  }

  function handleKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit();
    } else if (e.key === 'Escape') {
      e.preventDefault();
      close();
    }
  }

  return (
    <div className="rounded-lg border border-border bg-bg-surface p-1 shadow-lg">
      {!expanded ? (
        <button
          type="button"
          onClick={() => {
            const sel = getCurrentSelection();
            if (!sel) return;
            selectionRef.current = sel;
            try {
              sel.makeGhostHighlight();
            } catch (e) {
              /* abaikan */
            }
            setExpanded(true);
          }}
          className="flex items-center gap-1 px-3 py-1.5 rounded-md bg-brand text-white text-sm font-semibold hover:opacity-90"
        >
          <Plus className="h-4 w-4" />
          Tambah komentar
        </button>
      ) : (
        <form className="flex flex-col gap-2 p-2 w-64" onSubmit={handleSubmit}>
          <textarea
            rows={3}
            autoFocus
            value={comment}
            onChange={(e) => setComment(e.target.value)}
            onKeyDown={handleKeyDown}
            placeholder="Tulis komentar… (Enter untuk simpan)"
            className="w-full resize-none rounded-md border border-border bg-bg-surface px-3 py-2 text-sm placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-brand"
          />
          <p className="text-[11px] text-text-secondary">Enter = simpan · Shift+Enter = baris baru · Esc = batal</p>
          <div className="flex gap-2">
            <button
              type="button"
              onClick={close}
              disabled={saving}
              className="flex-1 px-3 py-1.5 rounded-md bg-bg-panel text-sm disabled:opacity-50"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={saving}
              className="flex-1 px-3 py-1.5 rounded-md bg-brand-fill hover:bg-brand-fill-hover text-white text-sm disabled:opacity-50"
            >
              {saving ? 'Menyimpan…' : 'Simpan'}
            </button>
          </div>
        </form>
      )}
    </div>
  );
}
