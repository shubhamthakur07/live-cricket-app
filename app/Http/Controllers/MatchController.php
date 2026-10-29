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

        $state = $match->live_state ?: [
            'inning' => 1,
            'batting_team_number' => 1,
            'bowling_team_number' => 2,
            'striker_id' => null,
            'non_striker_id' => null,
            'bowler_id' => null,
            'this_over' => [],
            'history' => [],
        ];

        $battingPlayers = $match->team1Players;
        $bowlingPlayers = $match->team2Players;

        $notOutBattingPlayers = $battingPlayers->filter(function($p) {
            $d = strtolower(trim((string)$p->dismissal));
            return empty($d) || $d === 'not out';
        });

        if (empty($state['striker_id']) && $notOutBattingPlayers->isNotEmpty()) {
            $state['striker_id'] = $notOutBattingPlayers->first()->id;
        }
        if (empty($state['non_striker_id']) && $notOutBattingPlayers->count() > 1) {
            $state['non_striker_id'] = $notOutBattingPlayers->where('id', '!=', $state['striker_id'])->first()?->id;
        }
        if (empty($state['bowler_id']) && $bowlingPlayers->isNotEmpty()) {
            $state['bowler_id'] = $bowlingPlayers->first()->id;
        }

        $striker = ($state['striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['striker_id']) : null;
        $nonStriker = ($state['non_striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['non_striker_id']) : null;
        $bowler = ($state['bowler_id'] ?? null) ? \App\Models\MatchPlayer::find($state['bowler_id']) : null;

        return view('matches.show', compact('match', 'teams', 'state', 'striker', 'nonStriker', 'bowler'));
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

            // Calculate overs from opponent bowlers
            $opponentTeamNum = $player->team_number == 1 ? 2 : 1;
            $opponentBowlers = \App\Models\MatchPlayer::where('match_id', $match->id)
                ->where('team_number', $opponentTeamNum)
                ->get();
            $totalBalls = 0;
            foreach ($opponentBowlers as $ob) {
                if ($ob->overs_bowled && $ob->overs_bowled !== '0.0') {
                    $parts = explode('.', (string) $ob->overs_bowled);
                    $totalBalls += ((int) ($parts[0] ?? 0) * 6) + (int) ($parts[1] ?? 0);
                }
            }
            $calculatedOvers = floor($totalBalls / 6) . '.' . ($totalBalls % 6);
            $existingOvers = $match->getScoreForTeam($player->team_name)?->overs;
            $finalOvers = ($totalBalls > 0) ? $calculatedOvers : ($existingOvers ?: '0.0');

            $score = $match->scores()->updateOrCreate(
                [
                    'match_id' => $match->id,
                    'inning_number' => $player->team_number,
                ],
                [
                    'batting_team' => $player->team_name,
                    'runs' => $teamTotalRuns,
                    'wickets' => $teamTotalWickets,
                    'overs' => $finalOvers,
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
     * CricHeroes Authentic Ball-By-Ball Scoring Action.
     */
    public function recordBall(Request $request, CricketMatch $match): JsonResponse
    {
        $this->authorizeMatch($match);

        $action = $request->input('action', 'run');
        $state = $match->live_state ?: [
            'inning' => 1,
            'batting_team_number' => 1,
            'bowling_team_number' => 2,
            'striker_id' => null,
            'non_striker_id' => null,
            'bowler_id' => null,
            'this_over' => [],
            'history' => [],
        ];

        $battingTeamNumber = $state['batting_team_number'] ?? 1;
        $battingTeamName = $battingTeamNumber == 1 ? $match->team1_name : $match->team2_name;
        $score = $match->scores()->firstOrCreate(
            [
                'match_id' => $match->id,
                'inning_number' => $battingTeamNumber,
            ],
            [
                'batting_team' => $battingTeamName,
                'runs' => 0,
                'wickets' => 0,
                'overs' => '0.0',
                'status' => 'live',
            ]
        );

        // Save previous snapshot for 1-click Undo
        if ($action !== 'undo') {
            $history = $state['history'] ?? [];
            $history[] = [
                'state' => [
                    'striker_id' => $state['striker_id'] ?? null,
                    'non_striker_id' => $state['non_striker_id'] ?? null,
                    'bowler_id' => $state['bowler_id'] ?? null,
                    'this_over' => $state['this_over'] ?? [],
                ],
                'score' => [
                    'runs' => $score->runs,
                    'wickets' => $score->wickets,
                    'overs' => $score->overs,
                ],
                'striker_stats' => ($state['striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['striker_id'])?->only(['runs_scored', 'balls_faced', 'fours', 'sixes', 'dismissal']) : null,
                'bowler_stats' => ($state['bowler_id'] ?? null) ? \App\Models\MatchPlayer::find($state['bowler_id'])?->only(['overs_bowled', 'maidens', 'runs_conceded', 'wickets_taken']) : null,
            ];
            if (count($history) > 20) array_shift($history);
            $state['history'] = $history;
        }

        // Action: Select Openers & Bowler
        if ($action === 'select_openers') {
            $state['striker_id'] = $request->input('striker_id') ? (int) $request->input('striker_id') : null;
            $state['non_striker_id'] = $request->input('non_striker_id') ? (int) $request->input('non_striker_id') : null;
            $state['bowler_id'] = $request->input('bowler_id') ? (int) $request->input('bowler_id') : null;
            $state['this_over'] = [];
            $match->update(['live_state' => $state, 'status' => 'live']);

            return $this->cricHeroesResponse($match, 'Openers & Opening Bowler ready! Game ON 🏏');
        }

        // Action: Select / Change Bowler
        if ($action === 'select_bowler') {
            $state['bowler_id'] = (int) $request->input('bowler_id');
            $state['this_over'] = [];
            $state['needs_new_bowler'] = false;
            $match->update(['live_state' => $state]);

            return $this->cricHeroesResponse($match, 'New Bowler selected! 🎯');
        }

        // Action: Swap Strike (Batter Strike Rotation)
        if ($action === 'swap_strike') {
            $tmp = $state['striker_id'];
            $state['striker_id'] = $state['non_striker_id'];
            $state['non_striker_id'] = $tmp;
            $match->update(['live_state' => $state]);

            return $this->cricHeroesResponse($match, 'Strike rotated! 🔄');
        }

        // Action: Undo Last Ball
        if ($action === 'undo') {
            $history = $state['history'] ?? [];
            if (!empty($history)) {
                $last = array_pop($history);
                $state['striker_id'] = $last['state']['striker_id'];
                $state['non_striker_id'] = $last['state']['non_striker_id'];
                $state['bowler_id'] = $last['state']['bowler_id'];
                $state['this_over'] = $last['state']['this_over'];
                $state['history'] = $history;
                $state['needs_new_bowler'] = false;

                $score->update($last['score']);

                if (!empty($last['striker_stats']) && $state['striker_id']) {
                    \App\Models\MatchPlayer::where('id', $state['striker_id'])->update($last['striker_stats']);
                }
                if (!empty($last['bowler_stats']) && $state['bowler_id']) {
                    \App\Models\MatchPlayer::where('id', $state['bowler_id'])->update($last['bowler_stats']);
                }

                $match->update(['live_state' => $state]);
                return $this->cricHeroesResponse($match, 'Last delivery undone! ↩️');
            }
            return $this->cricHeroesResponse($match, 'Nothing to undo.');
        }

        // Auto-select openers if not selected yet
        $battingPlayers = $match->players()->where('team_number', $battingTeamNumber)->get();
        $bowlingPlayers = $match->players()->where('team_number', $battingTeamNumber == 1 ? 2 : 1)->get();

        $notOutBattingPlayers = $battingPlayers->filter(function($p) {
            $d = strtolower(trim((string)$p->dismissal));
            return empty($d) || $d === 'not out';
        });

        if (empty($state['striker_id']) && $notOutBattingPlayers->isNotEmpty()) {
            $state['striker_id'] = $notOutBattingPlayers->first()->id;
        }
        if (empty($state['non_striker_id']) && $notOutBattingPlayers->count() > 1) {
            $state['non_striker_id'] = $notOutBattingPlayers->where('id', '!=', $state['striker_id'])->first()?->id;
        }
        if (empty($state['bowler_id']) && $bowlingPlayers->isNotEmpty()) {
            $state['bowler_id'] = $bowlingPlayers->first()->id;
        }

        $striker = ($state['striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['striker_id']) : null;
        $nonStriker = ($state['non_striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['non_striker_id']) : null;
        $bowler = ($state['bowler_id'] ?? null) ? \App\Models\MatchPlayer::find($state['bowler_id']) : null;

        $isLegalDelivery = false;
        $overBallText = '0';
        $runsScored = 0;
        $isWicket = false;
        $overCompleted = false;

        // Normal Run (0, 1, 2, 3, 4, 6)
        if ($action === 'run') {
            $runsScored = (int) $request->input('runs', 0);
            $isLegalDelivery = true;
            $overBallText = (string) $runsScored;

            if ($striker) {
                $striker->runs_scored += $runsScored;
                $striker->balls_faced += 1;
                if ($runsScored === 4) $striker->fours += 1;
                if ($runsScored === 6) $striker->sixes += 1;
                $striker->save();
                $striker->user?->recalculateCricketStats();
            }

            if ($bowler) {
                $bowler->runs_conceded += $runsScored;
                $bowler = $this->incrementBowlerBall($bowler);
                $bowler->save();
                $bowler->user?->recalculateCricketStats();
            }

            $score->runs += $runsScored;
            $score->overs = $this->incrementOversString($score->overs);
            $score->save();

            // Odd runs rotate strike!
            if (in_array($runsScored, [1, 3, 5])) {
                $tmp = $state['striker_id'];
                $state['striker_id'] = $state['non_striker_id'];
                $state['non_striker_id'] = $tmp;
            }
        } 
        // Wide or No Ball (Extras)
        elseif ($action === 'wide' || $action === 'no_ball') {
            $extraRuns = (int) $request->input('runs', 1);
            $overBallText = ($action === 'wide') ? ($extraRuns > 1 ? "{$extraRuns}WD" : 'WD') : ($extraRuns > 1 ? "{$extraRuns}NB" : 'NB');
            $isLegalDelivery = false;

            if ($bowler) {
                $bowler->runs_conceded += $extraRuns;
                $bowler->save();
                $bowler->user?->recalculateCricketStats();
            }

            $score->runs += $extraRuns;
            $score->save();
        } 
        // Byes / Leg Byes
        elseif ($action === 'bye' || $action === 'leg_bye') {
            $runs = (int) $request->input('runs', 1);
            $isLegalDelivery = true;
            $overBallText = ($action === 'bye') ? "{$runs}B" : "{$runs}LB";

            if ($striker) {
                $striker->balls_faced += 1;
                $striker->save();
            }
            if ($bowler) {
                $bowler = $this->incrementBowlerBall($bowler);
                $bowler->save();
            }

            $score->runs += $runs;
            $score->overs = $this->incrementOversString($score->overs);
            $score->save();

            if (in_array($runs, [1, 3])) {
                $tmp = $state['striker_id'];
                $state['striker_id'] = $state['non_striker_id'];
                $state['non_striker_id'] = $tmp;
            }
        } 
        // Wicket
        elseif ($action === 'wicket') {
            $isLegalDelivery = true;
            $isWicket = true;
            $overBallText = 'W';
            $howOut = $request->input('how_out', 'bowled');
            $dismissalText = $howOut;
            if ($bowler && !in_array($howOut, ['run out', 'timed out'])) {
                $dismissalText .= " b {$bowler->player_name}";
            }

            if ($striker) {
                $striker->balls_faced += 1;
                $striker->dismissal = $dismissalText;
                $striker->save();
                $striker->user?->recalculateCricketStats();
            }

            if ($bowler && $howOut !== 'run out') {
                $bowler->wickets_taken += 1;
                $bowler = $this->incrementBowlerBall($bowler);
                $bowler->save();
                $bowler->user?->recalculateCricketStats();
            } elseif ($bowler) {
                $bowler = $this->incrementBowlerBall($bowler);
                $bowler->save();
            }

            $score->wickets += 1;
            $score->overs = $this->incrementOversString($score->overs);
            $score->save();

            // Next batsman: if not specified, automatically find next available batsman in order
            $nextId = $request->input('next_batsman_id');
            if (empty($nextId)) {
                $battingSquad = $score->inning_number == 1 ? $match->team1Players : $match->team2Players;
                $nextBatter = $battingSquad->filter(function ($p) use ($striker, $state) {
                    if ($striker && $p->id == $striker->id) return false;
                    if (!empty($state['non_striker_id']) && $p->id == $state['non_striker_id']) return false;
                    $d = strtolower(trim((string)$p->dismissal));
                    return empty($d) || $d === 'not out';
                })->first();

                $nextId = $nextBatter?->id;
            }

            if ($nextId) {
                $state['striker_id'] = (int) $nextId;
            } else {
                // No more batsmen available (All Out / Inning Complete)
                $state['striker_id'] = null;
                $state['all_out'] = true;
            }
        }

        // Add to this_over delivery chips
        $thisOver = $state['this_over'] ?? [];
        $thisOver[] = $overBallText;
        $state['this_over'] = $thisOver;

        // Check if over completed (legal ball % 6 == 0)
        if ($isLegalDelivery) {
            $parts = explode('.', (string) $score->overs);
            $ballsInOver = (int) ($parts[1] ?? 0);
            if ($ballsInOver === 0) {
                $overCompleted = true;
                // Strike automatically rotates for new over!
                $tmp = $state['striker_id'];
                $state['striker_id'] = $state['non_striker_id'];
                $state['non_striker_id'] = $tmp;
                $state['needs_new_bowler'] = true;
            }
        }

        $match->update(['live_state' => $state]);

        return $this->cricHeroesResponse(
            $match,
            $isWicket ? 'WICKET! ☝️' : ($runsScored == 6 ? 'SIX! 💥' : ($runsScored == 4 ? 'FOUR! 🏏' : 'Ball recorded! ⚡')),
            ['over_completed' => $overCompleted, 'wicket' => $isWicket]
        );
    }

    protected function incrementOversString(?string $overs): string
    {
        $parts = explode('.', (string) ($overs ?: '0.0'));
        $ov = (int) ($parts[0] ?? 0);
        $bl = (int) ($parts[1] ?? 0) + 1;
        if ($bl >= 6) {
            $ov += 1;
            $bl = 0;
        }
        return "{$ov}.{$bl}";
    }

    protected function incrementBowlerBall(\App\Models\MatchPlayer $bowler): \App\Models\MatchPlayer
    {
        $parts = explode('.', (string) ($bowler->overs_bowled ?: '0.0'));
        $ov = (int) ($parts[0] ?? 0);
        $bl = (int) ($parts[1] ?? 0) + 1;
        if ($bl >= 6) {
            $ov += 1;
            $bl = 0;
        }
        $bowler->overs_bowled = "{$ov}.{$bl}";
        return $bowler;
    }

    protected function cricHeroesResponse(CricketMatch $match, string $msg, array $extra = []): JsonResponse
    {
        $match->load(['scores', 'players.user']);
        $state = $match->live_state ?: [];
        $striker = ($state['striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['striker_id']) : null;
        $nonStriker = ($state['non_striker_id'] ?? null) ? \App\Models\MatchPlayer::find($state['non_striker_id']) : null;
        $bowler = ($state['bowler_id'] ?? null) ? \App\Models\MatchPlayer::find($state['bowler_id']) : null;

        $score = $match->scores->where('inning_number', $state['batting_team_number'] ?? 1)->first();

        return response()->json(array_merge([
            'status' => 'success',
            'message' => $msg,
            'state' => $state,
            'striker' => $striker,
            'non_striker' => $nonStriker,
            'bowler' => $bowler,
            'score' => $score,
            'match' => $match,
        ], $extra));
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
