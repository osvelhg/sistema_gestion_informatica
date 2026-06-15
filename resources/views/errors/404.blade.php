@extends('errors.layout')

@section('title', 'Página no encontrada')

@section('content')
    <div class="surface-card w-full max-w-lg p-8 text-center shadow-panel sm:p-10">
        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-cyan-500/10 text-brand-600 ring-1 ring-cyan-500/20 dark:bg-cyan-400/10 dark:text-brand-300 dark:ring-cyan-400/20">
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
            </svg>
        </div>
        <p class="font-display text-5xl font-bold tracking-tight text-brand-600 dark:text-brand-300">404</p>
        <h1 class="mt-3 font-display text-xl font-semibold text-slate-900 dark:text-white">Página no encontrada</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            La dirección no existe o fue movida. Comprueba la URL o vuelve al panel.
        </p>
        <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:justify-center">
            @if(auth()->check())
                <a href="{{ url('/dashboard') }}" class="app-button-primary justify-center px-6 py-3">
                    Ir al panel
                </a>
            @elseif(Route::has('login'))
                <a href="{{ route('login') }}" class="app-button-primary justify-center px-6 py-3">
                    Iniciar sesión
                </a>
            @endif
            <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href='{{ url('/') }}')" class="app-button-secondary justify-center px-6 py-3">
                Volver atrás
            </button>
        </div>
    </div>
    <p class="mt-8 text-center text-xs text-slate-500 dark:text-slate-500">
        {{ config('app.name', 'SGI') }}
    </p>
@endsection
