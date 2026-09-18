import React, { useState } from 'react';
import { MessageSquare } from 'lucide-react';

/**
 * Kontrol komentar ala example-app yang menempel pada toolbar bawaan
 * highlight via slot `extraButtons` / `extraPanel` (TextHighlight & AreaHighlight).
 *
 * Panel berisi detail anotasi kampus: penulis, komentar dosen, balasan
 * mahasiswa, form balas (pemilik), tombol Selesai/Buka kembali & Hapus.
 */

export const STATUS_META = {
  open: { label: 'Open', bg: '#C9A97E' },
  addressed: { label: 'Diperbaiki', bg: '#D97706' },
  resolved: { label: 'Selesai', bg: '#7C9473' },
};

export function resolveButtonLabel(meta, canReview) {
  if (!meta) return 'Tandai Selesai';
  if (meta.resolutionStatus === 'resolved' || meta.resolutionStatus === 'addressed') return 'Buka kembali';
  return canReview ? 'Tandai Selesai' : 'Tandai Sudah Diperbaiki';
}

export function useAnnotationControls(highlight, meta, { canReview, canReply, onReply, onToggleResolve, onDelete, onSkipNext, hasNext }) {
  const [open, setOpen] = useState(false);
  const [draft, setDraft] = useState('');
  const [busy, setBusy] = useState(null); // 'reply' | 'resolve' | 'delete' | null

  const status = STATUS_META[meta?.resolutionStatus] || STATUS_META.open;
  const showReplyBox = canReply && meta?.isDosen && meta?.resolutionStatus === 'open' && !meta?.reply;

  async function handleReply() {
    const text = draft.trim();
    if (!text || busy || !meta) return;
    setBusy('reply');
    const ok = await onReply(meta.id, text);
    setBusy(null);
    if (ok) {
      setDraft('');
      setOpen(false);
    }
  }

  async function handleResolve() {
    if (busy || !meta) return;
    setBusy('resolve');
    await onToggleResolve(meta.id);
    setBusy(null);
  }

  async function handleDelete() {
    if (busy || !meta) return;
    setBusy('delete');
    await onDelete(meta.id);
    setBusy(null);
  }

  const button = (
    <button
      type="button"
      aria-label="Komentar anotasi"
      title="Komentar anotasi"
      onClick={(e) => {
        e.stopPropagation();
        setOpen((v) => !v);
      }}
      className="relative flex items-center justify-center rounded p-1 hover:bg-black/10"
    >
      <MessageSquare width={14} height={14} aria-hidden="true" />
      {meta?.resolutionStatus === 'open' && (
        <span className="absolute top-0.5 right-0.5 h-1.5 w-1.5 rounded-full" style={{ backgroundColor: '#D97706' }} aria-hidden="true" />
      )}
    </button>
  );

  const panel = open && meta ? (
    <div
      className="w-72 rounded-md border border-border bg-bg-surface p-3 text-left shadow-lg"
      onClick={(e) => e.stopPropagation()}
    >
      <div className="mb-1.5 flex items-center justify-between gap-2">
        <span className="text-xs font-semibold text-text-primary">
          Anotasi #{meta.id} · Hal. {meta.page}
        </span>
        <span className="text-[10px] px-1.5 py-0.5 rounded text-white shrink-0" style={{ backgroundColor: status.bg }}>
          {status.label}
        </span>
      </div>
      {meta.user && <p className="text-xs text-text-secondary mb-1">{meta.user}</p>}
      <p className="text-sm mb-2 whitespace-pre-wrap">{meta.comment}</p>

      {meta.reply && (
        <div className="mb-2 rounded-md bg-bg-panel p-2">
          <p className="text-[11px] font-semibold text-text-secondary mb-0.5">Balasan Mahasiswa</p>
          <p className="text-sm whitespace-pre-wrap">{meta.reply}</p>
        </div>
      )}

      {showReplyBox && (
        <div className="mb-2">
          <textarea
            rows={2}
            value={draft}
            onChange={(e) => setDraft(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                handleReply();
              }
            }}
            placeholder="Tulis balasan… lalu Enter"
            className="w-full rounded-md border border-border bg-bg-surface px-2.5 py-1.5 text-sm placeholder:text-text-secondary focus:outline-none focus:ring-2 focus:ring-brand"
          />
          <div className="mt-1 flex items-center gap-2">
            <button
              type="button"
              onClick={handleReply}
              disabled={busy === 'reply' || !draft.trim()}
              className="px-2.5 py-1 rounded-md bg-brand text-white text-xs font-semibold disabled:opacity-50"
            >
              {busy === 'reply' ? 'Mengirim…' : 'Kirim balasan'}
            </button>
            {hasNext && (
              <button type="button" onClick={() => onSkipNext?.(meta.id)} className="text-xs text-text-secondary hover:underline">
                Lewati →
              </button>
            )}
          </div>
        </div>
      )}

      <div className="flex items-center gap-1.5">
        <button
          type="button"
          onClick={handleResolve}
          disabled={busy === 'resolve'}
          className="px-2.5 py-1 rounded-md bg-sand text-white text-xs font-semibold disabled:opacity-50"
        >
          {busy === 'resolve' ? '…' : resolveButtonLabel(meta, canReview)}
        </button>
        <button
          type="button"
          onClick={handleDelete}
          disabled={busy === 'delete'}
          className="px-2.5 py-1 rounded-md bg-status-danger text-white text-xs font-semibold disabled:opacity-50"
        >
          {busy === 'delete' ? '…' : 'Hapus'}
        </button>
        <button
          type="button"
          onClick={() => setOpen(false)}
          className="ml-auto px-2.5 py-1 rounded-md bg-bg-panel text-xs"
        >
          Tutup
        </button>
      </div>
    </div>
  ) : null;

  return { button, panel };
}
