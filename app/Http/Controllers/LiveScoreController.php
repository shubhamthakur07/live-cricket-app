<?php

namespace App\Http\Controllers;

use App\Services\CricketDataService;
use Illuminate\Http\Request;

class LiveScoreController extends Controller
{
    public function index(CricketDataService $cricket, Request $request)
    {
        if ($request->get('refresh') === 'true') {
            $cricket->clearMatchCache();
        }

        $matches = $cricket->getCurrentMatches();

        $live = collect($matches)->where('status', 'live')->values();
        $scheduled = collect($matches)->where('status', 'scheduled')->values();
        $completed = collect($matches)->filter(fn($m) => in_array($m['status'] ?? '', ['completed', 'abandoned']))->values();

        // If no scheduled matches found in currentMatches, try to get upcoming matches from all matches
        if ($scheduled->isEmpty()) {
            $upcomingFromAll = $cricket->getUpcomingMatches();
            if (!empty($upcomingFromAll)) {
                $scheduled = collect($upcomingFromAll)->values();
            }
        }

        $cacheTtl = $cricket->getCacheTtl();

        $tab = $request->get('tab');
        if (!in_array($tab, ['live', 'upcoming', 'recent'])) {
            $tab = $live->count() > 0 ? 'live' : ($completed->count() > 0 ? 'recent' : 'upcoming');
        }

        return view('live.index', compact('live', 'scheduled', 'completed', 'matches', 'cacheTtl', 'tab'));
    }

    public function show(string $matchId, CricketDataService $cricket, Request $request)
    {
        $forceRefresh = $request->get('refresh') === 'true';

        if ($forceRefresh) {
            $cricket->clearMatchCache($matchId);
        }

        $match = $cricket->findMatchById($cricket->getCurrentMatches(), $matchId);

        if (!$match) {
            $match = $cricket->getMatchDetails($matchId);
            if (empty($match)) {
                abort(404, 'Match not found.');
            }
        }

        $scorecard = $cricket->getMatchScorecard($matchId);

        return view('live.show', compact('match', 'scorecard', 'forceRefresh'));
    }
}
