<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 p-8">
    <div class="max-w-4xl mx-auto">
      <div class="bg-white rounded-2xl shadow-xl p-6">
        <!-- Header Controls -->
        <div class="mb-6 flex items-center justify-between flex-wrap gap-4">
          <h2 class="text-2xl font-bold text-slate-800">Custom Calendar</h2>
          <div class="flex gap-2">
            <button
              @click="mode = 'single'"
              :class="[
                'px-4 py-2 rounded-lg font-medium transition-all',
                mode === 'single'
                  ? 'bg-blue-500 text-white shadow-md'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              ]"
            >
              Single Date
            </button>
            <button
              @click="mode = 'range'"
              :class="[
                'px-4 py-2 rounded-lg font-medium transition-all',
                mode === 'range'
                  ? 'bg-blue-500 text-white shadow-md'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
              ]"
            >
              Date Range
            </button>
          </div>
        </div>

        <!-- Calendar Navigation -->
        <div class="flex items-center justify-between mb-6">
          <button
            @click="previousMonth"
            class="p-2 hover:bg-slate-100 rounded-lg transition-colors"
          >
            <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          
          <div class="flex items-center gap-4">
            <select
              v-model="currentMonth"
              class="px-4 py-2 border border-slate-200 rounded-lg font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option v-for="(month, index) in months" :key="index" :value="index">
                {{ month }}
              </option>
            </select>
            <select
              v-model="currentYear"
              class="px-4 py-2 border border-slate-200 rounded-lg font-semibold text-slate-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option v-for="year in years" :key="year" :value="year">
                {{ year }}
              </option>
            </select>
          </div>

          <button
            @click="nextMonth"
            class="p-2 hover:bg-slate-100 rounded-lg transition-colors"
          >
            <svg class="w-6 h-6 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>

        <!-- Weekday Headers -->
        <div class="grid grid-cols-7 gap-2 mb-2">
          <div
            v-for="day in weekDays"
            :key="day"
            class="text-center text-sm font-semibold text-slate-500 py-2"
          >
            {{ day }}
          </div>
        </div>

        <!-- Calendar Grid -->
        <div class="grid grid-cols-7 gap-2">
          <div
            v-for="(day, index) in calendarDays"
            :key="index"
            @click="selectDate(day)"
            :class="[
              'aspect-square rounded-lg p-2 cursor-pointer transition-all relative',
              getDayClasses(day)
            ]"
          >
            <div class="flex flex-col h-full">
              <span :class="['text-sm font-medium', day.isCurrentMonth ? 'text-slate-700' : 'text-slate-300']">
                {{ day.date }}
              </span>
              
              <!-- Event Indicators -->
              <div v-if="getEventsForDay(day).length > 0" class="mt-auto">
                <div class="flex flex-wrap gap-1">
                  <div
                    v-for="(event, idx) in getEventsForDay(day).slice(0, 2)"
                    :key="idx"
                    :class="[
                      'text-xs px-1 py-0.5 rounded truncate',
                      event.color || 'bg-purple-100 text-purple-700'
                    ]"
                    :title="event.title"
                  >
                    {{ event.title }}
                  </div>
                </div>
                <div
                  v-if="getEventsForDay(day).length > 2"
                  class="text-xs text-slate-500 mt-1"
                >
                  +{{ getEventsForDay(day).length - 2 }} more
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Selected Info -->
        <div class="mt-6 p-4 bg-slate-50 rounded-lg">
          <div v-if="mode === 'single'" class="text-slate-700">
            <span class="font-semibold">Selected Date:</span>
            {{ selectedDate ? formatDate(selectedDate) : 'None' }}
          </div>
          <div v-else class="text-slate-700">
            <span class="font-semibold">Selected Range:</span>
            {{ rangeStart ? formatDate(rangeStart) : 'Start' }} 
            <span class="mx-2">→</span>
            {{ rangeEnd ? formatDate(rangeEnd) : 'End' }}
          </div>
        </div>

        <!-- Event Management -->
        <div class="mt-6 pt-6 border-t border-slate-200">
          <h3 class="text-lg font-semibold text-slate-800 mb-4">Add Event</h3>
          <div class="flex gap-2 flex-wrap">
            <input
              v-model="newEvent.date"
              type="date"
              class="px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <input
              v-model="newEvent.title"
              type="text"
              placeholder="Event title"
              class="flex-1 px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
            <select
              v-model="newEvent.color"
              class="px-4 py-2 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="bg-purple-100 text-purple-700">Purple</option>
              <option value="bg-blue-100 text-blue-700">Blue</option>
              <option value="bg-green-100 text-green-700">Green</option>
              <option value="bg-red-100 text-red-700">Red</option>
              <option value="bg-yellow-100 text-yellow-700">Yellow</option>
            </select>
            <button
              @click="addEvent"
              class="px-6 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors font-medium"
            >
              Add Event
            </button>
          </div>

          <!-- Events List -->
          <div v-if="events.length > 0" class="mt-4 space-y-2">
            <div
              v-for="(event, index) in events"
              :key="index"
              class="flex items-center justify-between p-3 bg-white border border-slate-200 rounded-lg"
            >
              <div class="flex items-center gap-3">
                <span :class="['px-3 py-1 rounded-lg text-sm font-medium', event.color]">
                  {{ event.title }}
                </span>
                <span class="text-sm text-slate-500">{{ event.date }}</span>
              </div>
              <button
                @click="removeEvent(index)"
                class="text-red-500 hover:text-red-700 transition-colors"
              >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'

