@extends('errors.layout')

@section('title', 'Error del servidor')

@php
    $showDetail = config('app.debug');
    $technical = $showDetail ? $exception->getMessage() : null;
@endphp

@section('content')
    <div class="surface-card w-full max-w-lg p-8 text-center shadow-panel sm:p-10">
        <div class="mx-auto mb-6 flex h-16 w-16 items-center justify-center rounded-2xl bg-rose-500/10 text-rose-600 ring-1 ring-rose-500/20 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/25">
            <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.6" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
            </svg>
        </div>
        <p class="font-display text-5xl font-bold tracking-tight text-rose-600 dark:text-rose-300">500</p>
        <h1 class="mt-3 font-display text-xl font-semibold text-slate-900 dark:text-white">Algo salió mal</h1>
        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            Ha ocurrido un error en el servidor. Si el problema continúa, contacta al administrador.
        </p>
        @if($technical)
            <pre class="mt-4 max-h-40 overflow-auto rounded-xl border border-slate-200/80 bg-slate-50 p-3 text-left text-xs text-slate-700 dark:border-slate-700 dark:bg-slate-900/80 dark:text-slate-300">{{ $technical }}</pre>
        @endif
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
            <a href="{{ url()->current() }}" class="app-button-secondary justify-center px-6 py-3">
                Reintentar
            </a>
        </div>
    </div>
    <p class="mt-8 text-center text-xs text-slate-500 dark:text-slate-500">
        {{ config('app.name', 'SGI') }}
    </p>
@endsection
