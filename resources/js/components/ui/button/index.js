import { cva } from "class-variance-authority";

export { default as Button } from "./Button.vue";

export const buttonVariants = cva(
  "inline-flex items-center justify-center whitespace-nowrap rounded text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground hover:bg-primary/90 active:bg-primary/95",
        primary:
          "bg-primary text-primary-foreground hover:bg-primary/90 active:bg-primary/95",
        destructive:
          "bg-destructive text-destructive-foreground hover:bg-destructive/90 active:bg-destructive/95",
        outline:
          "border border-border bg-background text-foreground hover:bg-muted",
        secondary:
          "border border-border bg-card text-foreground hover:bg-muted",
        ghost:
          "hover:bg-muted text-foreground",
        link:
          "text-primary underline-offset-4 hover:underline p-0 h-auto font-medium",
      },
      size: {
        default: "h-11 px-5 text-sm", // 44px primary height
        primary: "h-11 px-5 text-sm", // 44px primary height
        secondary: "h-9 px-3.5 text-xs", // 36px secondary height
        xs: "h-7 px-2 text-xs",
        sm: "h-9 px-3 text-xs", // 36px
        lg: "h-11 px-6 text-base", // 44px
        icon: "h-11 w-11 p-0", // 44px
        "icon-sm": "h-9 w-9 p-0", // 36px
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
);
