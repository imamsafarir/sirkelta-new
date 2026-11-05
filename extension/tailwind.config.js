/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./src/**/*.{html,ts,tsx,js}"
  ],
  theme: {
    extend: {
      colors: {
        wa: {
          light: '#25D366',
          DEFAULT: '#128C7E',
          dark: '#075E54',
          bg: '#EFEAE2'
        }
      }
    },
  },
  plugins: [],
}
