@extends('layouts.app')

@section('header')
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('matches.index') }}" class="p-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 transition" title="Back to My Matches">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
            </a>
            <div>
                <span class="text-[11px] font-black uppercase text-red-600 tracking-wider">CRICHEROES MATCH CENTER</span>
                <h2 class="font-black text-xl text-gray-900 leading-tight">
                    {{ $match->team1_name }} vs {{ $match->team2_name }}
                </h2>
            </div>
        </div>

        <div class="flex items-center gap-2">
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
        </div>
    </div>
@endsection

@section('content')
<div class="py-8 bg-slate-900 min-h-screen text-slate-100" x-data="{
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
    }
}">
    <!-- Floating Toast -->
    <div x-cloak x-show="toastMsg" x-transition 
         class="fixed bottom-6 right-6 z-50 bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-5 py-3 rounded-2xl shadow-2xl font-bold text-sm flex items-center gap-2 border border-white/20">
        <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
        <span x-text="toastMsg"></span>
    </div>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        @php
            $score1 = $match->getScoreForTeam($match->team1_name);
            $score2 = $match->getScoreForTeam($match->team2_name);
        @endphp

        <!-- CricHeroes Giant Live Scorecard Card -->
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
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Team 1</span>
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
                        <span class="text-[10px] font-bold text-slate-400 uppercase">Team 2</span>
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

        <!-- CricHeroes Fast Scorer Control Panel -->
        <div class="bg-[#131b2e] border border-white/10 rounded-3xl p-6 sm:p-8 shadow-xl space-y-6">
            <div class="flex items-center justify-between pb-4 border-b border-white/10">
                <div>
                    <h3 class="font-black text-lg text-white">⚡ Ball-By-Ball CricHeroes Scorer</h3>
                    <p class="text-slate-400 text-xs mt-0.5">Tap quick buttons to increment runs and wickets in real-time.</p>
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

            <!-- Fast Scoring Buttons -->
            <div>
                <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Fast Add Runs</span>
                <div class="grid grid-cols-6 gap-2 sm:gap-3 text-sm font-black">
                    <button type="button" @click="addRuns(0)" class="py-3 rounded-xl bg-white/10 hover:bg-white/20 text-white active:scale-95 transition">Dot (0)</button>
                    <button type="button" @click="addRuns(1)" class="py-3 rounded-xl bg-white/10 hover:bg-white/20 text-white active:scale-95 transition">+1</button>
                    <button type="button" @click="addRuns(2)" class="py-3 rounded-xl bg-white/10 hover:bg-white/20 text-white active:scale-95 transition">+2</button>
                    <button type="button" @click="addRuns(4)" class="py-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white active:scale-95 transition">4️⃣ Four</button>
                    <button type="button" @click="addRuns(6)" class="py-3 rounded-xl bg-purple-600 hover:bg-purple-500 text-white active:scale-95 transition">6️⃣ Six</button>
                    <button type="button" @click="addWicket()" class="py-3 rounded-xl bg-rose-600 hover:bg-rose-500 text-white active:scale-95 transition">W ☝️ Wicket</button>
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
                    <span x-show="!isSaving">Save & Publish Score ⚡</span>
                    <span x-show="isSaving">Updating...</span>
                </button>
            </div>
        </div>

        <!-- ============================================== -->
        <!-- CRICHEROES TEAM SQUADS & PLAYING XI -->
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
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/5 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-7 h-7 rounded-lg bg-blue-600 text-white font-black text-xs flex items-center justify-center shrink-0">
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
                                <div class="text-right font-mono text-[11px] shrink-0">
                                    @if($p->runs_scored > 0 || $p->balls_faced > 0)
                                        <span class="font-bold text-amber-300">{{ $p->runs_scored }} ({{ $p->balls_faced }}b)</span>
                                    @endif
                                    @if($p->overs_bowled && $p->overs_bowled !== '0.0')
                                        <span class="text-rose-400 ml-2">{{ $p->wickets_taken }}/{{ $p->runs_conceded }}</span>
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
                            <div class="p-2.5 rounded-xl bg-white/5 border border-white/5 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <div class="w-7 h-7 rounded-lg bg-rose-600 text-white font-black text-xs flex items-center justify-center shrink-0">
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
                                <div class="text-right font-mono text-[11px] shrink-0">
                                    @if($p->runs_scored > 0 || $p->balls_faced > 0)
                                        <span class="font-bold text-amber-300">{{ $p->runs_scored }} ({{ $p->balls_faced }}b)</span>
                                    @endif
                                    @if($p->overs_bowled && $p->overs_bowled !== '0.0')
                                        <span class="text-rose-400 ml-2">{{ $p->wickets_taken }}/{{ $p->runs_conceded }}</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>
@endsection
