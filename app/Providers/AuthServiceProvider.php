<?php

namespace App\Providers;

use App\Models\CricketMatch as MatchModel;
use App\Policies\MatchPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        MatchModel::class => MatchPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}