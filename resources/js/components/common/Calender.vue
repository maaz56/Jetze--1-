<!-- components/CalendarPopoverWithSelect.vue -->
<template>
  <div>
    <Popover v-model:open="isOpenPopover">
      <PopoverTrigger as-child>
        <!-- Large booking card trigger (Jetze FlightFilterCard & Header) -->
        <Button
          v-if="largeIcon"
          class="h-[110px] w-full justify-start rounded bg-white px-4 shadow-none hover:bg-gray-50 sm:h-[110px]"
        >
          <CalendarIcon class="mr-3 h-7 w-7 text-gray-600" />
          <div class="text-left">
            <p class="text-left text-lg font-bold leading-tight text-gray-900 sm:text-2xl">
              {{ largeDisplayText || displayText || "Select Date" }}
            </p>
            <p v-if="displayWeekday" class="mt-1 text-sm text-gray-500">
              {{ displayWeekday }}
            </p>
          </div>
        </Button>

        <!-- Standard / compact trigger -->
        <Button
          v-else
          class="h-10 w-full border border-primary/20 bg-white text-gray-700 shadow-sm shadow-slate-900/5 hover:border-primary/30 hover:bg-white font-medium transition-all duration-200 text-sm focus-visible:ring-primary/20 focus-visible:ring-offset-0"
          :class="{ 'text-primary': isOpenPopover }"
        >
          <CalendarIcon class="h-3.5 w-3.5 mr-1.5 text-gray-500" :class="{ 'text-primary': isOpenPopover }" />
          <span>{{ displayText || "Select Date" }}</span>
        </Button>
      </PopoverTrigger>

      <PopoverContent
        class="calendar-popover p-3 bg-white border border-gray-100 shadow-xl rounded w-auto max-w-none"
        side="bottom"
        align="start"
        :collision-padding="16"
      >
        <!-- Month + Year selects -->
        <div class="flex items-center justify-between mb-3">
          <div class="flex gap-1.5 items-center">
            <Select v-model="selectedMonth" @update:modelValue="onMonthYearChange">
              <SelectTrigger class="w-[110px] h-8 border-gray-200 rounded text-gray-700 text-xs">
                <span class="text-xs">{{ monthLabel || "Month" }}</span>
              </SelectTrigger>
              <SelectContent class="max-h-[180px] overflow-auto rounded text-xs">
                <SelectItem v-for="m in availableMonths" :key="m.month" :value="m.month" class="text-xs">
                  {{ m.label }}
                </SelectItem>
              </SelectContent>
            </Select>

            <Select v-model="selectedYear" @update:modelValue="onMonthYearChange">
              <SelectTrigger class="w-[85px] h-8 border-gray-200 rounded text-gray-700 text-xs">
                <span class="text-xs">{{ selectedYear || "Year" }}</span>
              </SelectTrigger>
              <SelectContent class="max-h-[180px] overflow-auto rounded text-xs">
                <SelectItem v-for="y in availableYears" :key="y" :value="String(y)" class="text-xs">
                  {{ y }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>

          <!-- Navigation arrows -->
          <div class="flex gap-0.5">
            <button
              type="button"
              @click="navigateMonths(-1)"
              :disabled="isPrevDisabled"
              class="w-7 h-7 rounded border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-primary/10 hover:text-primary transition-all disabled:opacity-40 disabled:cursor-not-allowed text-xs"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <button
              type="button"
              @click="navigateMonths(1)"
              :disabled="isNextDisabled"
              class="w-7 h-7 rounded border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-primary/10 hover:text-primary transition-all disabled:opacity-40 disabled:cursor-not-allowed text-xs"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Dual-month calendar view -->
        <div class="calendar-scroll-wrapper flex gap-4">
          <div class="calendar-month-block flex-shrink-0">
            <div class="text-center font-medium text-gray-700 mb-2 text-xs">
              {{ formatMonthYear(displayedDateLeft) }}
            </div>
            <CalendarGrid
              :date="displayedDateLeft"
              :prices="prices"
              :selected="selectedDate"
              @date-selected="handleDateSelect"
              :min-value="minCalendarDate"
              :max-value="maxCalendarDate"
            />
          </div>

          <!-- Right month (next) -->
          <div class="calendar-month-block flex-shrink-0">
            <div class="text-center font-medium text-gray-700 mb-2 text-xs">
              {{ formatMonthYear(displayedDateRight) }}
            </div>
            <CalendarGrid
              :date="displayedDateRight"
              :prices="prices"
              :selected="selectedDate"
              @date-selected="handleDateSelect"
              :min-value="minCalendarDate"
              :max-value="maxCalendarDate"
            />
          </div>
        </div>

      </PopoverContent>
    </Popover>
  </div>
</template>

<style scoped>
.calendar-popover {
  --calendar-cell-size: 2rem;
  width: auto;
  min-width: 480px;
}

@media (max-width: 640px) {
  .calendar-popover {
    width: calc(100vw - 1.5rem) !important;
    max-width: 340px !important;
    min-width: unset !important;
    padding: 8px !important;
  }

  .calendar-scroll-wrapper {
    display: block !important;
  }

  .calendar-scroll-wrapper .calendar-month-block:last-child {
    display: none !important;
  }

  .calendar-scroll-wrapper .calendar-month-block:first-child {
    width: 100% !important;
    display: block !important;
  }
}
</style>

<style>
/* Desktop cell sizes */
.calendar-day-cell {
  width: 2rem;
  height: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 0.25rem;
  font-size: 0.75rem;
  font-weight: 500;
  transition: all 0.2s ease;
  cursor: pointer;
}

.calendar-day-cell:hover:not(.disabled) {
  background-color: hsl(var(--primary) / 0.1);
  color: hsl(var(--primary));
}

.calendar-day-cell.selected {
  background-color: hsl(var(--primary)) !important;
  color: hsl(var(--primary-foreground)) !important;
  box-shadow: 0 2px 6px hsl(var(--primary) / 0.25);
}

.calendar-day-cell.today {
  border: 1.5px solid hsl(var(--primary));
  color: hsl(var(--primary));
  font-weight: 700;
}

.calendar-day-cell.disabled {
  opacity: 0.4;
  cursor: not-allowed;
  background-color: #f3f4f6;
}

.calendar-weekday {
  width: 2rem;
  height: 1.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 0.65rem;
  font-weight: 600;
  color: #9ca3af;
  text-transform: uppercase;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 2rem);
  gap: 1px;
}

