import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';
import flowbite from 'flowbite/plugin';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './vendor/laravel/jetstream/**/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
        './node_modules/flowbite-vue/**/*.{js,jsx,ts,tsx,vue}',
        './node_modules/flowbite/**/*.{js,jsx,ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['"Plus Jakarta Sans"', ...defaultTheme.fontFamily.sans],
                display: ['"Space Grotesk"', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                brand: {
                    50: '#ecfeff',
                    100: '#cffafe',
                    200: '#a5f3fc',
                    300: '#67e8f9',
                    400: '#22d3ee',
                    500: '#06b6d4',
                    600: '#0891b2',
                    700: '#0e7490',
                    800: '#155e75',
                    900: '#164e63',
                },
                ink: {
                    50: '#f8fafc',
                    100: '#f1f5f9',
                    200: '#e2e8f0',
                    300: '#cbd5e1',
                    400: '#94a3b8',
                    500: '#64748b',
                    600: '#475569',
                    700: '#334155',
                    800: '#1e293b',
                    900: '#0f172a',
                    950: '#020617',
                },
            },
            boxShadow: {
                panel: '0 10px 40px -18px rgba(15, 23, 42, 0.22)',
                glow: '0 0 0 1px rgba(34, 211, 238, 0.14), 0 18px 50px -22px rgba(8, 145, 178, 0.35)',
            },
            backgroundImage: {
                'mesh-light': 'radial-gradient(circle at top left, rgba(34,211,238,0.16), transparent 35%), radial-gradient(circle at top right, rgba(59,130,246,0.12), transparent 30%), linear-gradient(180deg, rgba(255,255,255,0.96), rgba(248,250,252,0.92))',
                'mesh-dark': 'radial-gradient(circle at top left, rgba(34,211,238,0.14), transparent 28%), radial-gradient(circle at top right, rgba(37,99,235,0.18), transparent 24%), linear-gradient(180deg, rgba(15,23,42,0.98), rgba(2,6,23,0.95))',
            },
        },
    },
    plugins: [forms, typography, flowbite],
};
