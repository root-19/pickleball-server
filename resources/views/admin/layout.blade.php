<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Admin') — Pickaball</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"/>
    <style>
        body { background: #0f172a; color: #e2e8f0; font-family: 'Inter', sans-serif; }
        .sidebar { background: #1e293b; min-height: 100vh; width: 240px; flex-shrink: 0; }
        .nav-link { display:flex; align-items:center; gap:10px; padding:10px 20px; border-radius:8px; color:#94a3b8; transition:all .2s; }
        .nav-link:hover, .nav-link.active { background:#4CAF50; color:#fff; }
        .card { background:#1e293b; border-radius:12px; padding:24px; }
        .stat-card { background: linear-gradient(135deg,#1e293b,#0f172a); border:1px solid #334155; border-radius:14px; padding:20px; }
        .badge { display:inline-block; padding:2px 10px; border-radius:99px; font-size:12px; font-weight:600; }
    </style>
</head>
<body>
<div class="flex">
    {{-- Sidebar --}}
    <aside class="sidebar fixed top-0 left-0 flex flex-col py-6 px-3 z-10">
        <div class="flex items-center gap-3 px-3 mb-8">
            <div class="w-9 h-9 rounded-lg bg-green-500 flex items-center justify-center text-white font-black text-lg">P</div>
            <span class="font-bold text-white text-lg">Pickaball</span>
        </div>
        <nav class="flex flex-col gap-1">
            <a href="{{ route('admin.dashboard') }}" class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <i class="fas fa-chart-line w-4"></i> Dashboard
            </a>
            <a href="{{ route('admin.users') }}" class="nav-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
                <i class="fas fa-users w-4"></i> Users
            </a>
            <a href="{{ route('admin.owners') }}" class="nav-link {{ request()->routeIs('admin.owners*') ? 'active' : '' }}">
                <i class="fas fa-store w-4"></i> Court Owners
            </a>
            <a href="{{ route('admin.payouts') }}" class="nav-link {{ request()->routeIs('admin.payouts*') ? 'active' : '' }}">
                <i class="fas fa-money-bill-transfer w-4"></i> Payouts
            </a>
        </nav>
        <div class="mt-auto px-3">
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="nav-link w-full text-left text-red-400 hover:bg-red-500 hover:text-white">
                    <i class="fas fa-sign-out-alt w-4"></i> Logout
                </button>
            </form>
        </div>
    </aside>

    {{-- Main --}}
    <main class="ml-60 flex-1 p-8 min-h-screen">
        @if(session('success'))
            <div class="mb-4 px-4 py-3 rounded-lg bg-green-500/20 text-green-400 border border-green-500/30">
                {{ session('success') }}
            </div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
