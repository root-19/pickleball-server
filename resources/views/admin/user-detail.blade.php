@extends('admin.layout')
@section('title', $user->name)
@section('content')

<div class="mb-6">
    <a href="{{ route('admin.users') }}" class="text-slate-400 hover:text-white text-sm"><i class="fas fa-arrow-left mr-1"></i> Back to Users</a>
</div>

<div class="flex items-center gap-5 mb-8">
    @if($user->profile_image)
        <img src="{{ url('storage/'.$user->profile_image) }}" class="w-16 h-16 rounded-2xl object-cover">
    @else
        <div class="w-16 h-16 rounded-2xl bg-blue-500/20 flex items-center justify-center text-blue-400 font-black text-2xl">
            {{ strtoupper(substr($user->name, 0, 1)) }}
        </div>
    @endif
    <div>
        <h1 class="text-2xl font-bold text-white">{{ $user->name }}</h1>
        <p class="text-slate-400 text-sm">{{ $user->email }} · Joined {{ $user->created_at->format('M d, Y') }}</p>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="stat-card">
        <div class="text-slate-400 text-xs mb-1">Total Bookings</div>
        <div class="text-2xl font-black text-white">{{ $bookings->count() }}</div>
    </div>
    <div class="stat-card">
        <div class="text-slate-400 text-xs mb-1">Total Spent</div>
        <div class="text-2xl font-black text-green-400">₱{{ number_format($bookings->sum('total_price'), 2) }}</div>
    </div>
    <div class="stat-card">
        <div class="text-slate-400 text-xs mb-1">Confirmed</div>
        <div class="text-2xl font-black text-blue-400">{{ $bookings->where('status','confirmed')->count() }}</div>
    </div>
    <div class="stat-card">
        <div class="text-slate-400 text-xs mb-1">Cancelled</div>
        <div class="text-2xl font-black text-red-400">{{ $bookings->where('status','cancelled')->count() }}</div>
    </div>
</div>

<div class="card overflow-hidden p-0">
    <div class="px-6 py-4 border-b border-slate-700">
        <h2 class="font-bold text-white">Booking History</h2>
    </div>
    <div class="table-responsive">
    <table class="w-full text-sm min-w-[600px]">
        <thead>
            <tr class="border-b border-slate-700">
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Code</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Court</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Date</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Time</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Amount</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bookings as $b)
            <tr class="border-b border-slate-700/50 hover:bg-slate-700/30">
                <td class="px-6 py-3 font-mono text-green-400 text-xs">{{ $b->booking_code }}</td>
                <td class="px-6 py-3 text-white">{{ $b->court->name ?? '—' }}</td>
                <td class="px-6 py-3 text-slate-300">{{ \Carbon\Carbon::parse($b->booking_date)->format('M d, Y') }}</td>
                <td class="px-6 py-3 text-slate-400">{{ $b->time_slot_start }} – {{ $b->time_slot_end }}</td>
                <td class="px-6 py-3 text-white font-semibold">₱{{ number_format($b->total_price, 2) }}</td>
                <td class="px-6 py-3">
                    <span class="badge {{ $b->status === 'confirmed' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400' }}">
                        {{ ucfirst($b->status) }}
                    </span>
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-6 py-10 text-center text-slate-500">No bookings yet.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
