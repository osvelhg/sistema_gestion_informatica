@extends('errors.layout')

@section('title', 'Acceso denegado')

@php
    $raw = $exception->getMessage();
    $description = filled($raw) && ! in_array($raw, ['Forbidden', 'HTTP 403 Forbidden'], true)
        ? $raw
        : 'No tienes permiso para ver este contenido o el módulo está desactivado.';
@endphp

@section('content')
    <div class="surface-card w-full max-w-lg p-8 text-center shadow-panel sm:p-10">
        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-amber-500/10 text-amber-700 ring-1 ring-amber-500/25 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/25">
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
            </svg>
        </div>
        <p class="font-display text-5xl font-bold tracking-tight text-amber-600 dark:text-amber-300">403</p>
        <h1 class="mt-3 font-display text-xl font-semibold text-slate-900 dark:text-white">Acceso denegado</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            {{ $description }}
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
