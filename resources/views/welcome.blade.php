<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Report Generator</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
        <main class="flex min-h-screen items-center justify-center px-4 py-10 sm:px-6">
            <section class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-6 shadow-sm sm:p-8">
                <header class="mb-8">
                    <p class="text-sm font-semibold text-teal-700">Report Generator</p>
                    <h1 class="mt-2 text-2xl font-bold text-slate-900">Welcome back</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Generate your daily reports quickly and consistently.</p>
                </header>

                <form method="POST" action="{{ route('login') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="username" class="block text-sm font-medium text-slate-700">Username</label>
                        <input id="username" name="username" type="text" value="{{ old('username') }}" required autofocus autocomplete="username" class="mt-2 block w-full rounded-md border-slate-300 px-3 py-3 text-base shadow-sm focus:border-teal-600 focus:ring-teal-600" />
                        @error('username')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                        <input id="password" name="password" type="password" required autocomplete="current-password" class="mt-2 block w-full rounded-md border-slate-300 px-3 py-3 text-base shadow-sm focus:border-teal-600 focus:ring-teal-600" />
                        @error('password')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <label for="remember" class="flex items-center gap-3 text-sm text-slate-700">
                        <input id="remember" name="remember" type="checkbox" value="1" class="h-4 w-4 rounded border-slate-300 text-teal-700 focus:ring-teal-600" />
                        Remember me
                    </label>

                    <button type="submit" class="w-full rounded-md bg-teal-700 px-4 py-3 text-base font-semibold text-white shadow-sm hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-600 focus:ring-offset-2">
                        Sign in
                    </button>
                </form>

                @if (Route::has('register'))
                    <p class="mt-6 text-center text-sm text-slate-600">
                        <a href="{{ route('register') }}" class="font-medium text-teal-700 hover:text-teal-800">Create account</a>
                    </p>
                @endif
            </section>
        </main>
    </body>
</html>