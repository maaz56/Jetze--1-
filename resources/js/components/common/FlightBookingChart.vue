<template>
    <div class="relative bg-white w-full p-4 rounded-xl">
      <h3 class="text-lg font-semibold mb-4 text-gray-800">Booking Distribution</h3>
      <div class="flex flex-col md:flex-row items-center justify-between">
        <!-- Chart -->
        <div class="w-full md:w-1/2 relative">
          <div class="chart-container mx-auto">
            <Doughnut :data="chartData" :options="chartOptions" />
          </div>
          <!-- 3D effect overlay -->
          <div class="absolute inset-0 pointer-events-none rounded-full" 
               style="background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0) 70%);"></div>
        </div>
        
        <!-- Metrics with 3D styled cards -->
        <div class="w-full  md:w-1/2 pl-2 mt-6 md:mt-0">
          <div class="space-y-3 pl-8">
            <div class="w-40 flex items-center p-2 bg-gradient-to-r from-blue-50 to-blue-100 rounded-lg transform transition-all duration-300 hover:translate-y-[-2px] hover:shadow-md">
              <span class="flex items-center justify-center w-10 h-10 bg-blue-500 rounded-full mr-3">
                <span class="text-white font-bold">All</span>
              </span>
              <div>
                <p class="text-sm text-blue-700 font-medium">Total</p>
                <p class="text-xl font-bold text-blue-900">{{ bookings?.total_count || 0 }}</p>
              </div>
            </div>
            
            <div class=" w-40 flex items-center p-3 bg-gradient-to-r from-green-50 to-green-100 rounded-lg transform transition-all duration-300 hover:translate-y-[-2px] hover:shadow-md">
              <span class="flex items-center justify-center w-10 h-10 bg-green-500 rounded-full mr-3">
                <span class="text-white font-bold">✓</span>
              </span>
              <div>
                <p class="text-sm text-green-700 font-medium">Ticketed</p>
                <p class="text-xl font-bold text-green-900">{{ bookings?.total_ticketed || 0 }}</p>
              </div>
            </div>
            
            <div class="w-40 flex items-center p-3 bg-gradient-to-r from-yellow-50 to-yellow-100 rounded-lg transform transition-all duration-300 hover:translate-y-[-2px] hover:shadow-md">
              <span class="flex items-center justify-center w-10 h-10 bg-yellow-500 rounded-full mr-3">
                <span class="text-white font-bold">⏱</span>
              </span>
              <div>
                <p class="text-sm text-yellow-700 font-medium">On Hold</p>
                <p class="text-xl font-bold text-yellow-900">{{ bookings?.total_booked || 0 }}</p>
              </div>
            </div>
            
            <div class="w-40 flex items-center p-3 bg-gradient-to-r from-red-50 to-red-100 rounded-lg transform transition-all duration-300 hover:translate-y-[-2px] hover:shadow-md">
              <span class="flex items-center justify-center w-10 h-10 bg-red-500 rounded-full mr-3">
                <span class="text-white font-bold">✕</span>
              </span>
              <div>
                <p class="text-sm text-red-700 font-medium">Canceled</p>
                <p class="text-xl font-bold text-red-900">{{ bookings?.total_canceled || 0 }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </template>
  
  <script setup>
  import { computed, onMounted } from 'vue'
  import { Doughnut } from 'vue-chartjs'
  import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js'
  
  // Register Chart.js components
  ChartJS.register(ArcElement, Tooltip, Legend)
  
  // Define props
  const props = defineProps({
      bookings: {
          type: Object,
          default: () => ({
              total_count: 0,
              total_ticketed: 0,
              total_canceled: 0,
              total_booked: 0,
          }),
      },
  })
  
  const toNumber = (value) => {
      const numericValue = Number.parseFloat(value)
      return Number.isFinite(numericValue) ? numericValue : 0
  }

  const buildVisibleSlices = (values) => {
      const maxValue = Math.max(...values)
      const minVisibleValue = maxValue > 0 ? Math.max(maxValue * 0.04, 1) : 0

      return values.map((value) => value > 0 && value < minVisibleValue ? minVisibleValue : value)
  }

  const bookingValues = computed(() => [
      toNumber(props.bookings?.total_ticketed),
      toNumber(props.bookings?.total_booked),
      toNumber(props.bookings?.total_canceled),
  ])

  const chartData = computed(() => ({
      labels: ['Ticketed', 'On Hold', 'Canceled'],
      datasets: [
          {
              data: buildVisibleSlices(bookingValues.value),
              actualData: bookingValues.value,
              backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
              borderWidth: 0,
              borderRadius: 5,
              hoverOffset: 3,
              // Add 3D effect with shadow
              shadowOffsetX: 3,
              shadowOffsetY: 3,
              shadowBlur: 10,
              shadowColor: 'rgba(0, 0, 0, 0.2)',
          },
      ],
  }))
  
  // Compute the percentage for the center of the chart
  const calculatePercentage = computed(() => {
      const total = props.bookings?.total_count || 0
      const ticketed = props.bookings?.total_ticketed || 0
      if (total === 0) return 0
      return Math.round((ticketed / total) * 100)
  })
  
  // Chart options with enhanced 3D effect
  const chartOptions = computed(() => ({
      responsive: true,
      maintainAspectRatio: false,
      cutout: '70%',
      plugins: {
          legend: {
              display: true,
              position: 'bottom',
              labels: {
                  boxWidth: 10,
                  boxHeight: 10,
                  padding: 12,
                  usePointStyle: true,
              },
          },
          tooltip: {
              enabled: true,
              backgroundColor: 'rgba(255, 255, 255, 0.9)',
              titleColor: '#1f2937',
              bodyColor: '#1f2937',
              borderColor: '#e5e7eb',
              borderWidth: 1,
              cornerRadius: 8,
              boxPadding: 6,
              usePointStyle: true,
              callbacks: {
                  label: function(context) {
                      const label = context.label || '';
                      const value = context.dataset.actualData?.[context.dataIndex] || 0;
                      const total = props.bookings?.total_count || 0;
                      const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                      return `${label}: ${value} (${percentage}%)`;
                  }
              }
          },
          centerText: {
              display: true,
              text: `${calculatePercentage.value}%`,
              subText: 'Ticketed'
          },
      },
  }))
  
  // Register custom plugin to display text in the center of the donut chart
  onMounted(() => {
      ChartJS.register({
          id: 'centerText',
          beforeDraw(chart) {
              const { ctx, chartArea } = chart
              const centerTextOptions = chart.options.plugins.centerText || {}
              const centerX = (chartArea.left + chartArea.right) / 2
              const centerY = (chartArea.top + chartArea.bottom) / 2

              ctx.save()
              ctx.textAlign = 'center'
              ctx.textBaseline = 'middle'
              
              // Draw percentage
              ctx.font = centerTextOptions.font || 'bold 24px Inter, sans-serif'
              ctx.fillStyle = '#1f2937'
              ctx.fillText(centerTextOptions.text, centerX, centerY - 10)
              
              // Draw label
              ctx.font = centerTextOptions.subTextFont || '14px Inter, sans-serif'
              ctx.fillStyle = '#6b7280'
              ctx.fillText(centerTextOptions.subText, centerX, centerY + 15)
              
              ctx.restore()
          },
      })
  })
  </script>
  
  <style scoped>
  .chart-container {
      position: relative;
      height: 220px;
      width: 220px;
  }
  
  /* 3D effect for the chart container */
  .chart-container::before {
      content: '';
      position: absolute;
      inset: -5px;
      border-radius: 50%;
      background: linear-gradient(135deg, rgba(255,255,255,0.3), rgba(255,255,255,0));
      z-index: 1;
      pointer-events: none;
  }
  
  /* Add subtle animation to the cards */
  @keyframes float {
      0% { transform: translateY(0px); }
      50% { transform: translateY(-5px); }
      100% { transform: translateY(0px); }
  }
  
  .hover\:shadow-md:hover {
      animation: float 3s ease-in-out infinite;
  }
  </style>
