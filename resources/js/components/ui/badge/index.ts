import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Badge } from "./Badge.vue"

/*
 * Skill K.E.P status badge (design-system .kep-badge).
 * Status must never rely on colour alone: pair it with an icon or text.
 * `achievement` (gold) marks results only (certificate, completion), never actions.
 */
export const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-sm border h-[1.375rem] px-2 text-xs font-medium leading-none w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 outline-none aria-invalid:border-destructive transition-[color,box-shadow] overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "bg-primary-subtle text-primary-subtle-foreground border-primary-border",
        secondary:
          "bg-muted text-muted-foreground border-border",
        success:
          "bg-success-subtle text-success border-success-border",
        warning:
          "bg-warning-subtle text-warning border-warning-border",
        destructive:
          "bg-destructive-subtle text-destructive border-destructive-border",
        info:
          "bg-info-subtle text-info border-info-border",
        achievement:
          "bg-achievement-subtle text-achievement-foreground border-achievement/45",
        outline:
          "text-foreground border-border-strong [a&]:hover:bg-accent [a&]:hover:text-accent-foreground",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)
export type BadgeVariants = VariantProps<typeof badgeVariants>
