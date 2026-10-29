<?php

namespace App\Http\Controllers;

use App\Models\CricketMatch;
use App\Models\MatchPost;
use App\Models\PostComment;
use App\Models\Score;
use App\Models\Team;
use App\Services\CricketDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class MatchController extends Controller
{
    /**
     * CricHeroes-style My Matches Center.
     */
    public function index(Request $request): View
    {
        $userId = $request->user()?->id ?? Auth::id();

        $query = CricketMatch::with(['scores']);
        if ($userId) {
            $query->where('user_id', $userId);
        }

        // Status tab filter
        $tab = $request->get('tab', 'all');
        if ($tab === 'live') {
            $query->where('status', 'live');
        } elseif ($tab === 'upcoming' || $tab === 'scheduled') {
            $query->where('status', 'scheduled');
        } elseif ($tab === 'completed') {
            $query->where('status', 'completed');
        }

        $matches = $query->orderByDesc('match_date')
            ->orderByDesc('created_at')
            ->paginate(12);

        // Overall stats for CricHeroes player/manager snapshot
        $allUserMatches = $userId ? CricketMatch::where('user_id', $userId)->get() : collect([]);
        $stats = [
            'total' => $allUserMatches->count(),
            'live' => $allUserMatches->where('status', 'live')->count(),
            'upcoming' => $allUserMatches->where('status', 'scheduled')->count(),
            'completed' => $allUserMatches->where('status', 'completed')->count(),
        ];

        return view('matches.index', compact('matches', 'stats', 'tab'));
    }

    /**
     * Show the form for creating a new match.
     */
    public function create(): View
    {
        $teams = Team::orderBy('name')->get();
        return view('matches.create', compact('teams'));
    }

    /**
     * Store a newly created match in storage.
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'team1_name' => 'required|string|max:255',
            'team2_name' => 'required|string|max:255',
            'tournament_name' => 'nullable|string|max:255',
            'match_type' => 'nullable|string|max:50',
            'total_overs' => 'nullable|integer|min:1|max:100',
            'ball_type' => 'nullable|string|max:50',
            'venue' => 'nullable|string|max:255',
            'match_date' => 'required|date',
            'status' => 'required|in:scheduled,live,completed,abandoned',
            'toss_decision' => 'nullable|string|max:255',
            'team1_runs' => 'nullable|integer|min:0',
            'team1_wickets' => 'nullable|integer|min:0|max:10',
            'team1_overs' => 'nullable|string|max:10',
            'team2_runs' => 'nullable|integer|min:0',
            'team2_wickets' => 'nullable|integer|min:0|max:10',
            'team2_overs' => 'nullable|string|max:10',
            'match_result' => 'nullable|string|max:255',
            'player_of_match' => 'nullable|string|max:255',
        ]);

        $userId = $request->user()?->id ?? Auth::id();
        if (!$userId) {
            abort(401);
        }

        $match = CricketMatch::create([
            'user_id' => $userId,
            'team1_name' => $validated['team1_name'],
            'team2_name' => $validated['team2_name'],
            'tournament_name' => $validated['tournament_name'] ?? 'Friendly Match',
            'match_type' => $validated['match_type'] ?? 'T20',
            'total_overs' => $validated['total_overs'] ?? 20,
            'ball_type' => $validated['ball_type'] ?? 'Tennis Ball',
            'venue' => $validated['venue'] ?? 'Local Ground',
            'match_date' => $validated['match_date'],
            'status' => $validated['status'],
            'toss_decision' => $validated['toss_decision'] ?? null,
            'match_result' => $validated['match_result'] ?? null,
            'player_of_match' => $validated['player_of_match'] ?? null,
        ]);

        // Create initial scores if provided or if match is live/completed
        if ($request->filled('team1_runs') || $validated['status'] === 'live' || $validated['status'] === 'completed') {
            Score::create([
                'match_id' => $match->id,
                'inning_number' => 1,
                'batting_team' => $validated['team1_name'],
                'runs' => $request->input('team1_runs', 0),
                'wickets' => $request->input('team1_wickets', 0),
                'overs' => $request->input('team1_overs', '0.0'),
                'status' => $validated['status'] === 'completed' ? 'completed' : 'live',
            ]);
        }

        if ($request->filled('team2_runs') || $validated['status'] === 'completed') {
            Score::create([
                'match_id' => $match->id,
                'inning_number' => 2,
                'batting_team' => $validated['team2_name'],
                'runs' => $request->input('team2_runs', 0),
                'wickets' => $request->input('team2_wickets', 0),
                'overs' => $request->input('team2_overs', '0.0'),
                'status' => $validated['status'] === 'completed' ? 'completed' : 'live',
            ]);
        }

        // Store Team 1 Players
        if ($request->filled('team1_players')) {
            $t1Players = is_array($request->input('team1_players')) 
                ? $request->input('team1_players') 
                : json_decode($request->input('team1_players'), true);
            if (is_array($t1Players)) {
                foreach ($t1Players as $p) {
                    if (!empty($p['player_name'])) {
                        $pUserId = $p['user_id'] ?? null;
                        if (!$pUserId && !empty($p['player_phone'])) {
                            $clean = preg_replace('/[^0-9]/', '', $p['player_phone']);
                            $existing = \App\Models\User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$clean}%"])->first();
                            if ($existing) $pUserId = $existing->id;
                        }
                        \App\Models\MatchPlayer::create([
                            'match_id' => $match->id,
                            'user_id' => $pUserId,
                            'team_number' => 1,
                            'team_name' => $validated['team1_name'],
                            'player_name' => $p['player_name'],
                            'player_phone' => $p['player_phone'] ?? null,
                            'player_role' => $p['player_role'] ?? 'Batter',
                            'is_captain' => !empty($p['is_captain']),
                            'is_wicketkeeper' => !empty($p['is_wicketkeeper']),
                        ]);
                    }
                }
            }
        }

        // Store Team 2 Players
        if ($request->filled('team2_players')) {
            $t2Players = is_array($request->input('team2_players')) 
                ? $request->input('team2_players') 
                : json_decode($request->input('team2_players'), true);
            if (is_array($t2Players)) {
                foreach ($t2Players as $p) {
                    if (!empty($p['player_name'])) {
                        $pUserId = $p['user_id'] ?? null;
                        if (!$pUserId && !empty($p['player_phone'])) {
                            $clean = preg_replace('/[^0-9]/', '', $p['player_phone']);
                            $existing = \App\Models\User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$clean}%"])->first();
                            if ($existing) $pUserId = $existing->id;
                        }
                        \App\Models\MatchPlayer::create([
                            'match_id' => $match->id,
                            'user_id' => $pUserId,
                            'team_number' => 2,
                            'team_name' => $validated['team2_name'],
                            'player_name' => $p['player_name'],
                            'player_phone' => $p['player_phone'] ?? null,
                            'player_role' => $p['player_role'] ?? 'Batter',
                            'is_captain' => !empty($p['is_captain']),
                            'is_wicketkeeper' => !empty($p['is_wicketkeeper']),
                        ]);
                    }
                }
            }
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Match created successfully!',
                'match' => $match,
            ]);
        }

        return redirect()->route('matches.index')->with('status', 'Match created successfully!');
    }

    /**
     * Display the specified match with CricHeroes scoreboard.
     */
    public function show(CricketMatch $match): View
    {
        $match->load(['scores', 'players.user']);
        $teams = Team::orderBy('name')->get();
        return view('matches.show', compact('match', 'teams'));
    }

    /**
     * Show the form for editing the specified match.
     */
    public function edit(CricketMatch $match): View
    {
        $this->authorizeMatch($match);
        $match->load(['players.user']);
        $teams = Team::orderBy('name')->get();
        return view('matches.edit', compact('match', 'teams'));
    }

    /**
     * Update the specified match.
     */
    public function update(Request $request, CricketMatch $match): RedirectResponse|JsonResponse
    {
        $this->authorizeMatch($match);

        $validated = $request->validate([
            'team1_name' => 'required|string|max:255',
            'team2_name' => 'required|string|max:255',
            'tournament_name' => 'nullable|string|max:255',
            'match_type' => 'nullable|string|max:50',
            'total_overs' => 'nullable|integer|min:1|max:100',
            'ball_type' => 'nullable|string|max:50',
            'venue' => 'nullable|string|max:255',
            'match_date' => 'required|date',
            'status' => 'required|in:scheduled,live,completed,abandoned',
            'toss_decision' => 'nullable|string|max:255',
            'match_result' => 'nullable|string|max:255',
            'player_of_match' => 'nullable|string|max:255',
        ]);

        $match->update($validated);

        // Sync Team 1 Players if sent
        if ($request->filled('team1_players')) {
            $t1Players = is_array($request->input('team1_players'))
                ? $request->input('team1_players')
                : json_decode($request->input('team1_players'), true);
            if (is_array($t1Players)) {
                \App\Models\MatchPlayer::where('match_id', $match->id)->where('team_number', 1)->delete();
                foreach ($t1Players as $p) {
                    if (!empty($p['player_name'])) {
                        $pUserId = $p['user_id'] ?? null;
                        if (!$pUserId && !empty($p['player_phone'])) {
                            $clean = preg_replace('/[^0-9]/', '', $p['player_phone']);
                            $existing = \App\Models\User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$clean}%"])->first();
                            if ($existing) $pUserId = $existing->id;
                        }
                        \App\Models\MatchPlayer::create([
                            'match_id' => $match->id,
                            'user_id' => $pUserId,
                            'team_number' => 1,
                            'team_name' => $validated['team1_name'],
                            'player_name' => $p['player_name'],
                            'player_phone' => $p['player_phone'] ?? null,
                            'player_role' => $p['player_role'] ?? 'All-Rounder',
                            'is_captain' => !empty($p['is_captain']),
                            'is_wicketkeeper' => !empty($p['is_wicketkeeper']),
                        ]);
                    }
                }
            }
        }

        // Sync Team 2 Players if sent
        if ($request->filled('team2_players')) {
            $t2Players = is_array($request->input('team2_players'))
                ? $request->input('team2_players')
                : json_decode($request->input('team2_players'), true);
            if (is_array($t2Players)) {
                \App\Models\MatchPlayer::where('match_id', $match->id)->where('team_number', 2)->delete();
                foreach ($t2Players as $p) {
                    if (!empty($p['player_name'])) {
                        $pUserId = $p['user_id'] ?? null;
                        if (!$pUserId && !empty($p['player_phone'])) {
                            $clean = preg_replace('/[^0-9]/', '', $p['player_phone']);
                            $existing = \App\Models\User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$clean}%"])->first();
                            if ($existing) $pUserId = $existing->id;
                        }
                        \App\Models\MatchPlayer::create([
                            'match_id' => $match->id,
                            'user_id' => $pUserId,
                            'team_number' => 2,
                            'team_name' => $validated['team2_name'],
                            'player_name' => $p['player_name'],
                            'player_phone' => $p['player_phone'] ?? null,
                            'player_role' => $p['player_role'] ?? 'All-Rounder',
                            'is_captain' => !empty($p['is_captain']),
                            'is_wicketkeeper' => !empty($p['is_wicketkeeper']),
                        ]);
                    }
                }
            }
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Match updated successfully!',
                'match' => $match,
            ]);
        }

        return redirect()->route('matches.index')->with('status', 'Match updated successfully.');
    }

    /**
     * Quick Score Update (CricHeroes live scorer action).
     */
    public function quickScore(Request $request, CricketMatch $match): JsonResponse
    {
        $this->authorizeMatch($match);

        $validated = $request->validate([
            'inning_number' => 'required|integer|in:1,2',
            'batting_team' => 'required|string|max:255',
            'runs' => 'required|integer|min:0',
            'wickets' => 'required|integer|min:0|max:10',
            'overs' => 'required|string|max:10',
            'status' => 'nullable|string|in:scheduled,live,completed,abandoned',
            'match_result' => 'nullable|string|max:255',
        ]);

        $score = Score::updateOrCreate(
            [
                'match_id' => $match->id,
                'inning_number' => $validated['inning_number'],
            ],
            [
                'batting_team' => $validated['batting_team'],
                'runs' => $validated['runs'],
                'wickets' => $validated['wickets'],
                'overs' => $validated['overs'],
                'status' => ($validated['status'] ?? $match->status) === 'completed' ? 'completed' : 'live',
            ]
        );

        if (!empty($validated['status'])) {
            $match->status = $validated['status'];
        }
        if (!empty($validated['match_result'])) {
            $match->match_result = $validated['match_result'];
        }
        $match->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Live Score updated! ⚡',
            'score' => $score,
            'match' => $match->load('scores'),
        ]);
    }

    /**
     * CricHeroes update individual player runs, wickets, overs & dismissal stats.
     */
    public function updatePlayerStats(Request $request, CricketMatch $match, \App\Models\MatchPlayer $player): JsonResponse
    {
        $this->authorizeMatch($match);

        $validated = $request->validate([
            'runs_scored' => 'required|integer|min:0',
            'balls_faced' => 'required|integer|min:0',
            'fours' => 'nullable|integer|min:0',
            'sixes' => 'nullable|integer|min:0',
            'dismissal' => 'nullable|string|max:100',
            'overs_bowled' => 'nullable|string|max:10',
            'maidens' => 'nullable|integer|min:0',
            'runs_conceded' => 'nullable|integer|min:0',
            'wickets_taken' => 'nullable|integer|min:0|max:10',
            'sync_team_score' => 'nullable|boolean',
        ]);

        $player->update([
            'runs_scored' => $validated['runs_scored'],
            'balls_faced' => $validated['balls_faced'],
            'fours' => $validated['fours'] ?? 0,
            'sixes' => $validated['sixes'] ?? 0,
            'dismissal' => $validated['dismissal'] ?: 'not out',
            'overs_bowled' => $validated['overs_bowled'] ?: '0.0',
            'maidens' => $validated['maidens'] ?? 0,
            'runs_conceded' => $validated['runs_conceded'] ?? 0,
            'wickets_taken' => $validated['wickets_taken'] ?? 0,
        ]);

        // Auto-link registered user if phone matches
        $user = $player->user;
        if (!$user && $player->player_phone) {
            $clean = preg_replace('/[^0-9]/', '', $player->player_phone);
            $user = \App\Models\User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$clean}%"])->first();
            if ($user) {
                $player->update(['user_id' => $user->id]);
            }
        }

        // Recalculate player's career passport stats
        if ($user) {
            $user->recalculateCricketStats();
        }

        // Optional: auto-sync team totals
        if (!empty($validated['sync_team_score'])) {
            $teamPlayers = \App\Models\MatchPlayer::where('match_id', $match->id)
                ->where('team_number', $player->team_number)
                ->get();
            $teamTotalRuns = $teamPlayers->sum('runs_scored');
            $teamTotalWickets = $teamPlayers->filter(fn($p) => $p->dismissal !== 'not out' && $p->dismissal !== 'dnb')->count();

            $score = $match->scores()->updateOrCreate(
                [
                    'match_id' => $match->id,
                    'inning_number' => $player->team_number,
                ],
                [
                    'batting_team' => $player->team_name,
                    'runs' => $teamTotalRuns,
                    'wickets' => $teamTotalWickets,
                    'overs' => $match->getScoreForTeam($player->team_name)?->overs ?? '0.0',
                    'status' => $match->status === 'completed' ? 'completed' : 'live',
                ]
            );
        }

        return response()->json([
            'status' => 'success',
            'message' => "Stats updated for {$player->player_name}! 🏏",
            'player' => $player->fresh()->load('user'),
        ]);
    }

    /**
     * Remove the specified match from storage.
     */
    public function destroy(CricketMatch $match): RedirectResponse
    {
        $this->authorizeMatch($match);
        $match->delete();

        return redirect()->route('matches.index')->with('status', 'Match deleted successfully.');
    }

    /**
     * Custom match authorization check.
     */
    protected function authorizeMatch(CricketMatch $match): void
    {
        $userId = Auth::id();
        if (!$userId || $match->user_id !== $userId) {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Dashboard view.
     */
    public function dashboard(Request $request, CricketDataService $cricket): View
    {
        $userId = $request->user()?->id ?? Auth::id();

        $userMatches = $userId
            ? CricketMatch::with(['scores'])
                ->where('user_id', $userId)
                ->orderByDesc('match_date')
                ->get()
            : collect([]);

        $totalMatches = $userMatches->count();
        $liveMatches = $userMatches->where('status', 'live')->count();

        // 1. Fetch real-time Cricbuzz editorial news
        try {
            $newsArticles = $cricket->getCricketNews();
            if (!empty($newsArticles)) {
                foreach (array_slice($newsArticles, 0, 10) as $news) {
                    $newsTitle = $news['title'] ?? '';
                    $newsImage = $news['image'] ?? null;
                    $newsStory = $news['story'] ?? '';
                    $newsContext = $news['context'] ?? 'CRICKET NEWS';

                    if (!empty($newsTitle)) {
                        $fullContent = "📰 **" . $newsTitle . "**\n\n" . $newsStory;
                        $existingPost = MatchPost::where('content', 'LIKE', '%' . substr($newsTitle, 0, 30) . '%')->first();

                        if (!$existingPost) {
                            MatchPost::create([
                                'match_id' => '147942',
                                'user_id' => null,
                                'user_name' => 'Cricbuzz Editorial',
                                'avatar_color' => 'emerald',
                                'content' => $fullContent,
                                'post_type' => 'news',
                                'media_url' => $newsImage,
                                'insta_url' => null,
                                'team_tag' => strtoupper($newsContext),
                                'likes_count' => rand(15, 85),
                            ]);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // News sync optional
        }

        // 2. Fetch all real live matches from Cricbuzz
        $cricbuzzMatches = collect([]);
        try {
            $fetched = $cricket->getCurrentMatches();
            $cricbuzzMatches = collect($fetched)->map(function ($item) {
                return [
                    'id' => (string) ($item['id'] ?? 'cric_' . rand(1000, 9999)),
                    'title' => $item['title'] ?? 'Cricket Match',
                    'name' => $item['title'] ?? ($item['name'] ?? 'Cricket Match'),
                    'team1' => $item['team1'] ?? 'Team A',
                    'team2' => $item['team2'] ?? 'Team B',
                    'teams' => [$item['team1'] ?? 'Team A', $item['team2'] ?? 'Team B'],
                    'score1' => $item['score1'] ?? '',
                    'score2' => $item['score2'] ?? '',
                    'status' => $item['status'] ?? 'Live',
                    'series' => $item['series'] ?? 'Live Series',
                ];
            });
        } catch (\Throwable $e) {
            $cricbuzzMatches = collect([]);
        }

        $allPosts = MatchPost::with('comments')
            ->latest()
            ->take(60)
            ->get();

        $allPosts->transform(function ($post) use ($cricbuzzMatches) {
            $matched = $cricbuzzMatches->firstWhere('id', (string) $post->match_id);
            $post->match_name = $matched ? $matched['title'] : ($post->team_tag ? $post->team_tag . ' Match' : 'Live Cricket Match');
            $post->series_name = $matched ? $matched['series'] : 'Cricket Series';
            $post->match_status = $matched ? $matched['status'] : 'Live';
            return $post;
        });

        return view('dashboard', [
            'totalMatches' => $totalMatches,
            'liveMatches' => $liveMatches,
            'cricbuzzMatches' => $cricbuzzMatches,
            'allPosts' => $allPosts,
        ]);
    }
}
