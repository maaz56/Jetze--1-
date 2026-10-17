<!-- components/CalendarPopoverWithSelect.vue -->
<template>
  <div class="">
    <Popover v-model:open="isOpenPopover">
      <PopoverTrigger as-child >
        <Button
          variant="outline"
          class="w-full h-10 bg-white"
        >
          <CalendarIcon class="mr-2 h-4 w-4" />
          <span>{{ displayText || 'Select Date' }}</span>
          
        </Button>
      </PopoverTrigger>

      <PopoverContent class=" p-3 bg-white">
        <!-- header: month + year selects -->
        <div class="flex items-center justify-between mb-2">
          <div ref="monthPicker" class="relative flex gap-2 items-center">
            <button
              type="button"
              class="flex h-9 w-[108px] items-center justify-between rounded border px-2 text-sm hover:border-primary/30 focus:outline-none focus:ring-2 focus:ring-primary/20"
              :aria-expanded="isMonthPickerOpen"
              aria-haspopup="listbox"
              @click="toggleMonthPicker"
            >
              <span>{{ months.find(m => m.month === selectedMonth)?.label || 'Month' }}</span>
              <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
              </svg>
            </button>

            <div
              v-if="isMonthPickerOpen"
              class="absolute left-0 top-full z-50 mt-1 max-h-64 w-[140px] overflow-y-auto rounded border border-gray-200 bg-white py-1 text-sm shadow-lg"
              role="listbox"
            >
              <button
                v-for="month in months"
                :key="month.month"
                type="button"
                role="option"
                :aria-selected="month.month === selectedMonth"
                class="year-picker-option"
                :class="{ 'bg-primary/10 text-primary font-medium': month.month === selectedMonth }"
                @click="selectMonth(month.month)"
              >{{ month.label }}</button>
            </div>
          </div>

          <div ref="yearPicker" class="relative flex gap-2 items-center">
            <button
              type="button"
              class="flex h-9 w-[80px] items-center justify-between rounded border px-2 text-sm hover:border-primary/30 focus:outline-none focus:ring-2 focus:ring-primary/20"
              :aria-expanded="isYearPickerOpen"
              aria-haspopup="listbox"
              @click="toggleYearPicker"
            >
              <span>{{ selectedYear || 'Year' }}</span>
              <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" />
              </svg>
            </button>

            <div
              v-if="isYearPickerOpen"
              class="year-picker absolute right-0 top-full z-50 mt-1 w-[140px] overflow-hidden rounded border border-gray-200 bg-white py-1 text-sm shadow-lg"
              role="listbox"
            >
              <button type="button" class="year-picker-nav" :disabled="isPreviousYearPageDisabled" aria-label="Show 4 earlier years" @click="changeYearPage(-1)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m18 15-6-6-6 6" /></svg>
              </button>

              <div class="year-picker-list">
                <Transition :name="yearPageTransition" mode="out-in">
                  <div :key="visibleYearStart" class="year-picker-page">
                    <button
                      v-for="year in visibleYears"
                      :key="year"
                      type="button"
                      role="option"
                      :aria-selected="year === selectedYear"
                      class="year-picker-option"
                      :class="{ 'bg-primary/10 text-primary font-medium': year === selectedYear }"
                      @click="selectYear(year)"
                    >{{ year }}</button>
                  </div>
                </Transition>
              </div>

              <button type="button" class="year-picker-nav" :disabled="isNextYearPageDisabled" aria-label="Show 4 later years" @click="changeYearPage(1)">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m6 9 6 6 6-6" /></svg>
              </button>
            </div>
          </div>

        </div>

        <!-- calendar -->
        <Calendar
          v-model="selectedDate"
          v-model:placeholder="placeholder"
          :min-value="minCalendarDate"
          :max-value="maxCalendarDate"
          class="rounded-lg border"
        >
          <!-- Keep default slots of your project's Calendar component -->
        </Calendar>

      </PopoverContent>
    </Popover>
  </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * IMPORTANT: adjust these imports to match your project's shadcn-vue component locations.
 * Common paths:
 *  - '@/components/ui/calendar'
 *  - '@/components/ui/popover'
 * If you use different names for Popover children, adapt the template accordingly.
 */
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Calendar as CalendarIcon } from 'lucide-vue-next';

import { CalendarDate } from '@internationalized/date';
import { toDate } from 'reka-ui/date';
import Button from '../ui/button/Button.vue';
import { getTravellerDobRange } from '@/lib/travellerDob';

