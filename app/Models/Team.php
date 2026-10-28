<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code'];

    public function homeMatches(): HasMany
    {
        return $this->hasMany(CricketMatch::class, 'team1_id');
    }

    public function awayMatches(): HasMany
    {
        return $this->hasMany(CricketMatch::class, 'team2_id');
    }
}
