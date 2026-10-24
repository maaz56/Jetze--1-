import { cva } from "class-variance-authority";

export { default as Badge } from "./Badge.vue";

export const badgeVariants = cva(
    "inline-flex items-center rounded-sm border px-2 py-0.5 text-xs font-semibold tracking-wide transition-colors focus:outline-none focus:ring-1 focus:ring-ring",
    {
        variants: {
            variant: {
                default:
                    "border-transparent bg-primary text-primary-foreground",
                secondary:
                    "border-border bg-muted text-muted-foreground",
                accent:
                    "border-border bg-card text-foreground",
                destructive:
                    "border-transparent bg-destructive text-destructive-foreground",
                success:
                    "border-transparent bg-chart-2 text-white",
                deal:
                    "border-transparent bg-chart-4 text-foreground",
                outline:
                    "border-border bg-transparent text-foreground",
            },
        },
        defaultVariants: {
            variant: "default",
        },
    },
);
