<template>
    <div class="min-h-screen bg-gray-50 p-4 md:p-8 font-sans">

      <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-lg overflow-hidden">
        <!-- Header Section -->
        <div class="bg-emerald-600 text-white p-6 flex flex-col md:flex-row justify-between">
          <div class="flex-1">
            <div class="flex items-start">
              <div class="mr-4">
                <div class="bg-white p-2 rounded-lg">
                  <Airplane class="h-8 w-8 text-emerald-600" />
                </div>
              </div>
              <div>
                <h1 class="text-2xl font-bold"></h1>
                <p></p>
                <p class="text-sm mt-1">Office No 47 Toheed Mall Sargodha</p>
                <p class="text-sm">ultimabytes@gmail.com</p>
              </div>
            </div>
          </div>
          <div class="mt-4 md:mt-0 md:text-right">
            <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
              <span class="font-medium">Booking Reference:</span>
              <span class="font-bold"></span>
              
              <span class="font-medium">Travel Date:</span>
              <span class="font-bold">{{ pnrDetails?.startDate }}</span>
              
              <span class="font-medium">Booking Status:</span>
              <span class="font-bold">Booked</span>
              
              <span class="font-medium">Agency PNR:</span>
              <span class="font-bold">{{ pnrDetails?.bookingId }}</span>
              
              <span class="font-medium">Airline PNR:</span>
              <span class="font-bold"></span>
              
              <span class="font-medium">Ticket Number:</span>
              <span class="font-bold">Pending</span>
            </div>
          </div>
        </div>
  
        <!-- Flight Information Section -->
        <div class="p-6 border-b">
          <h2 class="text-lg font-bold mb-4 text-gray-800 uppercase tracking-wider">Flight Information</h2>
          
          <div class="space-y-8">
            <div v-for="(flight, index) in pnrDetails.flights" :key="index">
                <pre>{{ flight }}</pre>
            </div>
            <div class="flex flex-col md:flex-row">
              <div class="flex-1">
                <div class="flex flex-col">
                  <span class="text-sm text-gray-500">Departure:</span>
                  <span class="font-medium"></span>
                  <span class="font-medium"></span>
                  <span class="text-sm">Terminal: </span>
                </div>
              </div>
              
              <div class="flex flex-col items-center justify-center my-4 md:my-0">
                <div class="flex items-center">
                  <div class="w-16 h-0.5 bg-gray-300"></div>
                  <ArrowRight class="h-5 w-5 text-gray-400 mx-2" />
                  <div class="w-16 h-0.5 bg-gray-300"></div>
                </div>
                <div class="text-sm text-gray-500 mt-1"></div>
              </div>
              
              <div class="flex-1 md:text-right">
                <div class="flex flex-col">
                  <span class="text-sm text-gray-500">Arrival:</span>
                  <span class="font-medium"></span>
                  <span class="font-medium"></span>
                  <span class="text-sm">Terminal: N/A</span>
                </div>
              </div>
            </div>
            
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center bg-gray-50 p-4 rounded-lg">
              <div class="flex items-center mb-2 sm:mb-0">
                <img src="https://hebbkx1anhila5yf.public.blob.vercel-storage.com/Screenshot%202025-03-23%20at%206.47.12%E2%80%AFAM-V14XU47XorDTH7b38LjjvOyC4gNgXY.png" alt="Airline Logo" class="h-8 w-8 mr-3 object-contain" />
                <div>
                  <div class="font-medium"></div>
                  <div class="text-sm text-gray-500">
                    Flight: 
                  </div>
                </div>
              </div>
              <div class="text-sm">
                <span class="bg-emerald-100 text-emerald-800 px-2 py-1 rounded font-medium">
                  Class:
                </span>
              </div>
            </div>
          </div>
        </div>
  
        <!-- Passenger & Ticket Details -->
        <div class="p-6 border-b">
          <h2 class="text-lg font-bold mb-4 text-gray-800 uppercase tracking-wider">Passenger & Ticket Details</h2>
          <div v-for="(traveller, index) in pnrDetails.travelers" :key="index">
                <pre>{{ traveller }}</pre>
            </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead>
                <tr class="bg-gray-50">
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Traveller Name</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Gender</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Country</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Check-in Baggage</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cabin Baggage</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ticket No</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr>
                  <td class="px-4 py-3 whitespace-nowrap">
                    <div class="font-medium"></div>
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                  
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    Pakistan
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    30 kg
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    7 kg
                  </td>
                  <td class="px-4 py-3 whitespace-nowrap">
                    Pending
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
  
        <!-- Fare Breakdown -->
        <div class="p-6 border-b">
          <h2 class="text-lg font-bold mb-4 text-gray-800 uppercase tracking-wider">Fare Breakdown</h2>
          
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead>
                <tr class="bg-gray-50">
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pax Type</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Base Fare</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tax + Fees</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pax Count</th>
                  <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Cost</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr>
                  <td class="px-4 py-3 whitespace-nowrap"></td>
                  <td class="px-4 py-3 whitespace-nowrap">PKR 27,521.00</td>
                  <td class="px-4 py-3 whitespace-nowrap">PKR 10,459.00</td>
                  <td class="px-4 py-3 whitespace-nowrap">1</td>
                  <td class="px-4 py-3 whitespace-nowrap font-medium">PKR 37,980.00</td>
                </tr>
                <tr class="bg-gray-50">
                  <td colspan="4" class="px-4 py-3 text-right font-bold">TOTAL</td>
                  <td class="px-4 py-3 whitespace-nowrap font-bold">PKR 37,980.00</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
  
        <!-- Cancellation Policies -->
        <div class="p-6">
          <h2 class="text-lg font-bold mb-4 text-gray-800 uppercase tracking-wider">Cancellation & Amendment Policies</h2>
          
          <ul class="space-y-2 text-sm text-gray-700">
            <li class="flex items-start">
              <div class="flex-shrink-0 h-5 w-5 text-emerald-500 mr-2">
                <Circle class="h-2 w-2" />
              </div>
              <p>Cancellation of a ticket can be done either by emailing us or directly through the airline.</p>
            </li>
            <li class="flex items-start">
              <div class="flex-shrink-0 h-5 w-5 text-emerald-500 mr-2">
                <Circle class="h-2 w-2" />
              </div>
              <p>For ticket amendments, please send us an email along with your booking reference (e.g., Trip 20 - ) and the new travel details.</p>
            </li>
            <li class="flex items-start">
              <div class="flex-shrink-0 h-5 w-5 text-emerald-500 mr-2">
                <Circle class="h-2 w-2" />
              </div>
              <p>Cancellations or amendments must be made at least 24 hours prior to departure.</p>
            </li>
            <li class="flex items-start">
              <div class="flex-shrink-0 h-5 w-5 text-emerald-500 mr-2">
                <Circle class="h-2 w-2" />
              </div>
              <p>Requests made within 24 hours of departure should be directed to the airline.</p>
            </li>
            <li class="flex items-start">
              <div class="flex-shrink-0 h-5 w-5 text-emerald-500 mr-2">
                <Circle class="h-2 w-2" />
              </div>
              <p>Cancellation policy follows airline rules. Special and promotional fares are non-refundable.</p>
            </li>
            <li class="flex items-start">
              <div class="flex-shrink-0 h-5 w-5 text-emerald-500 mr-2">
                <Circle class="h-2 w-2" />
              </div>
              <p>If you have canceled your booking directly with the airline, please email us to process the applicable refund.</p>
            </li>
          </ul>
        </div>
  
        <!-- Footer -->
        <div class="bg-gray-50 p-4 text-center text-sm text-gray-500 border-t">
          <p>Thank you for booking with us. For any assistance, please contact our customer support.</p>
          <p class="mt-1">© {{ new Date().getFullYear() }} Travel Services. All rights reserved.</p>
        </div>
      </div>
    </div>
  </template>

<script setup>
import { useRoute, useRouter } from "vue-router";
import { computed, onMounted, ref, watch } from "vue";
import { useStore } from "vuex";

import {
   
    FETCH_PNR_DETAILS,
    


} from "@/services/store/actions.type";

const store = useStore();
const route = useRoute();

const pnr = route.query.pnr;
const pnrDetails = computed(() => store.getters["flight/pnrData"]);
const isLoading = ref(false);



function fetchPnrDetails() {
    if (!pnr) {
        error.value = "No PNR provided.";
        return;
    }
    store.dispatch("flight/" + FETCH_PNR_DETAILS, {
        pnr: pnr,
    });
}

onMounted(() => {
    fetchPnrDetails();
});

const booking = ref(pnrDetails);
const flight = computed(() => booking.value.flights[0]);
const travelerName = computed(() => {
  const traveler = booking.value.travelers[0];
  return `${traveler.givenName} ${traveler.surname}`;
});

// Helper functions


</script>

<style scoped>
/* Add any additional component-specific styles here */
</style>
