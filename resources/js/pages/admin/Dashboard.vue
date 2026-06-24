<script setup>
import { useRoute } from 'vue-router'
import { useStore } from "vuex";
import { Button } from "@/components/ui/button";

import { computed, onMounted, ref } from "vue";
import {
  CalendarIcon,
  CheckCircleIcon,
  Receipt,
  MoveRight,
  Ban,
  UsersRound,
  UserRoundCheck,
  UserRoundX,
  BookUser,
  X,
  Hourglass,
  Plus,
  Eye,
  Badge,
  CircleDollarSign,
  CircleChevronRight, 
  CirclePause,
  BarChart3,
  Plane
} from "lucide-vue-next";
import {
  FETCH_USER_SUMMARY,
  FETCH_BOOKING_DATA,
  FETCH_TOTAL_APPROVED_DEPOSIT,
  FETCH_DEPOSITS_DATA_AGENTS
} from "@/services/store/actions.type";
import moment from "moment";
import { useAuthStore } from "@/services/stores/auth";
import { useUserStore } from "@/services/stores/user";
import { formatAmount } from '@/lib/utils';
import FlightBookingChart from '@/components/common/FlightBookingChart.vue';
import depositChart from '@/components/common/depositChart.vue';
import UserChart from '@/components/common/UserChart.vue';

const store = useStore();
const userStore = useUserStore();
const authStore = useAuthStore();

const users = computed(() => userStore.users);
const bookings = computed(() => store.getters["flight/bookingData"]);
const usersSummary = computed(() => store.getters["user/usersSummary"]);
const agentDepositTotals = computed(() => store.getters["deposit/totalApprovedDeposit"]);
const agentsDepositData = computed(() => store.getters["deposit/depositDataWithAgents"]);
const user = computed(() => authStore.user);
const emit = defineEmits(['filter-change'])
const activeFilter = ref('all');

function fetchUsers() {
  userStore.fetchUsers({
    role: 'agent',
  });
}

function filterBookings(filter) {
  activeFilter.value = filter;
  emit('filter-change', filter);
}

function fetchBookings() {
  store.dispatch("flight/" + FETCH_BOOKING_DATA, {
    user_role: user.value.role,
    bookingFilter: activeFilter.value,
  });
}

const parseFlightData = (flightDataString) => {
  try {
    return JSON.parse(flightDataString);
  } catch (error) {
    console.error("Error parsing flight data:", error);
    return null;
  }
};

const bookingsData = computed(() => {
  return {
    total_count: bookings?.value?.total_count,
    total_ticketed: bookings?.value?.total_ticketed,
    total_canceled: bookings?.value?.total_canceled,
    total_booked: bookings?.value?.total_booked,
  };
});

function fetchTotalApprovedDepost() {
  store.dispatch("deposit/" + FETCH_TOTAL_APPROVED_DEPOSIT);
}

function fetchUserSummary() {
  store.dispatch("user/" + FETCH_USER_SUMMARY);
}

function fetchAgentsDeposits() {
  store.dispatch("deposit/" + FETCH_DEPOSITS_DATA_AGENTS);
}

onMounted(() => {
  fetchBookings();
  fetchUserSummary();
  fetchTotalApprovedDepost();
  fetchAgentsDeposits();
  fetchUsers();
});
</script>

