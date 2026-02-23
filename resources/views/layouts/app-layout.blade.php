<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Agentic Banking' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50" x-data="{ sidebarOpen: false }">
    <div class="min-h-screen">
        <!-- Header -->
        <x-app-header />

        <div class="flex">
            <!-- Sidebar -->
            <x-app-sidebar />

            <!-- Main Content -->
            <main class="flex-1 p-6 lg:p-8">
                <!-- Flash Messages -->
                @if (session('success'))
                <div class="mb-6 bg-white border border-gray-300 text-gray-900 px-4 py-3 rounded-xl shadow-sm">
                    {{ session('success') }}
                </div>
                @endif

                @if (session('error'))
                <div class="mb-6 bg-white border border-gray-300 text-gray-900 px-4 py-3 rounded-xl shadow-sm">
                    {{ session('error') }}
                </div>
                @endif

                <!-- Page Content -->
                {{ $slot }}
            </main>
        </div>
    </div>

    @livewireScripts
    @stack('scripts')
</body>
</html>
