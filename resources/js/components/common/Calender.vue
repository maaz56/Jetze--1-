<!-- components/CalendarPopoverWithSelect.vue -->
<template>
  <div>
    <Popover v-model:open="isOpenPopover">
      <PopoverTrigger as-child>
        <Button
          class="h-[110px] w-full justify-start rounded  bg-white px-4 shadow-none hover:bg-gray-50 sm:h-[110px]"
        >
          <CalendarIcon class="mr-2 h-4 w-4 text-gray-600" />
          <div class="text-left">
            <p class="text-left text-lg font-bold leading-tight text-gray-900 sm:text-2xl">
              {{ displayText || "Select Date" }}
            </p>
            <p v-if="displayWeekday" class="mt-1 text-sm text-gray-500">
              {{ displayWeekday }}
            </p>
          </div>
        </Button>
      </PopoverTrigger>

      <PopoverContent
        side="bottom"
        :side-offset="8"
        :avoid-collisions="false"
        class="header-calendar-container w-auto max-w-none border-0 bg-white p-4 shadow-md"
      >
        <!-- Month + Year selects -->
        <!-- Month + Year selects -->
<div class="mb-4 flex items-center justify-between">
  <div class="flex gap-2 items-center">
    <Select v-model="selectedMonth" @update:modelValue="onMonthYearChange">
      <SelectTrigger class="w-[140px] border-gray-200">
        <span>
          {{ monthLabel || "Month" }}
        </span>
      </SelectTrigger>
      <SelectContent class="!max-h-[200px]  overflow-auto">
        <SelectItem
          v-for="m in availableMonths"
          :key="m.month"
          :value="m.month"
        >
          {{ m.label }}
        </SelectItem>
      </SelectContent>
    </Select>

    <Select v-model="selectedYear" @update:modelValue="onMonthYearChange">
      <SelectTrigger class="w-[140px] border-gray-200">
        <span>{{ selectedYear || "Year" }}</span>
      </SelectTrigger>
      <SelectContent class="!max-h-[200px] overflow-auto">
        <SelectItem v-for="y in availableYears" :key="y" :value="String(y)">
          {{ y }}
        </SelectItem>
      </SelectContent>
    </Select>
  </div>

  <!-- Navigation arrows (optional) -->
  <div class="flex gap-2 hidden sm:block">
    <Button variant="outline" size="icon" @click="navigateMonths(-1)" :disabled="isPrevDisabled">
      <
    </Button>
    <Button variant="outline" size="icon" @click="navigateMonths(1)" :disabled="isNextDisabled">
      >
    </Button>
  </div>
</div>

        <!-- Dual-month calendar view -->
        <div class="flex gap-6">
          <!-- Left month (current) -->
          <div>
            <div class="mb-2 text-center font-medium">
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
          <div class="hidden sm:block">
            <div class="mb-2 text-center font-medium">
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

        <!-- Optional: note about prices -->
        <p class="mt-4 text-xs text-gray-500">
          Prices are estimated for one adult and may change.
        </p>
      </PopoverContent>
    </Popover>
  </div>
</template>

<style scoped>
.header-calendar-container {
  --calendar-cell-size: 2.5rem;
}
</style>

<script setup>
import { computed, ref, watch } from "vue";
import { CalendarDate } from "@internationalized/date";
import { toDate } from "reka-ui/date"; // or your date utility
import { CalendarIcon } from "lucide-vue-next";
import { nextTick } from "vue";

// shadcn-vue components – adjust import paths as needed
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

// NEW: custom calendar grid component (you'll need to create this)
import CalendarGrid from "./CalendarGrid.vue"; // ← create this file

const props = defineProps({
  modelValue: { type: String, default: "" },
  minValue: { type: String, default: "" },
  maxValue: { type: String, default: "" },
  prices: { type: Object, default: () => ({}) },
});

const emit = defineEmits(["update:modelValue"]);

const selectedDate = ref(null);
const isOpenPopover = ref(false);

// Display controls
const now = new Date();
const displayedDateLeft = ref(
  new CalendarDate(now.getFullYear(), now.getMonth() + 1, 1)
);
const displayedDateRight = computed(
  () => displayedDateLeft.value.add({ months: 1 }),
);

// Navigation
function navigateMonths(delta) {
  const newDate = displayedDateLeft.value.add({ months: delta });
  // Optional: clamp to min/max
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

function compareMonthOnly(a, b) {
  if (!a || !b) return 0;
  if (a.year !== b.year) return a.year - b.year;
  return a.month - b.month;
}

const availableYears = computed(() => {
  let minY = minCalendarDate.value?.year ?? displayedDateLeft.value.year - 20;
  let maxY = maxCalendarDate.value?.year ?? displayedDateLeft.value.year + 20;
  const yrs = [];
  for (let y = minY; y <= maxY; y++) yrs.push(y);
  return yrs;
});

const availableMonths = computed(() => {
  const year = Number(selectedYear.value) || new Date().getFullYear(); // fallback
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
// Month/Year selects
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
      // Optionally jump to the month of the selected date
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
  // isOpenPopover.value = false;

  nextTick(() => {
    isOpenPopover.value = false;
  });
});

// Helpers
function formatMonthYear(date) {
  return new Intl.DateTimeFormat("en", {
    month: "long",
    year: "numeric",
  }).format(toDate(date));
}

function handleDateSelect(date) {
  selectedDate.value = date;
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

const minCalendarDate = computed(() => parseYYYYmmddToCalendarDate(props.minValue));
const maxCalendarDate = computed(() => parseYYYYmmddToCalendarDate(props.maxValue));

const displayText = computed(() => {
  if (!props.modelValue) return "";
  const parsed = parseYYYYmmddToCalendarDate(props.modelValue);
  if (!parsed) return props.modelValue;
  return new Intl.DateTimeFormat("en-GB", {
    day: "numeric",
    month: "short",
    year: "2-digit",
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
