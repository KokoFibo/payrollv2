<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow, noarchive, nosnippet, noimageindex, noai, noimageai">
        <meta name="googlebot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
        <meta name="bingbot" content="noindex, nofollow, noarchive, nosnippet, noimageindex">
        <meta name="GPTBot" content="noindex, nofollow, noarchive">
        <meta name="CCBot" content="noindex, nofollow, noarchive">
        <meta name="anthropic-ai" content="noindex, nofollow, noarchive">
        <meta name="ClaudeBot" content="noindex, nofollow, noarchive">
        <meta name="theme-color" content="#b91c1c">

        <title inertia>{{ config('app.name', 'Payroll Workspace') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700|plus-jakarta-sans:400,500,600,700,800&display=swap" rel="stylesheet" />
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
        <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
        <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">

        @routes
        @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
        @inertiaHead
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        @inertia
    </body>
</html>
