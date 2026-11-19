@php($theme = in_array($appearance ?? 'system', ['light', 'dark'], true) ? $appearance : null)
<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="kep-root"
    data-density="comfortable"
    @if ($theme) data-theme="{{ $theme }}" @endif
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{--
            Theme: data-theme="light|dark" on <html> forces a theme; without it the
            design-system tokens follow the OS (prefers-color-scheme), so no script is needed.
            The inline background avoids a white flash before the stylesheet loads.
        --}}
        <style>
            html { background-color: #f8f9fb; }
            html[data-theme='dark'] { background-color: #0d1014; }
            @media (prefers-color-scheme: dark) {
                html:not([data-theme='light']) { background-color: #0d1014; }
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Skill K.E.P') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