<template>
  <div class="bg-gray-50 min-h-screen p-4">
    <!-- Booking Overview Section -->
    <div class="bg-white mb-6 rounded-xl  overflow-hidden transform transition-all duration-300 hover:shadow-xl">
      <div class="bg-primary p-4">
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <Plane class="h-8 w-8 text-white mr-3 transform -rotate-45" />
            <h2 class="text-2xl font-bold text-white">Booking Overview</h2>
          </div>
          <button @click="$router.push({ name: 'AdminCustomerBookings' })" 
            class="bg-white/20 backdrop-blur-sm text-white hover:bg-white/30 rounded-lg px-4 py-2 flex items-center transition-all duration-300">
            <Eye class="w-4 h-4 me-2" />
            View All
          </button>
        </div>
      </div>
      <div class="p-6">
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
          <!-- Total Bookings -->
          <div class="relative overflow-hidden bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg">
            <div class="absolute -right-6 -bottom-6 opacity-10">
              <CalendarIcon class="h-24 w-24 text-blue-500" />
            </div>
            <div class="z-10 flex items-center">
              <div class="bg-blue-500 p-3 rounded-lg mr-4">
                <CalendarIcon class="h-6 w-6 text-white" />
              </div>
              <div>
                <p class="text-sm font-medium text-blue-600">All Bookings</p>
                <p class="text-3xl font-bold text-blue-800">{{ bookings?.total_count || 0 }}</p>
              </div>
            </div>
          </div>

          <!-- Ticketed -->
          <div class="relative overflow-hidden bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg"
            :class="{ 'ring-2 ring-green-500': activeFilter === 'ticketed' }" 
            @click="filterBookings('ticketed')">
            <div class="absolute -right-6 -bottom-6 opacity-10">
              <CheckCircleIcon class="h-24 w-24 text-green-500" />
            </div>
            <div class="z-10 flex items-center">
              <div class="bg-green-500 p-3 rounded-lg mr-4">
                <CheckCircleIcon class="h-6 w-6 text-white" />
              </div>
              <div>
                <p class="text-sm font-medium text-green-600">Ticketed</p>
                <p class="text-3xl font-bold text-green-800">{{ bookings?.total_ticketed || 0 }}</p>
              </div>
            </div>
          </div>

          <!-- Total Canceled -->
          <div class="relative overflow-hidden bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg"
            :class="{ 'ring-2 ring-red-500': activeFilter === 'canceled' }" 
            @click="filterBookings('canceled')">
            <div class="absolute -right-6 -bottom-6 opacity-10">
              <Ban class="h-24 w-24 text-red-500" />
            </div>
            <div class="z-10 flex items-center">
              <div class="bg-red-500 p-3 rounded-lg mr-4">
                <Ban class="h-6 w-6 text-white" />
              </div>
              <div>
                <p class="text-sm font-medium text-red-600">Total Canceled</p>
                <p class="text-3xl font-bold text-red-800">{{ bookings?.total_canceled || 0 }}</p>
              </div>
            </div>
          </div>

          <!-- On Hold -->
          <div class="relative overflow-hidden bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg"
            :class="{ 'ring-2 ring-yellow-500': activeFilter === 'booked' }" 
            @click="filterBookings('booked')">
            <div class="absolute -right-6 -bottom-6 opacity-10">
              <CirclePause class="h-24 w-24 text-yellow-500" />
            </div>
            <div class="z-10 flex items-center">
              <div class="bg-yellow-500 p-3 rounded-lg mr-4">
                <CirclePause class="h-6 w-6 text-white" />
              </div>
              <div>
                <p class="text-sm font-medium text-yellow-600">On Hold</p>
                <p class="text-3xl font-bold text-yellow-800">{{ bookings?.total_booked || 0 }}</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Table and Chart Section -->
        <div class="grid grid-cols-5 gap-6">
          <!-- Table -->
          <div class="col-span-3 bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="max-h-96 overflow-auto">
              <table class="min-w-full">
                <thead class="bg-gray-50 sticky top-0">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Booking Date
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      PNR
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Customer Email
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Flight Route
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Action
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="booking in bookings?.bookings" :key="booking.id" class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-900">
                      {{ booking.created_at ? moment(booking.created_at).format('DD-MM-YYYY') : '' }}
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-xs font-medium text-gray-900">
                      
                       {{ booking.itinerary_reference ?? booking.itinerary_ref ?? booking.pnr }}
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                      {{ booking.agency_email }}
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                      <div v-if="booking?.booking_source == 1">
                                        <div v-for="(flight, flightIndex) in parseFlightData(booking?.flight_data)?.leg?.flights"
                                            :key="flightIndex" class="flex gap-2 justify-start items-center">
                                            <div class="flex gap-2">

                                                <p>
                                                    {{ flight?.from?.iata }}
                                                </p>
                                                <p>To</p>
                                                <p>

                                                    {{ flight?.to?.iata }}
                                                </p>
                                            </div>

                                        </div>
                                    </div>
                                    <div v-else>
                                        <div v-if="booking.flight_data" class="">
                                            <div v-for="(leg, legIndex
                                            ) in parseFlightData(booking.flight_data,).legs" :key="legIndex" class="">
                                                <div v-for="(stop, stopIndex
                                                ) in leg.stops" :key="stopIndex" class="">
                                                    <div class="flex gap-2 justify-start items-center">
                                                        <!-- Departure -->
                                                        <div class=" ">
                                                            <div class="">
                                                                {{
                                                                    stop.departure
                                                                        .airport
                                                                        .city_name
                                                                }}

                                                                ({{
                                                                    stop.departure
                                                                        .airport
                                                                        .iata_code
                                                                }})
                                                            </div>
                                                        </div>
                                                        To
                                                        <!--     Arrival -->
                                                        <div class="">
                                                            <div class="">
                                                                {{
                                                                    stop.arrival
                                                                        .airport
                                                                        .city_name
                                                                }}
                                                                ({{
                                                                    stop.arrival
                                                                        .airport
                                                                        .iata_code
                                                                }})
                                                            </div>
                                                        </div>
                                                    </div>

                                                </div>
                                            </div>
                                        </div>
                                    </div>
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap">
                      <span class="px-3 uppercase text-xs leading-5 py-1 rounded-full" :class="{
                        'bg-green-100 text-green-800': booking.status === 'ticketed',
                        'bg-red-100 text-red-800': booking.status === 'canceled',
                        'bg-yellow-100 text-yellow-800': booking.status === 'booked',
                      }">
                        {{ booking.status }}
                      </span>
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                      <button @click="
                                                $router.push({
                                                    name: 'AdminCustomerBookingsLayout',
                                                    query: {
                                                        booking_id:
                                                            booking.id,
                                                        pnr: booking?.itinerary_ref ?
                                                            booking?.itinerary_ref
                                                            : booking.pnr,
                                                        agent_id: booking.agent_id,
                                                        flight_mode: 'B2C',
                                                        booking_source:
                                                            booking?.booking_source,
                                                        flight_provider:
                                                            booking?.flight_provider,
                                                    },
                                                })
                                                " class="text-primary hover:text-purple-800">
                                                <CircleChevronRight class="w-5 h-5" />
                                            </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          
          <!-- Chart -->
          <div class="col-span-2 bg-white rounded-xl border border-gray-100   flex items-center justify-center">
            <FlightBookingChart :bookings="bookingsData" />
          </div>
        </div>
      </div>
    </div>

    <!-- Agents Overview Section -->
    <!-- <div class="bg-white mb-6 rounded-xl shadow-lg overflow-hidden transform transition-all duration-300 hover:shadow-xl">
      <div class="bg-primary p-4">
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <UsersRound class="h-8 w-8 text-white mr-3" />
            <h2 class="text-2xl font-bold text-white">Agents Overview</h2>
          </div>
          <button @click="$router.push({ name: 'Users' })" 
            class="bg-white/20 backdrop-blur-sm text-white hover:bg-white/30 rounded-lg px-4 py-2 flex items-center transition-all duration-300">
            <Eye class="w-4 h-4 me-2" />
            View All
          </button>
        </div>
      </div>
      <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
         
          <div class="relative overflow-hidden bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg">
            <div class="absolute -right-6 -bottom-6 opacity-10">
              <UsersRound class="h-24 w-24 text-blue-500" />
            </div>
            <div class="z-10 flex items-center">
              <div class="bg-blue-500 p-3 rounded-lg mr-4">
                <UsersRound class="h-6 w-6 text-white" />
              </div>
              <div>
                <p class="text-sm font-medium text-blue-600">Total Agents</p>
                <p class="text-3xl font-bold text-blue-800">{{ usersSummary?.total_agents || 0 }}</p>
              </div>
            </div>
          </div>

      
          <div class="relative overflow-hidden bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg">
            <div class="absolute -right-6 -bottom-6 opacity-10">
              <UserRoundCheck class="h-24 w-24 text-green-500" />
            </div>
            <div class="z-10 flex items-center">
              <div class="bg-green-500 p-3 rounded-lg mr-4">
                <UserRoundCheck class="h-6 w-6 text-white" />
              </div>
              <div>
                <p class="text-sm font-medium text-green-600">Approved Agents</p>
                <p class="text-3xl font-bold text-green-800">{{ usersSummary?.approved_agents || 0 }}</p>
              </div>
            </div>
          </div>

         
          <div class="relative overflow-hidden bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg">
            <div class="absolute -right-6 -bottom-6 opacity-10">
              <UserRoundX class="h-24 w-24 text-amber-500" />
            </div>
            <div class="z-10 flex items-center">
              <div class="bg-amber-500 p-3 rounded-lg mr-4">
                <UserRoundX class="h-6 w-6 text-white" />
              </div>
              <div>
                <p class="text-sm font-medium text-amber-600">Pending Agents</p>
                <p class="text-3xl font-bold text-amber-800">{{ usersSummary?.pending_agents || 0 }}</p>
              </div>
            </div>
          </div>

         
          <div class="relative overflow-hidden bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg">
            <div class="absolute -right-6 -bottom-6 opacity-10">
              <BookUser class="h-24 w-24 text-purple-500" />
            </div>
            <div class="z-10 flex items-center">
              <div class="bg-purple-500 p-3 rounded-lg mr-4">
                <BookUser class="h-6 w-6 text-white" />
              </div>
              <div>
                <p class="text-sm font-medium text-purple-600">Users</p>
                <p class="text-3xl font-bold text-purple-800">{{ usersSummary?.total_users || 0 }}</p>
              </div>
            </div>
          </div>
        </div>
        <div class="grid grid-cols-5 gap-6 pt-4">
         
          <div class="col-span-3 bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="max-h-96 overflow-auto">
              <table class="min-w-full">
                <thead class="bg-gray-50 sticky top-0">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Company
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Email
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                     Phone
                    </th>
                   
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Action
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="user in users?.data" :key="user.id" class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-900">
                      {{ user?.agent_data?.company_name }}
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-xs font-medium text-gray-900">
                      {{ user.email }}
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                      {{ user?.agent_data?.phone }}
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                      <Badge v-if="user.is_approved"
                      class="py-2 px-3 text-xs uppercase rounded-full bg-green-100 text-green-800 cursor-default">
                      Approved
                    </Badge>
                    <Badge v-else
                      class="py-2 px-3 rounded-full text-xs uppercase bg-destructive/20 hover:bg-destructive/20 text-red-800 border-destructive/50 cursor-default">
                      Pending
                    </Badge>
                    </td>
                   
                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                      <button @click="
                      $router.push({
                        name: 'UserDetails',
                        query: {
                          user_id:
                            user.id,
                        },
                      })
                      " class="text-primary hover:text-purple-800">
                      <CircleChevronRight class="w-5 h-5" />
                    </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          
        
          <div class="col-span-2 bg-white rounded-xl border border-gray-100   flex items-center justify-center">
            <UserChart :users="usersSummary" />
          </div>
      </div>
    </div>

   
    
    </div> -->
    <div class="bg-white mb-6 rounded-xl shadow-lg overflow-hidden transform transition-all duration-300 hover:shadow-xl">
      <div class="bg-primary p-4">
        <div class="flex justify-between items-center">
          <div class="flex items-center">
            <CircleDollarSign class="h-8 w-8 text-white mr-3" />
            <h2 class="text-2xl font-bold text-white">Deposits Overview</h2>
          </div>
          <button @click="$router.push({ name: 'Users' })" 
            class="bg-white/20 backdrop-blur-sm text-white hover:bg-white/30 rounded-lg px-4 py-2 flex items-center transition-all duration-300">
            <Eye class="w-4 h-4 me-2" />
            View All
          </button>
        </div>
      </div>

      <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
          <!-- Total Deposits -->
          <div class="relative overflow-hidden bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg">
            <div class="absolute -right-6 -bottom-6 opacity-10">
              <CircleDollarSign class="h-24 w-24 text-blue-500" />
            </div>
            <div class="z-10 flex items-center">
              <div class="bg-blue-500 p-3 rounded-lg mr-4">
                <CircleDollarSign class="h-6 w-6 text-white" />
              </div>
              <div>
                <p class="text-sm font-medium text-blue-600">Total Deposits</p>
                <p class="text-xl font-bold text-blue-800">{{ formatAmount(agentDepositTotals?.totalDeposits || 0) }}</p>
              </div>
            </div>
          </div>

          <!-- Approved Deposits -->
          <div class="relative overflow-hidden bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg">
            <div class="absolute -right-6 -bottom-6 opacity-10">
              <CheckCircleIcon class="h-24 w-24 text-green-500" />
            </div>
            <div class="z-10 flex items-center">
              <div class="bg-green-500 p-3 rounded-lg mr-4">
                <CheckCircleIcon class="h-6 w-6 text-white" />
              </div>
              <div>
                <p class="text-sm font-medium text-green-600">Approved Deposits</p>
                <p class="text-xl font-bold text-green-800">{{ formatAmount(agentDepositTotals?.totalApprovedDeposits) }}</p>
              </div>
            </div>
          </div>

          <!-- Pending Deposits -->
          <div class="relative overflow-hidden bg-gradient-to-br from-amber-50 to-amber-100 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg">
            <div class="absolute -right-6 -bottom-6 opacity-10">
              <Hourglass class="h-24 w-24 text-amber-500" />
            </div>
            <div class="z-10 flex items-center">
              <div class="bg-amber-500 p-3 rounded-lg mr-4">
                <Hourglass class="h-6 w-6 text-white" />
              </div>
              <div>
                <p class="text-sm font-medium text-amber-600">Pending Deposits</p>
                <p class="text-xl font-bold text-amber-800">{{ formatAmount(agentDepositTotals?.totalPendingDeposits) }}</p>
              </div>
            </div>
          </div>

          <!-- Rejected Deposits -->
          <div class="relative overflow-hidden bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-5 flex items-center transform transition-all duration-300 hover:translate-y-[-5px] hover:shadow-lg">
            <div class="absolute -right-6 -bottom-6 opacity-10">
              <X class="h-24 w-24 text-red-500" />
            </div>
            <div class="z-10 flex items-center">
              <div class="bg-red-500 p-3 rounded-lg mr-4">
                <X class="h-6 w-6 text-white" />
              </div>
              <div>
                <p class="text-sm font-medium text-red-600">Rejected Deposits</p>
                <p class="text-xl font-bold text-red-800">{{ formatAmount(agentDepositTotals?.totalRejectedDeposits) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="grid grid-cols-5 gap-6 p-6">
          <!-- Table -->
          <div class="col-span-3 bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="max-h-96 overflow-auto">
              <table class="min-w-full">
                <thead class="bg-gray-50 sticky top-0">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Date
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Name
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                     Amount
                    </th>
                    <!-- <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Flight Route
                    </th> -->
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Status
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Action
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <tr v-for="deposit in agentsDepositData?.deposits" :key="deposit?.id" class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-900">
                      {{ deposit?.date? moment(deposit?.date).format('DD-MM-YYYY'):'-' }}
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-xs font-medium text-gray-900">
                      {{ deposit?.agent?.agent_data?.company_name }}
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                      {{ deposit?.amount }}
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                        <Badge v-if="deposit?.deposit_status == 'pending'"
                      class="py-2 px-3 rounded-full text-xs uppercase bg-destructive/20 hover:bg-destructive/20 text-red-800 border-destructive/50 cursor-default">
                      Pending
                    </Badge>
                    <Badge v-else
                      class="py-2 px-3 rounded-full text-xs uppercase bg-green-100 text-green-800 border-destructive/50 cursor-default">
                      Approved
                    </Badge>
                    </td>
                    <!-- <td class="px-6 py-3 whitespace-nowrap">
                      <span class="px-3 uppercase text-xs leading-5 py-1 rounded-full" :class="{
                        'bg-green-100 text-green-800': booking.status === 'ticketed',
                        'bg-red-100 text-red-800': booking.status === 'canceled',
                        'bg-yellow-100 text-yellow-800': booking.status === 'booked',
                      }">
                        {{ booking.status }}
                      </span>
                    </td> -->
                    <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500">
                      <button @click="
                      $router.push({
                        name: 'DepositDetails',
                        query: {
                          deposit_id: deposit.id,
                        },
                      })
                      " class="text-primary hover:text-purple-800">
                      <CircleChevronRight class="w-5 h-5" />
                    </button>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
          
          <!-- Chart -->
          <div class="col-span-2 bg-white rounded-xl border border-gray-100   flex items-center justify-center">
            <depositChart  :deposits="agentDepositTotals" />
          </div>
      </div>

      </div>
  </div>
</template>

<style scoped>
.bg-sidebar {
  background-color: #f8f9fa;
}

/* 3D card effect */
.rounded-xl {
  box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.05), 0 4px 6px -2px rgba(0, 0, 0, 0.025);
}

/* Scrollbar styling */
::-webkit-scrollbar {
  width: 8px;
  height: 8px;
}

::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb {
  background: #d1d5db;
  border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
  background: #9ca3af;
}
</style>