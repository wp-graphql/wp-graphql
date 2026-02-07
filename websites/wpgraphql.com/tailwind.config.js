const defaultTheme = require("tailwindcss/defaultTheme");

module.exports = {
  content: ["./src/**/*.{js,ts,jsx,tsx,mdx}"],
  darkMode: "class",
  theme: {
    screens: {
      sm: "600px",
      md: "900px",
      lg: "1200px",
      xl: "1800px",
    },
    extend: {
      colors: {
        teal: {
          DEFAULT: '#0ECAD4',
          light: '#5EDCE2',
          lightest: '#E7FAFB',
          dark: '#0CA8B1',
        },
        blue: {
          DEFAULT: '#006BD6',
          dark: '#00366B',
          light: '#D5E6F8',
        },
        navy: {
          DEFAULT: '#002447',
        },
        purple: {
          DEFAULT: '#7A45E5',
          light: '#E9E0FB',
        },
        green: {
          DEFAULT: '#039B5C',
          light: '#D3F3E2',
        },
        yellow: {
          DEFAULT: '#FFC34E',
          light: '#FFF9ED',
        },
        orange: {
          DEFAULT: '#FF6119',
          light: '#FFE5D9',
        },
        red: {
          DEFAULT: '#DD1243',
        },
        lightGray: {
          DEFAULT: '#F4F5F6',
        },
        mediumGray: {
          DEFAULT: '#5B6C74',
        },
        darkGray: {
          DEFAULT: '#1F2426',
        },
        gradients: {
          center: ['#5EDCE2', '#0CA8B1'],
          power: ['#0ECAD4', '#006BD6'],
          build: ['#0ECAD4', '#7A45E5'],
          grow: ['#0ECAD4', '#039B5C'],
          elevate: ['#0ECAD4', '#00366B'],
          spark: ['#FFC34E', '#FF6119'],
          centerLight: ['#E7FAFB', '#D7F6F8'],
          powerLight: ['#E7FAFB', '#D5E6F8'],
          buildLight: ['#E7FAFB', '#E9E0FB'],
          growLight: ['#E7FAFB', '#D3F3E2'],
          elevateLight: ['#E7FAFB', '#F5F6F7'],
          sparkLight: ['#FFF9ED', '#FFE5D9'],
        },
      },
      backgroundImage: theme => ({
        'gradient-center': `radial-gradient(circle at -100% -200%, ${theme('colors.gradients.center[0]')}, ${theme('colors.gradients.center[1]')})`,
        'gradient-power': `radial-gradient(circle at -100% -200%, ${theme('colors.gradients.power[0]')}, ${theme('colors.gradients.power[1]')})`,
        'gradient-build': `radial-gradient(circle at -100% -200%, ${theme('colors.gradients.build[0]')}, ${theme('colors.gradients.build[1]')})`,
        'gradient-grow': `radial-gradient(circle at -100% -200%, ${theme('colors.gradients.grow[0]')}, ${theme('colors.gradients.grow[1]')})`,
        'gradient-elevate': `radial-gradient(circle at -100% -200%, ${theme('colors.gradients.elevate[0]')}, ${theme('colors.gradients.elevate[1]')})`,
        'gradient-spark': `radial-gradient(circle at -100% -200%, ${theme('colors.gradients.spark[0]')}, ${theme('colors.gradients.spark[1]')})`,
        'gradient-center-light': `radial-gradient(circle at -100% -200%, ${theme('colors.gradients.centerLight[0]')}, ${theme('colors.gradients.centerLight[1]')})`,
        'gradient-power-light': `radial-gradient(circle at -100% -200%, ${theme('colors.gradients.powerLight[0]')}, ${theme('colors.gradients.powerLight[1]')})`,
        'gradient-build-light': `radial-gradient(circle at -100% -200%, ${theme('colors.gradients.buildLight[0]')}, ${theme('colors.gradients.buildLight[1]')})`,
        'gradient-grow-light': `radial-gradient(circle at -100% -200%, ${theme('colors.gradients.growLight[0]')}, ${theme('colors.gradients.growLight[1]')})`,
        'gradient-elevate-light': `radial-gradient(circle at -100% -200%, ${theme('colors.gradients.elevateLight[0]')}, ${theme('colors.gradients.elevateLight[1]')})`,
        'gradient-spark-light': `radial-gradient(circle at -100% -200%, ${theme('colors.gradients.sparkLight[0]')}, ${theme('colors.gradients.sparkLight[1]')})`,
      }),
      fontFamily: {
        lora: ["Lora", ...defaultTheme.fontFamily.serif],
        sans: ["Inter", ...defaultTheme.fontFamily.sans],
        mono: ["Fira Code VF", ...defaultTheme.fontFamily.mono],
        source: ["Source Sans Pro", ...defaultTheme.fontFamily.sans],
        "ubuntu-mono": ["Ubuntu Mono", ...defaultTheme.fontFamily.mono]
      },
      spacing: {
        18: "4.5rem",
        full: "100%",
      },
      maxWidth: {
        "8xl": "90rem",
      },
      typography: (theme) => ({
        DEFAULT: {
          css: {
            maxWidth: "none",
            color: theme("colors.navy.700"),
            hr: {
              borderColor: theme("colors.slate.100"),
              marginTop: "3em",
              marginBottom: "3em",
            },
            "h1, h2, h3, h4, h5": {
              fontFamily: theme('fontFamily.lora').join(', '),
            },
            "h1, h2, h3": {
              letterSpacing: "-0.025em"
            },
            h2: {
              marginBottom: `${16 / 24}em`,
            },
            h3: {
              marginTop: "2.4em",
              lineHeight: "1.4",
            },
            h4: {
              marginTop: "2em",
              fontSize: "1.125em",
            },
            "h2 small, h3 small, h4 small": {
              fontFamily: theme("fontFamily.mono").join(", "),
              color: theme("colors.slate.500"),
              fontWeight: 500,
            },
            "h2 small": {
              fontSize: theme("fontSize.lg")[0],
              ...theme("fontSize.lg")[1],
            },
            "h3 small": {
              fontSize: theme("fontSize.base")[0],
              ...theme("fontSize.base")[1],
            },
            "h4 small": {
              fontSize: theme("fontSize.sm")[0],
              ...theme("fontSize.sm")[1],
            },
            "h2, h3, h4": {
              "scroll-margin-top": "var(--scroll-mt)",
            },
            iframe: {
              maxWidth: "100%",
            },
            ul: {
              listStyleType: "none",
              paddingLeft: 0,
            },
            "ul > li": {
              position: "relative",
              paddingLeft: "1.75em",
            },
            "ul > li::before": {
              content: '""',
              width: "0.75em",
              height: "0.125em",
              position: "absolute",
              top: "calc(0.875em - 0.0625em)",
              left: 0,
              borderRadius: "999px",
              backgroundColor: theme("colors.slate.300"),
            },
            a: {
              fontWeight: theme("fontWeight.semibold"),
              textDecoration: "none",
              color: theme("colors.blue.dark"),
              borderBottom: `2px solid ${theme("colors.blue.dark")}`,
              transition: "color .22s cubic-bezier(0.65,0.05,0.36,1), border-color .22s cubic-bezier(0.65,0.05,0.36,1)"
            },
            "a:hover": {
              color: theme("colors.blue.DEFAULT"),
              borderColor: theme("colors.blue.DEFAULT"),
            },
            "a code": {
              color: "inherit",
              fontWeight: "inherit",
            },
            strong: {
              color: theme("colors.slate.900"),
              fontWeight: theme("fontWeight.semibold"),
            },
            "a strong": {
              color: "inherit",
              fontWeight: "inherit",
            },
            code: {
              fontWeight: theme("fontWeight.medium"),
              fontVariantLigatures: "none",
            },
            pre: {
              color: theme("colors.slate.50"),
              borderRadius: theme("borderRadius.xl"),
              padding: theme("padding.5"),
              boxShadow: theme("boxShadow.md"),
              display: "flex",
              marginTop: `${20 / 14}em`,
              marginBottom: `${32 / 14}em`,
            },
            "p + pre": {
              marginTop: `${-4 / 14}em`,
            },
            "pre + pre": {
              marginTop: `${-16 / 14}em`,
            },
            "pre code": {
              flex: "none",
              minWidth: "100%",
            },
            table: {
              fontSize: theme("fontSize.sm")[0],
              lineHeight: theme("fontSize.sm")[1].lineHeight,
            },
            thead: {
              color: theme("colors.slate.700"),
              borderBottomColor: theme("colors.slate.200"),
            },
            "thead th": {
              paddingTop: 0,
              fontWeight: theme("fontWeight.semibold"),
            },
            "tbody tr": {
              borderBottomColor: theme("colors.slate.100"),
            },
            "tbody tr:last-child": {
              borderBottomWidth: "1px",
            },
            "tbody code": {
              fontSize: theme("fontSize.xs")[0],
            },
            "figure figcaption": {
              textAlign: "center",
              fontStyle: "italic",
            },
            "figure > figcaption": {
              marginTop: `${12 / 14}em`,
            },
            "h2 a, h3 a, h4 a, h5 a, h6 a": {
              textDecoration: "none",
              borderBottom: 0,
            },
          },
        },
        dark: {
          css: {
            color: theme("colors.slate.200"),
            "h1, h2, h3, h4, thead th": {
              color: theme("colors.slate.200"),
            },
            "h2 small, h3 small, h4 small": {
              color: theme("colors.slate.400"),
            },
            code: {
              color: theme("colors.slate.200"),
            },
            hr: {
              borderColor: theme("colors.slate.200"),
              opacity: "0.05",
            },
            pre: {
              boxShadow: "inset 0 0 0 1px rgb(255 255 255 / 0.1)",
            },
            a: {
              color: theme("colors.white"),
              borderBottomColor: theme("colors.white"),
            },
            strong: {
              color: theme("colors.slate.200"),
            },
            thead: {
              color: theme("colors.slate.300"),
              borderBottomColor: "rgb(148 163 184 / 0.2)",
            },
            "tbody tr": {
              borderBottomColor: "rgb(148 163 184 / 0.1)",
            },
            blockQuote: {
              color: theme("colors.white"),
            },
            "h2 a, h3 a, h4 a, h5 a, h6 a": {
              textDecoration: "none",
              borderBottom: 0,
            },
          },
        },
      }),
    },
  },
  plugins: [
    require("@tailwindcss/aspect-ratio"),
    require("@tailwindcss/typography"),
  ],
};