/* Props & emits for v-model + min/max */
const props = defineProps({
  modelValue: { type: String, default: '' }, // 'YYYY-mm-dd' (user-specified format)
  minValue: { type: String, default: '' },   // optional 'YYYY-mm-dd'
  maxValue: { type: String, default: '' },   // optional 'YYYY-mm-dd'
  dobType: { type: String, default: '' },    // adult | child | infant
})

const emit = defineEmits(['update:modelValue'])

/* Internal state */
// This is what Calendar consumes as its v-model; Radix-vue date structures or CalendarDate
const selectedDate = ref(null)

// placeholder controls visible month in calendar (CalendarDate expected)
const now = new Date()
const placeholder = ref(new CalendarDate(now.getFullYear(), now.getMonth() + 1, 1))
const isOpenPopover = ref(false)
/* Selected month/year shown in selects (month: 0-based in selects) */
const selectedMonth = ref(placeholder.value.month - 1)
const selectedYear = ref(placeholder.value.year)

// available years list (only between min and max)
const effectiveDobRange = computed(() => {
  if (props.dobType) {
    return getTravellerDobRange(props.dobType);
  }

  return {
    min: props.minValue,
    max: props.maxValue,
  };
});

const minYear = computed(() => minCalendarDate.value?.year ?? (placeholder.value.year - 100))
const maxYear = computed(() => maxCalendarDate.value?.year ?? (placeholder.value.year + 10))
const isYearPickerOpen = ref(false)
const yearPicker = ref(null)
const isMonthPickerOpen = ref(false)
const monthPicker = ref(null)
const visibleYearStart = ref(placeholder.value.year)
const yearPageTransition = ref('year-page-down')
const visibleYears = computed(() => {
  const years = []
  for (let year = visibleYearStart.value; year <= Math.min(visibleYearStart.value + 3, maxYear.value); year++) {
    years.push(year)
  }
  return years
})
const isPreviousYearPageDisabled = computed(() => visibleYearStart.value <= minYear.value)
const isNextYearPageDisabled = computed(() => visibleYearStart.value + 3 >= maxYear.value)

// The selected year always exposes all twelve months. The Calendar itself
// enforces the date range by disabling out-of-range days.
const months = computed(() => {
  const result = []
  const currentYear = selectedYear.value

  for (let m = 1; m <= 12; m++) {
    const d = new Date(currentYear, m - 1, 1)

    result.push({
      label: new Intl.DateTimeFormat('en', { month: 'long' }).format(d),
      month: m - 1, // 0-based for select
      dateObj: new CalendarDate(currentYear, m, 1),
    })
  }

  return result
})


/* Helpers: format and parse 'YYYY-mm-dd' strings.
   NOTE: user requested 'YYYY-mm-dd' — here we interpret 'mm' as month number (01..12).
*/
function formatDateValueToYYYYmmdd(dateVal) {
  if (!dateVal) return ''
  // dateVal might be a CalendarDate-like object; use toDate to get JS Date
  const d = toDate(dateVal)
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}
function parseYYYYmmddToCalendarDate(s) {
  if (!s || typeof s !== 'string') return null
  const parts = s.split('-')
  if (parts.length !== 3) return null
  const [yStr, mStr, dStr] = parts
  const y = Number(yStr)
  const m = Number(mStr)
  const d = Number(dStr)
  if (!Number.isFinite(y) || !Number.isFinite(m) || !Number.isFinite(d)) return null
  return new CalendarDate(y, m, d) // CalendarDate expects 1-based month
}

/* Watch incoming modelValue -> reflect in selectedDate & placeholder */
watch(
  () => props.modelValue,
  (val) => {
    if (!val) {
      selectedDate.value = null
      return
    }
    const cal = parseYYYYmmddToCalendarDate(val)
    if (cal) {
      selectedDate.value = cal
      // also set placeholder to the month of that date so calendar shows it
      placeholder.value = new CalendarDate(cal.year, cal.month, 1)
    }
  },
  { immediate: true }
)
watch(
  selectedDate,
  (val) => {
    if (!val) {
      emit('update:modelValue', '')
      return
    }
    const str = formatDateValueToYYYYmmdd(val)
    emit('update:modelValue', str)
    isOpenPopover.value = false // close popover on date selection
  },
  { immediate: true }
)
/* min/max props -> CalendarDate or undefined (passed to Calendar as :min/:max) */
const minCalendarDate = computed(() => parseYYYYmmddToCalendarDate(effectiveDobRange.value.min))
const maxCalendarDate = computed(() => parseYYYYmmddToCalendarDate(effectiveDobRange.value.max))

