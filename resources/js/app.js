import './bootstrap';
import 'sweetalert2/dist/sweetalert2.min.css';
import 'vue-select/dist/vue-select.css';
import '../css/app.css';

import { createApp, h } from 'vue';
import VueSelect from 'vue-select';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/src/js/index.js';
import 'flowbite';
import { initializeTheme } from './Composables/useTheme';

const appName = import.meta.env.VITE_APP_NAME || 'SGI';

initializeTheme()

/** Mantiene window.Ziggy alineado con Laravel (props compartidas), evitando listas obsoletas tras nuevas rutas o visitas Inertia. */
function applyZiggyFromPage(page) {
    const z = page?.props?.ziggy;
    if (!z || typeof window === 'undefined') {
        return;
    }
    window.Ziggy = {
        url: z.url,
        port: z.port ?? null,
        defaults: z.defaults ?? {},
        routes: { ...(z.routes ?? {}) },
        location: z.location,
    };
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        applyZiggyFromPage(props.initialPage);

        router.on('success', (event) => {
            applyZiggyFromPage(event.detail.page);
        });

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .component('VSelect', VueSelect)
            .mount(el);
    },
    progress: {
        color: '#2563eb',
    },
});
