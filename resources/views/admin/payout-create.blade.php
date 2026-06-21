@extends('admin.layout')
@section('title', 'Create Payout')
@section('content')

<div class="mb-6">
    <a href="{{ route('admin.owners.show', $owner->id) }}" class="text-slate-400 hover:text-white text-sm">
        <i class="fas fa-arrow-left mr-1"></i> Back to {{ $owner->name }}
    </a>
</div>

<div class="flex items-center gap-4 mb-8">
    <div class="w-12 h-12 rounded-2xl bg-green-500/20 flex items-center justify-center text-green-400 font-black text-xl">
        {{ strtoupper(substr($owner->name, 0, 1)) }}
    </div>
    <div>
        <h1 class="text-2xl font-bold text-white">Create Payout</h1>
        <p class="text-slate-400 text-sm">{{ $owner->name }} · {{ $owner->email }}</p>
    </div>
</div>

{{-- Earnings Summary --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
    <div class="stat-card">
        <div class="text-slate-400 text-xs mb-1">Total Gross Revenue</div>
        <div class="text-2xl font-black text-white">₱{{ number_format($gross, 2) }}</div>
        <div class="text-slate-500 text-xs mt-1">From all confirmed bookings</div>
    </div>
    <div class="stat-card">
        <div class="text-slate-400 text-xs mb-1">Total Net (90%)</div>
        <div class="text-2xl font-black text-green-400">₱{{ number_format($net, 2) }}</div>
        <div class="text-slate-500 text-xs mt-1">After 10% platform fee</div>
    </div>
    <div class="stat-card" style="border:1px solid rgba(96,165,250,0.4)">
        <div class="text-slate-400 text-xs mb-1">Available to Payout</div>
        <div class="text-2xl font-black text-blue-400">₱{{ number_format(max($available, 0), 2) }}</div>
        <div class="text-slate-500 text-xs mt-1">Net minus ₱{{ number_format($alreadyPaid, 2) }} already paid</div>
    </div>
</div>

@if($available <= 0)
    <div class="mb-6 px-5 py-4 rounded-xl bg-yellow-500/10 border border-yellow-500/30 text-yellow-400 text-sm">
        <i class="fas fa-triangle-exclamation mr-2"></i>
        This owner has no available balance for payout. All earnings have already been paid out.
    </div>
@endif

{{-- Create Payout Form --}}
<div class="card max-w-xl">
    <h2 class="font-bold text-white text-lg mb-6">Payout Details</h2>

    @if($errors->any())
        <div class="mb-4 px-4 py-3 rounded-lg bg-red-500/20 text-red-400 text-sm border border-red-500/30">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('admin.payouts.store', $owner->id) }}">
        @csrf

        {{-- Payout Account --}}
        <div class="mb-5">
            <label class="text-slate-400 text-xs uppercase tracking-wider font-semibold mb-2 block">
                Payout Account
            </label>
            @if($payoutAccounts->isEmpty())
                <div class="px-4 py-3 rounded-xl bg-slate-700 text-slate-400 text-sm border border-slate-600">
                    <i class="fas fa-exclamation-circle mr-2 text-yellow-400"></i>
                    Owner has not added any payout accounts yet.
                </div>
                <input type="hidden" name="payout_account_id" value="">
            @else
                @foreach($payoutAccounts as $acc)
                <label class="flex items-center gap-3 p-4 rounded-xl border-2 cursor-pointer mb-2 transition
                    {{ $acc->is_primary ? 'border-green-500 bg-green-500/10' : 'border-slate-600 bg-slate-700/50' }}">
                    <input type="radio" name="payout_account_id" value="{{ $acc->id }}"
                        {{ $acc->is_primary ? 'checked' : '' }}
                        class="accent-green-500">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-white font-semibold capitalize">{{ $acc->type }}</span>
                            @if($acc->is_primary)
                                <span class="text-xs bg-green-500 text-white px-2 py-0.5 rounded-full font-bold">Primary</span>
                            @endif
                        </div>
                        <div class="text-slate-400 text-xs mt-0.5">
                            @if($acc->type !== 'card')
                                {{ $acc->account_number }} · {{ $acc->account_name }}
                            @else
                                **** {{ $acc->card_last_four }} · {{ $acc->card_holder }} · {{ $acc->card_expiry }}
                            @endif
                        </div>
                    </div>
                </label>
                @endforeach
            @endif
        </div>

        {{-- Amount --}}
        <div class="mb-5">
            <label class="text-slate-400 text-xs uppercase tracking-wider font-semibold mb-2 block">
                Net Amount to Pay (₱)
            </label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 font-bold text-lg">₱</span>
                <input type="number" name="net_amount" step="0.01" min="1"
                    max="{{ max($available, 0) }}"
                    value="{{ old('net_amount', max($available, 0)) }}"
                    placeholder="0.00"
                    class="w-full pl-10 pr-4 py-3 rounded-xl bg-slate-700 text-white border border-slate-600
                           focus:border-green-500 focus:outline-none text-lg font-bold"
                    {{ $available <= 0 ? 'disabled' : '' }}>
            </div>
            <p class="text-slate-500 text-xs mt-1">Max available: ₱{{ number_format(max($available, 0), 2) }}</p>
        </div>

        {{-- Admin Note --}}
        <div class="mb-6">
            <label class="text-slate-400 text-xs uppercase tracking-wider font-semibold mb-2 block">
                Note (optional)
            </label>
            <textarea name="admin_note" rows="3" placeholder="e.g. Weekly payout — June 19, 2026"
                class="w-full px-4 py-3 rounded-xl bg-slate-700 text-white border border-slate-600
                       focus:border-green-500 focus:outline-none resize-none text-sm">{{ old('admin_note') }}</textarea>
        </div>

        <div class="flex gap-3">
            <a href="{{ route('admin.owners.show', $owner->id) }}"
               class="flex-1 py-3 rounded-xl bg-slate-600 hover:bg-slate-500 text-white font-bold text-center transition">
                Cancel
            </a>
            <button type="submit" {{ $available <= 0 ? 'disabled' : '' }}
                class="flex-2 px-8 py-3 rounded-xl bg-green-500 hover:bg-green-600 disabled:opacity-40
                       disabled:cursor-not-allowed text-white font-bold transition">
                <i class="fas fa-paper-plane mr-2"></i> Create Payout
            </button>
        </div>
    </form>
</div>
@endsection
