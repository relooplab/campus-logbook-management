<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfComment extends Model
{
    use HasFactory;

    /** File types a comment can belong to. */
    public const FILE_TYPE_DRAFT = 'draft';
    public const FILE_TYPE_CATATAN = 'catatan';

    public const FILE_TYPES = [self::FILE_TYPE_DRAFT, self::FILE_TYPE_CATATAN];

    protected $fillable = [
        'logbook_entry_id',
        'user_id',
        'file_type',
        'page_number',
        'pos_x',
        'pos_y',
        'x2',
        'y2',
        'comment',
        'is_resolved',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'pos_x' => 'float',
            'pos_y' => 'float',
            'x2' => 'float',
            'y2' => 'float',
            'is_resolved' => 'boolean',
            'payload' => 'array',
        ];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(LogbookEntry::class, 'logbook_entry_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * True when this comment represents a highlighted area (has geometry).
     */
    public function isArea(): bool
    {
        return $this->pos_x !== null
            && $this->pos_y !== null
            && $this->x2 !== null
            && $this->y2 !== null;
    }

    public function scopeFileType($query, $fileType)
    {
        return $query->where('file_type', $fileType);
    }

    /**
     * Ambil geometri (page + normalized x1,y1,x2,y2) dari payload
     * W3C Web Annotation (selector FragmentSelector media-frags).
     * Kembalikan null bila tidak tersedia.
     */
    public function geometryFromPayload(): ?array
    {
        $payload = $this->payload;
        if (!is_array($payload)) {
            return null;
        }

        $target = $payload['target'] ?? null;
        if (!is_array($target)) {
            return null;
        }

        $selector = $target['selector'] ?? null;
        if (!is_array($selector)) {
            return null;
        }

        $value = $selector['value'] ?? '';
        if (!is_string($value)) {
            return null;
        }

        $page = null;
        $coords = null;
        foreach (explode('&', $value) as $part) {
            if (str_starts_with($part, 'page=')) {
                $page = (int) substr($part, 5);
            } elseif (str_starts_with($part, 'xywh=normalized:')) {
                $coords = array_map('floatval', explode(',', substr($part, 17)));
            }
        }

        if ($page === null || !is_array($coords) || count($coords) < 4) {
            return null;
        }

        return [
            'page' => $page,
            'x1' => $coords[0],
            'y1' => $coords[1],
            'x2' => $coords[2],
            'y2' => $coords[3],
        ];
    }

    /**
     * Sinkronkan kolom geometri dari payload Web Annotation.
     */
    public function syncFromPayload(): void
    {
        $geo = $this->geometryFromPayload();
        if ($geo) {
            $this->page_number = $geo['page'];
            $this->pos_x = $geo['x1'];
            $this->pos_y = $geo['y1'];
            $this->x2 = $geo['x2'];
            $this->y2 = $geo['y2'];
        }

        $body = $this->payload['body'] ?? null;
        if (is_array($body) && isset($body[0]['value'])) {
            $this->comment = $body[0]['value'];
            $this->is_resolved = !empty($body[0]['resolved']);
        }
    }

    /**
     * Bangun payload W3C Web Annotation dari kolom-kolom sederhana
     * (fallback bila payload kosong, mis. data lama).
     */
    public function buildPayloadFromColumns(): array
    {
        return [
            '@context' => 'http://www.w3.org/ns/anno.jsonld',
            'type' => 'Annotation',
            'motivation' => 'commenting',
            'body' => [
                [
                    'type' => 'TextualBody',
                    'value' => $this->comment,
                    'purpose' => 'commenting',
                    'resolved' => (bool) $this->is_resolved,
                ],
            ],
            'target' => [
                'type' => 'SpecificResource',
                'source' => 'urn:logbook-ta:entry:'.$this->logbook_entry_id.':'.$this->file_type,
                'selector' => [
                    'type' => 'FragmentSelector',
                    'conformsTo' => 'http://www.w3.org/TR/media-frags/',
                    'value' => sprintf('page=%d&xywh=normalized:%f,%f,%f,%f', $this->page_number, $this->pos_x, $this->pos_y, $this->x2, $this->y2),
                ],
            ],
        ];
    }
}
