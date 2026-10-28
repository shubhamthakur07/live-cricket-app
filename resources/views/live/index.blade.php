@extends('layouts.app')

@section('content')
    <div id="live-scores-root"
         data-live='@json($live)'
         data-scheduled='@json($scheduled)'
         data-completed='@json($completed)'
         data-cache-ttl='{{ ($cacheTtl["current_matches"] ?? 900) / 60 }}'
         data-initial-tab='{{ $tab ?? "live" }}'>
        {{-- Loading Skeleton while React mounts --}}
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 animate-pulse">
            <div class="h-28 bg-gray-200 rounded-xl mb-6"></div>
            <div class="h-14 bg-gray-200 rounded-xl mb-6"></div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="h-48 bg-gray-200 rounded-xl"></div>
                <div class="h-48 bg-gray-200 rounded-xl"></div>
                <div class="h-48 bg-gray-200 rounded-xl"></div>
            </div>
        </div>
    </div>
@endsection
