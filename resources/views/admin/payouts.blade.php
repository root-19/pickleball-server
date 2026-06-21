@extends('admin.layout')
@section('title', 'Payouts')
@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-white">Payouts</h1>
    <p class="text-slate-400 text-sm mt-1">Release earnings to court owners</p>
</div>

{{-- Summary Cards --}}
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="stat-card" style="border:1px solid rgba(96,165,250,0.4)">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center text-blue-400"><i class="fas fa-wallet text-sm"></i></span>
            <span class="text-slate-400 text-sm font-semibold">Total Available</span>
        </div>
        <div class="text-3xl font-black text-blue-400">₱{{ number_format($totalAvailable, 2) }}</div>
        <div class="text-slate-500 text-xs mt-1">Unpaid net earnings across all owners</div>
    </div>
    <div class="stat-card" style="border:1px solid rgba(245,158,11,0.4)">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-8 h-8 rounded-lg bg-yellow-500/20 flex items-center justify-center text-yellow-400"><i class="fas fa-clock text-sm"></i></span>
            <span class="text-slate-400 text-sm font-semibold">Pending / Processing</span>
        </div>
        <div class="text-3xl font-black text-yellow-400">₱{{ number_format($totalPending, 2) }}</div>
        <div class="text-slate-500 text-xs mt-1">Payouts in queue</div>
    </div>
    <div class="stat-card" style="border:1px solid rgba(76,175,80,0.4)">
        <div class="flex items-center gap-2 mb-2">
            <span class="w-8 h-8 rounded-lg bg-green-500/20 flex items-center justify-center text-green-400"><i class="fas fa-check-circle text-sm"></i></span>
            <span class="text-slate-400 text-sm font-semibold">Total Completed</span>
        </div>
        <div class="text-3xl font-black text-green-400">₱{{ number_format($totalCompleted, 2) }}</div>
        <div class="text-slate-500 text-xs mt-1">Successfully released</div>
    </div>
</div>

{{-- Tabs --}}
<div class="flex gap-1 mb-6 bg-slate-800 rounded-xl p-1 w-fit">
    <a href="{{ route('admin.payouts', ['tab' => 'owners', 'search' => $search]) }}"
       class="px-5 py-2 rounded-lg text-sm font-semibold transition {{ $tab === 'owners' ? 'bg-green-500 text-white' : 'text-slate-400 hover:text-white' }}">
        <i class="fas fa-store mr-1"></i> Owners with Earnings
    </a>
    <a href="{{ route('admin.payouts', ['tab' => 'history', 'search' => $search]) }}"
       class="px-5 py-2 rounded-lg text-sm font-semibold transition {{ $tab === 'history' ? 'bg-green-500 text-white' : 'text-slate-400 hover:text-white' }}">
        <i class="fas fa-history mr-1"></i> Payout History
    </a>
</div>

{{-- Search --}}
<form method="GET" action="{{ route('admin.payouts') }}" class="flex gap-3 mb-4">
    <input type="hidden" name="tab" value="{{ $tab }}">
    <input type="text" name="search" value="{{ $search }}" placeholder="Search owner name or email…"
        class="px-4 py-2 rounded-xl bg-slate-700 text-white border border-slate-600 focus:border-green-500 focus:outline-none text-sm w-72">
    <button type="submit" class="px-4 py-2 rounded-xl bg-slate-600 hover:bg-slate-500 text-white text-sm font-semibold">Search</button>
    @if($search)
        <a href="{{ route('admin.payouts', ['tab' => $tab]) }}" class="px-4 py-2 rounded-xl bg-slate-700 text-slate-400 hover:text-white text-sm">Clear</a>
    @endif
</form>

