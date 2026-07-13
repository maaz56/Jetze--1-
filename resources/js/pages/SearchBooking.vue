<script setup>
import { onMounted, ref, computed } from 'vue';
import Nav from '@/components/shared/Nav.vue';
import { MapPin, Phone, Mail, Send, ChevronDown, Search, CircleChevronRight } from 'lucide-vue-next';
import store from '@/config/store';
import { FETCH_CUSTOMER_BOOKING } from '@/services/store/actions.type';
import moment from "moment";
import { debounce } from "lodash";
import { validate } from 'vee-validate';

const refCode = ref('');
const email = ref('');
const activeFilter = ref('all');
const currentPage = ref(1);
const perPage = ref(10);
const isLoading = ref(false);
const refCodeError = ref(false);
const emailError = ref(false);
const emit = defineEmits(['filter-change']);



const bookings = computed(() => store.getters["flight/allCustomerBooking"]);

function filterBookings(type) {
  activeFilter.value = type;
  console.log("Filter changed to:", type);
  emit('filter-change', type);
  debouncedFetchBookings();
}
const debouncedFetchBookings = debounce(findBooking, 300);


function findBooking() {
  // Logic to find booking using referralCode and email

  if (!validateInputs()) {
    return;
  }
  store.dispatch('flight/' + FETCH_CUSTOMER_BOOKING, {
    refCode: refCode.value,
    email: email.value,
    userRole: 'customer',
    bookingFilter: activeFilter.value,
    page: currentPage.value,
    perPage: perPage.value,
  });
}

function validateInputs() {
  if (!refCode.value) {
    refCodeError.value = true;
    return false;
  }
  if (!email.value) {
    emailError.value = true;
    return false;
  }
  refCodeError.value = false;
  emailError.value = false; 
  return true;
}

const parseFlightData = (flightDataString) => {
  try {
    return JSON.parse(flightDataString);
  } catch (error) {
    console.error("Error parsing flight data:", error);
    return null;
  }
};

const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    weekday: "short",
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};
const statusStyles = computed(() => ({
  ticketed: 'bg-green-100 text-green-800 uppercase',
  booked: 'bg-yellow-100 text-yellow-800 uppercase',
  canceled: 'bg-red-100 text-red-800 uppercase',
}));

// Dynamic contact information
const contactInfo = ref({
  company: 'apnaTcket Travels',
  license: 'Govt Lic No LHR-9245',
  address: 'ABCDE Street',
  phone: '+92 300 0000000',
  emails: [
    'info@apnaTckettravel.com',
    'tickets.apnaTcket@gmail.com',
    'visa.apnaTcket@gmail.com',
  ],
});

// Dynamic FAQs
const faqs = ref([
  {
    question: 'How can I book a flight with apnaTcket?',
    answer: 'You can book flights directly on our website by entering your travel details, selecting your preferred flight, and completing the payment process. Our support team is available 24/7 for assistance.',
    isOpen: false,
  },
  {
    question: 'What is your cancellation policy?',
    answer: 'Cancellation policies vary by airline. You can view the specific terms during booking or contact our support team for detailed assistance.',
    isOpen: false,
  },
  {
    question: 'Do you offer travel insurance?',
    answer: 'Yes, we provide optional travel insurance during the booking process to cover unexpected events. Contact us for more details.',
    isOpen: false,
  },
]);

// Toggle FAQ
const toggleFAQ = (index) => {
  faqs.value[index].isOpen = !faqs.value[index].isOpen;
};

onMounted(() => {
  // Any additional setup on mount
});
</script>

