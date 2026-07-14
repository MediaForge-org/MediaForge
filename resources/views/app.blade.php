<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'MediaForge') }}</title>

        {{-- Set appearance + design preset before first paint to avoid a flash. --}}
        <script>
            (function () {
                var root = document.documentElement;
                var stored = localStorage.getItem('mediaforge.theme');
                var dark = stored === 'dark' || ((stored === null || stored === 'system') &&
                    window.matchMedia('(prefers-color-scheme: dark)').matches);
                root.setAttribute('data-theme', dark ? 'dark' : 'light');

                var presets = ['neon-command', 'streaming-os', 'glass-workspace', 'holographic-console', 'hybrid'];
                var preset = localStorage.getItem('mediaforge.preset');
                root.setAttribute('data-design-preset', presets.indexOf(preset) !== -1 ? preset : 'hybrid');
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
    <body class="antialiased">
        @inertia
    </body>
</html>
