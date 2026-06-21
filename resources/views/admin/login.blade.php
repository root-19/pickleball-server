<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Pickaball</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background:#0f172a; }</style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="w-full max-w-sm p-8 rounded-2xl shadow-2xl" style="background:#1e293b;">
        <div class="flex flex-col items-center mb-8">
            <div class="w-14 h-14 rounded-2xl bg-green-500 flex items-center justify-center text-white font-black text-3xl mb-3">P</div>
            <h1 class="text-2xl font-bold text-white">Pickaball Admin</h1>
            <p class="text-slate-400 text-sm mt-1">Sign in to your admin account</p>
        </div>

        @if($errors->any())
            <div class="mb-4 px-4 py-3 rounded-lg bg-red-500/20 text-red-400 text-sm border border-red-500/30">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('admin.login.post') }}" class="flex flex-col gap-4">
            @csrf
            <div>
                <label class="text-slate-400 text-xs uppercase tracking-wider mb-1 block">Email</label>
                <input type="email" name="email" value="{{ old('email') }}" required
                    class="w-full px-4 py-3 rounded-xl bg-slate-700 text-white border border-slate-600 focus:border-green-500 focus:outline-none"
                    placeholder="admin@pickaball.com">
            </div>
            <div>
                <label class="text-slate-400 text-xs uppercase tracking-wider mb-1 block">Password</label>
                <input type="password" name="password" required
                    class="w-full px-4 py-3 rounded-xl bg-slate-700 text-white border border-slate-600 focus:border-green-500 focus:outline-none"
                    placeholder="••••••••">
            </div>
            <button type="submit"
                class="w-full py-3 rounded-xl bg-green-500 hover:bg-green-600 text-white font-bold text-base transition mt-2">
                Sign In
            </button>
        </form>
    </div>
</body>
</html>
