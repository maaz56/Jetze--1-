<template>
  <div class="w-full bg-white p-5 rounded border border-slate-200 flex flex-col justify-between">
    <div class="flex items-center justify-between border-b border-slate-100 pb-3 mb-3">
      <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">Deposits Overview</h3>
      <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">
        {{ formatAmount(normalizedDeposits.totalDeposits, 'AED') }}
      </span>
    </div>

    <!-- Chart on Upper Side, Details on Bottom -->
    <div class="flex flex-col items-center justify-center flex-1 py-1">
      <!-- Chart -->
      <div class="relative flex items-center justify-center my-auto py-2">
        <div class="chart-container">
          <Doughnut :data="chartData" :options="chartOptions" />
        </div>
      </div>

      <!-- Metrics / Details List (Bottom) -->
      <div class="w-full space-y-2 mt-4 pt-3 border-t border-slate-100">
        <!-- Approved -->
        <div class="flex items-center justify-between rounded border border-slate-100 bg-slate-50/60 px-3 py-2 text-xs transition hover:bg-slate-50">
          <div class="flex items-center gap-2">
            <span class="h-2.5 w-2.5 rounded-sm bg-emerald-500"></span>
            <span class="font-medium text-slate-700">Approved</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="font-bold text-slate-900">{{ formatAmount(normalizedDeposits.totalApprovedDeposits, 'AED') }}</span>
            <span class="text-[11px] text-slate-500 font-medium">({{ getPercentage(normalizedDeposits.totalApprovedDeposits) }}%)</span>
          </div>
        </div>

        <!-- Pending -->
        <div class="flex items-center justify-between rounded border border-slate-100 bg-slate-50/60 px-3 py-2 text-xs transition hover:bg-slate-50">
          <div class="flex items-center gap-2">
            <span class="h-2.5 w-2.5 rounded-sm bg-amber-500"></span>
            <span class="font-medium text-slate-700">Pending</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="font-bold text-slate-900">{{ formatAmount(normalizedDeposits.totalPendingDeposits, 'AED') }}</span>
            <span class="text-[11px] text-slate-500 font-medium">({{ getPercentage(normalizedDeposits.totalPendingDeposits) }}%)</span>
          </div>
        </div>

        <!-- Rejected -->
        <div class="flex items-center justify-between rounded border border-slate-100 bg-slate-50/60 px-3 py-2 text-xs transition hover:bg-slate-50">
          <div class="flex items-center gap-2">
            <span class="h-2.5 w-2.5 rounded-sm bg-rose-500"></span>
            <span class="font-medium text-slate-700">Rejected</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="font-bold text-slate-900">{{ formatAmount(normalizedDeposits.totalRejectedDeposits, 'AED') }}</span>
            <span class="text-[11px] text-slate-500 font-medium">({{ getPercentage(normalizedDeposits.totalRejectedDeposits) }}%)</span>
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
import { formatAmount } from '@/lib/utils'

ChartJS.register(ArcElement, Tooltip, Legend)

const props = defineProps({
  deposits: {
    type: Object,
    default: () => ({
      totalDeposits: 0,
      totalApprovedDeposits: 0,
      totalPendingDeposits: 0,
      totalRejectedDeposits: 0,
    }),
  },
})

const toAmount = (value) => {
  const rawAmount = value && typeof value === 'object' ? value.amount : value
  const amount = typeof rawAmount === 'string' ? Number(rawAmount.replace(/,/g, '')) : Number(rawAmount)
  return Number.isFinite(amount) ? amount : 0
}

const normalizedDeposits = computed(() => ({
  totalDeposits: toAmount(props.deposits?.totalDeposits),
  totalApprovedDeposits: toAmount(props.deposits?.totalApprovedDeposits),
  totalPendingDeposits: toAmount(props.deposits?.totalPendingDeposits),
  totalRejectedDeposits: toAmount(props.deposits?.totalRejectedDeposits),
}))

function getPercentage(value) {
  const total = normalizedDeposits.value.totalDeposits
  if (!total) return 0
  return Math.round(((value || 0) / total) * 100)
}

const chartData = computed(() => {
  const approved = normalizedDeposits.value.totalApprovedDeposits
  const pending = normalizedDeposits.value.totalPendingDeposits
  const rejected = normalizedDeposits.value.totalRejectedDeposits
  const isEmpty = approved === 0 && pending === 0 && rejected === 0

  return {
    labels: isEmpty ? ['No Data'] : ['Approved', 'Pending', 'Rejected'],
    datasets: [
      {
        data: isEmpty ? [1] : [approved, pending, rejected],
        backgroundColor: isEmpty ? ['#e2e8f0'] : ['#10b981', '#f59e0b', '#ef4444'],
        borderWidth: 2,
        borderColor: '#ffffff',
        hoverOffset: 4,
      },
    ],
  }
})

const calculatePercentage = computed(() => {
  const total = normalizedDeposits.value.totalDeposits
  const approved = normalizedDeposits.value.totalApprovedDeposits
  if (total === 0) return 0
  return Math.round((approved / total) * 100)
})

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '72%',
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      enabled: true,
      backgroundColor: '#0f172a',
      titleColor: '#f8fafc',
      bodyColor: '#f8fafc',
      borderColor: '#334155',
      borderWidth: 1,
      cornerRadius: 4,
      padding: 8,
      boxPadding: 4,
      callbacks: {
        label: function (context) {
          const label = context.label || ''
          const value = context.raw || 0
          const total = normalizedDeposits.value.totalDeposits
          const percentage = total > 0 ? Math.round((value / total) * 100) : 0
          return ` ${label}: ${formatAmount(value, 'AED')} (${percentage}%)`
        },
      },
    },
    centerText: {
      display: true,
      text: `${calculatePercentage.value}%`,
      subText: 'Approved',
    },
  },
}))

onMounted(() => {
  ChartJS.register({
    id: 'centerTextDeposit',
    beforeDraw(chart) {
      if (!chart.options.plugins.centerText?.display) return
      const { ctx, chartArea } = chart
      const centerX = (chartArea.left + chartArea.right) / 2
      const centerY = (chartArea.top + chartArea.bottom) / 2

      ctx.save()
      ctx.textAlign = 'center'
      ctx.textBaseline = 'middle'

      ctx.font = 'bold 20px Inter, system-ui, sans-serif'
      ctx.fillStyle = '#0f172a'
      ctx.fillText(chart.options.plugins.centerText.text, centerX, centerY - 8)

      ctx.font = '500 11px Inter, system-ui, sans-serif'
      ctx.fillStyle = '#64748b'
      ctx.fillText(chart.options.plugins.centerText.subText, centerX, centerY + 12)

      ctx.restore()
    },
  })
})
</script>

<style scoped>
.chart-container {
  position: relative;
  height: 180px;
  width: 180px;
}
</style>
