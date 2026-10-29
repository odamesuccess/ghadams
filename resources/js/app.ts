import '../css/app.css';

document.documentElement.classList.remove('light');
document.documentElement.classList.add('dark');

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { fillViewportTable } from './directives/fillViewportTable';

const appName = import.meta.env.VITE_APP_NAME || 'GHADAMS';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .directive('fill-viewport-table', fillViewportTable)
            .mount(el);
    },
    progress: {
        color: '#2563eb',
    },
});

if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/sw.js')
            .then((registration) => {
                console.info('GHADAMS PWA service worker registered:', registration.scope);

                registration.addEventListener('updatefound', () => {
                    const installingWorker = registration.installing;

                    if (!installingWorker) {
                        return;
                    }

                    installingWorker.addEventListener('statechange', () => {
                        if (
                            installingWorker.state === 'installed' &&
                            navigator.serviceWorker.controller
                        ) {
                            console.info('A new GHADAMS version is available. Refresh to update.');
                        }
                    });
                });
            })
            .catch((error) => {
                console.error('GHADAMS PWA service worker registration failed:', error);
            });
    });
}

