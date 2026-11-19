import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Button } from "./Button.vue"

/*
 * Skill K.E.P variants (design-system .kep-btn):
 *   default     → primary action          (.kep-btn--primary)
 *   outline     → neutral, bordered       (.kep-btn)
 *   secondary   → tinted brand, subtle    (.kep-btn--subtle)
 *   ghost       → no chrome               (.kep-btn--ghost)
 *   destructive → dangerous action        (.kep-btn--danger)
 *   link        → inline text link
 * Heights follow the density tokens (--kep-control-h*), so data-density="compact" shrinks them.
 */
export const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md border border-transparent text-sm font-medium leading-none transition-[background-color,border-color,box-shadow,color] duration-(--kep-duration-fast) ease-standard disabled:pointer-events-none disabled:opacity-50 aria-disabled:pointer-events-none aria-disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 shrink-0 [&_svg]:shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background aria-invalid:border-destructive",
  {
    variants: {
      variant: {
        default:
          "bg-primary text-primary-foreground border-primary shadow-xs hover:bg-primary-hover hover:border-primary-hover active:bg-primary-active",
        destructive:
          "bg-destructive text-destructive-foreground border-destructive hover:bg-danger-hover hover:border-danger-hover focus-visible:ring-destructive",
        outline:
          "bg-card text-foreground border-border-strong hover:bg-muted",
        secondary:
          "bg-secondary text-secondary-foreground hover:bg-[color-mix(in_oklab,var(--kep-primary-subtle)_70%,var(--kep-primary)_12%)]",
        ghost:
          "text-muted-foreground hover:bg-muted hover:text-foreground",
        link: "text-link underline-offset-2 hover:underline decoration-1 hover:decoration-2",
      },
      size: {
        "default": "h-(--kep-control-h) px-(--kep-control-px) has-[>svg]:px-3",
        "sm": "h-(--kep-control-h-sm) gap-1.5 px-2.5 text-xs has-[>svg]:px-2",
        "lg": "h-(--kep-control-h-lg) px-5 text-base has-[>svg]:px-4",
        "icon": "size-(--kep-control-h)",
        "icon-sm": "size-(--kep-control-h-sm)",
        "icon-lg": "size-(--kep-control-h-lg)",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  },
)
export type ButtonVariants = VariantProps<typeof buttonVariants>
