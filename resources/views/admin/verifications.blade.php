@extends('admin.layout')
@section('title', 'Verifications')
@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-white">Account Verifications</h1>
    <p class="text-slate-400 text-sm mt-1">Review and approve owner verification requests</p>
</div>

@if(session('error'))
    <div class="mb-4 px-4 py-3 rounded-lg bg-red-500/20 text-red-400 border border-red-500/30">
        {{ session('error') }}
    </div>
@endif

{{-- Status filter tabs --}}
@php
    $tabs = [
        ''         => ['All', $counts['all']],
        'pending'  => ['Pending', $counts['pending']],
        'approved' => ['Verified', $counts['approved']],
        'rejected' => ['Rejected', $counts['rejected']],
    ];
@endphp
<div class="flex flex-wrap gap-2 mb-6">
    @foreach($tabs as $key => [$label, $count])
        <a href="{{ route('admin.verifications', array_filter(['status' => $key])) }}"
           class="px-4 py-2 rounded-xl text-sm font-semibold transition
                  {{ (string) $status === (string) $key ? 'bg-green-500 text-white' : 'bg-slate-700 text-slate-300 hover:bg-slate-600' }}">
            {{ $label }} <span class="opacity-70">({{ $count }})</span>
        </a>
    @endforeach
</div>

@php
    $badgeMap = [
        'approved' => 'bg-green-500/20 text-green-400',
        'pending'  => 'bg-yellow-500/20 text-yellow-400',
        'rejected' => 'bg-red-500/20 text-red-400',
    ];
    $labelMap = [
        'approved' => 'Verified',
        'pending'  => 'Pending Review',
        'rejected' => 'Rejected',
    ];
@endphp

<div class="space-y-4">
    @forelse($verifications as $v)
        <div class="card">
            {{-- Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
                <div class="flex items-center gap-4">
                    @if($v->user && $v->user->profile_image)
                        <img src="{{ url('storage/'.$v->user->profile_image) }}" class="w-12 h-12 rounded-xl object-cover">
                    @else
                        <div class="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center text-purple-400 font-black text-lg">
                            {{ strtoupper(substr($v->user->name ?? '?', 0, 1)) }}
                        </div>
                    @endif
                    <div>
                        <div class="text-white font-bold">{{ $v->user->name ?? 'Unknown Owner' }}</div>
                        <div class="text-slate-400 text-sm">{{ $v->email }}</div>
                        @if($v->user && $v->user->company_name)
                            <div class="text-slate-500 text-xs mt-0.5"><i class="fas fa-building mr-1"></i>{{ $v->user->company_name }}</div>
                        @endif
                    </div>
                </div>
                <span class="badge {{ $badgeMap[$v->status] ?? 'bg-slate-500/20 text-slate-400' }}">
                    {{ $labelMap[$v->status] ?? ucfirst($v->status) }}
                </span>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Left: images --}}
                <div>
                    <p class="text-slate-400 text-xs mb-2">Valid ID</p>
                    @if($v->id_image)
                        <a href="{{ url('storage/'.$v->id_image) }}" target="_blank">
                            <img src="{{ url('storage/'.$v->id_image) }}" class="w-full max-w-xs rounded-lg border border-slate-700 mb-4">
                        </a>
                    @else
                        <p class="text-slate-500 text-sm mb-4">No ID uploaded.</p>
                    @endif

                    <p class="text-slate-400 text-xs mb-2">Court Photos</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach(['court_image_1','court_image_2','court_image_3'] as $ci)
                            @if($v->{$ci})
                                <a href="{{ url('storage/'.$v->{$ci}) }}" target="_blank">
                                    <img src="{{ url('storage/'.$v->{$ci}) }}" class="w-24 h-24 object-cover rounded-lg border border-slate-700">
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Right: links & documents --}}
                <div>
                    <p class="text-slate-400 text-xs mb-2">Social Media</p>
                    <ul class="text-sm text-slate-300 space-y-1 mb-4">
                        <li><i class="fab fa-facebook w-5 text-slate-500"></i> {{ $v->facebook ?: '—' }}</li>
                        <li><i class="fab fa-instagram w-5 text-slate-500"></i> {{ $v->instagram ?: '—' }}</li>
                        <li><i class="fab fa-tiktok w-5 text-slate-500"></i> {{ $v->tiktok ?: '—' }}</li>
                        <li><i class="fas fa-globe w-5 text-slate-500"></i> {{ $v->website ?: '—' }}</li>
                    </ul>

                    <p class="text-slate-400 text-xs mb-2">Documents</p>
                    @if(!empty($v->documents))
                        <ul class="text-sm space-y-1">
                            @foreach($v->documents as $doc)
                                <li><a href="{{ url('storage/'.$doc) }}" target="_blank" class="text-blue-400 hover:underline">
                                    <i class="fas fa-file mr-1"></i>{{ basename($doc) }}</a></li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-slate-500 text-sm">No documents uploaded.</p>
                    @endif

                    <p class="text-slate-500 text-xs mt-4">Submitted {{ $v->updated_at->diffForHumans() }}</p>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex flex-wrap gap-3 mt-6 pt-6 border-t border-slate-700">
                @if($v->status !== 'approved')
                    <form method="POST" action="{{ route('admin.owners.verification', $v->user_id) }}">
                        @csrf
                        <input type="hidden" name="status" value="approved">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-green-500 hover:bg-green-600 text-white font-bold transition">
                            <i class="fas fa-check mr-1"></i> Approve
                        </button>
                    </form>
                @endif
                @if($v->status !== 'rejected')
                    <form method="POST" action="{{ route('admin.owners.verification', $v->user_id) }}">
                        @csrf
                        <input type="hidden" name="status" value="rejected">
                        <button type="submit" class="px-5 py-2.5 rounded-xl bg-red-500 hover:bg-red-600 text-white font-bold transition">
                            <i class="fas fa-xmark mr-1"></i> Reject
                        </button>
                    </form>
                @endif
                @if($v->user)
                    <a href="{{ route('admin.owners.show', $v->user_id) }}"
                       class="px-5 py-2.5 rounded-xl bg-slate-700 hover:bg-slate-600 text-white font-semibold transition">
                        <i class="fas fa-eye mr-1"></i> View Owner
                    </a>
                @endif
            </div>
        </div>
    @empty
        <div class="card text-center py-12 text-slate-500">
            <i class="fas fa-shield-halved text-4xl mb-3 opacity-40"></i>
            <p>No verification requests{{ $status ? ' with this status' : '' }}.</p>
        </div>
    @endforelse
</div>

<div class="mt-6">
    {{ $verifications->links() }}
</div>
@endsection
