/** @type {import('tailwindcss').Config} */
export default {
  darkMode: 'class',
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      colors: {
        tbank: {
          yellow: '#FFDD2D',
          black: '#1F1F1F',
          gray: '#F6F7F8',
        },
      },
      fontSize: {
        'dynamic-base': 'clamp(1rem, 2.5vw, 1.125rem)',
      },
    },
  },
  plugins: [],
};
