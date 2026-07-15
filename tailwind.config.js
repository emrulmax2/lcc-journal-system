/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.{ts,tsx}',
  ],
  theme: {
    extend: {
      colors: {
        // Brand — trust teal (primary action) + authority navy (structure/text)
        brand: {
          50: '#F0FDFA',
          100: '#CCFBF1',
          200: '#99F6E4',
          300: '#5EEAD4',
          400: '#2DD4BF',
          500: '#14B8A6',
          600: '#0D9488',
          700: '#0F766E',
          800: '#115E59',
          900: '#134E4A',
        },
        ink: {
          50: '#F8FAFC',
          100: '#F1F5F9',
          200: '#E2E8F0',
          300: '#CBD5E1',
          400: '#94A3B8',
          500: '#64748B',
          600: '#475569',
          700: '#334155',
          800: '#1E293B',
          900: '#0F172A',
          950: '#020617',
        },
        // Editorial accent — open-access / impact badges
        gold: {
          50: '#FFFBEB',
          100: '#FEF3C7',
          200: '#FDE68A',
          300: '#FCD34D',
          400: '#FBBF24',
          500: '#D97706',
          600: '#B45309',
          700: '#92400E',
        },
        // Status carriers. Deposit failures, review recommendations and submission
        // states were reaching for stock emerald/red utilities, which sit outside the
        // design system and so could never be restyled centrally. They get real tokens.
        success: {
          50: '#ECFDF5',
          100: '#D1FAE5',
          600: '#059669',
          700: '#047857',
          800: '#065F46',
        },
        danger: {
          50: '#FEF2F2',
          100: '#FEE2E2',
          600: '#DC2626',
          700: '#B91C1C',
          800: '#991B1B',
        },
      },
      fontFamily: {
        serif: ['Newsreader', 'Georgia', 'serif'],
        sans: ['Inter', 'system-ui', 'sans-serif'],
      },
      maxWidth: {
        prose: '68ch', // 65-75 characters per line
      },
      boxShadow: {
        card: '0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 24px -12px rgba(15, 23, 42, 0.12)',
        lift: '0 2px 4px rgba(15, 23, 42, 0.06), 0 18px 40px -16px rgba(15, 23, 42, 0.22)',
      },
      zIndex: {
        // Scale system: dropdown 10, sticky 20, overlay 30, modal 50
        dropdown: '10',
        sticky: '20',
        overlay: '30',
        modal: '50',
      },
    },
  },
  plugins: [],
}
