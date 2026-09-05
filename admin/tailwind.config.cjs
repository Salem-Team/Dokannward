module.exports = {
  darkMode: "class",
  content: [
    './resources/views/**/*.blade.php',
    './resources/views/**/*.php',
    './resources/js/**/*.js',
    './app/Livewire/**/*.php',
  ],
  theme: {
    extend: {
      colors: {
        /* Dokan Ward brand — monochrome palette derived from the logo */
        "primary": "#0a0a0a",
        "background-light": "#fafafa",
        "background-dark": "#0a0a0a",
        "secondary": "#6b6b6b",
        "accent": "#000000",
        "text-primary": "#2a2a2a",
        "text-secondary": "#6b6b6b",
        "dokannward": {
          white: "#fefdf9",
          paper: "#f7f2ea",
          mist: "#ebe3d8",
          line: "#ddd2c4",
          ash: "#8a7a6a",
          graphite: "#5c4a38",
          ink: "#3a2a1a",
          black: "#2a1f14",
          bronze: "#b49480",
        },
        /* Alias kept so existing `*-zibra-*` utility classes keep working */
        "zibra": {
          white: "#fefdf9",
          paper: "#f7f2ea",
          mist: "#ebe3d8",
          line: "#ddd2c4",
          ash: "#8a7a6a",
          graphite: "#5c4a38",
          ink: "#3a2a1a",
          black: "#2a1f14",
        },
        /* Remap non-semantic accent colors used across the dashboard
           (buttons, focus rings, active nav states, gradients) onto the
           Dokan Ward grayscale ramp so every existing `blue-600`, `purple-600`,
           `indigo-500`, `pink-500` utility renders on-brand automatically.
           Functional colors (red/green/yellow/amber) are left untouched
           so errors, success and warnings stay legible. */
        blue: {
          50: "#fafafa",
          100: "#ececec",
          200: "#dcdcdc",
          300: "#c2c2c2",
          400: "#9a9a9a",
          500: "#6b6b6b",
          600: "#2a2a2a",
          700: "#1a1a1a",
          800: "#101010",
          900: "#0a0a0a",
          950: "#000000",
        },
        indigo: {
          50: "#fafafa",
          100: "#ececec",
          200: "#dcdcdc",
          300: "#c2c2c2",
          400: "#9a9a9a",
          500: "#6b6b6b",
          600: "#2a2a2a",
          700: "#1a1a1a",
          800: "#101010",
          900: "#0a0a0a",
          950: "#000000",
        },
        purple: {
          50: "#fafafa",
          100: "#ececec",
          200: "#dcdcdc",
          300: "#c2c2c2",
          400: "#9a9a9a",
          500: "#4a4a4a",
          600: "#242424",
          700: "#161616",
          800: "#0d0d0d",
          900: "#050505",
          950: "#000000",
        },
        pink: {
          50: "#fafafa",
          100: "#ececec",
          200: "#dcdcdc",
          300: "#c2c2c2",
          400: "#9a9a9a",
          500: "#575757",
          600: "#2a2a2a",
          700: "#1a1a1a",
          800: "#101010",
          900: "#0a0a0a",
          950: "#000000",
        },
      },
      fontFamily: {
        "display": ["Merriweather Sans", "Helvetica Neue", "Arial", "sans-serif"],
        "body": ["Merriweather Sans", "Helvetica Neue", "Arial", "sans-serif"],
        "accent": ["Anonymous Pro", "ui-monospace", "monospace"],
        "arabic": ["Noto Naskh Arabic", "serif"]
      },
      borderRadius: {
        "DEFAULT": "0.25rem",
        "lg": "0.35rem",
        "xl": "0.5rem",
        "full": "9999px"
      },
    },
  },
  plugins: [
    require('tailwindcss-rtl'),
  ],
}
