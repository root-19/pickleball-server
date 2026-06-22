@extends('admin.layout')
@section('title', 'Messages')
@section('content')

<style>
.msg-layout { display: flex; height: calc(100vh - 120px); gap: 0; background: #1e293b; border-radius: 16px; overflow: hidden; border: 1px solid #334155; }
.msg-sidebar { width: 320px; flex-shrink: 0; border-right: 1px solid #334155; display: flex; flex-direction: column; }
.msg-sidebar-header { padding: 16px; border-bottom: 1px solid #334155; }
.msg-sidebar-search input { width: 100%; background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 8px 12px; color: #e2e8f0; font-size: 13px; outline: none; }
.msg-sidebar-search input:focus { border-color: #4CAF50; }
.msg-user-list { flex: 1; overflow-y: auto; }
.msg-user-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; cursor: pointer; transition: background .15s; border-bottom: 1px solid #1e293b; }
.msg-user-item:hover { background: #0f172a; }
.msg-user-item.active { background: #0f172a; border-left: 3px solid #4CAF50; }
.msg-avatar { width: 42px; height: 42px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; flex-shrink: 0; }
.msg-user-info { flex: 1; min-width: 0; }
.msg-user-name { font-weight: 600; color: #e2e8f0; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.msg-user-preview { font-size: 12px; color: #64748b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 2px; }
.msg-badge { background: #4CAF50; color: #fff; border-radius: 99px; font-size: 11px; font-weight: 700; padding: 2px 7px; flex-shrink: 0; }
.msg-main { flex: 1; display: flex; flex-direction: column; min-width: 0; }
.msg-main-header { padding: 16px 20px; border-bottom: 1px solid #334155; display: flex; align-items: center; gap: 12px; }
.msg-thread { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; }
.msg-bubble-wrap { display: flex; align-items: flex-end; gap: 8px; }
.msg-bubble-wrap.user { justify-content: flex-start; }
.msg-bubble-wrap.admin { justify-content: flex-end; }
.msg-bubble { max-width: 65%; padding: 10px 14px; border-radius: 16px; font-size: 14px; line-height: 1.5; }
.msg-bubble.user { background: #334155; color: #e2e8f0; border-bottom-left-radius: 4px; }
.msg-bubble.admin { background: #4CAF50; color: #fff; border-bottom-right-radius: 4px; }
.msg-time { font-size: 11px; color: #64748b; margin-top: 4px; }
.msg-compose { padding: 16px 20px; border-top: 1px solid #334155; display: flex; gap: 10px; align-items: flex-end; }
.msg-compose textarea { flex: 1; background: #0f172a; border: 1px solid #334155; border-radius: 12px; padding: 10px 14px; color: #e2e8f0; font-size: 14px; resize: none; outline: none; min-height: 44px; max-height: 120px; }
.msg-compose textarea:focus { border-color: #4CAF50; }
.msg-send-btn { background: #4CAF50; border: none; color: #fff; width: 44px; height: 44px; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background .15s; flex-shrink: 0; }
.msg-send-btn:hover { background: #43a047; }
.msg-empty { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: #475569; }
</style>

<div class="mb-4">
    <h1 class="text-2xl font-bold text-white">Messages</h1>
    <p class="text-slate-400 text-sm mt-1">Help center conversations</p>
</div>

<div class="msg-layout">

    {{-- Left: User list --}}
    <div class="msg-sidebar">
        <div class="msg-sidebar-header">
            <div class="msg-sidebar-search">
                <form method="GET" action="{{ route('admin.messages') }}">
                    @if($selectedUser)
                        <input type="hidden" name="user" value="{{ $selectedUser->id }}">
                    @endif
                    <input type="text" name="search" placeholder="Search users..." value="{{ $search }}" autocomplete="off">
                </form>
            </div>
        </div>
        <div class="msg-user-list">
            @forelse($users as $u)
                <a href="{{ route('admin.messages', ['user' => $u->id, 'search' => $search]) }}" class="msg-user-item {{ $selectedUser && $selectedUser->id == $u->id ? 'active' : '' }}">
                    <div class="msg-avatar bg-blue-500/20 text-blue-400">
                        {{ strtoupper(substr($u->name, 0, 1)) }}
                    </div>
                    <div class="msg-user-info">
                        <div class="msg-user-name">{{ $u->name }}</div>
                        <div class="msg-user-preview">{{ Str::limit(optional($u->latest_message)->message ?? 'No messages', 35) }}</div>
                    </div>
                    @if($u->unreplied_count > 0)
                        <span class="msg-badge">{{ $u->unreplied_count }}</span>
                    @endif
                </a>
            @empty
                <div class="text-center py-10 text-slate-500 text-sm">No users found</div>
            @endforelse
        </div>
    </div>

    {{-- Right: Conversation --}}
    <div class="msg-main">
        @if($selectedUser)
            {{-- Header --}}
            <div class="msg-main-header">
                <div class="msg-avatar bg-blue-500/20 text-blue-400" style="width:38px;height:38px;font-size:15px;">
                    {{ strtoupper(substr($selectedUser->name, 0, 1)) }}
                </div>
                <div>
                    <div class="text-white font-semibold text-sm">{{ $selectedUser->name }}</div>
                    <div class="text-slate-500 text-xs">{{ $selectedUser->email }}</div>
                </div>
            </div>

            {{-- Thread --}}
            <div class="msg-thread" id="msgThread">
                @forelse($thread as $msg)
                    @php $isAdmin = !is_null($msg->admin_id); @endphp
                    <div class="msg-bubble-wrap {{ $isAdmin ? 'admin' : 'user' }}">
                        @if(!$isAdmin)
                            <div class="msg-avatar bg-blue-500/20 text-blue-400" style="width:30px;height:30px;font-size:12px;flex-shrink:0;">
                                {{ strtoupper(substr($selectedUser->name, 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <div class="msg-bubble {{ $isAdmin ? 'admin' : 'user' }}">{{ $msg->message }}</div>
                            <div class="msg-time {{ $isAdmin ? 'text-right' : '' }}">{{ $msg->created_at->format('M d, g:i A') }}</div>
                        </div>
                        @if($isAdmin)
                            <div class="msg-avatar bg-green-500/20 text-green-400" style="width:30px;height:30px;font-size:12px;flex-shrink:0;">
                                A
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="text-center text-slate-500 text-sm mt-10">No messages yet</div>
                @endforelse
            </div>

            {{-- Compose --}}
            <div class="msg-compose">
                <form action="{{ route('admin.messages.reply.user', $selectedUser->id) }}" method="POST" style="display:flex;gap:10px;align-items:flex-end;width:100%">
                    @csrf
                    <textarea name="reply" rows="1" placeholder="Type a reply..." required
                        onInput="this.style.height='auto';this.style.height=this.scrollHeight+'px'"
                        onKeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();this.closest('form').submit();}"
                    ></textarea>
                    <button type="submit" class="msg-send-btn">
                        <i class="fas fa-paper-plane text-sm"></i>
                    </button>
                </form>
            </div>
        @else
            <div class="msg-empty">
                <i class="fas fa-comments text-5xl mb-4"></i>
                <p class="text-lg font-medium">Select a conversation</p>
                <p class="text-sm mt-1">Choose a user from the left to view messages</p>
            </div>
        @endif
    </div>
</div>

<script>
    // Auto-scroll to bottom of thread
    const thread = document.getElementById('msgThread');
    if (thread) thread.scrollTop = thread.scrollHeight;
</script>

@endsection
