import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', 'Inter', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                ink: {
                    DEFAULT: '#000000',
                    900: '#0a0a0b',
                    800: '#141416',
                    700: '#1c1c1e',
                    600: '#26262a',
                    500: '#38383c',
                },
                gain: {
                    DEFAULT: '#30d67b',
                    soft: 'rgba(48, 214, 123, 0.16)',
                },
                loss: {
                    DEFAULT: '#ff5a5f',
                    soft: 'rgba(255, 90, 95, 0.16)',
                },
                accent: {
                    DEFAULT: '#2f6bff',
                    600: '#1e50e0',
                },
                muted: '#8a8a8e',
            },
            maxWidth: {
                app: '460px',
            },
            borderRadius: {
                xl2: '1.25rem',
            },
        },
    },

    plugins: [forms],
};