{{-- TAB: Owners with Earnings --}}
@if($tab === 'owners')
<div class="card overflow-hidden p-0">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-700">
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Owner</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Payout Account</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Gross Revenue</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Net (90%)</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Already Paid</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Available</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Last Payout</th>
                <th class="px-6 py-4"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($owners as $owner)
            <tr class="border-b border-slate-700/50 hover:bg-slate-700/20 transition">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-purple-500/20 flex items-center justify-center text-purple-400 font-bold text-sm flex-shrink-0">
                            {{ strtoupper(substr($owner->name, 0, 1)) }}
                        </div>
                        <div>
                            <div class="text-white font-semibold">{{ $owner->name }}</div>
                            <div class="text-slate-500 text-xs">{{ $owner->email }}</div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    @if($owner->payout_account)
                        @php $acc = $owner->payout_account; @endphp
                        <div class="flex items-center gap-2">
                            @if($acc->type === 'gcash')
                                <span class="w-6 h-6 rounded bg-blue-500/20 flex items-center justify-center text-blue-400 text-xs font-bold">G</span>
                            @elseif($acc->type === 'maya')
                                <span class="w-6 h-6 rounded bg-green-500/20 flex items-center justify-center text-green-400 text-xs font-bold">M</span>
                            @else
                                <span class="w-6 h-6 rounded bg-purple-500/20 flex items-center justify-center text-purple-400 text-xs"><i class="fas fa-credit-card" style="font-size:10px"></i></span>
                            @endif
                            <div>
                                <div class="text-slate-300 text-xs font-semibold capitalize">{{ $acc->type }}</div>
                                <div class="text-slate-500 text-xs">
                                    {{ $acc->type !== 'card' ? $acc->account_number : '**** '.$acc->card_last_four }}
                                </div>
                            </div>
                        </div>
                    @else
                        <span class="text-slate-600 text-xs italic">No account set</span>
                    @endif
                </td>
                <td class="px-6 py-4 text-slate-300">₱{{ number_format($owner->gross, 2) }}</td>
                <td class="px-6 py-4 text-white font-semibold">₱{{ number_format($owner->net, 2) }}</td>
                <td class="px-6 py-4 text-slate-400">₱{{ number_format($owner->already_paid, 2) }}</td>
                <td class="px-6 py-4">
                    <span class="font-black text-lg {{ $owner->available > 0 ? 'text-green-400' : 'text-slate-600' }}">
                        ₱{{ number_format($owner->available, 2) }}
                    </span>
                </td>
                <td class="px-6 py-4 text-slate-500 text-xs">
                    {{ $owner->last_payout ? $owner->last_payout->created_at->format('M d, Y') : 'Never' }}
                </td>
                <td class="px-6 py-4 text-right">
                    @if($owner->available > 0)
                        <a href="{{ route('admin.payouts.create', $owner->id) }}"
                           class="inline-flex items-center gap-1 px-4 py-2 rounded-lg bg-green-500 hover:bg-green-600 text-white text-xs font-bold transition">
                            <i class="fas fa-paper-plane"></i> Pay Out
                        </a>
                    @else
                        <span class="text-slate-600 text-xs">Fully paid</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" class="px-6 py-16 text-center text-slate-500">
                    <i class="fas fa-store text-3xl mb-3 block opacity-30"></i>
                    No owners with confirmed bookings found.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endif

{{-- TAB: Payout History --}}
@if($tab === 'history')
<div class="card overflow-hidden p-0">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-700">
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Reference</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Owner</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Sent To</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Net Amount</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Status</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Date</th>
                <th class="px-6 py-4 text-slate-400 font-semibold">Update Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($history as $p)
            @php
                $statusColors = [
                    'pending'    => 'bg-yellow-500/20 text-yellow-400',
                    'processing' => 'bg-blue-500/20 text-blue-400',
                    'completed'  => 'bg-green-500/20 text-green-400',
                    'failed'     => 'bg-red-500/20 text-red-400',
                ];
                $acc = $p->payoutAccount;
            @endphp
            <tr class="border-b border-slate-700/50 hover:bg-slate-700/20 transition">
                <td class="px-6 py-4 font-mono text-green-400 text-xs font-bold">{{ $p->reference }}</td>
                <td class="px-6 py-4">
                    <div class="text-white font-medium">{{ $p->owner->name ?? '—' }}</div>
                    <div class="text-slate-500 text-xs">{{ $p->owner->email ?? '' }}</div>
                </td>
                <td class="px-6 py-4">
                    @if($acc)
                        <div class="text-slate-300 text-xs font-semibold capitalize">{{ $acc->type }}</div>
                        <div class="text-slate-500 text-xs">
                            {{ $acc->type !== 'card' ? $acc->account_number : '**** '.$acc->card_last_four }}
                            @if($acc->type !== 'card') · {{ $acc->account_name }} @endif
                        </div>
                    @else
                        <span class="text-slate-600 text-xs italic">No account</span>
                    @endif
                    @if($p->admin_note)
                        <div class="text-slate-600 text-xs mt-1 italic">"{{ $p->admin_note }}"</div>
                    @endif
                </td>
                <td class="px-6 py-4 text-green-400 font-black text-lg">₱{{ number_format($p->net_amount, 2) }}</td>
                <td class="px-6 py-4">
                    <span class="badge {{ $statusColors[$p->status] ?? '' }}">{{ ucfirst($p->status) }}</span>
                </td>
                <td class="px-6 py-4 text-slate-400 text-xs">{{ $p->created_at->format('M d, Y') }}</td>
                <td class="px-6 py-4">
                    @if(!in_array($p->status, ['completed', 'failed']))
                    <form method="POST" action="{{ route('admin.payouts.status', $p->id) }}" class="flex gap-2">
                        @csrf
                        <select name="status" class="px-2 py-1 rounded-lg bg-slate-700 text-white border border-slate-600 text-xs">
                            <option value="pending"    {{ $p->status === 'pending'    ? 'selected' : '' }}>Pending</option>
                            <option value="processing" {{ $p->status === 'processing' ? 'selected' : '' }}>Processing</option>
                            <option value="completed">Completed ✓</option>
                            <option value="failed">Failed ✗</option>
                        </select>
                        <button type="submit" class="px-3 py-1 rounded-lg bg-green-500 hover:bg-green-600 text-white text-xs font-bold">Save</button>
                    </form>
                    @else
                        <span class="text-slate-600 text-xs">
                            {{ $p->processed_at ? $p->processed_at->format('M d, Y') : 'Done' }}
                        </span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="px-6 py-16 text-center text-slate-500">
                    <i class="fas fa-history text-3xl mb-3 block opacity-30"></i>
                    No payout history yet.
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-6 py-4 border-t border-slate-700">
        {{ $history->links() }}
    </div>
</div>
@endif

@endsection