/* Mobile overrides */
@media (max-width: 640px) {
  .calendar-grid {
    grid-template-columns: repeat(7, 1fr) !important;
    width: 100% !important;
    gap: 1px !important;
  }

  .calendar-weekday {
    width: 100% !important;
    min-width: 0 !important;
    height: 1.5rem !important;
    font-size: 0.55rem !important;
  }

  .calendar-day-cell {
    width: 100% !important;
    min-width: 0 !important;
    height: 1.75rem !important;
    font-size: 0.65rem !important;
  }
}
</style>

<script setup>
import { computed, ref, watch, nextTick } from "vue";
import { CalendarDate } from "@internationalized/date";
import { toDate } from "reka-ui/date";
import { CalendarIcon } from "lucide-vue-next";
import { getTravellerDobRange } from "@/lib/travellerDob";

import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
} from "@/components/ui/select";
import Button from "@/components/ui/button/Button.vue";
import CalendarGrid from "./CalendarGrid.vue";

const props = defineProps({
  modelValue: { type: String, default: "" },
  minValue: { type: String, default: "" },
  maxValue: { type: String, default: "" },
  dobType: { type: String, default: "" },
  prices: { type: Object, default: () => ({}) },
  largeIcon: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue", "selected"]);

const selectedDate = ref(null);
const isOpenPopover = ref(false);

function open() {
  isOpenPopover.value = true;
}

function close() {
  isOpenPopover.value = false;
}

defineExpose({
  open,
  close,
});

// Display controls
const now = new Date();
const displayedDateLeft = ref(
  new CalendarDate(now.getFullYear(), now.getMonth() + 1, 1)
);
const displayedDateRight = computed(
  () => displayedDateLeft.value.add({ months: 1 }),
);

const effectiveDobRange = computed(() => {
  if (props.dobType) {
    return getTravellerDobRange(props.dobType);
  }

  return {
    min: props.minValue,
    max: props.maxValue,
  };
});

function compareMonthOnly(a, b) {
  if (!a || !b) return 0;
  if (a.year !== b.year) return a.year - b.year;
  return a.month - b.month;
}

function navigateMonths(delta) {
  const newDate = displayedDateLeft.value.add({ months: delta });
  if (
    minCalendarDate.value &&
    compareMonthOnly(newDate, minCalendarDate.value) < 0
  ) return;
  if (
    maxCalendarDate.value &&
    compareMonthOnly(newDate, maxCalendarDate.value) > 0
  ) return;

  displayedDateLeft.value = newDate;
}

const isPrevDisabled = computed(() => {
  if (!minCalendarDate.value) return false;
  const prev = displayedDateLeft.value.subtract({ months: 1 });
  return compareMonthOnly(prev, minCalendarDate.value) < 0;
});

const isNextDisabled = computed(() => {
  if (!maxCalendarDate.value) return false;
  const next = displayedDateRight.value.add({ months: 1 });
  return compareMonthOnly(next, maxCalendarDate.value) > 0;
});

const availableYears = computed(() => {
  let minY = minCalendarDate.value?.year ?? displayedDateLeft.value.year - 20;
  let maxY = maxCalendarDate.value?.year ?? displayedDateLeft.value.year + 20;
  const yrs = [];
  for (let y = minY; y <= maxY; y++) yrs.push(y);
  return yrs;
});

const availableMonths = computed(() => {
  const year = Number(selectedYear.value) || new Date().getFullYear();
  const list = [];
  for (let m = 1; m <= 12; m++) {
    const monthStart = new CalendarDate(year, m, 1);
    if (minCalendarDate.value && compareMonthOnly(monthStart, minCalendarDate.value) < 0) continue;
    if (maxCalendarDate.value && compareMonthOnly(monthStart, maxCalendarDate.value) > 0) continue;

    list.push({
      month: String(m - 1),
      label: new Intl.DateTimeFormat("en", { month: "long" }).format(toDate(monthStart)),
    });
  }
  return list;
});

const monthLabel = computed(() => {
  return availableMonths.value.find((m) => m.month === selectedMonth.value)?.label;
});

const selectedMonth = ref(String(displayedDateLeft.value.month - 1));
const selectedYear = ref(String(displayedDateLeft.value.year));

watch(displayedDateLeft, (newVal) => {
  selectedMonth.value = String(newVal.month - 1);
  selectedYear.value = String(newVal.year);
}, { immediate: true });

function onMonthYearChange() {
  if (selectedYear.value && selectedMonth.value !== undefined) {
    displayedDateLeft.value = new CalendarDate(
      Number(selectedYear.value),
      Number(selectedMonth.value) + 1,
      1
    );
  }
}

// Sync selected date
watch(
  () => props.modelValue,
  (val) => {
    if (!val) {
      selectedDate.value = null;
      return;
    }
    const cal = parseYYYYmmddToCalendarDate(val);
    if (cal) {
      selectedDate.value = cal;
      displayedDateLeft.value = new CalendarDate(cal.year, cal.month, 1);
    }
  },
  { immediate: true },
);

watch(selectedDate, (val) => {
  if (!val) {
    emit("update:modelValue", "");
    return;
  }
  const str = formatDateValueToYYYYmmdd(val);
  emit("update:modelValue", str);
  nextTick(() => {
    isOpenPopover.value = false;
  });
});

function handleDateSelect(date) {
  selectedDate.value = date;
  emit("selected", formatDateValueToYYYYmmdd(date));
}

function formatMonthYear(date) {
  return new Intl.DateTimeFormat("en", {
    month: "long",
    year: "numeric",
  }).format(toDate(date));
}

function formatDateValueToYYYYmmdd(dateVal) {
  if (!dateVal) return "";
  const d = toDate(dateVal);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(
    d.getDate(),
  ).padStart(2, "0")}`;
}

function parseYYYYmmddToCalendarDate(s) {
  if (!s) return null;
  const [y, m, d] = s.split("-").map(Number);
  if (!y || !m || !d) return null;
  return new CalendarDate(y, m, d);
}

const minCalendarDate = computed(() => parseYYYYmmddToCalendarDate(effectiveDobRange.value.min));
const maxCalendarDate = computed(() => parseYYYYmmddToCalendarDate(effectiveDobRange.value.max));

watch(
  [minCalendarDate, maxCalendarDate, () => props.dobType],
  () => {
    if (props.modelValue) return;

    const anchor = maxCalendarDate.value ?? minCalendarDate.value;
    if (anchor) {
      displayedDateLeft.value = new CalendarDate(anchor.year, anchor.month, 1);
    }
  },
  { immediate: true },
);

const largeDisplayText = computed(() => {
  if (!props.modelValue) return "";
  const parsed = parseYYYYmmddToCalendarDate(props.modelValue);
  if (!parsed) return props.modelValue;
  return new Intl.DateTimeFormat("en-GB", {
    day: "numeric",
    month: "short",
    year: "2-digit",
  }).format(toDate(parsed));
});

const displayText = computed(() => {
  if (!props.modelValue) return "";
  const parsed = parseYYYYmmddToCalendarDate(props.modelValue);
  if (!parsed) return props.modelValue;
  return new Intl.DateTimeFormat("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
  }).format(toDate(parsed));
});

const displayWeekday = computed(() => {
  if (!props.modelValue) return "";
  const parsed = parseYYYYmmddToCalendarDate(props.modelValue);
  if (!parsed) return "";
  return new Intl.DateTimeFormat("en-US", { weekday: "long" }).format(
    toDate(parsed),
  );
});
</script>
