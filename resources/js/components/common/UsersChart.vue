<template>
    <div class=" bg-white w-full">
        <!-- <h2 class="text-xl font-bold mb-4">FlightBooking Analytics</h2> -->
        <div class="flex items-center">
            <!-- Chart -->
            <div class="w-1/2">
                <Doughnut :data="chartData" :options="chartOptions" />
            </div>
            <!-- Metrics -->
            <div class="w-1/2 pl-4">
                <div class="mb-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-blue-600 mr-2"></span>
                    <span>All Agents: {{ users?.total_agents || 0 }}</span>
                </div>
                <div class="mb-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-[#58d68d] mr-2"></span>
                    <span>Approved: {{ users?.approved_agents || 0 }}</span>
                </div>
                <div class="mb-2">
                    <span class="inline-block w-2 h-2 rounded-full bg-[#f7dc6f] mr-2"></span>
                    <span>Pending: {{ users?.pending_agents || 0 }}</span>
                </div>
                <!-- <div>
                    <span class="inline-block w-4 h-4 bg-red-500 mr-2"></span>
                    <span>Canceled: {{ bookings?.total_canceled || 0 }}</span>
                </div> -->
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
    users: {
        type: Object,
        default: () => ({
            total_agents: 0,
            approved_agents: 0,
            pending_agents: 0,
            total_booked: 0,
        }),
    },
})

// Compute chart data
const chartData = computed(() => ({
    labels: ['Approved', 'Pending'],
    datasets: [
        {
            data: [
                // props.users?.total_agents || 0,
                props.users?.approved_agents || 0,
                props.users?.pending_agents || 0,
            ],
            backgroundColor: ['#58d68d', '#f7dc6f'],
            borderWidth: 0,
            // Add rounded ends to the segments
        },
    ],
}))

// Compute the percentage for the center of the chart
const calculatePercentage = computed(() => {
    const total = props.users?.total_agents || 0
    const approved = props.users?.approved_agents || 0
    if (total === 0) return 0
    return Math.round((approved / total) * 100)
})

// Chart options
const chartOptions = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: false, // Hide default legend
        },
        tooltip: {
            enabled: true,
        },
        centerText: {
            display: true,
            text: `Total\n${calculatePercentage.value}%`,
        },
    },
}))

// Register custom plugin to display text in the center of the donut chart
onMounted(() => {
    ChartJS.register({
        id: 'centerText',
        beforeDraw(chart) {
            const { ctx, chartArea } = chart
            const centerX = (chartArea.left + chartArea.right) / 2
            const centerY = (chartArea.top + chartArea.bottom) / 2

            ctx.save()
            ctx.textAlign = 'center'
            ctx.textBaseline = 'middle'
            ctx.font = ' 14px Arial'
            ctx.fillStyle = '#000'

            const text = chart.options.plugins.centerText.text.split('\n')
            text.forEach((line, index) => {
                ctx.fillText(line, centerX, centerY + index * 25 - 10)
            })

            ctx.restore()
        },
    })
})
</script>

<style scoped>
.chart-container {
    position: relative;
    height: 200px;
    width: 200px;
}
</style>