// WPGraphQL Product Family — tokens.ts (FINAL v4, all five products)
export const navy    = { 950:"#080D18",900:"#0C1220",800:"#131B30",700:"#1A2540",600:"#223050",500:"#2A3B60",400:"#435678",300:"#6578A0",200:"#96A8C8",100:"#C8D2E8",50:"#EDF0F6" } as const;
export const orange  = { 600:"#C45C00",500:"#E06A00",400:"#F27800",300:"#FF8C1A",200:"#FFAA4D" } as const;
export const cyan    = { 600:"#007A8C",500:"#009AB0",400:"#00B8D4",300:"#00D4F5",200:"#5DE8FF" } as const;
export const violet  = { 700:"#3B1A6B",600:"#4C23A0",500:"#5E2EC4",400:"#7040E8",300:"#8B5CF6",200:"#A78BFA" } as const;
export const emerald = { 700:"#064E3B",600:"#065F46",500:"#047857",400:"#059669",300:"#10B981",200:"#34D399" } as const;
export const rose    = { 700:"#881337",600:"#9F1239",500:"#BE123C",400:"#E11D48",300:"#F43F5E",200:"#FB7185" } as const;

export const products = {
  wpgraphql:   { accent:orange[300],  markShape:"circle"         as const, cssThemeClass:"" },
  radiql:      { accent:cyan[300],    markShape:"rounded-square" as const, cssThemeClass:"theme-radiql",      statusBar:undefined },
  ide:         { accent:violet[300],  markShape:"rounded-square" as const, cssThemeClass:"theme-ide",         statusBar:violet[500] },
  acf:         { accent:emerald[300], markShape:"rounded-square" as const, cssThemeClass:"theme-acf",         statusBar:undefined },
  smartCache:  { accent:rose[300],    markShape:"rounded-square" as const, cssThemeClass:"theme-smart-cache", statusBar:undefined },
} as const;

export const shared = {
  background:navy[950], surface:navy[900], surfaceRaised:navy[800], border:navy[700],
  textPrimary:"#F0F4FF", textSecondary:navy[200], textMuted:navy[300],
  fonts:{ sans:"'Bricolage Grotesque',system-ui,sans-serif", mono:"'DM Mono',ui-monospace,monospace" },
  googleFontsUrl:"https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,500;12..96,600;12..96,700;12..96,800&family=DM+Mono:ital,wght@0,300;0,400;0,500;1,400&display=swap",
} as const;

// SVG paths
export const WPGRAPHQL_ELEPHANT_PATH = "m117.592 300.896c0-35.138.58-39.429 7.074-52.301 5.682-11.133 20.758-25.05 30.732-28.065 2.203-.696 2.899.348 6.726 9.858 12.408 31.195 37.11 54.505 69.349 65.29l8.465 2.899.348 16.815c.116 9.394-.116 16.932-.58 16.816-.58 0-2.899-3.131-5.45-6.958-11.945-18.671-35.718-30.036-59.724-28.645-21.802 1.276-40.589 12.061-52.765 30.152l-4.175 6.147zm25.165 85.353c10.09-3.015 17.743-13.568 17.743-24.47 0-7.77 9.51-16.699 17.627-16.699 10.321 0 17.396 6.958 18.787 18.44 1.276 10.32 5.567 16.815 14.032 21.337 4.407 2.436 6.147 2.552 32.471 2.552 26.441 0 28.065-.116 32.588-2.552 5.566-3.015 11.712-9.51 12.872-14.032.58-1.74.928-25.049.928-51.838v-48.706l-2.9-5.103c-4.87-8.582-10.437-11.597-24.469-13.452-19.019-2.436-30.036-7.538-41.053-18.787-8.117-8.118-14.96-21.57-16.815-33.051-3.71-21.918 7.19-46.503 26.325-59.26 11.48-7.654 20.526-10.437 33.979-10.437 8.813 0 12.64.58 19.25 2.9 14.728 5.218 25.745 14.031 33.515 27.02 8.234 13.916 8.002 10.205 8.698 94.514.58 68.885.928 76.539 2.783 82.337 6.146 19.02 18.903 34.559 34.443 42.097 21.338 10.437 42.212 11.133 60.767 2.087 19.019-9.393 33.747-30.615 37.69-54.389 2.435-14.612-1.16-23.193-11.83-28.528-10.32-5.219-21.917-3.827-29.107 3.479-4.639 4.639-6.262 8.118-8.234 17.86-2.551 12.06-8.118 17.394-18.323 17.394-6.378 0-12.524-3.247-15.424-8.233-2.203-3.827-2.319-6.61-2.899-78.743-.58-66.566-.812-75.727-2.667-82.801-12.409-47.895-49.403-80.366-98.69-86.513-24.584-3.015-56.94 6.843-78.858 24.354-17.627 13.916-29.108 30.615-36.53 52.997l-3.479 9.974-11.944 4.29c-19.02 6.727-28.645 12.641-42.909 26.441-12.872 12.525-21.802 26.441-27.6 43.14-5.335 15.772-5.799 21.339-5.799 75.844v51.374l2.668 5.102c3.015 5.683 10.089 11.25 16.003 12.64 2.204.465 14.38.929 27.253 1.044 17.511.116 24.701-.347 29.108-1.623zm132.204-172.793c6.03-2.551 8.35-4.87 11.48-11.597 4.523-9.625 3.248-20.526-3.362-28.064-4.755-5.45-9.51-7.306-18.555-7.306-6.03 0-8.234.58-12.64 3.363-15.077 9.51-14.265 34.79 1.39 42.792 6.147 3.016 15.425 3.363 21.687.812z";
export const RADIQL_BOLT_PATH    = "M 100 14 L 56 86 L 80 86 L 60 146 L 108 68 L 82 68 Z";
export const RADIQL_BOLT_PATH_SM = "M 5 1 L 1 6.5 L 4 6.5 L 3 11 L 7 5.5 L 4 5.5 Z";
// IDE, ACF, and Smart Cache marks are multi-element — use their logo components.
