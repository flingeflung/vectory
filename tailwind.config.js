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
                'btn-primary': 'var(--color-btn-primary)',
                'btn-primary-hover': 'var(--color-btn-primary-hover)',
                'btn-primary-content': 'var(--color-btn-primary-content)',
                'btn-secondary': 'var(--color-btn-secondary)',
                'btn-secondary-hover': 'var(--color-btn-secondary-hover)',
                'btn-secondary-content': 'var(--color-btn-secondary-content)',
                'btn-secondary-border': 'var(--color-btn-secondary-border)',
            },
        },
    },

    plugins: [forms],
};
