<script setup>
import { Primitive } from "radix-vue";
import { buttonVariants } from ".";
import { cn } from "@/lib/utils";
import { Loader2 } from "lucide-vue-next";

const props = defineProps({
    variant: { type: null, required: false },
    size: { type: null, required: false },
    class: { type: null, required: false },
    asChild: { type: Boolean, required: false },
    as: { type: null, required: false, default: "button" },
    isLoading: { type: Boolean, required: false, default: false },
});
</script>

<template>
    <Primitive
        :as="as"
        :as-child="asChild"
        :class="cn(buttonVariants({ variant, size }), props.class)"
    >
        <Loader2 v-if="isLoading" class="animate-spin w-4 h-4 me-2" />
        <slot />
    </Primitive>
</template>
