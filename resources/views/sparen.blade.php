@php
    $sparenUser = auth()->user();
    $sparenBoot = [
        'user' => $sparenUser
            ? [
                'name' => $sparenUser->name,
                'email' => $sparenUser->email,
                'avatar' => $sparenUser->avatar ?? null,
            ]
            : null,
    ];
@endphp
<!DOCTYPE html>
<html lang="nl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Financiën</title>
        <script>
            window.__SPAREN__ = @json($sparenBoot);
        </script>
        @vite(['resources/js/sparen/main.tsx'])
    </head>
    <body class="bg-slate-950">
        <div id="root"></div>
    </body>
</html>
