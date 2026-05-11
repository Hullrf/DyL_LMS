import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                dyl: {
                    navy:         '#1e3a5f',
                    'navy-700':   '#172e4c',
                    'navy-800':   '#0f1e33',
                    blue:         '#2563eb',
                    'blue-600':   '#1d4ed8',
                    gold:         '#d97706',
                    'gold-400':   '#fbbf24',
                    'gold-100':   '#fef3c7',
                    orange:       '#c8682e',   /* naranja teja — navbar */
                    'orange-700': '#b05820',   /* naranja teja oscuro — menú móvil */
                },
            },
            boxShadow: {
                'card': '0 1px 3px 0 rgba(0,0,0,.06), 0 1px 2px -1px rgba(0,0,0,.06)',
                'card-hover': '0 4px 12px 0 rgba(0,0,0,.08)',
            },
        },
    },

    plugins: [
        forms,
        // Plugin para line-clamp sin necesidad de @tailwindcss/line-clamp en v3.3+
    ],
};
