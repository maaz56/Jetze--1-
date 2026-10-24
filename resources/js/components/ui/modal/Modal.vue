<script setup>
import {
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
} from "radix-vue";
import { X } from "lucide-vue-next";
import { cn } from "@/lib/utils";

const props = defineProps({
  open: { type: Boolean, default: false },
  title: { type: String, default: "" },
  description: { type: String, default: "" },
  maxWidth: { type: String, default: "max-w-lg" },
  class: { type: null, required: false },
});

const emits = defineEmits(["update:open", "close"]);

const handleOpenUpdate = (value) => {
  emits("update:open", value);
  if (!value) {
    emits("close");
  }
};
</script>

<template>
  <DialogRoot :open="open" @update:open="handleOpenUpdate">
    <slot name="trigger" />
    <DialogPortal>
      <DialogOverlay
        class="fixed inset-0 z-50 bg-black/60 backdrop-blur-[2px] data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
      />
      <DialogContent
        :class="
          cn(
            'fixed left-1/2 top-1/2 z-50 grid w-full -translate-x-1/2 -translate-y-1/2 gap-4 border border-border bg-background p-6 shadow-lg duration-200 rounded',
            props.maxWidth,
            props.class
          )
        "
      >
        <div v-if="title || $slots.header" class="flex flex-col space-y-1.5 text-left pr-6">
          <slot name="header">
            <DialogTitle v-if="title" class="text-lg font-bold text-foreground">
              {{ title }}
            </DialogTitle>
            <DialogDescription v-if="description" class="text-xs text-muted-foreground">
              {{ description }}
            </DialogDescription>
          </slot>
        </div>

        <slot />

        <div v-if="$slots.footer" class="flex items-center justify-end gap-2 border-t border-border pt-4">
          <slot name="footer" />
        </div>

        <DialogClose
          class="absolute right-4 top-4 rounded-sm p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus:outline-none focus:ring-1 focus:ring-ring"
        >
          <X class="h-4 w-4" />
          <span class="sr-only">Close</span>
        </DialogClose>
      </DialogContent>
    </DialogPortal>
  </DialogRoot>
</template>
