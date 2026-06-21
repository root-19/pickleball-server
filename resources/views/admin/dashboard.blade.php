@extends('admin.layout')
@section('title', 'Dashboard')
@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-white">Dashboard</h1>
    <p class="text-slate-400 text-sm mt-1">Platform overview</p>
</div>

{{-- Top Stat Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-400 text-sm">Total Users</span>
            <span class="w-9 h-9 rounded-lg bg-blue-500/20 flex items-center justify-center text-blue-400"><i class="fas fa-users text-sm"></i></span>
        </div>
        <div class="text-3xl font-black text-white">{{ number_format($totalUsers) }}</div>
        <div class="text-slate-500 text-xs mt-1">Registered players</div>
    </div>
    <div class="stat-card">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-400 text-sm">Court Owners</span>
            <span class="w-9 h-9 rounded-lg bg-purple-500/20 flex items-center justify-center text-purple-400"><i class="fas fa-store text-sm"></i></span>
        </div>
        <div class="text-3xl font-black text-white">{{ number_format($totalOwners) }}</div>
        <div class="text-slate-500 text-xs mt-1">Active court owners</div>
    </div>
    <div class="stat-card">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-400 text-sm">Total Bookings</span>
            <span class="w-9 h-9 rounded-lg bg-green-500/20 flex items-center justify-center text-green-400"><i class="fas fa-calendar-check text-sm"></i></span>
        </div>
        <div class="text-3xl font-black text-white">{{ number_format($totalBookings) }}</div>
        <div class="text-slate-500 text-xs mt-1">All confirmed bookings</div>
    </div>
    <div class="stat-card">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-400 text-sm">Total Courts</span>
            <span class="w-9 h-9 rounded-lg bg-orange-500/20 flex items-center justify-center text-orange-400"><i class="fas fa-map-marker-alt text-sm"></i></span>
        </div>
        <div class="text-3xl font-black text-white">{{ number_format($totalCourts) }}</div>
        <div class="text-slate-500 text-xs mt-1">Listed courts</div>
    </div>
</div>

{{-- Unread Messages Notification --}}
@if($unreadMessages && $unreadMessages->count() > 0)
<div class="card mb-6" style="border: 1px solid rgba(239, 68, 68, 0.4);">
    <div class="flex items-center gap-2 mb-4">
        <span class="w-8 h-8 rounded-lg bg-red-500/20 flex items-center justify-center text-red-400"><i class="fas fa-envelope text-sm"></i></span>
        <span class="text-slate-400 text-sm font-semibold">Users with Unread Messages</span>
        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full">{{ $unreadMessages->count() }}</span>
    </div>
    <div class="space-y-2 max-h-64 overflow-y-auto">
        @foreach($unreadMessages as $userId => $messages)
            @php
                $latestMessage = $messages->first();
                $user = $latestMessage->user;
                $messageCount = $messages->count();
            @endphp
            <div class="flex items-center justify-between p-3 rounded-lg bg-slate-800/50">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400 font-bold">
                        {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                    </div>
                    <div>
                        <div class="text-white font-medium">{{ $user->name ?? 'Unknown User' }}</div>
                        <div class="text-slate-500 text-xs">{{ $user->email ?? '' }}</div>
                        <div class="text-slate-400 text-xs mt-1">
                            {{ $messageCount }} unreplied message{{ $messageCount > 1 ? 's' : '' }}
                        </div>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="bg-red-500/20 text-red-400 text-xs px-2 py-1 rounded">{{ $messageCount }}</span>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endif

{{-- Revenue Breakdown --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
    <div class="stat-card" style="border:1px solid rgba(76,175,80,0.4)">
        <div class="flex items-center gap-2 mb-3">
            <span class="w-8 h-8 rounded-lg bg-green-500/20 flex items-center justify-center text-green-400"><i class="fas fa-peso-sign text-sm"></i></span>
            <span class="text-slate-400 text-sm font-semibold">Gross Revenue</span>
        </div>
        <div class="text-3xl font-black text-green-400">₱{{ number_format($grossRevenue, 2) }}</div>
        <div class="text-slate-500 text-xs mt-2">Total value of all paid confirmed bookings</div>
    </div>
    <div class="stat-card" style="border:1px solid rgba(245,158,11,0.4)">
        <div class="flex items-center gap-2 mb-3">
            <span class="w-8 h-8 rounded-lg bg-yellow-500/20 flex items-center justify-center text-yellow-400"><i class="fas fa-building text-sm"></i></span>
            <span class="text-slate-400 text-sm font-semibold">Pickaball Platform (10%)</span>
        </div>
        <div class="text-3xl font-black text-yellow-400">₱{{ number_format($platformEarnings, 2) }}</div>
        <div class="text-slate-500 text-xs mt-2">10% fee deducted from every booking</div>
    </div>
    <div class="stat-card" style="border:1px solid rgba(96,165,250,0.4)">
        <div class="flex items-center gap-2 mb-3">
            <span class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center text-blue-400"><i class="fas fa-store text-sm"></i></span>
            <span class="text-slate-400 text-sm font-semibold">Total Owner Net (90%)</span>
        </div>
        <div class="text-3xl font-black text-blue-400">₱{{ number_format($ownerPayouts, 2) }}</div>
        <div class="text-slate-500 text-xs mt-2">Combined net earnings of all {{ $totalOwners }} owners after 10% fee</div>
    </div>
</div>

{{-- Charts Row 1 --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Daily Revenue Line Chart --}}
    <div class="card">
        <h2 class="font-bold text-white mb-1">Daily Revenue <span class="text-slate-500 text-sm font-normal">(Last 30 days)</span></h2>
        <canvas id="dailyRevenueChart" height="120"></canvas>
    </div>
    {{-- Daily Bookings --}}
    <div class="card">
        <h2 class="font-bold text-white mb-1">Daily Bookings <span class="text-slate-500 text-sm font-normal">(Last 30 days)</span></h2>
        <canvas id="dailyBookingsChart" height="120"></canvas>
    </div>
</div>

{{-- Charts Row 2 --}}
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    {{-- Monthly Revenue --}}
    <div class="card">
        <h2 class="font-bold text-white mb-1">Monthly Revenue <span class="text-slate-500 text-sm font-normal">(Last 6 months)</span></h2>
        <canvas id="monthlyRevenueChart" height="120"></canvas>
    </div>
    {{-- New Users --}}
    <div class="card">
        <h2 class="font-bold text-white mb-1">New User Registrations <span class="text-slate-500 text-sm font-normal">(Last 30 days)</span></h2>
        <canvas id="newUsersChart" height="120"></canvas>
    </div>
</div>

<script>
const chartDefaults = {
    responsive: true,
    plugins: { legend: { labels: { color: '#94a3b8' } } },
    scales: {
        x: { ticks: { color: '#64748b', maxTicksLimit: 10 }, grid: { color: '#1e293b' } },
        y: { ticks: { color: '#64748b' }, grid: { color: '#334155' } }
    }
};

const last30Labels   = {!! json_encode($last30->pluck('label')) !!};
const last30Revenue  = {!! json_encode($last30->pluck('revenue')) !!};
const last30Platform = {!! json_encode($last30->pluck('platform')) !!};
const last30Bookings = {!! json_encode($last30->pluck('bookings')) !!};

new Chart(document.getElementById('dailyRevenueChart'), {
    type: 'line',
    data: {
        labels: last30Labels,
        datasets: [
            { label: 'Net to Owners (₱)', data: last30Revenue,  borderColor: '#4CAF50', backgroundColor: 'rgba(76,175,80,0.1)', tension: 0.4, fill: true, pointRadius: 2 },
            { label: 'Platform Earned (₱)', data: last30Platform, borderColor: '#F59E0B', backgroundColor: 'rgba(245,158,11,0.08)', tension: 0.4, fill: false, pointRadius: 2 },
        ]
    },
    options: { ...chartDefaults }
});

new Chart(document.getElementById('dailyBookingsChart'), {
    type: 'line',
    data: {
        labels: last30Labels,
        datasets: [
            { label: 'Bookings', data: last30Bookings, borderColor: '#60A5FA', backgroundColor: 'rgba(96,165,250,0.1)', tension: 0.4, fill: true, pointRadius: 2 }
        ]
    },
    options: { ...chartDefaults }
});

const monthlyLabels   = {!! json_encode($monthly->pluck('label')) !!};
const monthlyGross    = {!! json_encode($monthly->pluck('gross')) !!};
const monthlyPlatform = {!! json_encode($monthly->pluck('platform')) !!};
const monthlyOwners   = {!! json_encode($monthly->pluck('owners')) !!};

new Chart(document.getElementById('monthlyRevenueChart'), {
    type: 'bar',
    data: {
        labels: monthlyLabels,
        datasets: [
            { label: 'Platform (₱)', data: monthlyPlatform, backgroundColor: '#F59E0B' },
            { label: 'Owners (₱)',   data: monthlyOwners,   backgroundColor: '#4CAF50' },
        ]
    },
    options: { ...chartDefaults, scales: { ...chartDefaults.scales, x: { ...chartDefaults.scales.x, stacked: true }, y: { ...chartDefaults.scales.y, stacked: true } } }
});

const nuLabels = {!! json_encode($newUsersChart->pluck('label')) !!};
const nuCounts = {!! json_encode($newUsersChart->pluck('count')) !!};

new Chart(document.getElementById('newUsersChart'), {
    type: 'line',
    data: {
        labels: nuLabels,
        datasets: [
            { label: 'New Users', data: nuCounts, borderColor: '#A78BFA', backgroundColor: 'rgba(167,139,250,0.1)', tension: 0.4, fill: true, pointRadius: 2 }
        ]
    },
    options: { ...chartDefaults }
});
</script>
@endsection
