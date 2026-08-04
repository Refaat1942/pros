/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/cashier/**/*.blade.php',
        './resources/views/workshop/**/*.blade.php',
        './resources/views/spec/**/*.blade.php',
        './resources/views/reception/pages/delivery.blade.php',
        './resources/views/technical/pages/bom.blade.php',
        './resources/views/public/tracking_page.blade.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Tajawal', 'Segoe UI', 'Tahoma', 'sans-serif'],
            },
            colors: {
                cashier: { DEFAULT: '#0e7490', dark: '#155e75', light: '#ecfeff' },
                workshop: { DEFAULT: '#7c3aed', dark: '#6d28d9', light: '#f5f3ff' },
                wh: { DEFAULT: '#7c3aed', dark: '#6d28d9', light: '#f5f3ff' },
                recv: { DEFAULT: '#059669', dark: '#047857', light: '#ecfdf5' },
                spec: { DEFAULT: '#d97706', dark: '#b45309', light: '#fef3c7' },
            },
        },
    },
};
