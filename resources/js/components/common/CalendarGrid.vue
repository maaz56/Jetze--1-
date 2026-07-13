<!-- CalendarGrid.vue -->
<template>
  <div class="calendar-grid-wrap">
    <div class="grid grid-cols-7 gap-1 text-center text-xs">
      <!-- Weekday headers -->
      <div v-for="day in ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']" :key="day" class="py-1.5 text-[10px] font-semibold text-gray-500">
        {{ day }}
      </div>

      <!-- Days -->
      <template v-for="day in days" :key="day?.toString()">
        <div
          v-if="day"
          class="calendar-day-cell relative flex flex-col items-center justify-center rounded-sm text-xs"
          :class="{
            'border border-blue-500 font-semibold': isSelected(day),
            'text-gray-400': !isInMonth(day),
            'hover:bg-gray-100 cursor-pointer': isInMonth(day) && !isDisabled(day),
            'opacity-50 cursor-not-allowed': isDisabled(day),
          }"
          @click="day && !isDisabled(day) && $emit('date-selected', day)"
        >
          <span>{{ day.day }}</span>
          <span v-if="priceForDay(day)" class="mt-0.5 text-[9px] leading-none text-green-600">
            {{ formatPrice(priceForDay(day)) }}
          </span>
        </div>
        <div v-else class="calendar-day-cell"></div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.calendar-grid-wrap {
  width: calc((var(--calendar-cell-size, 3.35rem) * 7) + (0.25rem * 6));
}

.calendar-day-cell {
  width: var(--calendar-cell-size, 3.35rem);
  height: var(--calendar-cell-size, 3.35rem);
}
</style>

<script setup>
import { computed } from "vue";
import { CalendarDate, GregorianCalendar, getDayOfWeek } from "@internationalized/date";

const props = defineProps({
  date: { type: CalendarDate, required: true },
  selected: { type: CalendarDate, default: null },
  prices: { type: Object, default: () => ({}) },
  minValue: { type: CalendarDate, default: null },
  maxValue: { type: CalendarDate, default: null },
});

const emit = defineEmits(["date-selected"]);

const days = computed(() => {
  const date = props.date; // e.g., new CalendarDate(2026, 1, 1)
  const calendar = date.calendar; // GregorianCalendar instance

  // Number of days in the month
  const daysInMonth = calendar.getDaysInMonth(date);

  // Day of week: 0 = Sunday, 1 = Monday, ..., 6 = Saturday (for 'en-US' or most locales)
  // We want Monday as first column → offset = dayOfWeek (Sunday = 6 empties)
  const dayOfWeek = getDayOfWeek(date, "en-US"); // 0-6
  const emptyCellsBefore = dayOfWeek === 0 ? 6 : dayOfWeek - 1;

  const result = [];

  // Leading empty cells
  for (let i = 0; i < emptyCellsBefore; i++) {
    result.push(null);
  }

  // Actual days
  for (let day = 1; day <= daysInMonth; day++) {
    result.push(new CalendarDate(date.year, date.month, day));
  }

  // Pad to 42 cells
  while (result.length < 42) {
    result.push(null);
  }

  return result;
});

function isSelected(day) {
  if (!day || !props.selected) return false;
  return day.compare(props.selected) === 0;
}

function isInMonth(day) {
  return day && day.month === props.date.month && day.year === props.date.year;
}

function isDisabled(day) {
  if (!day) return true;
  if (props.minValue && day.compare(props.minValue) < 0) return true;
  if (props.maxValue && day.compare(props.maxValue) > 0) return true;
  return false;
}

function priceForDay(day) {
  if (!day) return null;
  const key = `${day.year}-${String(day.month).padStart(2, "0")}-${String(day.day).padStart(2, "0")}`;
  return props.prices[key];
}

function formatPrice(price) {
  if (!price) return "";
  return price.toLocaleString();
}
</script>