/* When user picks a date in Calendar, update v-model string */
watch(selectedDate, (val) => {
  if (!val) {
    emit('update:modelValue', '')
    return
  }
  const str = formatDateValueToYYYYmmdd(val)
  emit('update:modelValue', str)
})

/* Update selects when placeholder changes (e.g., user navigates through calendar) */
watch(placeholder, (val) => {
  selectedMonth.value = val.month - 1
  selectedYear.value = val.year
  if (isYearPickerOpen.value) alignYearPage(val.year)
})

/* Called when month/year selects change */
function onMonthYearChange() {
  placeholder.value = new CalendarDate(Number(selectedYear.value), Number(selectedMonth.value) + 1, 1)
}

function alignYearPage(year = selectedYear.value) {
  const latestStart = Math.max(minYear.value, maxYear.value - 3)
  visibleYearStart.value = Math.max(minYear.value, Math.min(year - 1, latestStart))
}

function toggleYearPicker() {
  isYearPickerOpen.value = !isYearPickerOpen.value
  if (isYearPickerOpen.value) {
    isMonthPickerOpen.value = false
    alignYearPage()
  }
}

function changeYearPage(direction) {
  const nextStart = visibleYearStart.value + direction * 4
  if (nextStart < minYear.value || nextStart > maxYear.value) return

  yearPageTransition.value = direction > 0 ? 'year-page-down' : 'year-page-up'
  visibleYearStart.value = nextStart
}

function selectYear(year) {
  selectedYear.value = year
  onMonthYearChange()
  isYearPickerOpen.value = false
}

function toggleMonthPicker() {
  isMonthPickerOpen.value = !isMonthPickerOpen.value
  if (isMonthPickerOpen.value) {
    isYearPickerOpen.value = false
  }
}

function selectMonth(month) {
  selectedMonth.value = month
  onMonthYearChange()
  isMonthPickerOpen.value = false
}

function closeYearPickerOnOutsideClick(event) {
  if (yearPicker.value && !yearPicker.value.contains(event.target)) {
    isYearPickerOpen.value = false
  }
  if (monthPicker.value && !monthPicker.value.contains(event.target)) {
    isMonthPickerOpen.value = false
  }
}

onMounted(() => document.addEventListener('mousedown', closeYearPickerOnOutsideClick))
onBeforeUnmount(() => document.removeEventListener('mousedown', closeYearPickerOnOutsideClick))

/* Popover action buttons */
function clear() {
  selectedDate.value = null
  emit('update:modelValue', '')
}
function confirm() {
  // Keep selectedDate already updating modelValue via watch; close is handled by popover internals
  // If you need to programmatically close Popover you'd need a ref to it (skipped to keep example simple)
}

/* Display text for trigger button */
const displayText = computed(() => {
  if (!props.modelValue) return ''
  return props.modelValue
})

watch(
  [minCalendarDate, maxCalendarDate, () => props.dobType],
  () => {
    if (props.modelValue) return

    const anchor = maxCalendarDate.value ?? minCalendarDate.value
    if (anchor) {
      placeholder.value = new CalendarDate(anchor.year, anchor.month, 1)
      selectedMonth.value = placeholder.value.month - 1
      selectedYear.value = placeholder.value.year
    }
  },
  { immediate: true },
)

</script>

<style scoped>
.year-picker-list {
  min-height: 8rem;
  overflow: hidden;
}

.year-picker-page {
  display: grid;
}

.year-picker-nav,
.year-picker-option {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: center;
}

.year-picker-nav {
  height: 1.75rem;
  color: #6b7280;
  transition: background-color 150ms ease, color 150ms ease;
}

.year-picker-nav:hover:not(:disabled) {
  color: hsl(var(--primary));
  background: hsl(var(--primary) / 0.05);
}

.year-picker-nav:disabled {
  cursor: not-allowed;
  opacity: 0.35;
}

.year-picker-option {
  height: 2rem;
  color: #374151;
  transition: background-color 150ms ease, color 150ms ease;
}

.year-picker-option:hover {
  color: hsl(var(--primary));
  background: hsl(var(--primary) / 0.1);
}

.year-page-down-enter-active,
.year-page-down-leave-active,
.year-page-up-enter-active,
.year-page-up-leave-active {
  transition: transform 180ms ease, opacity 180ms ease;
}

.year-page-down-enter-from,
.year-page-up-leave-to {
  opacity: 0;
  transform: translateY(0.5rem);
}

.year-page-down-leave-to,
.year-page-up-enter-from {
  opacity: 0;
  transform: translateY(-0.5rem);
}
</style>
