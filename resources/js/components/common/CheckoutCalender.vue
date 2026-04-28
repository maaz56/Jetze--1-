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
          <div class="flex gap-2 items-center">
            
            <Select v-model="selectedMonth" @update:modelValue="onMonthYearChange" class="px-2 py-1 border rounded">
              <SelectTrigger>
                 <span class=" w-[90px]">
                  {{ months.find(m => m.month === selectedMonth)?.label || 'Month' }}
                </span>
              </SelectTrigger>
              <SelectContent class="!h-[200px] overflow-auto ">
                <SelectItem v-for="m in months" :key="m.month" :value="m.month">{{ m.label }}</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="flex gap-2 items-center">
           
            <Select v-model="selectedYear" @update:modelValue="onMonthYearChange">
              <SelectTrigger class="px-2 py-1 border rounded w-[80px]">
                <span>{{ selectedYear||'Year' }}</span>
              </SelectTrigger>
              <SelectContent  class="!h-[200px] overflow-auto ">
                <SelectItem v-for="y in years" :key="y.year" :value="y.year">
                  {{ y.year }}
                </SelectItem>
              </SelectContent>
            </Select>
          </div>
        </div>

        <!-- calendar -->
        <Calendar
          v-model="selectedDate"
          :placeholder="placeholder"
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
import { computed, ref, watch } from 'vue';

/**
 * IMPORTANT: adjust these imports to match your project's shadcn-vue component locations.
 * Common paths:
 *  - '@/components/ui/calendar'
 *  - '@/components/ui/popover'
 * If you use different names for Popover children, adapt the template accordingly.
 */
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger } from '@/components/ui/select';
import { Calendar as CalendarIcon } from 'lucide-vue-next';

import { CalendarDate } from '@internationalized/date';
import { toDate } from 'reka-ui/date';
import Button from '../ui/button/Button.vue';

/* Props & emits for v-model + min/max */
const props = defineProps({
  modelValue: { type: String, default: '' }, // 'YYYY-mm-dd' (user-specified format)
  minValue: { type: String, default: '' },   // optional 'YYYY-mm-dd'
  maxValue: { type: String, default: '' },   // optional 'YYYY-mm-dd'
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
const years = computed(() => {
  let minY = minCalendarDate.value?.year ?? (placeholder.value.year - 100)
  let maxY = maxCalendarDate.value?.year ?? (placeholder.value.year + 10)

  const result = []
  for (let y = minY; y <= maxY; y++) {
    result.push({ year: y, dateObj: new CalendarDate(y, 1, 1) })
  }
  return result
})

// available months list (depends on selected year, and respects min/max)
const months = computed(() => {
  const result = []
  const currentYear = selectedYear.value

  for (let m = 1; m <= 12; m++) {
    const d = new Date(currentYear, m - 1, 1)

    // skip months before min
    if (
      minCalendarDate.value &&
      (currentYear < minCalendarDate.value.year ||
        (currentYear === minCalendarDate.value.year && m < minCalendarDate.value.month))
    ) {
      continue
    }

    // skip months after max
    if (
      maxCalendarDate.value &&
      (currentYear > maxCalendarDate.value.year ||
        (currentYear === maxCalendarDate.value.year && m > maxCalendarDate.value.month))
    ) {
      continue
    }

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
const minCalendarDate = computed(() => parseYYYYmmddToCalendarDate(props.minValue))
const maxCalendarDate = computed(() => parseYYYYmmddToCalendarDate(props.maxValue))

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
})

/* Called when month/year selects change */
function onMonthYearChange() {
  placeholder.value = new CalendarDate(Number(selectedYear.value), Number(selectedMonth.value) + 1, 1)
}

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
</script>

<style scoped>
/* customize to your design system */
</style>
