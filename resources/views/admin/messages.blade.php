@extends('admin.layout')
@section('title', 'Messages')
@section('content')

<div class="mb-6">
    <h1 class="text-2xl font-bold text-white">Messages</h1>
    <p class="text-slate-400 text-sm mt-1">View all user messages</p>
</div>

{{-- Search --}}
<div class="card mb-6">
    <form action="{{ route('admin.messages') }}" method="GET" class="flex gap-3">
        <input 
            type="text" 
            name="search" 
            placeholder="Search messages..." 
            value="{{ $search }}"
            class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-white placeholder-slate-500 focus:outline-none focus:border-green-500"
        >
        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-6 py-2 rounded-lg font-medium">
            Search
        </button>
        @if($search)
            <a href="{{ route('admin.messages') }}" class="bg-slate-700 hover:bg-slate-600 text-white px-6 py-2 rounded-lg font-medium">
                Clear
            </a>
        @endif
    </form>
</div>

@if($messages->count() > 0)
    @foreach($messages as $message)
        @php
            $user = $message->user;
            $isUnreplied = is_null($message->admin_id);
        @endphp
        <div class="card mb-4" style="border-left: 4px solid {{ $isUnreplied ? '#4CAF50' : '#64748b' }}">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400 font-bold text-lg shrink-0">
                    {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                </div>
                <div class="flex-1">
                    <div class="flex items-center justify-between mb-2">
                        <div class="flex items-center gap-2">
                            <h3 class="text-white font-bold">{{ $user->name ?? 'Unknown User' }}</h3>
                            @if($isUnreplied)
                                <span class="bg-green-500 text-white text-xs px-2 py-1 rounded-full">Unreplied</span>
                            @endif
                        </div>
                        <span class="text-slate-500 text-xs">
                            {{ $message->created_at->format('M d, Y g:i A') }}
                        </span>
                    </div>
                    <p class="text-slate-500 text-sm mb-1">{{ $user->email ?? '' }}</p>
                    <p class="text-white text-sm">{{ $message->message }}</p>
                    @if($message->admin_id)
                        <span class="text-green-400 text-xs mt-1">Replied by admin</span>
                    @else
                        <span class="text-yellow-400 text-xs mt-1">Pending reply</span>
                    @endif
                </div>
                @if($isUnreplied)
                    <span class="w-2 h-2 rounded-full bg-green-500 shrink-0 mt-2"></span>
                @endif
            </div>

            {{-- Reply Form --}}
            @if($isUnreplied)
                <form action="{{ route('admin.messages.reply', $message->id) }}" method="POST" class="mt-4 pt-4 border-t border-slate-700">
                    @csrf
                    <textarea 
                        name="reply" 
                        rows="3" 
                        placeholder="Type your reply..." 
                        class="w-full bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-white placeholder-slate-500 focus:outline-none focus:border-green-500 resize-none"
                        required
                    ></textarea>
                    <div class="flex justify-end mt-2">
                        <button type="submit" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg font-medium text-sm">
                            Send Reply
                        </button>
                    </div>
                </form>
            @endif
        </div>
    @endforeach
    
    <div class="flex justify-center mt-6">
        {{ $messages->links() }}
    </div>
@else
    <div class="card text-center py-12">
        <i class="fas fa-envelope-open text-6xl text-slate-600 mb-4"></i>
        <h3 class="text-white text-lg font-medium mb-2">No Messages Found</h3>
        <p class="text-slate-500 text-sm">There are no messages in the system yet.</p>
    </div>
@endif

@endsection
