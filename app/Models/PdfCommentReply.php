<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfCommentReply extends Model
{
    protected $fillable = ['user_id', 'body'];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(PdfComment::class, 'pdf_comment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}