<script setup>
import { useForm, Head } from '@inertiajs/vue3'

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Iniciar Sesion" />

    <div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-[radial-gradient(circle_at_top,_rgba(56,189,248,0.18),_transparent_35%),linear-gradient(135deg,_#020617_0%,_#0f172a_45%,_#082f49_100%)] px-4 py-10">
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.04)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.04)_1px,transparent_1px)] bg-[size:72px_72px] opacity-30" />
        <div class="relative z-10 w-full max-w-md">
            <div class="mb-8 text-center">
                <p class="text-xs font-semibold uppercase tracking-[0.35em] text-cyan-200/80">SGI Platform</p>
                <h1 class="mt-4 font-display text-5xl font-bold tracking-tight text-white">SGI</h1>
                <p class="mt-3 text-sm text-slate-300">Sistema de Gestion Informatica con una experiencia moderna, clara y enfocada en productividad.</p>
            </div>

            <div class="rounded-[28px] border border-white/10 bg-white/10 p-8 shadow-2xl shadow-cyan-950/30 backdrop-blur-2xl">
                <h2 class="text-center text-2xl font-semibold text-white">Iniciar sesion</h2>
                <p class="mt-2 text-center text-sm text-slate-300">Accede a tu espacio de trabajo y continua donde lo dejaste.</p>

                <form class="mt-8 space-y-5" @submit.prevent="submit">
                    <div class="space-y-2">
                        <label for="email" class="text-sm font-medium text-slate-200">Usuario o correo</label>
                        <input id="email" v-model="form.email" type="text" autocomplete="username" required class="w-full rounded-2xl border border-white/10 bg-slate-950/40 px-4 py-3 text-sm text-white placeholder:text-slate-400 focus:border-cyan-400/70 focus:outline-none focus:ring-2 focus:ring-cyan-400/30" placeholder="jperez o jperez@empresa.cu" />
                        <p v-if="form.errors.email" class="text-xs text-rose-300">{{ form.errors.email }}</p>
                    </div>

                    <div class="space-y-2">
                        <label for="password" class="text-sm font-medium text-slate-200">Contrasena</label>
                        <input id="password" v-model="form.password" type="password" autocomplete="current-password" required class="w-full rounded-2xl border border-white/10 bg-slate-950/40 px-4 py-3 text-sm text-white placeholder:text-slate-400 focus:border-cyan-400/70 focus:outline-none focus:ring-2 focus:ring-cyan-400/30" placeholder="••••••••" />
                        <p v-if="form.errors.password" class="text-xs text-rose-300">{{ form.errors.password }}</p>
                    </div>

                    <label class="flex items-center gap-3 text-sm text-slate-300">
                        <input id="remember" v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-white/20 bg-slate-900/40 text-cyan-400 focus:ring-cyan-400/40" />
                        Recordar sesion
                    </label>

                    <button type="submit" :disabled="form.processing" class="flex w-full items-center justify-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 disabled:cursor-not-allowed disabled:opacity-60">
                        {{ form.processing ? 'Entrando...' : 'Entrar' }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</template>
