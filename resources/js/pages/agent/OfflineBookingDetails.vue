```vue
<template>
    <section>
        <div class="min-h-screen bg-gray-100">
            <!-- Loading Spinner -->
            <div v-if="isLoading" class="fixed inset-0 bg-gray-100 bg-opacity-75 flex items-center justify-center z-50">
                <Spinner />
            </div>
            <div v-else id="print-section">
                <div class="bg-white p-4 gap-2 flex justify-end mx-auto" id="topBar">
                    <button @click="isEmailDialogOpen = true"
                        class="px-4 py-2 text-sm font-medium text-white bg-gray-600 rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        <MailIcon class="h-5 w-5 inline-block mr-1" />
                        Email
                    </button>
                    <button @click="printDocument"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        <Printer class="h-5 w-5 inline-block mr-1" />
                        Print
                    </button>
                    <button @click="downloadPDF"
                        class="px-4 py-2 text-sm font-medium text-white bg-primary rounded-md hover:bg-primary/50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                        <Download class="h-5 w-5 inline-block mr-1" />
                        Download PDF
                    </button>
                </div>
                <div class="bg-white overflow-hidden print:shadow-none print:border print:border-gray-300">
                    <div class="relative grid grid-cols-8 justify-between h-48  bg-gradient-to-r from-primary/80 to-primary/95 items-center print:bg-white print:border-b-2 print:border-green-800">
                        <div class="col-span-1">
                            <img class="h-24 w-24 justify-start ms-7" :src="offlineBookingDetails?.user?.agent_data?.logo || '/logo.png'" alt="Logo" />
                        </div>
                        <div class="col-span-5 text-white ps-4 print:text-black border-l-2 print:border-gray-800">
                            <h1 class="text-2xl font-bold">{{ offlineBookingDetails?.user?.agent_data?.company_name || "Your Branding Text" }}</h1>
                            <p>{{ offlineBookingDetails?.user?.agent_data?.mobile || "Your Branding Text" }}</p>
                            <p class="line-clamp-2">{{ offlineBookingDetails?.user?.agent_data?.address || "Your Branding Text" }}</p>
                            <p>{{ offlineBookingDetails?.user?.agent_data?.company_email || "" }}</p>
                        </div>
                        <div class="col-span-2 flex flex-col ps-1 text-white text-xs border-l-2 print:text-black print:border-gray-800">
                            <div class="grid grid-cols-2 gap-4">
                                <p>Booking Reference:</p>
                                <p class="font-semibold">{{ offlineBookingDetails?.user?.agent_data?.agent_uid }}_ {{ offlineBookingDetails?.id + 1000 }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <p>Travel Date:</p>
                                <p class="font-semibold">{{ formatDate(offlineBookingDetails?.route?.dateRange?.start) }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <p>Booking Status:</p>
                                <p class="font-semibold capitalize">{{ offlineBookingDetails?.status }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <p>GDS PNR:</p>
                                <p class="font-semibold">{{ offlineBookingDetails?.booking_pnr || "N/A" }}</p>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <p>Airline PNR:</p>
                                <p class="font-semibold">N/A</p>
                            </div>
                        </div>
                    </div>

                    <!-- Flight Information -->
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-lg font-bold text-gray-800 mb-4">FLIGHT INFORMATION</h2>
                        <div class="space-y-3">
                            <div class="flex flex-col sm:flex-row justify-between gap-3 p-3 bg-gray-50 rounded-lg text-xs print:border-gray-400">
                                <div class="flex items-center gap-2 mt-2 sm:mt-0 border-t sm:border-t-0 pt-2 sm:pt-0 sm:border-r sm:pr-3">
                                    <div>
                                        <p class="font-semibold text-gray-800">Route: {{ offlineBookingDetails?.route?.origin }} → {{ offlineBookingDetails?.route?.destination }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between flex-1 w-full">
                                    <div class="flex-1 max-w-[40%]">
                                        <p class="text-gray-800 mt-0.5 font-semibold">{{ offlineBookingDetails?.route?.origin }}</p>
                                        <p class="text-gray-800 font-semibold">Departure: {{ formatDate(offlineBookingDetails?.route?.dateRange?.start) }}</p>
                                    </div>
                                    <div class="flex flex-col items-center px-1">
                                        <MoveRight class="h-5 w-5 text-gray-400" />
                                    </div>
                                    <div class="flex-1 max-w-[40%] text-right print:border-l-2 print:border-gray-300">
                                        <p class="text-gray-800 mt-0.5 font-semibold">{{ offlineBookingDetails?.route?.destination }}</p>
                                        <p class="text-gray-800 font-semibold">Arrival: {{ formatDate(offlineBookingDetails?.route?.dateRange?.start) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Passenger & Ticket Details -->
                    <div class="p-6 border-b border-gray-200 bg-white">
                        <h2 class="text-lg font-bold text-gray-800 mb-2">PASSENGER & TICKET DETAILS</h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-xs border-collapse border-2 border-gray-300 print:border-gray-400">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Traveller Name</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Type</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Gender</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Nationality</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Document Type</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Document No</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Expiry Date</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Issue Country</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">DOB</th>
                                        <th class="py-1.5 px-2 text-left font-medium text-gray-600">Ticket No</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(traveller, index) in offlineBookingDetails?.travellers" :key="index" class="hover:bg-gray-50 transition-colors">
                                        <td class="py-1.5 px-2 uppercase">{{ traveller.first_name }} {{ traveller.last_name }} <span class="text-gray-500 text-xs ml-1">({{ traveller.type }})</span></td>
                                        <td class="py-1.5 px-2 uppercase">{{ traveller.type }}</td>
                                        <td class="py-1.5 px-2 uppercase">{{ traveller.gender || 'N/A' }}</td>
                                        <td class="py-1.5 px-2 uppercase">{{ traveller.nationality || 'N/A' }}</td>
                                        <td class="py-1.5 px-2 uppercase">{{ traveller.document_type || 'N/A' }}</td>
                                        <td class="py-1.5 px-2 uppercase">{{ traveller.document_no || 'N/A' }}</td>
                                        <td class="py-1.5 px-2 uppercase">{{ traveller.expiry_date || 'N/A' }}</td>
                                        <td class="py-1.5 px-2 uppercase">{{ traveller.issue_country || 'N/A' }}</td>
                                        <td class="py-1.5 px-2 uppercase">{{ traveller.dob || 'N/A' }}</td>
                                        <td class="py-1.5 px-2">N/A</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <!-- Amount Section -->
                    <div class="p-6 border-b border-gray-200 bg-white flex justify-end">
                        <div class="flex flex-col items-end">
                            <span class="text-gray-500 text-xs uppercase tracking-wide">Total Amount</span>
                            <span class="text-3xl font-extrabold text-primary mt-1">
                                {{ formatAmount(offlineBookingDetails?.amount) }}



                            </span>
                        </div>
                    </div>
                    
                    <!-- Travel Information -->
                    <div class="w-full p-6">
                        <div class="bg-white border border-gray-200 rounded-lg p-6 grid gap-3 grid-cols-2">
                            <!-- Travel Documents -->
                            <div class="mb-8 col-span-1">
                                <div class="flex items-center mb-4">
                                    <div class="w-6 h-6 mr-3">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-6 h-6 text-primary">
                                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
                                            <polyline points="14,2 14,8 20,8" />
                                            <line x1="16" y1="13" x2="8" y2="13" />
                                            <line x1="16" y1="17" x2="8" y2="17" />
                                            <polyline points="10,9 9,9 8,9" />
                                        </svg>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-800">Travel Documents</h3>
                                </div>
                                <p class="text-sm text-gray-600 mb-4 leading-relaxed">
                                    Please be advised that you are required to produce various travel documents depending on your journey, destination, and purpose of travel. The documents required may include the following:
                                </p>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li class="flex items-start">
                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>A passport with a minimum validity of 6 months is required, with sufficient empty pages in the back.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>A valid visa for the country you are visiting. Also check if a transit visa is required if you are transiting between other countries during your journey.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>A valid National ID for GCC nationals travelling within the Arabian Gulf region; please check if the country you are visiting allows entry with your National ID card.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>Immigration authorities require airlines to provide advance passenger information prior to departure, so please ensure that your bookings have been updated prior to your travel.</span>
                                    </li>
                                    <li class="flex items-start">
                                        <span class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                        <span>Passengers from SAARC countries like India and Pakistan travelling to the GCC may require OK to board approval; please ensure your booking is updated with approval 24 hours prior to travel.</span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Don't Miss Your Flight -->
                            <div class="col-span-1">
                                <div class="mb-8">
                                    <div class="flex items-center mb-4">
                                        <div class="w-6 h-6 mr-3">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-6 h-6 text-primary">
                                                <path d="M17.8 19.2 16 11l3.5-3.5C21 6 21 4 19 4s-3 2-4.5 3.5L11 16l-7.2 1.8a1 1 0 0 0-.8.8 1 1 0 0 0 .8.8L11 16l3.5 4.5C16 22 18 22 18 20s-2-3-3.5-4.5L16 11l1.8 7.2Z" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-800">Don't Miss Your Flight</h3>
                                    </div>
                                    <ul class="space-y-2 text-sm text-gray-700">
                                        <li class="flex items-start">
                                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                            <span>Please make sure you're at the airport well ahead of your flight's departure time.</span>
                                        </li>
                                        <li class="flex items-start">
                                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                            <span>For international flights, it's typically advised to arrive at the airport at least <span class="font-semibold">4 hours</span> before departure, but this can vary depending on circumstances.</span>
                                        </li>
                                        <li class="flex items-start">
                                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full mt-2 mr-3 flex-shrink-0"></span>
                                            <span>For domestic flights, it's typically advised to arrive at the airport at least <span class="font-semibold">2 hours</span> before departure, but this can vary depending on circumstances.</span>
                                        </li>
                                    </ul>
                                </div>

                                <!-- Boarding Pass -->
                                <div class="mb-8">
                                    <div class="flex items-center mb-4">
                                        <div class="w-6 h-6 mr-3">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-6 h-6 text-primary">
                                                <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z" />
                                                <path d="M13 5v2" />
                                                <path d="M13 17v2" />
                                                <path d="M13 11v2" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-800">Boarding Pass</h3>
                                    </div>
                                    <p class="text-sm text-gray-700">
                                        If you'd like to get your boarding pass before heading to the airport, our team may be able to assist you.
                                    </p>
                                </div>

                                <!-- Extra Baggage -->
                                <div class="mb-8">
                                    <div class="flex items-center mb-4">
                                        <div class="w-6 h-6 mr-3">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-6 h-6 text-primary">
                                                <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z" />
                                                <path d="M3 6h18" />
                                                <path d="M16 10a4 4 0 0 1-8 0" />
                                            </svg>
                                        </div>
                                        <h3 class="text-lg font-semibold text-gray-800">Extra Baggage</h3>
                                    </div>
                                    <p class="text-sm text-gray-700">
                                        You can contact a travel advisor to add extra baggage, subject to the airline's availability and rates.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Support -->
                        <div class="p-6 text-center text-gray-700 text-sm bg-gray-50">
                            <p>Thank you for choosing {{ offlineBookingDetails?.user?.agent_data?.company_name }}</p>
                            <p>For assistance, please contact us at {{ offlineBookingDetails?.user?.agent_data?.mobile }} or {{ offlineBookingDetails?.user?.agent_data?.company_email }}</p>
                        </div>
                    </div>
                </div>

                <!-- Email Dialog -->
                <div v-if="isEmailDialogOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" @click.self="isEmailDialogOpen = false">
                    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                        <div class="flex items-start justify-between mb-4">
                            <h3 class="text-lg font-medium text-gray-900">Enter Email to Send</h3>
                            <button @click="isEmailDialogOpen = false" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <div class="mt-2">
                            <div>
                                <Label class="block text-sm font-medium text-gray-700 mb-1">Agency Email: {{ offlineBookingDetails?.user?.agent_data?.company_email }}</Label>
                                <span>Or enter new one</span>
                                <Input type="text" v-model="custEmail" class="flex-1 mt-2 rounded-md border-gray-300 shadow-sm focus:border-[#0056FF] focus:ring-[#0056FF]" placeholder="Enter email" />
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end space-x-3">
                            <button @click="isEmailDialogOpen = false" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                Cancel
                            </button>
                            <button @click="sendEmail" class="px-4 py-2 bg-green-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                Send Email
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import Spinner from "@/components/common/Spinner.vue";
import Input from "@/components/common/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import { MailIcon, MoveRight, Download, Printer } from "lucide-vue-next";
import { computed, ref, onMounted } from "vue";
import { useRoute } from "vue-router";
import { useStore } from "vuex";
import { FETCH_OFFLINE_BOOKING_DETAILS, SEND_EMAIL, SEND_OFFLINE_BOOKING_EMAIL } from "@/services/store/actions.type";
import html2pdf from "html2pdf.js";
import { formatAmount } from "@/lib/utils";

const store = useStore();
const route = useRoute();
const custEmail = ref(null);
const isEmailDialogOpen = ref(false);

const isLoading = computed(() => store.getters["offlineBooking/isLoading"]);
const offlineBookingDetails = computed(() => {
    const details = store.getters["offlineBooking/offlineBookingDetails"];
    if (details && typeof details.route === "string") {
        try {
            details.route = JSON.parse(details.route);
        } catch (error) {
            console.error("Error parsing route:", error);
            details.route = {};
        }
    }
    return details;
});

function formatDate(dateString) {
    if (!dateString) return "N/A";
    const date = new Date(dateString);
    return date.toLocaleDateString("en-US", {
        weekday: "short",
        year: "numeric",
        month: "short",
        day: "numeric",
    });
}

function sendEmail() {
    store.dispatch("offlineBooking/" + SEND_OFFLINE_BOOKING_EMAIL, {
        email: custEmail.value || offlineBookingDetails?.value?.user?.email,
        booking_id: offlineBookingDetails?.value?.id,
    });
    isEmailDialogOpen.value = false;
    custEmail.value = null;
}

function downloadPDF() {
    const element = document.getElementById("print-section");
    const options = {
        margin: 2,
        filename: `booking_${offlineBookingDetails?.value?.id}.pdf`,
        image: { type: "jpeg", quality: 0.98 },
        html2canvas: {
            scale: 2,
            logging: true,
            useCORS: true,
            windowWidth: element.scrollWidth,
            windowHeight: element.scrollHeight,
        },
        jsPDF: { unit: "mm", format: "a4", orientation: "landscape" },
    };
    html2pdf().from(element).set(options).save();
}

function printDocument() {
    window.print();
}

function getOfflineBookingDetails() {
    store.dispatch("offlineBooking/" + FETCH_OFFLINE_BOOKING_DETAILS, {
        bookingId: route.query.id,
    }).then(() => {
        isLoading.value = false;
    }).catch(error => {
        console.error("Error fetching offline booking details:", error);
        isLoading.value = false;
    });
}

onMounted(() => {
    getOfflineBookingDetails();
});
</script>

<style>
@media print {
    @page {
        margin: 0;
        size: auto;
    }
    html, body * {
        visibility: hidden;
        margin: 0;
        padding: 0;
        font-family: Arial, sans-serif;
        color: #111827;
        font-size: 9pt;
    }
    #print-section, #print-section * {
        visibility: visible;
    }
    #print-section {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        margin: 0;
        padding: 0;
    }
    .p-6 {
        padding: 10px !important;
    }
    .space-y-4>*+* {
        margin-top: 4px !important;
    }
    table {
        border-collapse: collapse;
        width: 100%;
        font-size: 8pt;
    }
    th, td {
        border: 1px solid #374151;
        padding: 4px;
        text-align: left;
    }
    th {
        background-color: #f3f4f6 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .bg-gray-50, .bg-gray-100 {
        background-color: #f9fafb !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    table, tr, .border-b-2 {
        page-break-inside: avoid;
    }
    .text-center.text-gray-700.text-sm {
        font-size: 7pt !important;
        padding: 5px !important;
    }
    #topBar {
        display: none !important;
    }
}
</style>
```