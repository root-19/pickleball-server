@extends('admin.layout')
@section('title', $owner->name)
@section('content')

<div class="mb-6">
    <a href="{{ route('admin.owners') }}" class="text-slate-400 hover:text-white text-sm"><i class="fas fa-arrow-left mr-1"></i> Back to Owners</a>
</div>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
    <div class="flex items-center gap-5">
        @if($owner->profile_image)
            <img src="{{ url('storage/'.$owner->profile_image) }}" class="w-16 h-16 rounded-2xl object-cover">
        @else
            <div class="w-16 h-16 rounded-2xl bg-purple-500/20 flex items-center justify-center text-purple-400 font-black text-2xl">
                {{ strtoupper(substr($owner->name, 0, 1)) }}
            </div>
        @endif
        <div>
            <h1 class="text-2xl font-bold text-white">{{ $owner->name }}</h1>
            <p class="text-slate-400 text-sm">{{ $owner->email }} · Joined {{ $owner->created_at->format('M d, Y') }}</p>
            @if($owner->company_name)
                <p class="text-slate-500 text-xs mt-1"><i class="fas fa-building mr-1"></i>{{ $owner->company_name }}</p>
            @endif
        </div>
    </div>
    <a href="{{ route('admin.payouts.create', $owner->id) }}"
       class="flex items-center gap-2 px-5 py-3 rounded-xl bg-green-500 hover:bg-green-600 text-white font-bold transition">
        <i class="fas fa-money-bill-transfer"></i> Pay Out Owner
    </a>
</div>

{{-- Verification --}}
@php
    $vStatus = $verification->status ?? 'not_submitted';
    $vBadge = [
        'approved'      => 'bg-green-500/20 text-green-400',
        'pending'       => 'bg-yellow-500/20 text-yellow-400',
        'rejected'      => 'bg-red-500/20 text-red-400',
        'not_submitted' => 'bg-slate-500/20 text-slate-400',
    ][$vStatus] ?? 'bg-slate-500/20 text-slate-400';
    $vLabel = [
        'approved'      => 'Verified',
        'pending'       => 'Pending Review',
        'rejected'      => 'Rejected',
        'not_submitted' => 'Not Submitted',
    ][$vStatus] ?? ucfirst($vStatus);
@endphp
<div class="card mb-8">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-bold text-white"><i class="fas fa-shield-halved mr-2 text-purple-400"></i>Account Verification</h2>
        <span class="badge {{ $vBadge }}">{{ $vLabel }}</span>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-2 rounded-lg bg-green-500/15 text-green-400 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="mb-4 px-4 py-2 rounded-lg bg-red-500/15 text-red-400 text-sm">{{ session('error') }}</div>
    @endif

    @if(!$verification)
        <p class="text-slate-500 text-sm">This owner has not submitted any verification info yet.</p>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
                <p class="text-slate-400 text-xs mb-1">Registered Email</p>
                <p class="text-white text-sm mb-4">{{ $verification->email }}</p>

                <p class="text-slate-400 text-xs mb-2">Social Media</p>
                <ul class="text-sm text-slate-300 space-y-1 mb-4">
                    <li><i class="fab fa-facebook w-5 text-slate-500"></i> {{ $verification->facebook ?: '—' }}</li>
                    <li><i class="fab fa-instagram w-5 text-slate-500"></i> {{ $verification->instagram ?: '—' }}</li>
                    <li><i class="fab fa-tiktok w-5 text-slate-500"></i> {{ $verification->tiktok ?: '—' }}</li>
                    <li><i class="fas fa-globe w-5 text-slate-500"></i> {{ $verification->website ?: '—' }}</li>
                </ul>

                <p class="text-slate-400 text-xs mb-2">Documents</p>
                @if(!empty($verification->documents))
                    <ul class="text-sm space-y-1">
                        @foreach($verification->documents as $doc)
                            <li><a href="{{ url('storage/'.$doc) }}" target="_blank" class="text-blue-400 hover:underline">
                                <i class="fas fa-file mr-1"></i>{{ basename($doc) }}</a></li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-slate-500 text-sm">No documents uploaded.</p>
                @endif
            </div>

            <div>
                <p class="text-slate-400 text-xs mb-2">Valid ID</p>
                @if($verification->id_image)
                    <a href="{{ url('storage/'.$verification->id_image) }}" target="_blank">
                        <img src="{{ url('storage/'.$verification->id_image) }}" class="w-full max-w-xs rounded-lg border border-slate-700 mb-4">
                    </a>
                @else
                    <p class="text-slate-500 text-sm mb-4">No ID uploaded.</p>
                @endif

                <p class="text-slate-400 text-xs mb-2">Court Photos</p>
                <div class="flex flex-wrap gap-2">
                    @foreach(['court_image_1','court_image_2','court_image_3'] as $ci)
                        @if($verification->{$ci})
                            <a href="{{ url('storage/'.$verification->{$ci}) }}" target="_blank">
                                <img src="{{ url('storage/'.$verification->{$ci}) }}" class="w-24 h-24 object-cover rounded-lg border border-slate-700">
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        <div class="flex gap-3 mt-6 pt-6 border-t border-slate-700">
            <form method="POST" action="{{ route('admin.owners.verification', $owner->id) }}">
                @csrf
                <input type="hidden" name="status" value="approved">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-green-500 hover:bg-green-600 text-white font-bold transition">
                    <i class="fas fa-check mr-1"></i> Approve
                </button>
            </form>
            <form method="POST" action="{{ route('admin.owners.verification', $owner->id) }}">
                @csrf
                <input type="hidden" name="status" value="rejected">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white font-bold transition">
                    <i class="fas fa-xmark mr-1"></i> Reject
                </button>
            </form>
        </div>
    @endif
