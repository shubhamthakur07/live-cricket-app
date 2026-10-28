@extends('layouts.app')

@section('header')
    <div class="flex items-center gap-3">
        <a href="{{ route('matches.index') }}" class="p-2 rounded-xl bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
        </a>
        <div>
            <span class="text-[11px] font-black uppercase text-red-600 tracking-wider">CRICHEROES SETTINGS</span>
            <h2 class="font-black text-xl text-gray-900 leading-tight">Edit Match Details & Playing XI</h2>
        </div>
    </div>
@endsection

@section('content')
<div class="py-8 bg-slate-900 min-h-screen text-slate-100 font-sans" x-data="{
    matchTeam1Name: '{{ addslashes($match->team1_name) }}',
    matchTeam2Name: '{{ addslashes($match->team2_name) }}',
    activeSquadTab: 1, // 1 for team 1, 2 for team 2
    team1Squad: {{ json_encode($match->team1Players->map(fn($p) => [
        'id' => $p->id,
        'player_name' => $p->player_name,
        'player_phone' => $p->player_phone,
        'user_id' => $p->user_id,
        'player_role' => $p->player_role,
        'is_captain' => (bool) $p->is_captain,
        'is_wicketkeeper' => (bool) $p->is_wicketkeeper,
    ])) }},
    team2Squad: {{ json_encode($match->team2Players->map(fn($p) => [
        'id' => $p->id,
        'player_name' => $p->player_name,
        'player_phone' => $p->player_phone,
        'user_id' => $p->user_id,
        'player_role' => $p->player_role,
        'is_captain' => (bool) $p->is_captain,
        'is_wicketkeeper' => (bool) $p->is_wicketkeeper,
    ])) }},
    playerSearchQuery: '',
    isSearchingPlayer: false,
    searchedPlayer: null,
    searchNotFound: false,
    newPlayerName: '',
    newPlayerRole: 'All-Rounder',
    isCaptainTag: false,
    isWkTag: false,
    toastMsg: '',
    showToast(msg) {
        this.toastMsg = msg;
        setTimeout(() => { this.toastMsg = ''; }, 3000);
    },

    // WhatsApp / Phone Player Lookup
    async searchPlayerWhatsApp() {
        const query = this.playerSearchQuery.trim();
        if (!query) {
            this.showToast('Please enter a WhatsApp number or player name');
            return;
        }

        this.isSearchingPlayer = true;
        this.searchedPlayer = null;
        this.searchNotFound = false;

        try {
            const res = await fetch('/api/players/lookup?query=' + encodeURIComponent(query));
            const data = await res.json();

            if (data.status === 'success' && data.found) {
                this.searchedPlayer = data.player;
                this.newPlayerName = data.player.name;
                this.newPlayerRole = data.player.role || 'All-Rounder';
            } else {
                this.searchNotFound = true;
                this.newPlayerName = isNaN(query) ? query : '';
            }
        } catch(e) {
            console.error(e);
            this.showToast('Error looking up player');
        } finally {
            this.isSearchingPlayer = false;
        }
    },

    addPlayerToSquad() {
        const name = (this.newPlayerName || (this.searchedPlayer ? this.searchedPlayer.name : '')).trim();
        if (!name) {
            this.showToast('Player name is required');
            return;
        }

        const playerObj = {
            player_name: name,
            player_phone: this.searchedPlayer ? this.searchedPlayer.phone : this.playerSearchQuery.trim(),
            user_id: this.searchedPlayer ? this.searchedPlayer.user_id : null,
            player_role: this.searchedPlayer ? this.searchedPlayer.role : this.newPlayerRole,
            is_captain: this.isCaptainTag,
            is_wicketkeeper: this.isWkTag,
            stats: this.searchedPlayer ? this.searchedPlayer.stats : null,
            avatar: this.searchedPlayer ? this.searchedPlayer.avatar : null,
        };

        if (this.activeSquadTab === 1) {
            this.team1Squad.push(playerObj);
            this.showToast(name + ' added to ' + (this.matchTeam1Name || 'Team 1') + '! 🏏');
        } else {
            this.team2Squad.push(playerObj);
            this.showToast(name + ' added to ' + (this.matchTeam2Name || 'Team 2') + '! 🏏');
        }

        // Reset
        this.searchedPlayer = null;
        this.searchNotFound = false;
        this.playerSearchQuery = '';
        this.newPlayerName = '';
        this.isCaptainTag = false;
        this.isWkTag = false;
    },

    removePlayer(teamNum, index) {
        if (teamNum === 1) {
            this.team1Squad.splice(index, 1);
        } else {
            this.team2Squad.splice(index, 1);
        }
    }
}">
    <!-- Floating Toast -->
    <div x-cloak x-show="toastMsg" x-transition 
         class="fixed bottom-6 right-6 z-50 bg-gradient-to-r from-emerald-600 to-teal-600 text-white px-5 py-3 rounded-2xl shadow-2xl font-bold text-sm flex items-center gap-2 border border-white/20">
        <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
        <span x-text="toastMsg"></span>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-[#131b2e] border border-white/10 rounded-3xl p-6 sm:p-8 shadow-2xl">
            <form method="POST" action="{{ route('matches.update', $match) }}" class="space-y-5">
                @csrf
                @method('PUT')

                <!-- Hidden inputs for Squad Players -->
                <input type="hidden" name="team1_players" :value="JSON.stringify(team1Squad)">
                <input type="hidden" name="team2_players" :value="JSON.stringify(team2Squad)">

                <!-- Tournament Name -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Tournament / Series Name</label>
                    <input type="text" name="tournament_name" value="{{ old('tournament_name', $match->tournament_name) }}" required
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                </div>

                <!-- Teams Row -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Team 1 Name</label>
                        <input type="text" name="team1_name" x-model="matchTeam1Name" value="{{ old('team1_name', $match->team1_name) }}" required
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Team 2 Name</label>
                        <input type="text" name="team2_name" x-model="matchTeam2Name" value="{{ old('team2_name', $match->team2_name) }}" required
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                </div>

                <!-- Overs, Ball Type, Status -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Total Overs</label>
                        <input type="number" name="total_overs" value="{{ old('total_overs', $match->total_overs) }}" min="1" max="100" required
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Ball Type</label>
                        <select name="ball_type" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white">
                            <option value="Tennis Ball" {{ $match->ball_type === 'Tennis Ball' ? 'selected' : '' }}>🎾 Tennis Ball</option>
                            <option value="Leather Ball" {{ $match->ball_type === 'Leather Ball' ? 'selected' : '' }}>🏏 Leather Ball</option>
                            <option value="Tape Ball" {{ $match->ball_type === 'Tape Ball' ? 'selected' : '' }}>⚡ Tape Ball</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Match Status</label>
                        <select name="status" class="w-full bg-slate-950 border border-white/10 rounded-xl px-3 py-2.5 text-xs text-white">
                            <option value="live" {{ $match->status === 'live' ? 'selected' : '' }}>🔴 Live</option>
                            <option value="scheduled" {{ $match->status === 'scheduled' ? 'selected' : '' }}>⏳ Scheduled</option>
                            <option value="completed" {{ $match->status === 'completed' ? 'selected' : '' }}>🏆 Completed</option>
                            <option value="abandoned" {{ $match->status === 'abandoned' ? 'selected' : '' }}>🚫 Abandoned</option>
                        </select>
                    </div>
                </div>

                <!-- Match Date & Venue -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Match Date</label>
                        <input type="date" name="match_date" value="{{ old('match_date', $match->match_date->format('Y-m-d')) }}" required
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Venue / Ground</label>
                        <input type="text" name="venue" value="{{ old('venue', $match->venue) }}"
                               class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                    </div>
                </div>

                <!-- Toss & Result -->
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Toss Details</label>
                    <input type="text" name="toss_decision" value="{{ old('toss_decision', $match->toss_decision) }}"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Match Result / Current Situation</label>
                    <input type="text" name="match_result" value="{{ old('match_result', $match->match_result) }}"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">Player of the Match (Optional)</label>
                    <input type="text" name="player_of_match" value="{{ old('player_of_match', $match->player_of_match) }}"
                           class="w-full bg-slate-950 border border-white/10 rounded-xl px-3.5 py-2.5 text-sm text-white focus:ring-2 focus:ring-red-500 focus:outline-none">
                </div>

                <!-- ============================================================== -->
                <!-- CRICHEROES SQUAD BUILDER & WHATSAPP LOOKUP SECTION -->
                <!-- ============================================================== -->
                <div class="pt-5 border-t border-white/10 space-y-4">
                    <div>
                        <span class="text-xs font-black uppercase text-emerald-400 flex items-center gap-1.5">
                            <span>📱 ADD PLAYERS VIA WHATSAPP / PHONE</span>
                        </span>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            Enter player's WhatsApp number to auto-fetch past cricket career stats & add to playing squad.
                        </p>
                    </div>

                    <!-- Team Selector Tabs -->
                    <div class="flex items-center gap-2 p-1 bg-black/40 rounded-xl border border-white/5">
                        <button type="button" 
                                @click="activeSquadTab = 1"
                                :class="activeSquadTab === 1 ? 'bg-red-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                class="flex-1 py-2 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                            <span x-text="matchTeam1Name || 'Team 1'"></span>
                            <span class="px-1.5 py-0.2 rounded-full bg-black/30 text-[10px]" x-text="team1Squad.length"></span>
                        </button>
                        <button type="button" 
                                @click="activeSquadTab = 2"
                                :class="activeSquadTab === 2 ? 'bg-red-600 text-white font-bold' : 'text-slate-400 hover:text-white'"
                                class="flex-1 py-2 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                            <span x-text="matchTeam2Name || 'Team 2'"></span>
                            <span class="px-1.5 py-0.2 rounded-full bg-black/30 text-[10px]" x-text="team2Squad.length"></span>
                        </button>
                    </div>

                    <!-- WhatsApp / Phone Search Input -->
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-emerald-400 text-sm">
                                💬
                            </span>
                            <input type="text" x-model="playerSearchQuery" 
                                   @keydown.enter.prevent="searchPlayerWhatsApp()"
                                   placeholder="Enter WhatsApp/Phone (e.g. 9876543210, 9812345678) or Player Name"
                                   class="w-full bg-slate-950 border border-white/10 rounded-xl pl-9 pr-3 py-2 text-xs text-white focus:ring-2 focus:ring-emerald-500 focus:outline-none">
                        </div>
                        <button type="button" @click="searchPlayerWhatsApp()" 
                                class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shrink-0 transition flex items-center gap-1.5">
                            <span x-show="!isSearchingPlayer">Fetch Record 🔍</span>
                            <span x-show="isSearchingPlayer" class="animate-spin text-xs">⏳</span>
                        </button>
                    </div>

                    <!-- 1. Player Found Card with Past Stats -->
                    <template x-if="searchedPlayer">
                        <div class="p-3.5 rounded-xl bg-gradient-to-r from-slate-950 to-indigo-950/70 border border-emerald-500/30 animate-in fade-in space-y-2.5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <img :src="searchedPlayer.avatar" class="w-10 h-10 rounded-full object-cover border border-emerald-400">
                                    <div>
                                        <div class="font-extrabold text-white text-xs flex items-center gap-1">
                                            <span x-text="searchedPlayer.name"></span>
                                            <span class="text-blue-400 text-[10px]">✓</span>
                                        </div>
                                        <div class="text-[11px] text-emerald-400 font-mono" x-text="searchedPlayer.phone"></div>
                                    </div>
                                </div>
                                <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30" x-text="searchedPlayer.role"></span>
                            </div>

                            <!-- Past Cricket Career Stats (CricHeroes Record) -->
                            <div class="grid grid-cols-4 gap-1.5 p-2 rounded-lg bg-black/40 text-[10px] text-center border border-white/5">
                                <div>
                                    <div class="text-slate-400 uppercase font-bold text-[9px]">Matches</div>
                                    <div class="font-black text-white text-xs" x-text="searchedPlayer.stats.matches"></div>
                                </div>
                                <div>
                                    <div class="text-slate-400 uppercase font-bold text-[9px]">Runs (HS)</div>
                                    <div class="font-black text-amber-300 text-xs" x-text="searchedPlayer.stats.runs + ' (' + searchedPlayer.stats.highest_score + ')'"></div>
                                </div>
                                <div>
                                    <div class="text-slate-400 uppercase font-bold text-[9px]">Avg / SR</div>
                                    <div class="font-black text-white text-xs" x-text="searchedPlayer.stats.batting_avg + ' / ' + searchedPlayer.stats.strike_rate"></div>
                                </div>
                                <div>
                                    <div class="text-slate-400 uppercase font-bold text-[9px]">Wickets (BBI)</div>
                                    <div class="font-black text-rose-400 text-xs" x-text="searchedPlayer.stats.wickets + ' (' + searchedPlayer.stats.best_bowling + ')'"></div>
                                </div>
                            </div>

                            <!-- Captain / WK checkbox and Add button -->
                            <div class="flex items-center justify-between pt-1">
                                <div class="flex items-center gap-3 text-xs text-slate-300">
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" x-model="isCaptainTag" class="rounded bg-slate-900 border-white/20 text-red-600 focus:ring-0">
                                        <span>Captain (C)</span>
                                    </label>
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" x-model="isWkTag" class="rounded bg-slate-900 border-white/20 text-amber-500 focus:ring-0">
                                        <span>Wicketkeeper (WK)</span>
                                    </label>
                                </div>
                                <button type="button" @click="addPlayerToSquad()"
                                        class="px-3.5 py-1.5 rounded-lg bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 text-white text-xs font-black shadow-md transition">
                                    + Add to Playing XI
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- 2. Player Not Found in DB: Quick Add Card -->
                    <template x-if="searchNotFound">
                        <div class="p-3.5 rounded-xl bg-white/5 border border-amber-500/30 animate-in fade-in space-y-2 text-xs">
                            <div class="flex items-center gap-1.5 text-amber-300 font-bold">
                                <span>⚡</span>
                                <span>New Player! Not registered yet. Enter details to add to this squad:</span>
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" x-model="newPlayerName" placeholder="Player Full Name" 
                                       class="bg-slate-950 border border-white/10 rounded-lg px-2.5 py-1.5 text-xs text-white">
                                <select x-model="newPlayerRole" class="bg-slate-950 border border-white/10 rounded-lg px-2.5 py-1.5 text-xs text-white">
                                    <option value="Top-Order Batter">Top-Order Batter</option>
                                    <option value="Middle-Order Batter">Middle-Order Batter</option>
                                    <option value="All-Rounder" selected>All-Rounder</option>
                                    <option value="Fast Bowler">Fast Bowler</option>
                                    <option value="Spin Bowler">Spin Bowler</option>
                                    <option value="Wicketkeeper Batter">Wicketkeeper Batter</option>
                                </select>
                            </div>
                            <div class="flex items-center justify-between pt-1">
                                <div class="flex items-center gap-3 text-slate-300">
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" x-model="isCaptainTag" class="rounded bg-slate-900 border-white/20 text-red-600">
                                        <span>Captain (C)</span>
                                    </label>
                                    <label class="flex items-center gap-1 cursor-pointer">
                                        <input type="checkbox" x-model="isWkTag" class="rounded bg-slate-900 border-white/20 text-amber-500">
                                        <span>WK</span>
                                    </label>
                                </div>
                                <button type="button" @click="addPlayerToSquad()"
                                        class="px-3 py-1 rounded-lg bg-red-600 hover:bg-red-500 text-white font-bold text-xs">
                                    + Add Player
                                </button>
                            </div>
                        </div>
                    </template>

                    <!-- 3. Current Squad List Display -->
                    <div class="space-y-2">
                        <div class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center justify-between">
                            <span>Playing Squad: <span class="text-white" x-text="activeSquadTab === 1 ? (matchTeam1Name || 'Team 1') : (matchTeam2Name || 'Team 2')"></span></span>
                            <span class="text-slate-400" x-text="(activeSquadTab === 1 ? team1Squad.length : team2Squad.length) + ' players'"></span>
                        </div>

                        <!-- Team 1 Squad Chips -->
                        <div x-show="activeSquadTab === 1" class="flex flex-wrap gap-2">
                            <template x-if="team1Squad.length === 0">
                                <div class="text-[11px] text-slate-500 italic py-1">No players added to this team yet. Search WhatsApp above to add.</div>
                            </template>
                            <template x-for="(p, index) in team1Squad" :key="index">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-blue-600/20 border border-blue-500/30 text-xs text-white">
                                    <span class="font-bold" x-text="p.player_name"></span>
                                    <template x-if="p.is_captain"><span class="px-1 rounded bg-red-600 text-[9px] font-black">C</span></template>
                                    <template x-if="p.is_wicketkeeper"><span class="px-1 rounded bg-amber-600 text-[9px] font-black">WK</span></template>
                                    <button type="button" @click="removePlayer(1, index)" class="text-slate-400 hover:text-red-400 ml-1 font-bold">✕</button>
                                </div>
                            </template>
                        </div>

                        <!-- Team 2 Squad Chips -->
                        <div x-show="activeSquadTab === 2" class="flex flex-wrap gap-2">
                            <template x-if="team2Squad.length === 0">
                                <div class="text-[11px] text-slate-500 italic py-1">No players added to this team yet. Search WhatsApp above to add.</div>
                            </template>
                            <template x-for="(p, index) in team2Squad" :key="index">
                                <div class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg bg-rose-600/20 border border-rose-500/30 text-xs text-white">
                                    <span class="font-bold" x-text="p.player_name"></span>
                                    <template x-if="p.is_captain"><span class="px-1 rounded bg-red-600 text-[9px] font-black">C</span></template>
                                    <template x-if="p.is_wicketkeeper"><span class="px-1 rounded bg-amber-600 text-[9px] font-black">WK</span></template>
                                    <button type="button" @click="removePlayer(2, index)" class="text-slate-400 hover:text-red-400 ml-1 font-bold">✕</button>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 pt-5 border-t border-white/10">
                    <a href="{{ route('matches.index') }}" class="px-4 py-2 text-xs font-bold text-slate-400 hover:text-white">Cancel</a>
                    <button type="submit" 
                            class="px-8 py-3 rounded-xl bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-500 hover:to-rose-500 text-white font-extrabold text-xs shadow-lg shadow-red-600/30 transition transform active:scale-95">
                        Update Match & Squads 🏏
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
