import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';

const appName = 'Migraine Tracker';

createInertiaApp({
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const pageImport = pages[`./Pages/${name}.vue`] as unknown as () => Promise<any>;

        if (!pageImport) {
            throw new Error(`Page not found: ${name}`);
        }

        const mod = await pageImport();
        return mod.default;
    },
    setup({ el, App, props, plugin }) {
        const vueApp = createApp({ render: () => h(App, props) });

        vueApp.use(plugin);
        vueApp.mount(el);
    },
    progress: {
        color: '#5F9E86',
    },
    title: (title) => (title ? `${title} • ${appName}` : appName),
});
