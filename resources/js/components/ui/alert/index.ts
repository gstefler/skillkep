import type { VariantProps } from "class-variance-authority"
import { cva } from "class-variance-authority"

export { default as Alert } from "./Alert.vue"
export { default as AlertDescription } from "./AlertDescription.vue"
export { default as AlertTitle } from "./AlertTitle.vue"

/* Skill K.E.P alert / banner (design-system .kep-alert): tinted background, coloured icon, body text stays readable. */
export const alertVariants = cva(
  "relative w-full rounded-md border px-4 py-3 text-sm text-foreground grid has-[>svg]:grid-cols-[calc(var(--spacing)*4)_1fr] grid-cols-[0_1fr] has-[>svg]:gap-x-3 gap-y-0.5 items-start [&>svg]:size-4 [&>svg]:translate-y-0.5",
  {
    variants: {
      variant: {
        default: "bg-card [&>svg]:text-muted-foreground",
        info: "bg-info-subtle border-info-border [&>svg]:text-info",
        success: "bg-success-subtle border-success-border [&>svg]:text-success",
        warning: "bg-warning-subtle border-warning-border [&>svg]:text-warning",
        destructive:
          "bg-destructive-subtle border-destructive-border [&>svg]:text-destructive",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  },
)

export type AlertVariants = VariantProps<typeof alertVariants>
