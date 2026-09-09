<!DOCTYPE html>
<html lang="nl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Inloggen · Financiën</title>
        @vite(['resources/css/app.css'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-200 flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h1 class="text-xl font-bold text-white">Financiën</h1>
            <p class="text-sm text-slate-400 mt-1">Log in met je account uit de database.</p>

            @if ($errors->any())
                <p class="mt-4 text-sm text-rose-400">{{ $errors->first() }}</p>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-300 mb-1">E-mail</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-indigo-500"
                    >
                </div>
                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-300 mb-1">Wachtwoord</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="w-full bg-slate-800 border border-slate-700 rounded-xl px-3.5 py-2.5 text-sm text-white focus:outline-none focus:border-indigo-500"
                    >
                </div>
                <label class="flex items-center gap-2 text-xs text-slate-400">
                    <input type="checkbox" name="remember" class="rounded border-slate-600 bg-slate-800">
                    Onthoud mij
                </label>
                <button
                    type="submit"
                    class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm py-2.5 rounded-xl transition-colors"
                >
                    Inloggen
                </button>
            </form>
        </div>
    </body>
</html>
