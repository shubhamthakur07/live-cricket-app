<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'phone',
        'bio',
        'avatar',
        'favorite_team',
        'player_role',
        'batting_style',
        'bowling_style',
        'matches_played',
        'total_runs',
        'highest_score',
        'batting_avg',
        'strike_rate',
        'fifties',
        'hundreds',
        'wickets_taken',
        'best_bowling',
        'economy_rate',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'batting_avg' => 'decimal:2',
            'strike_rate' => 'decimal:2',
            'economy_rate' => 'decimal:2',
        ];
    }

    /**
     * Posts created by this user.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(MatchPost::class, 'user_id')->latest();
    }

    /**
     * Matches played / performances.
     */
    public function matchPerformances(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'user_id')->latest();
    }

    /**
     * Users who follow this user.
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')->withTimestamps();
    }

    /**
     * Users that this user follows.
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->withTimestamps();
    }

    /**
     * Check if this user is following another user.
     */
    public function isFollowing(int|User $user): bool
    {
        $targetId = is_object($user) ? $user->id : (int) $user;
        return $this->following()->where('following_id', $targetId)->exists();
    }

    /**
     * Get avatar URL or sleek default avatar.
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            if (Str::startsWith($this->avatar, ['http://', 'https://'])) {
                return $this->avatar;
            }
            return asset($this->avatar);
        }

        $initials = urlencode($this->name ?: 'Cricket Fan');
        return "https://ui-avatars.com/api/?name={$initials}&background=0284c7&color=ffffff&bold=true&size=200";
    }

    /**
     * Get formatted username handle.
     */
    public function getHandleAttribute(): string
    {
        if ($this->username) {
            return '@' . ltrim($this->username, '@');
        }
        return '@' . Str::slug($this->name, '_');
    }
}
