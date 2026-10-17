<!-- CalendarGrid.vue -->
<template>
  <div class="calendar-grid-wrap">
    <div class="grid grid-cols-7 gap-0.5 text-center text-xs w-full">
      <!-- Weekday headers -->
      <div v-for="day in ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su']" :key="day" class="font-medium py-1 text-[9px] sm:text-[11px] text-gray-500">
        {{ day }}
      </div>

      <!-- Days -->
      <template v-for="day in days" :key="day?.toString()">
        <div
          v-if="day"
          class="calendar-day-cell relative flex flex-col items-center justify-center rounded transition-all duration-150"
          :class="{
            'selected border border-primary font-semibold': isSelected(day),
            'today': isToday(day) && !isSelected(day),
            'text-gray-400': !isInMonth(day),
            'hover:bg-primary/10 cursor-pointer': isInMonth(day) && !isDisabled(day),
            'disabled opacity-40 cursor-not-allowed': isDisabled(day),
          }"
          @click="day && !isDisabled(day) && $emit('date-selected', day)"
        >
          <span class="text-[11px] sm:text-sm">{{ day.day }}</span>
          <span v-if="priceForDay(day)" class="text-[7px] text-emerald-600 leading-none mt-0.5">
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
  width: calc((var(--calendar-cell-size, 2rem) * 7) + (0.125rem * 6));
}

.calendar-day-cell {
  width: var(--calendar-cell-size, 2rem);
  height: var(--calendar-cell-size, 2rem);
}

@media (max-width: 640px) {
  .calendar-grid-wrap {
    width: 100% !important;
  }

  .calendar-day-cell {
    width: 100% !important;
    height: 1.8rem !important;
  }
}
</style>

<script setup>
import { computed } from "vue";
import { CalendarDate, getDayOfWeek } from "@internationalized/date";

const props = defineProps({
  date: { type: CalendarDate, required: true },
  selected: { type: CalendarDate, default: null },
  prices: { type: Object, default: () => ({}) },
  minValue: { type: CalendarDate, default: null },
  maxValue: { type: CalendarDate, default: null },
});

const emit = defineEmits(["date-selected"]);

const now = new Date();
const todayObj = new CalendarDate(now.getFullYear(), now.getMonth() + 1, now.getDate());

const days = computed(() => {
  const date = props.date;
  const calendar = date.calendar;

  const daysInMonth = calendar.getDaysInMonth(date);

  const dayOfWeek = getDayOfWeek(date, "en-US");
  const emptyCellsBefore = dayOfWeek === 0 ? 6 : dayOfWeek - 1;

  const result = [];

  for (let i = 0; i < emptyCellsBefore; i++) {
    result.push(null);
  }

  for (let day = 1; day <= daysInMonth; day++) {
    result.push(new CalendarDate(date.year, date.month, day));
  }

  while (result.length < 42) {
    result.push(null);
  }

  return result;
});

function isSelected(day) {
  if (!day || !props.selected) return false;
  return day.compare(props.selected) === 0;
}

function isToday(day) {
  if (!day) return false;
  return day.compare(todayObj) === 0;
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
  if (typeof price === "number") {
    if (price >= 1000) return (price / 1000).toFixed(1).replace(/\.0$/, "") + "k";
    return price.toString();
  }
  return price.toString();
}
</script>
