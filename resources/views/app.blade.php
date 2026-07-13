<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'MediaForge') }}</title>

        {{-- Set the theme before first paint to avoid a flash of the wrong theme. --}}
        <script>
            (function () {
                var stored = localStorage.getItem('mediaforge.theme');
                var dark = stored === 'dark' || ((stored === null || stored === 'system') &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
            })();
        </script>

        {{-- Ziggy's @routes blob is intentionally omitted: the React/Inertia frontend
             uses plain path strings, not route(), so emitting the full route table
             (incl. Horizon admin routes) on every page load is dead weight. Re-add
             @routes here if a future page needs the client-side route() helper. --}}
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-surface text-fg antialiased">
        @inertia
    </body>
</html>
