import React from 'react';

/** Riwayat pesan per anotasi, dipakai oleh sidebar dan panel highlight. */
export default function AnnotationConversation({ annotation, currentUserId }) {
  const messages = annotation.replies?.length
    ? annotation.replies
    : (annotation.reply ? [{ id: 'legacy', user_id: null, user: 'Mahasiswa', body: annotation.reply }] : []);

  if (!messages.length) return null;

  return (
    <div className="mb-2 space-y-2" aria-label="Percakapan anotasi">
      {messages.map((message, index) => (
        <div key={message.id ?? `legacy-${index}`} className="min-w-0 rounded-md border-l-2 border-brand bg-bg-panel/40 p-2">
          <p className="text-[11px] font-semibold text-text-secondary">
            {message.user_id === currentUserId ? 'Anda' : (message.user || 'Mahasiswa')}
            {message.created_at && <time className="ml-1 font-normal" dateTime={message.created_at}>· {new Date(message.created_at).toLocaleString('id-ID')}</time>}
          </p>
          <p className="break-words [overflow-wrap:anywhere] whitespace-pre-wrap text-sm">{message.body}</p>
        </div>
      ))}
    </div>
  );
}