</div>

{{-- Summary Stats --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="stat-card">
        <div class="text-slate-400 text-xs mb-1">Courts</div>
        <div class="text-2xl font-black text-white">{{ $courts->count() }}</div>
    </div>
    <div class="stat-card">
        <div class="text-slate-400 text-xs mb-1">Gross Revenue</div>
        <div class="text-2xl font-black text-white">₱{{ number_format($gross, 2) }}</div>
    </div>
    <div class="stat-card">
        <div class="text-slate-400 text-xs mb-1">Net Earnings (90%)</div>
        <div class="text-2xl font-black text-green-400">₱{{ number_format($net, 2) }}</div>
    </div>
    <div class="stat-card">
        <div class="text-slate-400 text-xs mb-1">Platform Cut (10%)</div>
        <div class="text-2xl font-black text-yellow-400">₱{{ number_format($platform, 2) }}</div>
    </div>
</div>

{{-- Earnings Chart --}}
<div class="card mb-6">
    <h2 class="font-bold text-white mb-4">Monthly Earnings <span class="text-slate-500 text-sm font-normal">(Last 6 months)</span></h2>
    <canvas id="ownerEarningsChart" height="80"></canvas>
</div>

{{-- Courts --}}
<div class="card mb-6 overflow-hidden p-0">
    <div class="px-6 py-4 border-b border-slate-700">
        <h2 class="font-bold text-white">Courts ({{ $courts->count() }})</h2>
    </div>
    <div class="table-responsive">
    <table class="w-full text-sm min-w-[500px]">
        <thead>
            <tr class="border-b border-slate-700">
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Court Name</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Location</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Price/hr</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($courts as $court)
            <tr class="border-b border-slate-700/50 hover:bg-slate-700/30">
                <td class="px-6 py-3 text-white font-medium">{{ $court->name }}</td>
                <td class="px-6 py-3 text-slate-400">{{ $court->location }}</td>
                <td class="px-6 py-3 text-green-400 font-semibold">₱{{ number_format($court->price_per_hour, 2) }}</td>
                <td class="px-6 py-3">
                    <span class="badge {{ $court->is_active ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400' }}">
                        {{ $court->is_active ? 'Active' : 'Closed' }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>

{{-- Bookings --}}
<div class="card overflow-hidden p-0">
    <div class="px-6 py-4 border-b border-slate-700">
        <h2 class="font-bold text-white">All Bookings ({{ $bookings->count() }})</h2>
    </div>
    <div class="table-responsive">
    <table class="w-full text-sm min-w-[600px]">
        <thead>
            <tr class="border-b border-slate-700">
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Code</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Court</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Date</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Gross</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Net (90%)</th>
                <th class="text-left px-6 py-3 text-slate-400 font-semibold">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bookings->take(50) as $b)
            <tr class="border-b border-slate-700/50 hover:bg-slate-700/30">
                <td class="px-6 py-3 font-mono text-green-400 text-xs">{{ $b->booking_code }}</td>
                <td class="px-6 py-3 text-white">{{ $b->court->name ?? '—' }}</td>
                <td class="px-6 py-3 text-slate-300">{{ \Carbon\Carbon::parse($b->booking_date)->format('M d, Y') }}</td>
                <td class="px-6 py-3 text-white">₱{{ number_format($b->total_price, 2) }}</td>
                <td class="px-6 py-3 text-green-400 font-semibold">₱{{ number_format($b->total_price * 0.98, 2) }}</td>
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

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const ecLabels = {!! json_encode($earningsChart->pluck('label')) !!};
const ecGross  = {!! json_encode($earningsChart->pluck('gross')) !!};
const ecNet    = {!! json_encode($earningsChart->pluck('net')) !!};

new Chart(document.getElementById('ownerEarningsChart'), {
    type: 'line',
    data: {
        labels: ecLabels,
        datasets: [
            { label: 'Gross Revenue (₱)', data: ecGross, borderColor: '#60A5FA', backgroundColor: 'rgba(96,165,250,0.08)', tension: 0.4, fill: false, pointRadius: 4 },
            { label: 'Net Earnings 90% (₱)', data: ecNet, borderColor: '#4CAF50', backgroundColor: 'rgba(76,175,80,0.12)', tension: 0.4, fill: true, pointRadius: 4 },
        ]
    },
    options: {
        responsive: true,
        plugins: { legend: { labels: { color: '#94a3b8' } } },
        scales: {
            x: { ticks: { color: '#64748b' }, grid: { color: '#334155' } },
            y: { ticks: { color: '#64748b' }, grid: { color: '#334155' } }
        }
    }
});
</script>
@endsection
