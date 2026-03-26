/** @type {import('tailwindcss').Config} */
module.exports = {
    content: [
        './templates/**/*.twig',
        './public/js/**/*.js',
    ],
    theme: {
        extend: {},
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
};
