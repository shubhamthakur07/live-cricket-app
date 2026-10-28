<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'user_id',
        'team_number',
        'team_name',
        'player_name',
        'player_phone',
        'player_role',
        'is_captain',
        'is_wicketkeeper',
        'runs_scored',
        'balls_faced',
        'fours',
        'sixes',
        'dismissal',
        'overs_bowled',
        'maidens',
        'runs_conceded',
        'wickets_taken',
    ];

    protected $casts = [
        'is_captain' => 'boolean',
        'is_wicketkeeper' => 'boolean',
        'runs_scored' => 'integer',
        'balls_faced' => 'integer',
        'fours' => 'integer',
        'sixes' => 'integer',
        'maidens' => 'integer',
        'runs_conceded' => 'integer',
        'wickets_taken' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(CricketMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
