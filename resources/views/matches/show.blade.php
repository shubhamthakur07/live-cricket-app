@extends('layouts.app')

@section('header')
    @php
        $isOwner = auth()->check() && (auth()->id() === $match->user_id);
    @endphp
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('matches.index') }}" class="p-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 transition" title="Back to My Matches">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <span class="text-[11px] font-black uppercase text-red-600 tracking-wider">CRICHEROES MATCH ARENA</span>
                <h2 class="font-black text-xl text-gray-900 leading-tight">
                    {{ $match->team1_name }} vs {{ $match->team2_name }}
                </h2>
            </div>
        </div>

        <div class="flex items-center gap-2">
            @if($isOwner)
                <a href="{{ route('matches.edit', $match) }}" class="px-3 py-1.5 rounded-lg bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-bold transition">
                    Match Settings
                </a>
                <form method="POST" action="{{ route('matches.destroy', $match) }}" onsubmit="return confirm('Delete this match?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold transition">
                        Delete
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection

@section('content')
@php
    $isOwner = auth()->check() && (auth()->id() === $match->user_id);
    $score1 = $match->getScoreForTeam($match->team1_name);
    $score2 = $match->getScoreForTeam($match->team2_name);
@endphp

<div class="py-8 bg-slate-900 min-h-screen text-slate-100 font-sans" x-data="{
    scorecardInning: 1, // 1 for team 1 batting, 2 for team 2 batting
    scoreInning: {{ $match->scores->isNotEmpty() ? $match->scores->last()->inning_number : 1 }},
    scoreBattingTeam: '{{ $match->scores->isNotEmpty() ? $match->scores->last()->batting_team : $match->team1_name }}',
    scoreRuns: {{ $match->scores->isNotEmpty() ? $match->scores->last()->runs : 0 }},
    scoreWickets: {{ $match->scores->isNotEmpty() ? $match->scores->last()->wickets : 0 }},
    scoreOvers: '{{ $match->scores->isNotEmpty() ? ($match->scores->last()->overs ?? '0.0') : '0.0' }}',
    scoreStatus: '{{ $match->status }}',
    scoreResult: '{{ $match->match_result ?? '' }}',
    isSaving: false,
    toastMsg: '',
    showToast(msg) {
        this.toastMsg = msg;
        setTimeout(() => { this.toastMsg = ''; }, 3000);
    },
    advanceBall() {
        let parts = (this.scoreOvers || '0.0').toString().split('.');
        let ov = parseInt(parts[0] || 0);
        let bl = parseInt(parts[1] || 0);
        bl += 1;
        if (bl >= 6) {
            ov += 1;
            bl = 0;
        }
        this.scoreOvers = ov + '.' + bl;
    },
    addBallRun(r) {
        this.scoreRuns = parseInt(this.scoreRuns || 0) + r;
        this.advanceBall();
    },
    addBallWicket() {
        if (this.scoreWickets < 10) {
            this.scoreWickets = parseInt(this.scoreWickets || 0) + 1;
        }
        this.advanceBall();
    },
    addExtra(r) {
        this.scoreRuns = parseInt(this.scoreRuns || 0) + r;
        this.showToast('Extra (WD/NB): +' + r + ' run (Ball not counted)');
    },
    undoBall() {
        let parts = (this.scoreOvers || '0.0').toString().split('.');
        let ov = parseInt(parts[0] || 0);
        let bl = parseInt(parts[1] || 0);
        if (bl > 0) {
            bl -= 1;
        } else if (ov > 0) {
            ov -= 1;
            bl = 5;
        }
        this.scoreOvers = ov + '.' + bl;
    },
    addRuns(r) {
        this.scoreRuns = parseInt(this.scoreRuns || 0) + r;
    },
    addWicket() {
        if (this.scoreWickets < 10) {
            this.scoreWickets = parseInt(this.scoreWickets || 0) + 1;
        }
    },
    async saveScore() {
        this.isSaving = true;
        try {
            const res = await fetch('{{ route('matches.quick-score', $match) }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    inning_number: this.scoreInning,
                    batting_team: this.scoreBattingTeam,
                    runs: parseInt(this.scoreRuns),
                    wickets: parseInt(this.scoreWickets),
                    overs: this.scoreOvers,
                    status: this.scoreStatus,
                    match_result: this.scoreResult
                })
            });

            const data = await res.json();
            if (res.ok && data.status === 'success') {
                this.showToast('Scorecard updated! ⚡');
                setTimeout(() => { window.location.reload(); }, 600);
            } else {
                this.showToast(data.message || 'Failed to update score');
            }
        } catch(e) {
            console.error(e);
            this.showToast('Error saving score');
        } finally {
            this.isSaving = false;
        }
    },

    // Player Stats Scorer Modal State
    playerModalOpen: false,
    selectedPlayer: null,
    editRuns: 0,
    editBalls: 0,
    editFours: 0,
    editSixes: 0,
    editDismissal: 'not out',
    editOvers: '0.0',
    editMaidens: 0,
    editRunsConceded: 0,
    editWickets: 0,
    editSyncTeam: true,
    isSavingPlayer: false,

    openPlayerModal(p) {
        this.selectedPlayer = p;
        this.editRuns = parseInt(p.runs_scored || 0);
        this.editBalls = parseInt(p.balls_faced || 0);
        this.editFours = parseInt(p.fours || 0);
        this.editSixes = parseInt(p.sixes || 0);
        this.editDismissal = p.dismissal || 'not out';
        this.editOvers = p.overs_bowled || '0.0';
        this.editMaidens = parseInt(p.maidens || 0);
        this.editRunsConceded = parseInt(p.runs_conceded || 0);
        this.editWickets = parseInt(p.wickets_taken || 0);
        this.playerModalOpen = true;
    },

    async savePlayerStats() {
        if (!this.selectedPlayer) return;
        this.isSavingPlayer = true;
        try {
            const url = '/matches/{{ $match->id }}/players/' + this.selectedPlayer.id + '/stats';
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    runs_scored: parseInt(this.editRuns || 0),
                    balls_faced: parseInt(this.editBalls || 0),
                    fours: parseInt(this.editFours || 0),
                    sixes: parseInt(this.editSixes || 0),
                    dismissal: this.editDismissal,
                    overs_bowled: this.editOvers || '0.0',
                    maidens: parseInt(this.editMaidens || 0),
                    runs_conceded: parseInt(this.editRunsConceded || 0),
                    wickets_taken: parseInt(this.editWickets || 0),
                    sync_team_score: this.editSyncTeam
                })
            });

            const data = await res.json();
            if (res.ok && data.status === 'success') {
                this.showToast(data.message || 'Player stats updated! 🏏');
                this.playerModalOpen = false;
                setTimeout(() => { window.location.reload(); }, 600);
            } else {
                this.showToast(data.message || 'Failed to update player stats');
            }
        } catch(e) {
            console.error(e);
            this.showToast('Error saving player stats');
        } finally {
            this.isSavingPlayer = false;
        }
    }
}">
    <!-- Floating Toast -->
    <div x-cloak x-show="toastMsg" x-transition 
         class="fixed bottom-6 right-6 z-50 bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-5 py-3 rounded-2xl shadow-2xl font-bold text-sm flex items-center gap-2 border border-white/20">
        <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
        <span x-text="toastMsg"></span>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <!-- ============================================== -->
        <!-- CRICHEROES GIANT MATCH BANNER & SCORES -->
        <!-- ============================================== -->
        <div class="bg-[#131b2e] border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl relative overflow-hidden">
            <div class="flex flex-wrap items-center justify-between gap-2 pb-4 border-b border-white/10 text-xs">
                <div>
                    <span class="font-black text-rose-400 uppercase tracking-wider">{{ $match->tournament_name ?? 'Local Tournament' }}</span>
                    <span class="text-slate-400 ml-2">• {{ $match->total_overs }} Overs • {{ $match->ball_type }}</span>
                </div>
                <div>
                    @if($match->status === 'live')
                        <span class="px-3 py-1 rounded-full text-xs font-black bg-red-600/20 text-red-400 border border-red-500/30 flex items-center gap-1.5 animate-pulse">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span> LIVE
                        </span>
                    @elseif($match->status === 'completed')
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-emerald-600/20 text-emerald-400 border border-emerald-500/30">
                            COMPLETED
                        </span>
                    @else
                        <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-600/20 text-amber-400 border border-amber-500/30">
                            UPCOMING
                        </span>
                    @endif
                </div>
            </div>

            <!-- Teams Big Scores Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 my-6">
                <!-- Team 1 -->
                <div class="p-5 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-blue-400 uppercase">Team 1</span>
                        <div class="font-extrabold text-white text-lg sm:text-xl mt-0.5">{{ $match->team1_name }}</div>
                        <div class="text-xs text-slate-400 mt-1">Overs: {{ $score1->overs ?? '0.0' }} / {{ $match->total_overs }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl sm:text-4xl font-black text-white font-mono">
                            {{ $score1 ? "{$score1->runs}/{$score1->wickets}" : '0/0' }}
                        </div>
                    </div>
                </div>

                <!-- Team 2 -->
                <div class="p-5 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] font-bold text-rose-400 uppercase">Team 2</span>
                        <div class="font-extrabold text-white text-lg sm:text-xl mt-0.5">{{ $match->team2_name }}</div>
                        <div class="text-xs text-slate-400 mt-1">Overs: {{ $score2->overs ?? '0.0' }} / {{ $match->total_overs }}</div>
                    </div>
                    <div class="text-right">
                        <div class="text-3xl sm:text-4xl font-black text-white font-mono">
                            {{ $score2 ? "{$score2->runs}/{$score2->wickets}" : '0/0' }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Match Situation Strip -->
            <div class="pt-4 border-t border-white/10 flex flex-wrap items-center justify-between text-xs gap-3">
                <div class="font-bold text-amber-300 flex items-center gap-2">
                    <span>⚡</span>
                    <span>{{ $match->match_result ?: ($match->toss_decision ?: 'Match in progress') }}</span>
                </div>
                <div class="text-slate-400 flex items-center gap-1.5">
                    <span>📍</span>
                    <span>{{ $match->venue ?? 'Ground Venue' }}</span>
                </div>
            </div>

            @if($match->player_of_match)
                <div class="mt-3 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 text-xs font-bold flex items-center gap-2">
                    <span>🏆 Player of the Match:</span>
                    <span>{{ $match->player_of_match }}</span>
                </div>
            @endif
        </div>

        <!-- ============================================================== -->
        <!-- CRICHEROES COMPLETE SCORECARD (INDIVIDUAL RUNS & WICKETS) -->
        <!-- ============================================================== -->
        <div class="bg-[#131b2e] border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-white/10">
                <div>
                    <span class="text-[10px] font-black uppercase text-amber-400 tracking-wider">CRICHEROES SCORECARD</span>
                    <h3 class="font-black text-xl text-white mt-0.5">Player Personal Runs & Wickets</h3>
                    <p class="text-slate-400 text-xs">Detailed individual batting & bowling cards with strike rates, maidens, and economy.</p>
                </div>

                <!-- Innings Switcher Tabs -->
                <div class="flex items-center gap-2 p-1 bg-black/40 rounded-xl border border-white/10 self-start sm:self-auto">
                    <button type="button" 
                            @click="scorecardInning = 1"
                            :class="scorecardInning === 1 ? 'bg-red-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                            class="px-4 py-2 rounded-lg text-xs transition flex items-center gap-1.5">
                        <span>1st Innings: {{ $match->team1_name }}</span>
                        <span class="px-1.5 py-0.2 rounded-md bg-black/30 font-mono text-[10px]">{{ $score1 ? "{$score1->runs}/{$score1->wickets}" : '0/0' }}</span>
                    </button>
                    <button type="button" 
                            @click="scorecardInning = 2"
                            :class="scorecardInning === 2 ? 'bg-red-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                            class="px-4 py-2 rounded-lg text-xs transition flex items-center gap-1.5">
                        <span>2nd Innings: {{ $match->team2_name }}</span>
                        <span class="px-1.5 py-0.2 rounded-md bg-black/30 font-mono text-[10px]">{{ $score2 ? "{$score2->runs}/{$score2->wickets}" : '0/0' }}</span>
                    </button>
                </div>
            </div>

            <!-- ==================== INNINGS 1: TEAM 1 BATTING & TEAM 2 BOWLING ==================== -->
            <div x-show="scorecardInning === 1" class="space-y-6">
                <!-- Batting Table: Team 1 -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-black text-sm text-blue-400 flex items-center gap-2">
                            <span>🏏 {{ $match->team1_name }} Batting Card</span>
                        </h4>
                        <span class="text-xs text-slate-400 font-mono">
                            Overs: {{ $score1->overs ?? '0.0' }} / {{ $match->total_overs }}
                        </span>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-white/10 bg-black/20">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white/5 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-white/10">
                                <tr>
                                    <th class="py-3 px-4">Batter</th>
                                    <th class="py-3 px-3">Dismissal</th>
                                    <th class="py-3 px-3 text-right">R</th>
                                    <th class="py-3 px-3 text-right">B</th>
                                    <th class="py-3 px-3 text-right">4s</th>
                                    <th class="py-3 px-3 text-right">6s</th>
                                    <th class="py-3 px-3 text-right">SR</th>
                                    @if($isOwner)
                                        <th class="py-3 px-4 text-center">Score</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($match->team1Players as $p)
                                    <tr class="hover:bg-white/5 transition">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-md bg-blue-600/30 text-blue-300 font-bold text-[10px] flex items-center justify-center shrink-0">
                                                    {{ strtoupper(substr($p->player_name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <div class="font-extrabold text-white flex items-center gap-1.5">
                                                        @if($p->user_id)
                                                            <a href="{{ route('profile.user', $p->user_id) }}" class="hover:underline text-blue-400">
                                                                {{ $p->player_name }}
                                                            </a>
                                                        @else
                                                            <span>{{ $p->player_name }}</span>
                                                        @endif
                                                        @if($p->is_captain)<span class="px-1 rounded bg-red-600 text-[8px] font-black text-white">C</span>@endif
                                                        @if($p->is_wicketkeeper)<span class="px-1 rounded bg-amber-600 text-[8px] font-black text-white">WK</span>@endif
                                                    </div>
                                                    <div class="text-[9px] text-slate-400">{{ $p->player_role }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-slate-300 text-[11px]">
                                            @if($p->dismissal === 'not out')
                                                <span class="text-emerald-400 font-bold">not out</span>
                                            @elseif($p->dismissal === 'dnb')
                                                <span class="text-slate-500 italic">did not bat</span>
                                            @else
                                                <span class="text-slate-400">{{ $p->dismissal }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right font-black font-mono text-sm">
                                            @if($p->runs_scored >= 100)
                                                <span class="px-2 py-0.5 rounded-md bg-purple-600 text-white shadow-xs">{{ $p->runs_scored }}</span>
                                            @elseif($p->runs_scored >= 50)
                                                <span class="px-2 py-0.5 rounded-md bg-amber-600 text-white shadow-xs">{{ $p->runs_scored }}</span>
                                            @else
                                                <span class="text-white">{{ $p->runs_scored }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->balls_faced }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->fours }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->sixes }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->strike_rate }}</td>
                                        @if($isOwner)
                                            <td class="py-3 px-4 text-center">
                                                <button type="button" 
                                                        @click="openPlayerModal({{ json_encode($p) }})"
                                                        class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-white text-[10px] font-bold transition flex items-center gap-1 mx-auto">
                                                    <span>✏️ Score</span>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="py-4 text-center text-slate-500 italic">No players in {{ $match->team1_name }} squad.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bowling Table: Team 2 Bowlers bowling against Team 1 -->
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between">
                        <h4 class="font-black text-sm text-rose-400 flex items-center gap-2">
                            <span>🎯 {{ $match->team2_name }} Bowling Card</span>
                        </h4>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-white/10 bg-black/20">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white/5 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-white/10">
                                <tr>
                                    <th class="py-3 px-4">Bowler</th>
                                    <th class="py-3 px-3 text-right">O</th>
                                    <th class="py-3 px-3 text-right">M</th>
                                    <th class="py-3 px-3 text-right">R</th>
                                    <th class="py-3 px-3 text-right">W</th>
                                    <th class="py-3 px-3 text-right">ECO</th>
                                    @if($isOwner)
                                        <th class="py-3 px-4 text-center">Score</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($match->team2Players as $b)
                                    <tr class="hover:bg-white/5 transition">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-md bg-rose-600/30 text-rose-300 font-bold text-[10px] flex items-center justify-center shrink-0">
                                                    {{ strtoupper(substr($b->player_name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <div class="font-extrabold text-white flex items-center gap-1.5">
                                                        @if($b->user_id)
                                                            <a href="{{ route('profile.user', $b->user_id) }}" class="hover:underline text-rose-400">
                                                                {{ $b->player_name }}
                                                            </a>
                                                        @else
                                                            <span>{{ $b->player_name }}</span>
                                                        @endif
                                                        @if($b->is_captain)<span class="px-1 rounded bg-red-600 text-[8px] font-black text-white">C</span>@endif
                                                    </div>
                                                    <div class="text-[9px] text-slate-400">{{ $b->player_role }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->overs_bowled ?: '0.0' }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->maidens }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->runs_conceded }}</td>
                                        <td class="py-3 px-3 text-right font-black font-mono text-sm">
                                            @if($b->wickets_taken > 0)
                                                <span class="px-2 py-0.5 rounded-md bg-rose-600 text-white shadow-xs">{{ $b->wickets_taken }}</span>
                                            @else
                                                <span class="text-slate-400">0</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->economy_rate }}</td>
                                        @if($isOwner)
                                            <td class="py-3 px-4 text-center">
                                                <button type="button" 
                                                        @click="openPlayerModal({{ json_encode($b) }})"
                                                        class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-white text-[10px] font-bold transition flex items-center gap-1 mx-auto">
                                                    <span>✏️ Score</span>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-4 text-center text-slate-500 italic">No bowlers in {{ $match->team2_name }} squad.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ==================== INNINGS 2: TEAM 2 BATTING & TEAM 1 BOWLING ==================== -->
            <div x-show="scorecardInning === 2" class="space-y-6">
                <!-- Batting Table: Team 2 -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h4 class="font-black text-sm text-rose-400 flex items-center gap-2">
                            <span>🏏 {{ $match->team2_name }} Batting Card</span>
                        </h4>
                        <span class="text-xs text-slate-400 font-mono">
                            Overs: {{ $score2->overs ?? '0.0' }} / {{ $match->total_overs }}
                        </span>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-white/10 bg-black/20">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white/5 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-white/10">
                                <tr>
                                    <th class="py-3 px-4">Batter</th>
                                    <th class="py-3 px-3">Dismissal</th>
                                    <th class="py-3 px-3 text-right">R</th>
                                    <th class="py-3 px-3 text-right">B</th>
                                    <th class="py-3 px-3 text-right">4s</th>
                                    <th class="py-3 px-3 text-right">6s</th>
                                    <th class="py-3 px-3 text-right">SR</th>
                                    @if($isOwner)
                                        <th class="py-3 px-4 text-center">Score</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($match->team2Players as $p)
                                    <tr class="hover:bg-white/5 transition">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-md bg-rose-600/30 text-rose-300 font-bold text-[10px] flex items-center justify-center shrink-0">
                                                    {{ strtoupper(substr($p->player_name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <div class="font-extrabold text-white flex items-center gap-1.5">
                                                        @if($p->user_id)
                                                            <a href="{{ route('profile.user', $p->user_id) }}" class="hover:underline text-rose-400">
                                                                {{ $p->player_name }}
                                                            </a>
                                                        @else
                                                            <span>{{ $p->player_name }}</span>
                                                        @endif
                                                        @if($p->is_captain)<span class="px-1 rounded bg-red-600 text-[8px] font-black text-white">C</span>@endif
                                                        @if($p->is_wicketkeeper)<span class="px-1 rounded bg-amber-600 text-[8px] font-black text-white">WK</span>@endif
                                                    </div>
                                                    <div class="text-[9px] text-slate-400">{{ $p->player_role }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-slate-300 text-[11px]">
                                            @if($p->dismissal === 'not out')
                                                <span class="text-emerald-400 font-bold">not out</span>
                                            @elseif($p->dismissal === 'dnb')
                                                <span class="text-slate-500 italic">did not bat</span>
                                            @else
                                                <span class="text-slate-400">{{ $p->dismissal }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right font-black font-mono text-sm">
                                            @if($p->runs_scored >= 100)
                                                <span class="px-2 py-0.5 rounded-md bg-purple-600 text-white shadow-xs">{{ $p->runs_scored }}</span>
                                            @elseif($p->runs_scored >= 50)
                                                <span class="px-2 py-0.5 rounded-md bg-amber-600 text-white shadow-xs">{{ $p->runs_scored }}</span>
                                            @else
                                                <span class="text-white">{{ $p->runs_scored }}</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->balls_faced }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->fours }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->sixes }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $p->strike_rate }}</td>
                                        @if($isOwner)
                                            <td class="py-3 px-4 text-center">
                                                <button type="button" 
                                                        @click="openPlayerModal({{ json_encode($p) }})"
                                                        class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-white text-[10px] font-bold transition flex items-center gap-1 mx-auto">
                                                    <span>✏️ Score</span>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="py-4 text-center text-slate-500 italic">No players in {{ $match->team2_name }} squad.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bowling Table: Team 1 Bowlers bowling against Team 2 -->
                <div class="space-y-3 pt-2">
                    <div class="flex items-center justify-between">
                        <h4 class="font-black text-sm text-blue-400 flex items-center gap-2">
                            <span>🎯 {{ $match->team1_name }} Bowling Card</span>
                        </h4>
                    </div>

                    <div class="overflow-x-auto rounded-2xl border border-white/10 bg-black/20">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white/5 text-slate-400 font-bold uppercase text-[10px] tracking-wider border-b border-white/10">
                                <tr>
                                    <th class="py-3 px-4">Bowler</th>
                                    <th class="py-3 px-3 text-right">O</th>
                                    <th class="py-3 px-3 text-right">M</th>
                                    <th class="py-3 px-3 text-right">R</th>
                                    <th class="py-3 px-3 text-right">W</th>
                                    <th class="py-3 px-3 text-right">ECO</th>
                                    @if($isOwner)
                                        <th class="py-3 px-4 text-center">Score</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                @forelse($match->team1Players as $b)
                                    <tr class="hover:bg-white/5 transition">
                                        <td class="py-3 px-4">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-md bg-blue-600/30 text-blue-300 font-bold text-[10px] flex items-center justify-center shrink-0">
                                                    {{ strtoupper(substr($b->player_name, 0, 1)) }}
                                                </div>
                                                <div>
                                                    <div class="font-extrabold text-white flex items-center gap-1.5">
                                                        @if($b->user_id)
                                                            <a href="{{ route('profile.user', $b->user_id) }}" class="hover:underline text-blue-400">
                                                                {{ $b->player_name }}
                                                            </a>
                                                        @else
                                                            <span>{{ $b->player_name }}</span>
                                                        @endif
                                                        @if($b->is_captain)<span class="px-1 rounded bg-red-600 text-[8px] font-black text-white">C</span>@endif
                                                    </div>
                                                    <div class="text-[9px] text-slate-400">{{ $b->player_role }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->overs_bowled ?: '0.0' }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->maidens }}</td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->runs_conceded }}</td>
                                        <td class="py-3 px-3 text-right font-black font-mono text-sm">
                                            @if($b->wickets_taken > 0)
                                                <span class="px-2 py-0.5 rounded-md bg-rose-600 text-white shadow-xs">{{ $b->wickets_taken }}</span>
                                            @else
                                                <span class="text-slate-400">0</span>
                                            @endif
                                        </td>
                                        <td class="py-3 px-3 text-right text-slate-300 font-mono">{{ $b->economy_rate }}</td>
                                        @if($isOwner)
                                            <td class="py-3 px-4 text-center">
                                                <button type="button" 
                                                        @click="openPlayerModal({{ json_encode($b) }})"
                                                        class="px-2.5 py-1 rounded-lg bg-white/10 hover:bg-white/20 text-white text-[10px] font-bold transition flex items-center gap-1 mx-auto">
                                                    <span>✏️ Score</span>
                                                </button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-4 text-center text-slate-500 italic">No bowlers in {{ $match->team1_name }} squad.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- CRICHEROES BALL-BY-BALL FAST SCORER (IF OWNER) -->
        <!-- ============================================== -->
        @if($isOwner)
            <div class="bg-[#131b2e] border border-white/10 rounded-3xl p-6 sm:p-8 shadow-xl space-y-6">
                <div class="flex items-center justify-between pb-4 border-b border-white/10">
                    <div>
                        <h3 class="font-black text-lg text-white">⚡ Quick Team Scorer & Situation</h3>
                        <p class="text-slate-400 text-xs mt-0.5">Quickly increment team totals and match status.</p>
                    </div>
                </div>

                <!-- Inning & Batting Team Tabs -->
                <div class="grid grid-cols-2 gap-3">
                    <button type="button" 
                            @click="scoreInning = 1; scoreBattingTeam = '{{ $match->team1_name }}'"
                            :class="scoreInning === 1 ? 'bg-red-600 text-white font-bold' : 'bg-white/5 text-slate-300'"
                            class="py-3 rounded-xl text-xs transition border border-white/10">
                        1st Innings: {{ $match->team1_name }}
                    </button>
                    <button type="button" 
                            @click="scoreInning = 2; scoreBattingTeam = '{{ $match->team2_name }}'"
                            :class="scoreInning === 2 ? 'bg-red-600 text-white font-bold' : 'bg-white/5 text-slate-300'"
                            class="py-3 rounded-xl text-xs transition border border-white/10">
                        2nd Innings: {{ $match->team2_name }}
                    </button>
                </div>

                <!-- Fast Scoring Buttons (Ball by Ball) -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider">Ball-By-Ball Scoring (Advances Overs Automatically)</span>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-mono font-bold text-amber-400">Overs: <span x-text="scoreOvers"></span></span>
                            <button type="button" @click="undoBall()" class="px-2 py-0.5 rounded bg-white/10 hover:bg-white/20 text-slate-300 text-[10px] font-bold" title="Undo 1 ball">
                                ↩️ Undo Ball
                            </button>
                        </div>
                    </div>
                    <div class="grid grid-cols-4 sm:grid-cols-9 gap-2 text-xs font-black">
                        <button type="button" @click="addBallRun(0)" class="py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white active:scale-95 transition">Dot (0)</button>
                        <button type="button" @click="addBallRun(1)" class="py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white active:scale-95 transition">+1</button>
                        <button type="button" @click="addBallRun(2)" class="py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white active:scale-95 transition">+2</button>
                        <button type="button" @click="addBallRun(3)" class="py-2.5 rounded-xl bg-white/10 hover:bg-white/20 text-white active:scale-95 transition">+3</button>
                        <button type="button" @click="addBallRun(4)" class="py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white active:scale-95 transition">4️⃣ Four</button>
                        <button type="button" @click="addBallRun(6)" class="py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white active:scale-95 transition">6️⃣ Six</button>
                        <button type="button" @click="addBallWicket()" class="py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white active:scale-95 transition">☝️ Wicket</button>
                        <button type="button" @click="addExtra(1)" class="py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white active:scale-95 transition" title="Wide Ball (Extra run, ball not counted)">Wide</button>
                        <button type="button" @click="addExtra(1)" class="py-2.5 rounded-xl bg-orange-600 hover:bg-orange-500 text-white active:scale-95 transition" title="No Ball (Extra run, ball not counted)">No Ball</button>
                    </div>
                </div>

                <!-- Manual Score Inputs -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Total Runs</label>
                        <input type="number" x-model="scoreRuns" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-base text-white font-mono font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Wickets</label>
                        <input type="number" x-model="scoreWickets" min="0" max="10" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-base text-white font-mono font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Overs (e.g. 18.2)</label>
                        <input type="text" x-model="scoreOvers" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-base text-white font-mono font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 mb-1">Status</label>
                        <select x-model="scoreStatus" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white">
                            <option value="live">🔴 Live Now</option>
                            <option value="completed">🏆 Completed</option>
                            <option value="scheduled">⏳ Scheduled</option>
                            <option value="abandoned">🚫 Abandoned</option>
                        </select>
                    </div>
                </div>

                <!-- Result / Commentary Note -->
                <div>
                    <label class="block text-xs font-bold text-slate-400 mb-1">Match Situation / Result Banner</label>
                    <input type="text" x-model="scoreResult" placeholder="e.g. Royal Strikers need 14 runs in 10 balls"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-xs text-white">
                </div>

                <!-- Save Action Button -->
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                    <button type="button" @click="saveScore()" 
                            class="px-8 py-3 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-black text-sm shadow-lg shadow-red-600/30 transition transform active:scale-95">
                        <span x-show="!isSaving">Save & Publish Team Score ⚡</span>
                        <span x-show="isSaving">Updating...</span>
                    </button>
                </div>
            </div>
        @endif

        <!-- ============================================== -->
        <!-- CRICHEROES TEAM SQUADS & PLAYING XI CHIPS -->
        <!-- ============================================== -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Team 1 Squad -->
            <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div>
                        <span class="text-[10px] font-black uppercase text-blue-400">TEAM 1 SQUAD</span>
                        <h4 class="font-black text-base text-white mt-0.5">{{ $match->team1_name }}</h4>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-600/20 text-blue-400 border border-blue-500/30">
                        {{ $match->team1Players->count() }} Players
                    </span>
                </div>

                @if($match->team1Players->isEmpty())
                    <p class="text-xs text-slate-500 italic py-2">No players added to this team squad yet.</p>
                @else
                    <div class="space-y-2">
                        @foreach($match->team1Players as $p)
                            <div class="p-3 rounded-xl bg-white/5 border border-white/5 flex items-center justify-between text-xs hover:border-blue-500/30 transition">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-blue-600 text-white font-black text-xs flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($p->player_name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-white truncate flex items-center gap-1.5">
                                            @if($p->user_id)
                                                <a href="{{ route('profile.user', $p->user_id) }}" class="hover:underline text-blue-400">
                                                    {{ $p->player_name }}
                                                </a>
                                            @else
                                                <span>{{ $p->player_name }}</span>
                                            @endif
                                            @if($p->is_captain)<span class="px-1 rounded bg-red-600 text-[8px] font-black text-white">C</span>@endif
                                            @if($p->is_wicketkeeper)<span class="px-1 rounded bg-amber-600 text-[8px] font-black text-white">WK</span>@endif
                                        </div>
                                        <div class="text-[10px] text-slate-400">{{ $p->player_role }} @if($p->player_phone)• {{ $p->player_phone }}@endif</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <div class="text-right font-mono text-[11px]">
                                        <div class="font-black text-amber-300">
                                            {{ $p->runs_scored }} <span class="text-[9px] text-slate-400">({{ $p->balls_faced }}b)</span>
                                        </div>
                                        @if($p->overs_bowled && $p->overs_bowled !== '0.0')
                                            <div class="text-[10px] text-rose-400">
                                                {{ $p->wickets_taken }}/{{ $p->runs_conceded }} <span class="text-[9px] text-slate-500">({{ $p->overs_bowled }}ov)</span>
                                            </div>
                                        @endif
                                    </div>
                                    @if($isOwner)
                                        <button type="button" 
                                                @click="openPlayerModal({{ json_encode($p) }})"
                                                class="p-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs transition" title="Edit player score">
                                            ✏️
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Team 2 Squad -->
            <div class="p-6 rounded-3xl bg-[#131b2e] border border-white/10 shadow-xl space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-white/10">
                    <div>
                        <span class="text-[10px] font-black uppercase text-rose-400">TEAM 2 SQUAD</span>
                        <h4 class="font-black text-base text-white mt-0.5">{{ $match->team2_name }}</h4>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-600/20 text-rose-400 border border-rose-500/30">
                        {{ $match->team2Players->count() }} Players
                    </span>
                </div>

                @if($match->team2Players->isEmpty())
                    <p class="text-xs text-slate-500 italic py-2">No players added to this team squad yet.</p>
                @else
                    <div class="space-y-2">
                        @foreach($match->team2Players as $p)
                            <div class="p-3 rounded-xl bg-white/5 border border-white/5 flex items-center justify-between text-xs hover:border-rose-500/30 transition">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-8 h-8 rounded-lg bg-rose-600 text-white font-black text-xs flex items-center justify-center shrink-0">
                                        {{ strtoupper(substr($p->player_name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-white truncate flex items-center gap-1.5">
                                            @if($p->user_id)
                                                <a href="{{ route('profile.user', $p->user_id) }}" class="hover:underline text-rose-400">
                                                    {{ $p->player_name }}
                                                </a>
                                            @else
                                                <span>{{ $p->player_name }}</span>
                                            @endif
                                            @if($p->is_captain)<span class="px-1 rounded bg-red-600 text-[8px] font-black text-white">C</span>@endif
                                            @if($p->is_wicketkeeper)<span class="px-1 rounded bg-amber-600 text-[8px] font-black text-white">WK</span>@endif
                                        </div>
                                        <div class="text-[10px] text-slate-400">{{ $p->player_role }} @if($p->player_phone)• {{ $p->player_phone }}@endif</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <div class="text-right font-mono text-[11px]">
                                        <div class="font-black text-amber-300">
                                            {{ $p->runs_scored }} <span class="text-[9px] text-slate-400">({{ $p->balls_faced }}b)</span>
                                        </div>
                                        @if($p->overs_bowled && $p->overs_bowled !== '0.0')
                                            <div class="text-[10px] text-rose-400">
                                                {{ $p->wickets_taken }}/{{ $p->runs_conceded }} <span class="text-[9px] text-slate-500">({{ $p->overs_bowled }}ov)</span>
                                            </div>
                                        @endif
                                    </div>
                                    @if($isOwner)
                                        <button type="button" 
                                                @click="openPlayerModal({{ json_encode($p) }})"
                                                class="p-1.5 rounded-lg bg-white/10 hover:bg-white/20 text-white text-xs transition" title="Edit player score">
                                            ✏️
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    </div>

    <!-- ============================================================== -->
    <!-- CRICHEROES PLAYER STATS SCORER MODAL (EDIT RUNS & WICKETS) -->
    <!-- ============================================================== -->
    <div x-cloak x-show="playerModalOpen" 
         class="fixed inset-0 z-50 overflow-y-auto flex items-center justify-center p-4 bg-black/80 backdrop-blur-md"
         @keydown.escape.window="playerModalOpen = false">
        <div class="relative bg-slate-900 border border-white/10 rounded-3xl max-w-lg w-full p-6 sm:p-7 shadow-2xl space-y-5"
             @click.away="playerModalOpen = false">

            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-4 border-b border-white/10">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-red-600 to-rose-600 text-white font-black text-base flex items-center justify-center shadow-lg">
                        🏏
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase text-red-500 tracking-wider">CRICHEROES PLAYER SCORER</span>
                        <h3 class="font-black text-lg text-white" x-text="selectedPlayer ? selectedPlayer.player_name : 'Player'"></h3>
                        <div class="text-[11px] text-slate-400 flex items-center gap-2">
                            <span x-text="selectedPlayer ? selectedPlayer.team_name : ''"></span>
                            <span x-text="selectedPlayer && selectedPlayer.player_phone ? '• 📱 ' + selectedPlayer.player_phone : ''"></span>
                        </div>
                    </div>
                </div>
                <button type="button" @click="playerModalOpen = false" class="text-slate-400 hover:text-white text-lg font-bold">✕</button>
            </div>

            <!-- Batting Card Inputs -->
            <div class="p-4 rounded-2xl bg-white/5 border border-white/5 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase text-amber-400 flex items-center gap-1.5">
                        <span>🏏 Batting Performance (Runs & Balls)</span>
                    </span>
                    <!-- Quick Increment Buttons -->
                    <div class="flex items-center gap-1 text-[10px] font-bold">
                        <button type="button" @click="editRuns = parseInt(editRuns || 0) + 1; editBalls = parseInt(editBalls || 0) + 1" class="px-2 py-0.5 rounded bg-white/10 hover:bg-white/20 text-white">+1</button>
                        <button type="button" @click="editRuns = parseInt(editRuns || 0) + 4; editBalls = parseInt(editBalls || 0) + 1; editFours = parseInt(editFours || 0) + 1" class="px-2 py-0.5 rounded bg-blue-600 text-white">+4</button>
                        <button type="button" @click="editRuns = parseInt(editRuns || 0) + 6; editBalls = parseInt(editBalls || 0) + 1; editSixes = parseInt(editSixes || 0) + 1" class="px-2 py-0.5 rounded bg-purple-600 text-white">+6</button>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Runs Scored</label>
                        <input type="number" x-model="editRuns" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono font-bold text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Balls Faced</label>
                        <input type="number" x-model="editBalls" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">4s (Fours)</label>
                        <input type="number" x-model="editFours" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">6s (Sixes)</label>
                        <input type="number" x-model="editSixes" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-sm">
                    </div>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-slate-400 mb-1">Dismissal Status</label>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <select x-model="editDismissal" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                            <option value="not out">🟢 not out</option>
                            <option value="bowled">🔴 Bowled (b Bowler)</option>
                            <option value="caught">🔴 Caught (c Fielder b Bowler)</option>
                            <option value="lbw">🔴 LBW</option>
                            <option value="run out">🔴 Run Out</option>
                            <option value="stumped">🔴 Stumped (st Keeper b Bowler)</option>
                            <option value="hit wicket">🔴 Hit Wicket</option>
                            <option value="dnb">⚪ dnb (Did not bat)</option>
                        </select>
                        <input type="text" x-model="editDismissal" placeholder="Custom: c Aman b Rohit" 
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-xs text-white">
                    </div>
                </div>
            </div>

            <!-- Bowling Card Inputs -->
            <div class="p-4 rounded-2xl bg-white/5 border border-white/5 space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase text-rose-400 flex items-center gap-1.5">
                        <span>🎯 Bowling Performance (Wickets & Overs)</span>
                    </span>
                    <!-- Quick Increment Buttons -->
                    <div class="flex items-center gap-1 text-[10px] font-bold">
                        <button type="button" @click="editWickets = parseInt(editWickets || 0) + 1" class="px-2 py-0.5 rounded bg-rose-600 text-white">+1 Wicket</button>
                    </div>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Overs (e.g. 4.0)</label>
                        <input type="text" x-model="editOvers" placeholder="4.0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Maidens</label>
                        <input type="number" x-model="editMaidens" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Runs Conceded</label>
                        <input type="number" x-model="editRunsConceded" min="0" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono text-sm">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-400 mb-1">Wickets Taken</label>
                        <input type="number" x-model="editWickets" min="0" max="10" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2 text-white font-mono font-bold text-sm">
                    </div>
                </div>
            </div>

            <!-- Sync Checkbox -->
            <div class="flex items-center gap-2 px-1">
                <input type="checkbox" id="syncTeam" x-model="editSyncTeam" class="rounded bg-slate-950 border-white/20 text-red-600 focus:ring-0">
                <label for="syncTeam" class="text-xs text-slate-300">
                    ⚡ Auto-update Team Total Runs & Wickets from players
                </label>
            </div>

            <!-- Modal Actions -->
            <div class="flex items-center justify-end gap-3 pt-3 border-t border-white/10">
                <button type="button" @click="playerModalOpen = false" class="px-4 py-2 rounded-xl text-xs font-bold text-slate-400 hover:text-white">
                    Cancel
                </button>
                <button type="button" @click="savePlayerStats()" 
                        class="px-6 py-2.5 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-extrabold text-xs shadow-lg shadow-red-600/30 transition transform active:scale-95 flex items-center gap-2">
                    <span x-show="!isSavingPlayer">Save Player Stats & Sync Passport ⚡</span>
                    <span x-show="isSavingPlayer">Saving...</span>
                </button>
            </div>

        </div>
    </div>

</div>
@endsection
