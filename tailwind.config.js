import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // Enums wie ActivityCategory liefern komplette Tailwind-Klassen als
        // PHP-String zurück (z. B. dotClass()) - liegen aber unter app/, das
        // sonst nicht gescannt wird. Ohne diesen Pfad baut Tailwind diese
        // Klassen nie ins CSS.
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                topbar: 'var(--color-topbar)',
                'topbar-content': 'var(--color-topbar-content)',
                sidebar: 'var(--color-sidebar)',
                'sidebar-content': 'var(--color-sidebar-content)',
                'sidebar-content-hover': 'var(--color-sidebar-content-hover)',
                'sidebar-hover': 'var(--color-sidebar-hover)',
                'sidebar-active': 'var(--color-sidebar-active)',
                'sidebar-active-content': 'var(--color-sidebar-active-content)',
            },
        },
    },

    plugins: [forms],
};
