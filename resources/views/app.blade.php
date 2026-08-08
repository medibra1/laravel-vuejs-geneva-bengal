<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        {{-- No axios in this app (Inertia's own router handles everything
             else) — useConfirmsPassword.ts reads this directly for the one
             place it needs a plain fetch() instead of an Inertia visit. --}}
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        {{-- Applied synchronously, before any CSS paints, so the admin
             panel doesn't flash light before hydration can read the
             stored preference — see AdminLayout.vue. Harmless on public
             pages: nothing there uses a dark: variant. --}}
        <script>
            if (localStorage.getItem('admin.themeDark') === '1') {
                document.documentElement.classList.add('dark');
            }
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        {{-- Public-site brand fonts, matching the original Angular app
             (bengal-client): Sacramento for the script section titles,
             Montserrat for the bold uppercase subtitles/buttons. Harmless
             on admin pages, which never reference font-script/font-heading. --}}
        <link href="https://fonts.bunny.net/css?family=sacramento:400|montserrat:400,500,600,700|varela-round:400&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @vite(['resources/js/app.ts', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
