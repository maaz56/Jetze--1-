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
        <Button id="date" variant="outline" class="h-10 border-none bg-white/10">
          <CalendarIcon class="h-4 w-4 mr-2 text-white" />
          <span class="text-white">{{ displayText() }}</span>
        </Button>
      </PopoverTrigger>
      <PopoverContent class="w-auto p-0 bg-white" align="end">
        <div class="flex">
          <RangeCalendar
            v-model="calendarModel"
            mode="range"
            :numberOfMonths="smallerThanMd ? 1 : 2"
            :minValue="minCalendar"
            :maxValue="maxCalendar"
          />
        </div>
        <div class="flex gap-2 justify-end p-3 pt-0">
          <Button size="sm" class="bg-primary text-white" @click="applySelection">Apply</Button>
          <Button class="bg-white text-black" variant="outline" size="sm" @click="clearSelection">Clear</Button>
        </div>
      </PopoverContent>
    </Popover>
  </div>
</template>