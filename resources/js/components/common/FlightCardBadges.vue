<script setup>
defineProps({ flight: { type: Object, default: null } });
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <span class="rounded px-2 py-1 text-xs font-semibold" :class="flight?.is_refundable ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-600'">
            {{ flight?.is_refundable ? 'Refundable' : 'Non-Refundable' }}
        </span>
    </div>
</template>
