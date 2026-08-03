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
                    orange: {
                        50:  '#FFF7ED',
                        100: '#FFEDD5',
                        200: '#FED7AA',
                        300: '#FDBA74',
                        400: '#FB923C',
                        500: '#F97316',
                        600: '#EA580C',
                        700: '#C2410C',
                        800: '#9A3412',
                        900: '#7C2D12',
                    },
                    graphite: {
                        50:  '#F8FAFC',
                        100: '#F1F5F9',
                        200: '#E2E8F0',
                        300: '#CBD5E1',
                        400: '#94A3B8',
                        500: '#64748B',
                        600: '#475569',
                        700: '#334155',
                        800: '#1E293B',
                        900: '#0F172A',
                    },
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
