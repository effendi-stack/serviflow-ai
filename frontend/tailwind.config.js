/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        // Field-ops palette: deep slate workspace, safety-amber for
        // priority/action, teal for "AI-touched" affordances so the
        // human vs. AI origin of a value is always visually distinct.
        ink: '#12181f',
        slate: {
          950: '#0c1116',
          900: '#141c24',
          850: '#1a232c',
          800: '#212c36',
          700: '#324152',
        },
        amber: {
          500: '#f2a93b',
          600: '#d98d1f',
        },
        signal: {
          teal: '#3fb8af',
          red: '#e5533d',
        },
      },
      fontFamily: {
        display: ['"Space Grotesk"', 'system-ui', 'sans-serif'],
        body: ['"Inter"', 'system-ui', 'sans-serif'],
        mono: ['"IBM Plex Mono"', 'monospace'],
      },
    },
  },
  plugins: [],
}
