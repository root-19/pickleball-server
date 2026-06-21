@extends('admin.layout')
@section('title', 'Court Owners')
@section('content')

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Court Owners</h1>
        <p class="text-slate-400 text-sm mt-1">{{ $owners->total() }} registered owners</p>
    </div>
    <form method="GET" action="{{ route('admin.owners') }}" class="flex gap-2">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search name or email…"
            class="px-4 py-2 rounded-xl bg-slate-700 text-white border border-slate-600 focus:border-green-500 focus:outline-none text-sm w-64">
        <button type="submit" class="px-4 py-2 rounded-xl bg-green-500 hover:bg-green-600 text-white text-sm font-semibold">Search</button>
        @if($search)
            <a href="{{ route('admin.owners') }}" class="px-4 py-2 rounded-xl bg-slate-600 hover:bg-slate-500 text-white text-sm">Clear</a>
        @endif
    </form>
</div>

<div class="card overflow-hidden p-0">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-700">
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Owner</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Courts</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Gross Revenue</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Net Earnings (90%)</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Platform Cut (10%)</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Joined</th>
                <th class="px-6 py-4"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($owners as $owner)
            <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        @if($owner->profile_image)
                            <img src="{{ url('storage/'.$owner->profile_image) }}" class="w-9 h-9 rounded-full object-cover">
                        @else
                            <div class="w-9 h-9 rounded-full bg-purple-500/20 flex items-center justify-center text-purple-400 font-bold text-sm">
                                {{ strtoupper(substr($owner->name, 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <div class="text-white font-medium">{{ $owner->name }}</div>
                            <div class="text-slate-500 text-xs">{{ $owner->email }}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 text-slate-300">{{ $owner->courts_count }}</td>
                <td class="px-6 py-4 text-white font-semibold">₱{{ number_format($owner->gross_earnings, 2) }}</td>
                <td class="px-6 py-4 text-green-400 font-bold">₱{{ number_format($owner->net_earnings, 2) }}</td>
                <td class="px-6 py-4 text-yellow-400">₱{{ number_format($owner->platform_cut, 2) }}</td>
                <td class="px-6 py-4 text-slate-400">{{ $owner->created_at->format('M d, Y') }}</td>
                <td class="px-6 py-4 text-right">
                    <a href="{{ route('admin.owners.show', $owner->id) }}" class="text-green-400 hover:text-green-300 text-xs font-semibold">View</a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="px-6 py-12 text-center text-slate-500">No owners found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-6 py-4 border-t border-slate-700">
        {{ $owners->links() }}
    </div>
</div>
@endsection
