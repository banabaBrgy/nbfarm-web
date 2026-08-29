import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({

    plugins: [

        laravel({

            input: [

                'resources/css/app.css',

                'resources/css/auth.css',

                'resources/css/dashboard.css',

                'resources/css/owner.dashboard.css',

                'resources/js/app.js',

                'resources/css/sidebar.css',

                'resources/css/production.css',

                'resources/css/scan-qr.css',

                'resources/css/users.css',

                'resources/css/inventory.css',

                 'resources/js/revenue.js',

            ],

            refresh: true,

        }),

    ],

});