@extends('admin.layout')
@section('title', 'Users')
@section('content')

<div class="flex items-center justify-between mb-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Users</h1>
        <p class="text-slate-400 text-sm mt-1">{{ $users->total() }} registered players</p>
    </div>
    <form method="GET" action="{{ route('admin.users') }}" class="flex gap-2">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search name or email…"
            class="px-4 py-2 rounded-xl bg-slate-700 text-white border border-slate-600 focus:border-green-500 focus:outline-none text-sm w-64">
        <button type="submit" class="px-4 py-2 rounded-xl bg-green-500 hover:bg-green-600 text-white text-sm font-semibold">Search</button>
        @if($search)
            <a href="{{ route('admin.users') }}" class="px-4 py-2 rounded-xl bg-slate-600 hover:bg-slate-500 text-white text-sm">Clear</a>
        @endif
    </form>
</div>

<div class="card overflow-hidden p-0">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b border-slate-700">
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">User</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Email</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Phone</th>
                <th class="text-left px-6 py-4 text-slate-400 font-semibold">Joined</th>
                <th class="px-6 py-4"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($users as $user)
            <tr class="border-b border-slate-700/50 hover:bg-slate-700/30 transition">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        @if($user->profile_image)
                            <img src="{{ url('storage/'.$user->profile_image) }}" class="w-9 h-9 rounded-full object-cover">
                        @else
                            <div class="w-9 h-9 rounded-full bg-blue-500/20 flex items-center justify-center text-blue-400 font-bold text-sm">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                        @endif
                        <span class="text-white font-medium">{{ $user->name }}</span>
                    </div>
                </td>
                <td class="px-6 py-4 text-slate-300">{{ $user->email }}</td>
                <td class="px-6 py-4 text-slate-400">{{ $user->phone ?? '—' }}</td>
                <td class="px-6 py-4 text-slate-400">{{ $user->created_at->format('M d, Y') }}</td>
                <td class="px-6 py-4 text-right">
                    <a href="{{ route('admin.users.show', $user->id) }}" class="text-green-400 hover:text-green-300 text-xs font-semibold mr-3">View</a>
                    <form method="POST" action="{{ route('admin.users.destroy', $user->id) }}" class="inline" onsubmit="return confirm('Delete this user?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-red-400 hover:text-red-300 text-xs font-semibold">Delete</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-6 py-12 text-center text-slate-500">No users found.</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="px-6 py-4 border-t border-slate-700">
        {{ $users->links() }}
    </div>
</div>
@endsection
