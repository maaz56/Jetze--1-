<template>
  <div class="flex flex-col">
    <!-- Valid Price Available -->
    <template v-if="hasValidPrice">
      <div class="flex items-baseline gap-1.5 flex-wrap">
        <span class="text-xs text-slate-500 font-medium">from</span>
        <span class="text-xl sm:text-2xl font-bold tracking-tight text-primary">
          {{ formattedAmount }}
        </span>
      </div>

      <div class="flex items-center gap-1.5 mt-0.5 text-xs text-slate-500">
        <span>per person{{ journeyLabel ? ` · ${journeyLabel}` : '' }}</span>
        <span v-if="timeAgoText" class="text-slate-400">· {{ timeAgoText }}</span>

        <span
          v-if="price?.is_stale"
          class="inline-flex items-center text-amber-600 ml-1 cursor-help"
          title="Prices change often — search for the latest fare"
        >
          <Clock class="w-3 h-3" />
        </span>
      </div>
    </template>

    <!-- No price / 0 price / pending / stale-expired: Honest 'Check prices' -->
    <template v-else>
      <div class="flex flex-col">
        <span class="text-lg font-bold text-primary flex items-center gap-1">
          Check prices
        </span>
        <span class="text-xs text-slate-400 mt-0.5">Live fares on request</span>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Clock } from 'lucide-vue-next';
import { formatAmount } from '@/lib/utils';

const props = defineProps({
  price: {
    type: Object,
    default: null,
  },
  journeyType: {
    type: String,
    default: 'round',
  },
  compact: {
    type: Boolean,
    default: false,
  },
});

const hasValidPrice = computed(() => {
  if (!props.price) return false;
  const num = Number(props.price.amount);
  return Number.isFinite(num) && num > 0;
});

const formattedAmount = computed(() => {
  if (!hasValidPrice.value) return '';
  return formatAmount(props.price.amount, props.price.currency);
});

const journeyLabel = computed(() => {
  const jt = (props.journeyType || '').toLowerCase();
  if (jt === 'round') return 'round trip';
  if (jt === 'one-way' || jt === 'oneway') return 'one way';
  return '';
});

const timeAgoText = computed(() => {
  if (!props.price?.updated_at) return '';
  const date = new Date(props.price.updated_at);
  if (isNaN(date.getTime())) return '';

  const now = new Date();
  const diffMinutes = Math.floor((now.getTime() - date.getTime()) / (1000 * 60));

  if (diffMinutes < 1) return 'just now';
  if (diffMinutes < 60) return `${diffMinutes}m ago`;
  const diffHours = Math.floor(diffMinutes / 60);
  if (diffHours < 24) return `${diffHours}h ago`;
  const diffDays = Math.floor(diffHours / 24);
  return `${diffDays}d ago`;
});
</script>