const mode = ref('single')
const currentMonth = ref(new Date().getMonth())
const currentYear = ref(new Date().getFullYear())
const selectedDate = ref(null)
const rangeStart = ref(null)
const rangeEnd = ref(null)
const events = ref([
  { date: '2025-01-15', title: 'Team Meeting', color: 'bg-blue-100 text-blue-700' },
  { date: '2025-01-20', title: 'Project Deadline', color: 'bg-red-100 text-red-700' },
  { date: '2025-01-25', title: 'Birthday Party', color: 'bg-purple-100 text-purple-700' }
])

const newEvent = ref({
  date: '',
  title: '',
  color: 'bg-purple-100 text-purple-700'
})

const months = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
]

const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const years = computed(() => {
  const currentYear = new Date().getFullYear()
  return Array.from({ length: 21 }, (_, i) => currentYear - 10 + i)
})

const calendarDays = computed(() => {
  const firstDay = new Date(currentYear.value, currentMonth.value, 1)
  const lastDay = new Date(currentYear.value, currentMonth.value + 1, 0)
  const prevLastDay = new Date(currentYear.value, currentMonth.value, 0)
  
  const days = []
  
  // Previous month days
  const firstDayOfWeek = firstDay.getDay()
  for (let i = firstDayOfWeek - 1; i >= 0; i--) {
    days.push({
      date: prevLastDay.getDate() - i,
      month: currentMonth.value === 0 ? 11 : currentMonth.value - 1,
      year: currentMonth.value === 0 ? currentYear.value - 1 : currentYear.value,
      isCurrentMonth: false
    })
  }
  
  // Current month days
  for (let i = 1; i <= lastDay.getDate(); i++) {
    days.push({
      date: i,
      month: currentMonth.value,
      year: currentYear.value,
      isCurrentMonth: true
    })
  }
  
  // Next month days
  const remainingDays = 42 - days.length
  for (let i = 1; i <= remainingDays; i++) {
    days.push({
      date: i,
      month: currentMonth.value === 11 ? 0 : currentMonth.value + 1,
      year: currentMonth.value === 11 ? currentYear.value + 1 : currentYear.value,
      isCurrentMonth: false
    })
  }
  
  return days
})

const previousMonth = () => {
  if (currentMonth.value === 0) {
    currentMonth.value = 11
    currentYear.value--
  } else {
    currentMonth.value--
  }
}

const nextMonth = () => {
  if (currentMonth.value === 11) {
    currentMonth.value = 0
    currentYear.value++
  } else {
    currentMonth.value++
  }
}

const selectDate = (day) => {
  const date = new Date(day.year, day.month, day.date)
  
  if (mode.value === 'single') {
    selectedDate.value = date
  } else {
    if (!rangeStart.value || (rangeStart.value && rangeEnd.value)) {
      rangeStart.value = date
      rangeEnd.value = null
    } else {
      if (date < rangeStart.value) {
        rangeEnd.value = rangeStart.value
        rangeStart.value = date
      } else {
        rangeEnd.value = date
      }
    }
  }
}

const getDayClasses = (day) => {
  const date = new Date(day.year, day.month, day.date)
  const today = new Date()
  today.setHours(0, 0, 0, 0)
  
  const isToday = date.getTime() === today.getTime()
  const isSelected = mode.value === 'single' && selectedDate.value && 
    date.getTime() === selectedDate.value.getTime()
  
  let isInRange = false
  if (mode.value === 'range' && rangeStart.value && rangeEnd.value) {
    isInRange = date >= rangeStart.value && date <= rangeEnd.value
  }
  
  const isRangeStart = mode.value === 'range' && rangeStart.value && 
    date.getTime() === rangeStart.value.getTime()
  const isRangeEnd = mode.value === 'range' && rangeEnd.value && 
    date.getTime() === rangeEnd.value.getTime()
  
  return {
    'bg-blue-500 text-white shadow-lg scale-105': isSelected || isRangeStart || isRangeEnd,
    'bg-blue-100': isInRange && !isRangeStart && !isRangeEnd,
    'ring-2 ring-blue-400': isToday && !isSelected && !isRangeStart && !isRangeEnd,
    'hover:bg-slate-100': day.isCurrentMonth && !isSelected && !isInRange,
    'hover:bg-slate-50': !day.isCurrentMonth
  }
}

const formatDate = (date) => {
  if (!date) return ''
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  })
}

const getEventsForDay = (day) => {
  const dateStr = `${day.year}-${String(day.month + 1).padStart(2, '0')}-${String(day.date).padStart(2, '0')}`
  return events.value.filter(event => event.date === dateStr)
}

const addEvent = () => {
  if (newEvent.value.date && newEvent.value.title) {
    events.value.push({ ...newEvent.value })
    newEvent.value = {
      date: '',
      title: '',
      color: 'bg-purple-100 text-purple-700'
    }
  }
}

const removeEvent = (index) => {
  events.value.splice(index, 1)
}

watch(mode, () => {
  selectedDate.value = null
  rangeStart.value = null
  rangeEnd.value = null
})
</script>