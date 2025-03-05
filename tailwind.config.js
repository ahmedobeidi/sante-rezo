/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./assets/**/*.js", "./templates/**/*.html.twig"],
  theme: {
    extend: {
      fontFamily: {
        poppins: ['Poppins', 'sans-serif'], 
      },
      colors: {
        'dark-blue': '#2563EB',
        'dark-gray': '#2c2d3f',
        'off-blue': '#298BE9',
        'off-gray': '#4B5563'
      },
      screens: {

        '3xl': '2400px',
       
      },
    },
  },
  plugins: [],
};
