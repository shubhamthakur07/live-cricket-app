<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CricketMatch;
use App\Models\MatchPlayer;
use App\Models\User;
use App\Services\CricketDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MatchesController extends Controller
{
    public function current(CricketDataService $cricket): JsonResponse
    {
        $matches = $cricket->getCurrentMatches();
        return response()->json($matches);
    }

    public function summary(CricketDataService $cricket, Request $request): JsonResponse
    {
        if ($request->get('refresh') === 'true') {
            $cricket->clearMatchCache();
        }

        $matches = $cricket->getCurrentMatches();

        $live = collect($matches)->where('status', 'live')->values();
        $scheduled = collect($matches)->where('status', 'scheduled')->values();
        $completed = collect($matches)->filter(fn($m) => in_array($m['status'] ?? '', ['completed', 'abandoned']))->values();

        if ($scheduled->isEmpty()) {
            $upcomingFromAll = $cricket->getUpcomingMatches();
            if (!empty($upcomingFromAll)) {
                $scheduled = collect($upcomingFromAll)->values();
            }
        }

        return response()->json([
            'live' => $live,
            'scheduled' => $scheduled,
            'completed' => $completed,
            'all' => $matches,
            'cacheTtl' => $cricket->getCacheTtl(),
        ]);
    }

    public function show(string $matchId, CricketDataService $cricket): JsonResponse
    {
        $matches = $cricket->getCurrentMatches();
        $match = $cricket->findMatchById($matches, $matchId);

        if (!$match) {
            $detail = $cricket->getMatchDetails($matchId);
            if (!empty($detail)) {
                return response()->json($detail);
            }
            return response()->json(['error' => 'Match not found'], 404);
        }

        return response()->json($match);
    }

    public function scorecard(string $matchId, CricketDataService $cricket): JsonResponse
    {
        $scorecard = $cricket->getMatchScorecard($matchId);
        return response()->json($scorecard);
    }

    public function player(string $playerId, Request $request, CricketDataService $cricket): JsonResponse
    {
        $name = (string) $request->get('name', '');
        $data = $cricket->getPlayerProfile($playerId, $name);
        return response()->json($data);
    }

    public function validateKey(CricketDataService $cricket): JsonResponse
    {
        $valid = $cricket->validateApiKey();
        return response()->json(['valid' => $valid]);
    }

    /**
     * Look up a player by WhatsApp / Phone Number or Name (CricHeroes feature).
     */
    public function lookupPlayer(Request $request): JsonResponse
    {
        $query = trim($request->get('query', '') ?: $request->get('phone', ''));
        if (empty($query)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please enter a WhatsApp/phone number or player name.',
            ], 400);
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $query);

        $user = User::query()
            ->when(!empty($cleanPhone) && strlen($cleanPhone) >= 4, function ($q) use ($cleanPhone) {
                $q->where(function ($sub) use ($cleanPhone) {
                    $sub->where('phone', 'LIKE', "%{$cleanPhone}%")
                        ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$cleanPhone}%"]);
                });
            })
            ->orWhere('name', 'LIKE', "%{$query}%")
            ->orWhere('username', 'LIKE', "%{$query}%")
            ->first();

        if ($user) {
            return response()->json([
                'status' => 'success',
                'found' => true,
                'source' => 'user_profile',
                'player' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'handle' => $user->handle,
                    'phone' => $user->phone ?? $query,
                    'avatar' => $user->avatar_url,
                    'role' => $user->player_role ?? 'All-Rounder',
                    'batting_style' => $user->batting_style ?? 'Right Handed Bat',
                    'bowling_style' => $user->bowling_style ?? 'Right Arm Medium',
                    'stats' => [
                        'matches' => (int) $user->matches_played,
                        'runs' => (int) $user->total_runs,
                        'highest_score' => (string) $user->highest_score,
                        'batting_avg' => (float) $user->batting_avg,
                        'strike_rate' => (float) $user->strike_rate,
                        'fifties' => (int) $user->fifties,
                        'hundreds' => (int) $user->hundreds,
                        'wickets' => (int) $user->wickets_taken,
                        'best_bowling' => (string) $user->best_bowling,
                        'economy' => (float) $user->economy_rate,
                    ]
                ]
            ]);
        }

        // Check if player was added in past matches (MatchPlayer history)
        $pastPlayerQuery = MatchPlayer::query();
        if (!empty($cleanPhone) && strlen($cleanPhone) >= 4) {
            $pastPlayerQuery->where(function ($sub) use ($cleanPhone) {
                $sub->where('player_phone', 'LIKE', "%{$cleanPhone}%")
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(player_phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$cleanPhone}%"]);
            });
        } else {
            $pastPlayerQuery->where('player_name', 'LIKE', "%{$query}%");
        }
        $pastPlayer = $pastPlayerQuery->latest()->first();

        if ($pastPlayer) {
            $phoneFilter = $pastPlayer->player_phone ?: $query;
            $matchesPlayedCount = MatchPlayer::where('player_name', $pastPlayer->player_name)
                ->when($pastPlayer->player_phone, fn($q) => $q->orWhere('player_phone', $pastPlayer->player_phone))
                ->count();
            $runsScored = (int) MatchPlayer::where('player_name', $pastPlayer->player_name)
                ->when($pastPlayer->player_phone, fn($q) => $q->orWhere('player_phone', $pastPlayer->player_phone))
                ->sum('runs_scored');
            $wickets = (int) MatchPlayer::where('player_name', $pastPlayer->player_name)
                ->when($pastPlayer->player_phone, fn($q) => $q->orWhere('player_phone', $pastPlayer->player_phone))
                ->sum('wickets_taken');
            $highest = (int) MatchPlayer::where('player_name', $pastPlayer->player_name)
                ->when($pastPlayer->player_phone, fn($q) => $q->orWhere('player_phone', $pastPlayer->player_phone))
                ->max('runs_scored');

            return response()->json([
                'status' => 'success',
                'found' => true,
                'source' => 'past_matches',
                'player' => [
                    'user_id' => $pastPlayer->user_id,
                    'name' => $pastPlayer->player_name,
                    'handle' => '@' . Str::slug($pastPlayer->player_name),
                    'phone' => $pastPlayer->player_phone ?? $query,
                    'avatar' => 'https://ui-avatars.com/api/?name=' . urlencode($pastPlayer->player_name) . '&background=0D8ABC&color=fff',
                    'role' => $pastPlayer->player_role ?? 'All-Rounder',
                    'batting_style' => 'Right Handed Bat',
                    'bowling_style' => 'Right Arm Medium',
                    'stats' => [
                        'matches' => $matchesPlayedCount,
                        'runs' => $runsScored,
                        'highest_score' => (string) $highest,
                        'batting_avg' => $matchesPlayedCount > 0 ? round($runsScored / $matchesPlayedCount, 1) : 0,
                        'strike_rate' => 125.0,
                        'fifties' => 0,
                        'hundreds' => 0,
                        'wickets' => $wickets,
                        'best_bowling' => 'N/A',
                        'economy' => 7.2,
                    ]
                ]
            ]);
        }

        return response()->json([
            'status' => 'success',
            'found' => false,
            'message' => 'Player not registered yet. You can add them with this WhatsApp number.',
            'suggested_phone' => $query,
        ]);
    }

    /**
     * Get squad players for a match.
     */
    public function getMatchPlayers(string|int|CricketMatch $match): JsonResponse
    {
        if (!$match instanceof CricketMatch) {
            $match = CricketMatch::findOrFail($match);
        }
        $players = $match->players()->with('user')->get();
        return response()->json([
            'status' => 'success',
            'team1' => $players->where('team_number', 1)->values(),
            'team2' => $players->where('team_number', 2)->values(),
        ]);
    }

    /**
     * Add a player to a match squad.
     */
    public function addMatchPlayer(Request $request, string|int|CricketMatch $match): JsonResponse
    {
        if (!$match instanceof CricketMatch) {
            $match = CricketMatch::findOrFail($match);
        }

        $validated = $request->validate([
            'team_number' => 'required|in:1,2',
            'player_name' => 'required|string|max:100',
            'player_phone' => 'nullable|string|max:30',
            'player_role' => 'nullable|string|max:50',
            'is_captain' => 'nullable|boolean',
            'is_wicketkeeper' => 'nullable|boolean',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $teamName = $validated['team_number'] == 1 ? $match->team1_name : $match->team2_name;

        $userId = $validated['user_id'] ?? null;
        if (!$userId && !empty($validated['player_phone'])) {
            $clean = preg_replace('/[^0-9]/', '', $validated['player_phone']);
            $existing = User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$clean}%"])->first();
            if ($existing) {
                $userId = $existing->id;
            }
        }

        $player = MatchPlayer::create([
            'match_id' => $match->id,
            'user_id' => $userId,
            'team_number' => $validated['team_number'],
            'team_name' => $teamName,
            'player_name' => $validated['player_name'],
            'player_phone' => $validated['player_phone'] ?? null,
            'player_role' => $validated['player_role'] ?? 'All-Rounder',
            'is_captain' => $validated['is_captain'] ?? false,
            'is_wicketkeeper' => $validated['is_wicketkeeper'] ?? false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "{$player->player_name} added to {$teamName}!",
            'player' => $player->load('user'),
        ]);
    }

    /**
     * Remove a player from match squad.
     */
    public function removeMatchPlayer(string|int|MatchPlayer $player): JsonResponse
    {
        if (!$player instanceof MatchPlayer) {
            $player = MatchPlayer::findOrFail($player);
        }
        $player->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Player removed from squad.',
        ]);
    }
}
