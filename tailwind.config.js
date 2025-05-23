/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./assets/**/*.js", "./templates/**/*.html.twig", "./src/Form/*.php"],
  theme: {
    extend: {
      fontFamily: {
        poppins: ['Poppins', 'sans-serif'],
        nunito: ["Nunito Sans", 'sans-serif'] 
      },
      colors: {
        'dark-blue': '#2563EB',
        'dark-gray': '#2c2d3f',
        'off-blue': '#298BE9',
        'off-gray': '#4B5563',
        'off-white': '#f9f9f9',
        'off-test': '#edf6f9'
      },
      screens: {
        '3xl': '2400px',
      },
    },
  },
  plugins: [],
};
