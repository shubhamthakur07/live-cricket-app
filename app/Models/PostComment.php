<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_post_id',
        'user_id',
        'user_name',
        'avatar_color',
        'comment',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(MatchPost::class, 'match_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
