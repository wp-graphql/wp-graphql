import type { Config } from "tailwindcss";
const config: Config = {
  darkMode: ["class"],
  content: ["./pages/**/*.{ts,tsx}","./components/**/*.{ts,tsx}","./app/**/*.{ts,tsx}","./src/**/*.{ts,tsx}"],
  theme: {
    container: { center: true, padding: "2rem", screens: { "2xl": "1400px" } },
    extend: {
      colors: {
        navy: {
          950:"hsl(224,48%,7%)",900:"hsl(224,46%,9%)",800:"hsl(224,42%,13%)",
          700:"hsl(225,42%,18%)",600:"hsl(225,40%,22%)",500:"hsl(226,38%,27%)",
          400:"hsl(220,28%,36%)",300:"hsl(220,24%,51%)",200:"hsl(220,22%,67%)",
          100:"hsl(220,28%,82%)",50:"hsl(220,32%,93%)",
        },
        emerald: {
          700:"#064E3B",600:"#065F46",500:"#047857",400:"#059669",
          300:"#10B981", // PRIMARY
          200:"#34D399",100:"#6EE7B7",50:"#ECFDF5",
        },
        "orange-wpg":{ 300:"#FF8C1A" },
        "cyan-rql":  { 300:"#00D4F5" },
        "violet-ide":{ 300:"#8B5CF6" },
        border:"hsl(var(--border))",input:"hsl(var(--input))",ring:"hsl(var(--ring))",
        background:"hsl(var(--background))",foreground:"hsl(var(--foreground))",
        primary:{ DEFAULT:"hsl(var(--primary))",foreground:"hsl(var(--primary-foreground))" },
        secondary:{ DEFAULT:"hsl(var(--secondary))",foreground:"hsl(var(--secondary-foreground))" },
        destructive:{ DEFAULT:"hsl(var(--destructive))",foreground:"hsl(var(--destructive-foreground))" },
        muted:{ DEFAULT:"hsl(var(--muted))",foreground:"hsl(var(--muted-foreground))" },
        accent:{ DEFAULT:"hsl(var(--accent))",foreground:"hsl(var(--accent-foreground))" },
        popover:{ DEFAULT:"hsl(var(--popover))",foreground:"hsl(var(--popover-foreground))" },
        card:{ DEFAULT:"hsl(var(--card))",foreground:"hsl(var(--card-foreground))" },
        "chart-1":"hsl(var(--chart-1))","chart-2":"hsl(var(--chart-2))","chart-3":"hsl(var(--chart-3))","chart-4":"hsl(var(--chart-4))","chart-5":"hsl(var(--chart-5))",
        sidebar:{ DEFAULT:"hsl(var(--sidebar-background))",foreground:"hsl(var(--sidebar-foreground))",primary:"hsl(var(--sidebar-primary))","primary-foreground":"hsl(var(--sidebar-primary-foreground))",accent:"hsl(var(--sidebar-accent))","accent-foreground":"hsl(var(--sidebar-accent-foreground))",border:"hsl(var(--sidebar-border))",ring:"hsl(var(--sidebar-ring))" },
      },
      fontFamily: { sans:["Bricolage Grotesque","system-ui","sans-serif"],mono:["DM Mono","ui-monospace","monospace"],display:["Bricolage Grotesque","system-ui","sans-serif"] },
      fontSize: {
        "display-2xl":["4.5rem",{lineHeight:"1",letterSpacing:"-0.04em",fontWeight:"800"}],
        "display-xl": ["3.75rem",{lineHeight:"1.02",letterSpacing:"-0.035em",fontWeight:"800"}],
        "display-lg": ["3rem",   {lineHeight:"1.05",letterSpacing:"-0.03em", fontWeight:"700"}],
        "display-md": ["2.25rem",{lineHeight:"1.1", letterSpacing:"-0.025em",fontWeight:"700"}],
        "display-sm": ["1.875rem",{lineHeight:"1.15",letterSpacing:"-0.02em",fontWeight:"600"}],
      },
      borderRadius: { lg:"var(--radius)",md:"calc(var(--radius) - 2px)",sm:"calc(var(--radius) - 4px)",xl:"calc(var(--radius) + 4px)","2xl":"calc(var(--radius) + 8px)","app-icon":"22.5%",pill:"9999px" },
      boxShadow: {
        "glow-sm":"0 0 12px -2px hsl(160 84% 39% / 0.28)","glow-md":"0 0 24px -4px hsl(160 84% 39% / 0.38)","glow-lg":"0 0 40px -8px hsl(160 84% 39% / 0.45)",
        "elev-sm":"0 1px 3px 0 hsl(224 48% 4% / 0.45)","elev-md":"0 4px 12px -2px hsl(224 48% 4% / 0.55)","elev-lg":"0 12px 32px -4px hsl(224 48% 4% / 0.65)",
      },
      keyframes: {
        "accordion-down":{from:{height:"0"},to:{height:"var(--radix-accordion-content-height)"}},
        "accordion-up":{from:{height:"var(--radix-accordion-content-height)"},to:{height:"0"}},
        "fade-in":{from:{opacity:"0",transform:"translateY(4px)"},to:{opacity:"1",transform:"translateY(0)"}},
        "glow-pulse":{"0%,100%":{boxShadow:"0 0 8px -2px hsl(160 84% 39% / 0.2)"},"50%":{boxShadow:"0 0 20px -2px hsl(160 84% 39% / 0.55)"}},
        "row-highlight":{from:{background:"rgba(16,185,129,0)"},to:{background:"rgba(16,185,129,0.08)"}},
      },
      animation: {
        "accordion-down":"accordion-down 0.2s ease-out","accordion-up":"accordion-up 0.2s ease-out",
        "fade-in":"fade-in 0.2s ease-out","glow-pulse":"glow-pulse 2s ease-in-out infinite",
        "row-highlight":"row-highlight 0.3s ease-out forwards",
      },
    },
  },
  plugins:[require("tailwindcss-animate")],
};
export default config;
