import '../css/app.css';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from 'ziggy-js';
import GlobalLoadingOverlay from './components/ui/GlobalLoadingOverlay.vue';
import PwaInstallBanner from './components/pwa/PwaInstallBanner.vue';
import { initInertiaInterceptor } from './lib/smartQueue';
import { initPwa } from './lib/pwaManager';
import { initOfflineSyncListener } from './lib/offlineStorage';

const appName = import.meta.env.VITE_APP_NAME || 'Sirkelta';

initInertiaInterceptor(router);
initPwa();
initOfflineSyncListener();

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        if (name.includes('::')) {
            const [module, page] = name.split('::');
            return resolvePageComponent(
                `../../Modules/${module}/resources/assets/js/Pages/${page}.vue`,
                import.meta.glob('../../Modules/*/resources/assets/js/Pages/**/*.vue')
            );
        }

        return resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue')
        );
    },
    setup({ el, App, props, plugin }) {
        return createApp({
            render: () => h('div', { style: 'display: contents' }, [
                h(App, props),
                h(GlobalLoadingOverlay),
                h(PwaInstallBanner),
            ]),
        })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4f46e5',
    },
});
