<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CricketMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'user_id',
        'team1_id',
        'team2_id',
        'team1_name',
        'team2_name',
        'match_date',
        'venue',
        'status', // scheduled, live, completed, abandoned
        'external_match_id',
        'tournament_name',
        'match_type',
        'total_overs',
        'ball_type',
        'toss_decision',
        'match_result',
        'player_of_match',
    ];

    protected $casts = [
        'match_date' => 'date',
        'total_overs' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team1(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'match_id')->orderBy('inning_number');
    }

    public function players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function team1Players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id')->where('team_number', 1);
    }

    public function team2Players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id')->where('team_number', 2);
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Get score for a specific team.
     */
    public function getScoreForTeam(string $teamName): ?Score
    {
        return $this->scores->first(function ($score) use ($teamName) {
            return strtolower(trim($score->batting_team)) === strtolower(trim($teamName));
        });
    }

    /**
     * Get formatted score display like "164/4 (18.2 ov)" or "Yet to Bat".
     */
    public function formatTeamScore(string $teamName): string
    {
        $score = $this->getScoreForTeam($teamName);
        if (!$score) {
            return 'Yet to bat';
        }

        $oversStr = $score->overs ? "({$score->overs} ov)" : '';
        return "{$score->runs}/{$score->wickets} {$oversStr}";
    }
}