<template>
  <div class="min-h-screen bg-gray-50 rtl:text-right">
    <!-- Hero Section -->
    <section class="bg-[url('/public/assets/Saudi2.jpg')] relative bg-primary text-white py-24">
      <div class="container mx-auto px-4 text-center">
        <h1 class="text-4xl md:text-5xl font-bold mb-4 z-50 relative">
          Retrieve Booking
        </h1>
        <p class="text-lg md:text-xl max-w-2xl mx-auto mb-8 z-50 relative">
          Enter your booking reference and email to view or manage your trip
        </p>

        <form class="bg-white rounded-xl p-8 max-w-2xl mx-auto shadow-lg border z-30 relative">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <input v-model="refCode" type="text" placeholder="Booking Reference / PNR" class="w-full px-5 py-3 border border-gray-300 rounded-md text-gray-800 
                       focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent 
                       text-base uppercase tracking-wider placeholder:tracking-normal" />
                       <label class="text-xs text-red-600" v-if="refCodeError">*Please enter PNR.</label>
                       
            </div>

            <div>
              <input v-model="email" type="email" placeholder="Email Address" class="w-full px-5 py-3 border border-gray-300 rounded-md text-gray-800 
                       focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent 
                       text-base" />
                       <label class="text-xs text-red-600" v-if="emailError">*Please enter your Email.</label>
            </div>

            <div class="flex items-center">
              <button @click.prevent="findBooking" class="w-full bg-primary hover:bg-primary/90 text-white font-semibold 
                       py-3 px-6 rounded-md flex items-center justify-center gap-3 
                       transition-all duration-200 shadow-md hover:shadow-lg">
                <Search class="w-5 h-5" />
                Search
              </button>
            </div>
          </div>
        </form>
      </div>

      <div class="absolute inset-0 bg-gradient-to-b from-black/40 via-transparent to-transparent"></div>
    </section>

    <!-- Booking Results Section -->
    <section class="py-16">
      <div class="container mx-auto px-4">
        <div class="bg-white rounded-2xl  overflow-hidden">
          <!-- Header -->
          <!-- <div class="bg-gradient-to-r from-primary/5 to-primary/10 px-8 py-6 border-b">
            <h2 class="text-2xl font-bold text-gray-800">Bookings</h2>
          </div> -->

          <!-- Loading Overlay -->
          <div v-if="isLoading" class="absolute inset-0 bg-white/70 flex items-center justify-center z-10">
            <div class="flex flex-col items-center">
              <svg class="animate-spin h-12 w-12 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none"
                viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                </path>
              </svg>
              <p class="mt-4 text-gray-600 font-medium">Loading your bookings...</p>
            </div>
          </div>

          <!-- Table -->
          <div class="overflow-x-auto">
            <table v-if="bookings?.bookings?.length > 0" class="w-full text-sm text-left">
              <thead class="text-xs uppercase bg-gray-100 text-gray-700">
                <tr>
                  <th class="px-6 py-4">Booking Date</th>
                  <th class="px-6 py-4">PNR</th>
                  <th class="px-6 py-4">Route</th>
                  <th class="px-6 py-4">Passenger</th>
                  <th class="px-6 py-4">Travel Date</th>
                  <th class="px-6 py-4">Status</th>
                  <th class="px-6 py-4 text-center">Action</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="booking in bookings.bookings" :key="booking.id" class="hover:bg-gray-50 transition">
                  <td class="px-6 py-4 text-gray-900">
                    {{ moment(booking.created_at).format("DD MMM YYYY, hh:mm A") }}
                  </td>
                  <td class="px-6 py-4 font-medium text-primary">
                    {{ booking?.itinerary_ref || booking?.pnr || '—' }}
                  </td>
                  <td class="px-6 py-4">
                    <div class="text-sm">
                      <template v-if="booking.booking_source == 1">
                        <div v-for="(flight, i) in parseFlightData(booking.flight_data)?.leg?.flights || []" :key="i">
                          {{ flight.from.iata }} to {{ flight.to.iata }}
                        </div>
                      </template>
                      <template v-else>
                        <div v-for="(leg, i) in parseFlightData(booking.flight_data)?.legs || []" :key="i">
                          <div v-for="(stop, j) in leg.stops || []" :key="j">
                            {{ stop.departure?.airport?.iata_code }} to {{ stop.arrival?.airport?.iata_code }}
                          </div>
                        </div>
                      </template>
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <div v-for="p in booking.pessangers || []" :key="p.id" class="text-sm">
                      {{ p.title }} {{ p.first_name }} {{ p.last_name }}
                    </div>
                  </td>
                  <td class="px-6 py-4">
                    <template v-if="booking.booking_source == 1">
                      <div v-for="(f, i) in parseFlightData(booking.flight_data)?.leg?.flights || []" :key="i">
                        {{ formatDate(f.departure_at) }}
                      </div>
                    </template>
                    <template v-else>
                      <div v-for="(d, i) in parseFlightData(booking.flight_data)?.dates || []" :key="i">
                        {{ formatDate(d.departureDate) }}
                      </div>
                    </template>
                  </td>
                  <td class="px-6 py-4">
                    <span class="inline-flex px-3 py-1 rounded-full text-xs font-medium" :class="{
                      'bg-green-100 text-green-800': booking.status === 'confirmed',
                      'bg-yellow-100 text-yellow-800': booking.status === 'pending',
                      'bg-red-100 text-red-800': booking.status === 'cancelled',
                      'bg-gray-100 text-gray-800': !['confirmed', 'pending', 'cancelled'].includes(booking.status)
                    }">
                      {{ booking.status?.toUpperCase() || 'UNKNOWN' }}{{ booking.is_manually_issued ? ' (manually issued)' : '' }}
                    </span>
                  </td>
                  <td class="px-6 py-4 text-center">
                    <button @click="$router.push({
                      name: 'BookingsDetails',
                      query: {
                        booking_id: booking.id,
                        flight_mode: 'B2C',
                        flight_provider: parseFlightData(booking.flight_data)?.provider?.name || '',
                        pnr: booking.itinerary_ref || booking.pnr,
                        agent_id: booking.agent_id,
                        booking_source: booking.booking_source
                      }
                    })" class="text-primary hover:text-primary/80 transition">
                      <CircleChevronRight class="w-6 h-6" />
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>

            <!-- No Results -->

          </div>

          <!-- Pagination (if needed) -->
          <div v-if="bookings?.bookings?.length > 0"
            class="bg-gray-50 px-6 py-4 flex justify-between items-center text-sm">
            <span class="text-gray-600">
              Showing {{ (currentPage - 1) * perPage + 1 }} to {{ Math.min(currentPage * perPage, bookings.total_count)
              }}
              of {{ bookings.total_count }} bookings
            </span>
            <div class="flex gap-2">
              <button @click="changePage(currentPage - 1)" :disabled="currentPage === 1"
                class="px-4 py-2 border rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                Previous
              </button>
              <button @click="changePage(currentPage + 1)" :disabled="currentPage * perPage >= bookings.total_count"
                class="px-4 py-2 border rounded-lg disabled:opacity-50 disabled:cursor-not-allowed">
                Next
              </button>
            </div>
          </div>

          <section class="px-6 py-16 bg-gray-100/60">
            <div class="mx-auto max-w-7xl">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-10 lg:gap-16 text-center">

                <!-- 24/7 Customer Support -->
                <div class="flex flex-col items-center group">
                  <div
                    class="mb-6 p-8 bg-white rounded-3xl shadow-lg group-hover:shadow-2xl transition-all duration-300">
                    <img src="/public/customer_support.png" alt="24/7 Customer Support"
                      class="w-28 h-28 md:w-32 md:h-32 object-contain mx-auto" />
                  </div>
                  <h3 class="text-xl md:text-2xl font-bold text-gray-800 mt-4">
                    24/7 Customer Support
                  </h3>
                  <p class="text-gray-600 mt-2 text-sm md:text-base">
                    We're always here to help, any time of day or night
                  </p>
                </div>

                <!-- Refunds within 48 hours -->
                <div class="flex flex-col items-center group">
                  <div
                    class="mb-6 p-8 bg-white rounded-3xl shadow-lg group-hover:shadow-2xl transition-all duration-300">
                    <img src="/public/refund.png" alt="Fast Refunds"
                      class="w-28 h-28 md:w-32 md:h-32 object-contain mx-auto" />
                  </div>
                  <h3 class="text-xl md:text-2xl font-bold text-gray-800 mt-4">
                    Refunds within 48 hours
                  </h3>
                  <p class="text-gray-600 mt-2 text-sm md:text-base">
                    Quick and hassle-free refund processing guaranteed
                  </p>
                </div>

                <!-- Secure Transaction -->
                <div class="flex flex-col items-center group">
                  <div
                    class="mb-6 p-8 bg-white rounded-3xl shadow-lg group-hover:shadow-2xl transition-all duration-300">
                    <img src="/public/secure.png" alt="Secure Transaction"
                      class="w-28 h-28 md:w-32 md:h-32 object-contain mx-auto" />
                  </div>
                  <h3 class="text-xl md:text-2xl font-bold text-gray-800 mt-4">
                    Secure Transaction Guaranteed
                  </h3>
                  <p class="text-gray-600 mt-2 text-sm md:text-base">
                    Your payments are 100% safe and encrypted
                  </p>
                </div>

              </div>
            </div>
          </section>
        </div>
      </div>
    </section>
  </div>
</template>
