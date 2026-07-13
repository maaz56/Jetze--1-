<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from '@/components/ui/popover';
import { RangeCalendar } from '@/components/ui/range-calendar';
import { cn } from '@/lib/utils';
import { CalendarDate } from '@internationalized/date';
import { breakpointsTailwind, useBreakpoints } from '@vueuse/core';
import { CalendarIcon } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const breakpoints = useBreakpoints(breakpointsTailwind);
const smallerThanMd = breakpoints.smaller('md');

// Helpers
function toInternationalizedDate(dateObj: Date | string | null | undefined) {
  if (!dateObj) return null;
  if (typeof dateObj === 'string') {
    const [year, month, day] = dateObj.split('-');
    return new CalendarDate(Number(year), Number(month), Number(day));
  } else if (dateObj instanceof Date) {
    return new CalendarDate(dateObj.getFullYear(), dateObj.getMonth() + 1, dateObj.getDate());
  }
  return null;
}

function toStringDate(date: CalendarDate | null) {
  if (!date) return null;
  const mm = String(date.month).padStart(2, '0');
  const dd = String(date.day).padStart(2, '0');
  const yyyy = String(date.year);
  return `${yyyy}-${mm}-${dd}`;
}

// Props
const props = defineProps<{
  modelValue: { start: Date | string | null; end: Date | string | null } | null;
  headValue?: { start: Date | string | null; end: Date | string | null } | null;
  minValue?: Date | string | null;
  maxValue?: Date | string | null;
  heading?: string; // New prop for custom heading
}>();

const emit = defineEmits<{
  (e: 'update:modelValue', value: { start: string | null; end: string | null } | null): void;
}>();

// Calendar model
const calendarModel = ref<{ start?: CalendarDate; end?: CalendarDate }>({
  start: undefined,
  end: undefined,
});

// Sync with v-model
watch(
  () => props.modelValue,
  (newValue) => {
    calendarModel.value = {
      start: newValue?.start ? toInternationalizedDate(newValue.start) ?? undefined : undefined,
      end: newValue?.end ? toInternationalizedDate(newValue.end) ?? undefined : undefined,
    };
  },
  { immediate: true }
);

const isOpenPopover = ref(false);

// Convert min/max props
const minCalendar = props.minValue ? toInternationalizedDate(props.minValue) ?? undefined : undefined;
const maxCalendar = props.maxValue ? toInternationalizedDate(props.maxValue) ?? undefined : undefined;

// Apply and Clear
function applySelection() {
  const ensureCalendarDate = (obj: any) => {
    if (!obj) return null;
    if (obj instanceof CalendarDate) return obj;
    if (obj.year && obj.month && obj.day) return new CalendarDate(obj.year, obj.month, obj.day);
    return null;
  };

  const newValue = {
    start: toStringDate(ensureCalendarDate(calendarModel.value.start ?? null)),
    end: toStringDate(ensureCalendarDate(calendarModel.value.end ?? null)),
  };
  emit('update:modelValue', newValue);
  isOpenPopover.value = false;
}

function clearSelection() {
  calendarModel.value.start = undefined;
  calendarModel.value.end = undefined;
  emit('update:modelValue', { start: null, end: null });
  isOpenPopover.value = false;
}

// Compute display text for button
const displayText = () => {
  if (calendarModel.value.start && calendarModel.value.end) {
    return `${toStringDate(calendarModel.value.start)} - ${toStringDate(calendarModel.value.end)}`;
  }
  return props.heading || 'Departure Date - Arrival Date';
};
</script>

<template>
  <div :class="cn('grid gap-2', $attrs.class ?? '')">
    <Popover v-model:open="isOpenPopover">
      <PopoverTrigger as-child>
        <Button id="date" variant="outline" class="h-10 border-none bg-white/10 text-xs sm:text-sm">
          <CalendarIcon class="mr-1.5 h-3.5 w-3.5 text-white" />
          <span class="text-xs text-white sm:text-sm">{{ displayText() }}</span>
        </Button>
      </PopoverTrigger>
      <PopoverContent
        side="bottom"
        :side-offset="8"
        :avoid-collisions="false"
        class="w-auto bg-white p-0"
        align="end"
      >
        <div class="custom-calendar-container flex p-2">
          <RangeCalendar
            v-model="calendarModel"
            mode="range"
            :numberOfMonths="smallerThanMd ? 1 : 2"
            :minValue="minCalendar"
            :maxValue="maxCalendar"
            class="daterange-calendar"
          />
        </div>
        <div class="flex justify-end gap-1.5 px-2 pb-2">
          <Button size="sm" class="h-8 bg-primary px-3 text-xs text-white" @click="applySelection">Apply</Button>
          <Button class="h-8 bg-white px-3 text-xs text-black" variant="outline" size="sm" @click="clearSelection">Clear</Button>
        </div>
      </PopoverContent>
    </Popover>
  </div>
</template>

<style scoped>
.custom-calendar-container {
  --range-calendar-cell-size: 2.4rem;
}

.custom-calendar-container :deep(.daterange-calendar) {
  @apply p-1.5 text-xs;
}

.custom-calendar-container :deep(.daterange-calendar > div:nth-child(2)) {
  @apply mt-2 gap-x-3 gap-y-2;
}

.custom-calendar-container :deep(.daterange-calendar button) {
  @apply text-xs;
}

.custom-calendar-container :deep([data-selected]) {
  @apply bg-slate-200 text-slate-900 rounded-none;
}

.custom-calendar-container :deep([data-selection-start]),
.custom-calendar-container :deep([data-selection-end]) {
  @apply bg-white text-primary border border-primary rounded-sm relative font-semibold;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding-bottom: 0;
}

.custom-calendar-container :deep([data-selection-start]:hover),
.custom-calendar-container :deep([data-selection-end]:hover),
.custom-calendar-container :deep([data-selection-start]:focus),
.custom-calendar-container :deep([data-selection-end]:focus) {
  @apply bg-white text-primary;
}

.custom-calendar-container :deep([data-selection-start]::before),
.custom-calendar-container :deep([data-selection-end]::before) {
  position: absolute;
  top: 0;
  left: 0;
  font-size: 8px;
  line-height: 1;
  font-weight: 600;
  color: hsl(var(--primary-foreground));
  background-color: hsl(var(--primary));
  padding: 3px 4px;
  border-radius: 1px 0 3px 0;
}

.custom-calendar-container :deep([data-selection-start]::before) {
  content: 'Depart on';
}

.custom-calendar-container :deep([data-selection-end]::before) {
  content: 'Return on';
}

.custom-calendar-container :deep([data-selected]:not([data-selection-start]):not([data-selection-end])) {
  @apply bg-slate-200 text-slate-900;
}
</style>
