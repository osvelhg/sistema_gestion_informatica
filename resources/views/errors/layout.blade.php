<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('Error')) — {{ config('app.name', 'SGI') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css'])
    <script>
        (function () {
            var key = 'sgi-theme';
            try {
                var saved = localStorage.getItem(key) || 'system';
                var dark = saved === 'dark' || (saved === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
                document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            } catch (e) {}
        })();
    </script>
</head>
<body class="min-h-full font-sans antialiased bg-mesh-light text-ink-900 dark:bg-mesh-dark dark:text-slate-100">
    <div class="pointer-events-none fixed inset-0 -z-10 bg-[radial-gradient(circle_at_20%_20%,rgba(34,211,238,0.14),transparent_22%),radial-gradient(circle_at_80%_0%,rgba(59,130,246,0.14),transparent_26%)] dark:bg-[radial-gradient(circle_at_20%_20%,rgba(34,211,238,0.1),transparent_18%),radial-gradient(circle_at_80%_0%,rgba(37,99,235,0.14),transparent_22%)]" aria-hidden="true"></div>

    <main class="relative flex min-h-screen flex-col items-center justify-center px-4 py-12 sm:px-6">
        @yield('content')
    </main>
</body>
</html>
