<script setup>
import Spinner from "@/components/common/Spinner.vue";
import Button from "@/components/ui/button/Button.vue";
import CountryDropdown from "@/components/common/CountryDropdown.vue";
import Calender from '@/components/common/Calender.vue';
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import {
    CONFIRM_BOOKING,
    FETCH_AGENT_DATA,
    FETCH_AGENT_LEDGER,
    FETCH_ANCILLARIES,
    FETCH_BOOKING_STATS_SETINGS,
    FETCH_COUNTRIES,
    PATCH_ANCILLARIES,
    SAVE_BOOKING,
    SAVE_BOOKING_DATA,
    SEND_PAYMENT_REQUEST,
    SEND_PRICE_REQUEST,
    SEND_SOOPER_QOUTE
} from "@/services/store/actions.type";
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { ClockIcon, PlusCircle, SquareCheckBig, SquareX, Upload, XCircle, XIcon } from "lucide-vue-next";

import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command";
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from "@/components/ui/popover";
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue
} from "@/components/ui/select";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import moment from "moment";

import { useAuthStore } from "@/services/stores/auth";
import { computed, nextTick, onMounted, reactive, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { useStore } from "vuex";

import Badge from "@/components/ui/badge/Badge.vue";
import { calculateLayoverDetails, cn, formatAmount, formatDate, getAdjustedDateTime } from "@/lib/utils";
import { calculateFinalPrice } from "@/lib/utils.js";
import { Check, CheckCircle, ChevronsUpDown } from "lucide-vue-next";

import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { FETCH_TRAVELLERS } from "@/services/store/actions.type";
import { loadStripe } from '@stripe/stripe-js';
import { useRouter } from "vue-router";
import { toast } from "vue3-toastify";
import Tesseract from 'tesseract.js';


const route = useRoute();
const store = useStore();
const authStore = useAuthStore();

const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const agentData = computed(() => store.getters["user/agentData"]);
const router = useRouter();
const termsAccepted = ref(false);
const validationErrors = ref([]);

const showPreview = ref(false);
const isOpen = ref(false);
const currentState = ref(0);
const pnrData = ref(null);
const amount = ref(0);
const paymentDialog = ref(false);
const passengerCount = ref(0);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const isConfirmDialogOpen = ref(false);
const isLowBalanceDialogOpen = ref(false);
const isDisabled = ref(true);
const totalTicketPrice = ref(0);
const showPaymentDialog = ref(false);
const selectedFares = ref([]);
const selectedExtraData = ref([]);
const travellerIndex = ref();
const countdown = ref(null);
const showDialog = ref(false);
const timerInterval = ref();


// const flight = computed(() => store.getters["flight/flight"]);
const flight = ref(null);
const bookingStatusSetting = computed(() => store.getters["settings/bookingStatusSetting"]);
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const quote = computed(() => store.getters["flight/priceResponse"]);
const apiErrors = computed(() => store.getters["flight/apiErrors"]);
const qouteError = computed(() => store.getters["flight/qouteError"]);
const isConfirmed = computed(() => store.getters["flight/isConfirmed"]);
const ancillaries = computed(() => store.getters["flight/ancillaries"]);
const todayDate = computed(() => {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
});

const processing = ref(false);
const stripe = ref(null);
const paymentError = ref('');
const elements = ref(null);
const cardElement = ref(null);
const originalImage = ref(null);
const croppedImage = ref(null);
const progress = ref(null);
const passportData = ref(null);
const errorMessage = ref(null);

const clientSecret = ref('');
const publicKey = ref(import.meta.env.VITE_STRIPE_KEY);
const paymentIntentId = ref('');

const agentMargin = parseFloat(route.query.price_margin);
const countries = computed(() => {
    const countries = store.getters["country/countries"] || [];
    return countries.map((country) => ({
        value: country.name,
        label: country.name,
        code: country.code,
    }));
});

function togglePreview() {
      if (!validateForm()) {
            globalError.value = "Please fix the errors in the form before submitting";
            window.scrollTo({ top: 0, behavior: "smooth" });
            return;
        }
  showPreview.value = !showPreview.value;
}
function fetchAgentLedger() {
    if (user_id.value) {
        try {
            store.dispatch(`ledger/${FETCH_AGENT_LEDGER}`, {
                userId: user_id.value,
            });
            loading.value = false;
        } catch (err) {
            error.value = "Failed to load user data. Please try again.";
            loading.value = false;
        }
    } else {
        error.value = "No user ID provided.";
        loading.value = false;
    }
}
const totalPrice = computed(() => {
    const basePrice = parseFloat(flight.value?.pricing?.totalPrice || "0");
    const marginAmount = parseFloat(
        agentData.value?.agent_data?.margin_amount || "0",
    );

    //console.log("Base Price:", basePrice); // Debug log
    //console.log("Margin Amount:", marginAmount); // Debug log

    return basePrice + marginAmount;
});

const isLoading = computed(() => store.getters["flight/isLoading"]);
const loading = ref(true);
const error = ref(null);

const isOpenCountryDropdown = ref(false);
const mainContact = ref({
    email: "",
    phone: "",
    country: "",
});

const agencyContact = computed(() => ({
    email: agentData?.value?.email,
    phone: agentData?.value?.agent_data?.mobile,
}));

const travellers = ref([]);
const isSubmitting = ref(false);
const isPaymentMethodsVisible = ref(false);
const scanning = ref(false);

const isTermsDialogOpen = ref(false);
const paymentMethod = ref();
const globalError = ref("");
const errors = reactive({
    mainContact: {
        email: "",
        phone: "",
        country: "",
    },
    travellers: [],
});


const initializeTravellers = () => {
    // Check if we have detailed passenger info

    // Original implementation - create travelers from passenger info
   // Parse query params
const adults = parseInt(route.query.adults || 0);
const childs = parseInt(route.query.childs || 0);
const infants = parseInt(route.query.infants || 0);

// Helper to create a traveler object
const createTraveler = (type) => ({
    type,
    title: "",
    firstName: "",
    lastName: "",
    nationality: "",
    documentType: "passport",
    documentNo: "",
    expiryDate: "",
    issueCountry: "",
    dob: "",
    isOpenCountryDropdown: false,
    isOpenIssueCountryDropdown: false,
    gender: "",
    showAncillaries: false,
});

// Generate travelers from query parameters
travellers.value = [
    ...Array(adults).fill().map(() => createTraveler("ADT")),
    ...Array(childs).fill().map(() => createTraveler("CNN")),
    ...Array(infants).fill().map(() => createTraveler("INF")),
];


    // Initialize errors array for all travelers
    errors.travellers = travellers.value.map(() => ({
        title: "",
        firstName: "",
        lastName: "",
        nationality: "",
        documentType: "",
        documentNo: "",
        expiryDate: "",
        issueCountry: "",
        dob: "",
        gender: "",
    }));
};
// const initializeTravellers = () => {
//     travellers.value = flight.value.passengerInfo.reduce((acc, passenger) => {
//         // Create array of length passengerNumber for each type
//         const passengerArray = Array(passenger.passengerNumber)
//             .fill()
//             .map(() => ({
//                 type: passenger.passengerType,
//                 // Add any additional fields needed for each passenger
//                 title: "",
//                 firstName: "",
//                 lastName: "",
//                 nationality: "",
//                 documentType: "",
//                 documentNo: "",
//                 expiryDate: "",
//                 issueCountry: "",
//                 dob: "",
//                 gender: "",
//             }));

//         return [...acc, ...passengerArray];
//     }, []);
//     errors.travellers = travellers.value.map(() => ({
//         title: "",
//         firstName: "",
//         lastName: "",
//         nationality: "",
//         documentType: "",
//         documentNo: "",
//         expiryDate: "",
//         issueCountry: "",
//         dob: "",
//         gender: "",
//     }));
// };
// Helper function to get nested error
const getErrorPath = (path) => {
    const parts = path.split(".");
    let current = errors;

    for (const part of parts) {
        if (current[part] === undefined) {
            return "";
        }
        current = current[part];
    }

    return current;
};

// Validation functions
const validateEmail = (email) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
};

const validatePhone = (phone) => {
    // Basic phone validation - adjust as needed for your requirements
    const phoneRegex = /^\+?[0-9\s\-()]{8,20}$/;
    return phoneRegex.test(phone);
};

const validateDate = (date) => {
    if (!date) return false;

    const selectedDate = new Date(date);
    return !isNaN(selectedDate.getTime());
};

const validateExpiryDate = (date) => {
    if (!validateDate(date)) return false;

    const today = new Date();
    const expiryDate = new Date(date);

    // Document should not be expired
    return expiryDate > today;
};

const validateDob = (dob, type) => {
    if (!validateDate(dob)) return false;

    //console.log("Validating DOB:", dob, "for type:", type, "birthdate", new Date(dob), "today", new Date());
    const today = new Date();
    const birthDate = new Date(dob);
    const age = today.getFullYear() - birthDate.getFullYear();

    // Check if birthday has occurred this year
    const hasBirthdayOccurred =
        today.getMonth() > birthDate.getMonth() ||
        (today.getMonth() === birthDate.getMonth() &&
            today.getDate() >= birthDate.getDate());

    const adjustedAge = hasBirthdayOccurred ? age : age - 1;

    // Validate age based on passenger type
    switch (type) {
        case "adult": // Adult
            return adjustedAge >= 12;
        case "child": // Child
            return adjustedAge >= 2 && adjustedAge < 12;
        case "infant": // Infant
            return adjustedAge < 2;
        default:
            return true;
    }
};
// Main validation function
const validateForm = () => {
    let isValid = true;

    // Reset all errors
    globalError.value = "";
    errors.mainContact.email = "";
    errors.mainContact.phone = "";
    errors.mainContact.country = "";

    errors.travellers.forEach((traveller) => {
        Object.keys(traveller).forEach((key) => {
            traveller[key] = "";
        });
    });
    // Validate main contact
    if (!mainContact.value.email) {
        errors.mainContact.email = "Email is required";
        isValid = false;
    } else if (!validateEmail(mainContact.value.email)) {
        errors.mainContact.email = "Please enter a valid email address";
        isValid = false;
    }

    if (!mainContact.value.phone) {
        errors.mainContact.phone = "Phone number is required";
        isValid = false;
    } else if (!validatePhone(mainContact.value.phone)) {
        errors.mainContact.phone = "Please enter a valid phone number";
        isValid = false;
    }

    if (!mainContact.value.country) {
        errors.mainContact.country = "Country is required";
        isValid = false;
    }

    // Validate travellers
    travellers.value.forEach((traveller, index) => {
        if (!traveller.title) {
            fetchBookingStatus
            errors.travellers[index].title = "Title is required";
            isValid = false;
        }

        if (!traveller.firstName) {
            errors.travellers[index].firstName = "First name is required";
            isValid = false;
        }

        if (!traveller.lastName) {
            errors.travellers[index].lastName = "Last name is required";
            isValid = false;
        }

        if (!traveller.nationality) {
            errors.travellers[index].nationality = "Nationality is required";
            isValid = false;
        }

        if (!traveller.gender) {
            errors.travellers[index].gender = "Gender is required";
            isValid = false;
        }

        if (!traveller.dob) {
            errors.travellers[index].dob = "Date of birth is required";
            isValid = false;
        } else if (!validateDob(traveller.dob, traveller.type)) {
            errors.travellers[index].dob =
                `Invalid date of birth for ${traveller.type}`;
            isValid = false;
        }

        if (!traveller.documentType) {
            errors.travellers[index].documentType = "Document type is required";
            isValid = false;
        }

        if (!traveller.documentNo) {
            errors.travellers[index].documentNo = "Document number is required";
            isValid = false;
        }

        if (!traveller.expiryDate) {
            errors.travellers[index].expiryDate = "Expiry date is required";
            isValid = false;
        } else if (!validateExpiryDate(traveller.expiryDate)) {
            errors.travellers[index].expiryDate =
                "Document must not be expired";
            isValid = false;
        }

        if (!traveller.issueCountry) {
            errors.travellers[index].issueCountry = "Issue country is required";
            isValid = false;
        }
    });

    return isValid;
};

function fetchBookingStatus() {
    store.dispatch("settings/" + FETCH_BOOKING_STATS_SETINGS);
}
function handleConfirmDialogOpen() {
console.log("called");
    console.log("agenledger", agentLedger?.value.balance);
    //console.log("totalTicketPrice", amount?.value);
    if (agentLedger?.value.balance < amount?.value || agentLedger?.value.balance == 0) {
        isLowBalanceDialogOpen.value = true;
        toast
        return;
    }
    if (!validateForm()) {
        globalError.value =
            "Please fix the errors in the form before submitting";
        // Scroll to the top to show the global error
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
    }




    isConfirmDialogOpen.value = true;
}

function patchAncillaryCharges() {
    // store.dispatch("flight/" + PATCH_ANCILLARIES, {
    //     ref_id: quote?.value?.ref_id,
    //     extraCharges: extraCharges.value
    // })


}

async function confirmBooking() {
    error.value = '';
    isConfirmDialogOpen.value = false;

    await store.dispatch("flight/" + CONFIRM_BOOKING, {
        pnr: bookingDetails?.value[0]?.pnr,
        bookingId: bookingDetails?.value?.id,
        booking_uuid: pnrData.value?.data?.uuid,
        booking_status: paymentMethod.value === 'hold' ? 'booked' : paymentMethod.value === 'pay' ? 'ticketed' : paymentMethod.value === 'card' ? 'issued' : 'booked',
        booking_source: route.query.flight_source,
        flight_provider: route?.query.flight_provider,
        amount: parseFloat(pnrData?.value?.data?.billable_price).toFixed(2),
    }).then(() => {
        isDisabled.value = false;
        isOpen.value = false;

        closeDialogue();
    });

}
async function saveBooking(type) {
    try {
         if (!validateForm()) {
            globalError.value = "Please fix the errors in the form before submitting";
            window.scrollTo({ top: 0, behavior: "smooth" });
            return;
        }
        // ✅ Wait for ancillary charges to finish before continuing
        //if (ancillaries.value) {
            //await patchAncillaryCharges();
        //}
       

        isSubmitting.value = true;
        currentState.value = 0;

        if (paymentMethod.value === "pay" || paymentMethod.value === "card") {
            isOpen.value = true;
        }

        globalError.value = "";

        await store.dispatch("flight/" + SAVE_BOOKING, {
            main_contact: mainContact.value,
            travellers: travellers.value,
            agency_contact: agencyContact.value,
            agent_id: user.value.id,
            agency_name: agentData.value.agent_data.company_name,
            agency_mobile: agentData.value.agent_data.mobile,
            agency_email: agentData.value.email,
            amount: amount.value,
            agent_markup: agentData.value.agent_data.margin_amount,
            agent_discount: agentData.value.agent_data.agent_discount,
            agent_margin: agentMargin,
            flight: flight.value,
            TUI:quote.value?.TUI,
            NetAmount: quote.value?.NetAmount,
            fareType: quote.value?.FareType || flight.value?.provider?.fare_type,
            booking_status_setting: bookingStatusSetting?.value.bookingStatus,
            flight_source: route?.query.flight_source,
            flight_provider: route?.query.flight_provider,
            currency: agentData?.value?.agent_data?.currency?.code,
            exchange_rate: agentData?.value?.agent_data?.currency?.exchange_rate,
            fare_reference: selectedFares.value,
            selectedExtras: selectedExtras,
            type: paymentMethod.value || type,
            paymentMethod: paymentMethod.value || type,
            booking_status:
                paymentMethod.value === "hold"
                    ? "booked"
                    : paymentMethod.value === "pay"
                        ? "ticketed"
                        : paymentMethod.value === "card"
                            ? "booked"
                            : "booked",
            // body: quote.value,
        });

    } catch (error) {
        console.error("Error saving booking data:", error);
        globalError.value = "Failed to save booking. Please try again.";
        window.scrollTo({ top: 0, behavior: "smooth" });
    } finally {
        isSubmitting.value = false;
    }
}


const closeDialogue = () => {
    isOpen.value = false;

    router.push({
        name: "BookingsDetails", // Replace with the name of your route
        query: {
            flight_id: route.query.flight_id,
            booking_source: route.query.flight_source,
            flight_mode: "B2C",
            flight_provider: route.query.flight_provider,
        }, // Pass relevant query parameters if needed
    });
};
watch(bookingDetails, () => {
    if (paymentMethod.value === 'pay') {
        currentState.value = 1;
        parsePnrResponse();
        confirmBooking();
    } else if (paymentMethod.value === 'card') {
        currentState.value = 2;
        parsePnrResponse();
        confirmBooking();
    }
    else if (paymentMethod.value === 'hold') {
        parsePnrResponse();
    //     //console.log("pnrData", pnrData.value);
        router.push({
            name: "PaymentView", // Replace with the name of your route
            query: {
                flight_id: route.query.flight_id,
                booking_id: bookingDetails?.value?.id,
                pnr: bookingDetails?.value?.itinerary_ref,
                fare_reference: selectedFares.value,
                flight_mode: "B2B",
                booking_source: route.query.flight_source,
                flight_provider: route.query.flight_provider,
            }, // Pass relevant query parameters if needed
        });
    }


})

watch(isConfirmed, () => {
    if (isConfirmed.value == false) {

        currentState.value = 2;


    }
});

// Handle extra selection for a specific passenger (travellerIdx)
function handleExtraChange(flightIdx, segmentId, segmentIdx, travellerIdx, extra, type) {
    console.log(extra);
    // Ensure UI preview object exists
    if (!selectedExtras.value[flightIdx]) selectedExtras.value[flightIdx] = {};
    if (!selectedExtras.value[flightIdx][type]) selectedExtras.value[flightIdx][type] = {};
    if (!selectedExtras.value[flightIdx][type][segmentIdx]) selectedExtras.value[flightIdx][type][segmentIdx] = {};

    const qty = extra?.qty ?? 1;
    console.log(qty);
    const price = (extra.amount * qty);

    // Save preview data per passenger
    selectedExtras.value[flightIdx][type][segmentIdx][travellerIdx] = {
        title: extra.title || extra.seat_no || "",
        description: extra.description || extra.type || "",
        amount: price,
        currency: extra.currency?.symbol || "",
        refId: extra.ref_id,
        qty,
    };

    // Ensure API data object exists
    if (!selectedExtraData.value[flightIdx]) selectedExtraData.value[flightIdx] = {};
    if (!selectedExtraData.value[flightIdx][type]) selectedExtraData.value[flightIdx][type] = {};
    if (!selectedExtraData.value[flightIdx][type][segmentIdx]) selectedExtraData.value[flightIdx][type][segmentIdx] = {};

    const passengerRefId = ancillaries.value?.passengers?.[travellerIdx]?.ref_id;

    // Save API data per passenger
    selectedExtraData.value[flightIdx][type][segmentIdx][travellerIdx] = {
        segment_ref_id: segmentId,
        passenger_ref_id: passengerRefId,
        ref_id: extra.ref_id,
        price: price,
        currency: extra.currency?.code,
        qty,
    };
    console.log(selectedExtraData.value);
}

// Save selected extra for a specific passenger (travellerIdx)
function saveExtra(flightIdx, type, segmentIdx, travellerIdx) {
    // Ensure structure exists
    if (!extraCharges.value[flightIdx]) extraCharges.value[flightIdx] = {};
    if (!extraCharges.value[flightIdx][type]) extraCharges.value[flightIdx][type] = {};
    if (!extraCharges.value[flightIdx][type][segmentIdx]) extraCharges.value[flightIdx][type][segmentIdx] = {};

    const extraData = selectedExtraData.value[flightIdx]?.[type]?.[segmentIdx]?.[travellerIdx];
    if (!extraData) {
        console.warn(`No ${type} data to save for flight ${flightIdx}, segment ${segmentIdx}, passenger ${travellerIdx}`);
        return;
    }

    // Save complete extra data per passenger
    extraCharges.value[flightIdx][type][segmentIdx][travellerIdx] = {
        passenger_ref_id: extraData.passenger_ref_id,
        segment_ref_id: extraData.segment_ref_id,
        ref_id: extraData.ref_id,
        price: extraData.price,
        currency: extraData.currency,
        qty: extraData.qty,
    };

    // If saving a seat, update selectedSeat for this traveller
    if (type === 'seat') {
        if (!selectedSeat.value[flightIdx]) selectedSeat.value[flightIdx] = {};
        if (!selectedSeat.value[flightIdx][segmentIdx]) selectedSeat.value[flightIdx][segmentIdx] = {};
        selectedSeat.value[flightIdx][segmentIdx][travellerIdx] = extraData.ref_id;
    }
}
function calculateTaxes(fare) {
    return (
        parseFloat(fare?.taxes || 0) +
        parseFloat(fare?.surchage || 0) +
        parseFloat(fare?.fees || 0) +
        parseFloat(fare?.service_charges || 0) +
        parseFloat(fare?.ancillaries_charges || 0)
    );
}

function calculateTotalFare(fare) {
    const passengerCount = parseInt(route.query.passenger_count || 1);
    const agentAmount = parseFloat(agentData?.value?.agent_data?.margin_amount || 0);
    const agentDiscount = parseFloat(agentData?.value?.agent_data?.agent_discount || 0)
    const margin = parseFloat(agentMargin || 0);
    const airlineMargin = calculateFinalPrice(parseFloat(fare.base_price || 0), parseFloat(fare.margin_amount), fare.margin_type, fare.amount_type);

    const billable = parseFloat(fare.surchage || 0) + parseFloat(fare.taxes || 0) + parseFloat(fare.fees || 0) + parseFloat(fare.service_charges || 0) + parseFloat(fare.ancillaries_charges || 0) + (parseFloat(airlineMargin));

    const total = billable + (agentAmount * passengerCount) + margin -(agentDiscount * passengerCount);
    return total;
}
function calculateGrandTotal() {
    let total = 0;

    flight?.value?.leg?.flights?.forEach((flightItem, flightIndex) => {
        flightItem?.fares?.forEach(fare => {
            let extrasAmount = 0;

            const extrasForFlight = selectedExtras?.[flightIndex] || {};
            // Loop through groups: baggage, seat, meal
            ["baggage", "seat", "meal"].forEach(group => {
                const groupData = extrasForFlight[group];
                if (!groupData) return;
                // segment level
                Object.values(groupData).forEach(segment => {
                    if (!segment) return;

                    // passenger level
                    Object.values(segment).forEach(pax => {
                        if (!pax) return;

                        // item level
                        Object.values(pax).forEach(item => {
                            const price =
                                Number(item?.Charge ?? item?.SSRNetAmount ?? 0);

                            extrasAmount += price;
                        });
                    });
                });
            });

            if (selectedFares.value.includes(fare.ref_id)) {
                total += calculateTotalFare(fare) + extrasAmount;
            }
        });
    });
    amount.value = total;
    return total;
}


function sendSooperQoute() {
    // Save selected flight in localStorage
    localStorage.setItem("selectedFlight", JSON.stringify(flight.value));

    const body = {
        ref_id: flight?.value?.provider?.TUI,
        flight_provider: "AT",
        provider: "AT",
        fareType: flight?.value?.provider?.fare_type,
        legs: flight?.value?.leg?.flights
            .map(flightItem => {
                // Match fare from selectedFares.value
                const selectedFare = flightItem.fares.find(fare =>
                    selectedFares.value.includes(fare.ref_id)
                );

                if (selectedFare) {
                    return {
                        Index: flightItem?.flight_index,
                        selectedFare:selectedFare
                    };
                }
                return null;
            })
            .filter(item => item !== null)
    };

    store.dispatch("flight/" + SEND_PRICE_REQUEST, body);
}

function fetchAncillaries() {
    const body = {
        ref_id: quote?.value?.TUI,
        legs: flight?.value?.leg?.flights
            .map(flightItem => {
                // Match fare from selectedFares.value
                const selectedFare = flightItem.fares.find(fare =>
                    selectedFares.value.includes(fare.ref_id)
                );

                if (selectedFare) {
                    return {
                        Index: flightItem?.flight_index,
                        selectedFare:selectedFare
                    };
                }
                return null;
            })
            .filter(item => item !== null)
    };
    store.dispatch("flight/" + FETCH_ANCILLARIES, {
        flight_provider: route?.query.flight_provider,
        body: body,
    })
}

watch(quote, () => {
    if (!quote.value) {
        // router.back();
        return;
    }
    fetchAncillaries();
})

function fetchAgent() {
    if (user_id.value) {
        try {
            store.dispatch(`user/${FETCH_AGENT_DATA}`, {
                userId: user_id.value,
            });
            loading.value = false;
        } catch (err) {
            error.value = "Failed to load user data. Please try again.";
            loading.value = false;
        }
    } else {
        error.value = "No user ID provided.";
        loading.value = false;
    }
}

function fetchCountries(event) {

    store.dispatch("country/" + FETCH_COUNTRIES, {
        searchQuery: event.target.value,
    });
}

function parsePnrResponse() {
    try {
        const pnrResponseString = bookingDetails?.value?.pnr_response;

        //console.log('pnrResposenString', pnrResponseString);
        if (pnrResponseString) {
            pnrData.value = JSON.parse(pnrResponseString);
        } else {
            //console.log("No pnr_response found in bookingDetails");
            pnrData.value = null;
        }
    } catch (e) {
        console.error("Failed to parse pnr_response:", e);
        pnrData.value = null;
    }
}
function saveBookingData() {
    store.dispatch("flight/" + SAVE_BOOKING_DATA, {
        main_contact: mainContact.value,
        flight_data: flight.value,
        travellers: travellers.value,
        agency_contact: agencyContact.value,
        flight_id: route.query.flight_id,
    });
}

function initializeSelectedSeat() {
    // Structure: selectedSeat[flightIdx][segmentIdx][travellerIdx] = null
    const selectedSeats = {};
    if (!flight.value?.leg?.flights) return;
    flight.value.leg.flights.forEach((flightItem, flightIdx) => {
        selectedSeats[flightIdx] = {};
        flightItem.segments.forEach((segment, segmentIdx) => {
            selectedSeats[flightIdx][segmentIdx] = {};
            // For each traveller, initialize seat selection as null
            travellers.value.forEach((_, travellerIdx) => {
                selectedSeats[flightIdx][segmentIdx][travellerIdx] = null;
            });
        });
    });
    selectedSeat.value = selectedSeats;
}

function fetchFlight() {
    // store.dispatch("flight/" + FETCH_FLIGHT, {
    //     flight_id: route.query.flight_id,
    //     price_margin: route.query.price_margin,
    //     supplier: route.query.supplier,
    // }).then(() => {
    //     //console.log("Flight data fetched successfully:", flight.value);
    // });
    let selectedFlight;
    selectedFlight = localStorage.getItem("selectedFlight");

    flight.value = JSON.parse(selectedFlight);
    initializeSelectionStructures();
    //console.log("Flight data fetched successfully:", flight.value);

}



const openTermsDialog = () => {
    isTermsDialogOpen.value = true;
};

const closeTermsDialog = () => {
    isTermsDialogOpen.value = false;
};

const openPaymentDialog = async () => {
    showPaymentDialog.value = true;
    await nextTick(); // Wait for DOM to update

    if (!stripe.value || !cardElement.value) {
        await initializeStripe(); // Ensure Stripe is initialized
    }

    const container = document.getElementById('card-element');
    if (container && !container.hasChildNodes()) {
        cardElement.value.mount('#card-element');
    } else if (!container) {
        console.error('Card element container not found');
        toast.error('Payment form not available. Please try again.');
        showPaymentDialog.value = false; // Close dialog to prevent broken UI
    }
};
// Close payment dialog
const closePaymentDialog = () => {
    showPaymentDialog.value = false;
    paymentError.value = '';
    if (cardElement.value) {
        cardElement.value.unmount(); // Unmount to avoid duplicate mounting issues
        cardElement.value.clear(); // Clear card input
    }
};

function handlePaymentMethod(type) {


    if (!validateForm()) {
        globalError.value =
            "Please fix the errors in the form before submitting";
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
    }
    if (type === 'pay') {
        paymentMethod.value = 'pay';
        if (isPaymentMethodsVisible.value == true) {
            isPaymentMethodsVisible.value = false;
        } else {
            isPaymentMethodsVisible.value = true;
        }
    } else if (type == 'hold') {
        //console.log('Opening confirm dialog for hold payment');
        paymentMethod.value = 'hold';
        isConfirmDialogOpen.value = true;
    } else if (type == 'card') {
        //console.log('Opening payment dialog for card payment');
        paymentMethod.value = 'card';
        openPaymentDialog();

    }
}
watch(user_id, (newUserId) => {
    if (newUserId) {
        fetchAgent();
    }
});

// ✅ Initialize Stripe & Card Elements Only Once
const initializeStripe = async () => {
    try {
        if (stripe.value) {
            //console.log('Stripe already initialized');
            return;
        }

        //console.log('Initializing Stripe with public key', publicKey.value);
        stripe.value = await loadStripe(publicKey.value);
        //console.log('Stripe loaded:', stripe.value);
        if (!stripe.value) {
            console.error('loadStripe returned null');
            throw new Error('Failed to initialize Stripe: loadStripe returned null');
        }

        elements.value = stripe.value.elements();
        if (!cardElement.value) {
            cardElement.value = elements.value.create('card', {
                style: {
                    base: {
                        padding: '10px',
                        fontSize: '16px',
                        color: '#32325d',
                        fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                        '::placeholder': { color: '#aab7c4' },
                    },
                    invalid: { color: '#fa755a' },
                },
                classes: { base: 'p-3 border rounded-lg bg-white shadow-sm', invalid: 'border-red-500' },
            });

            // Attach change event listener
            cardElement.value.on('change', (event) => {
                paymentError.value = event.error ? event.error.message : '';
            });
        }
    } catch (error) {
        console.error('Stripe initialization error:', error);
        toast.error('Failed to initialize payment system. Please try again.');
    }
};

const handlePayment = async () => {
    if (!stripe.value || !cardElement.value) {
        toast.error('Payment form not ready. Please try again.');
        return;
    }

    processing.value = true;

    try {
        const feeMultiplier = 1.03; // 100% + 3% fee

        const response = await store.dispatch(`flight/${SEND_PAYMENT_REQUEST}`, {
            amount: Math.round(amount.value * feeMultiplier * 100),
            currency: 'aed',
        });

        clientSecret.value = response?.clientSecret;
        if (!clientSecret.value) {
            throw new Error('No client secret received from server');
        }


        await confirmCardPayment();
    } catch (error) {
        console.error('Payment request error:', error);
        toast.error('Failed to initiate payment. Please try again.');
        showPaymentDialog.value = false; // Close dialog on error
        processing.value = false;
    }
};


const confirmCardPayment = async () => {
    if (!stripe.value || !cardElement.value || !clientSecret.value) {
        toast.error('Payment form not ready.');
        processing.value = false;
        return;
    }

    processing.value = true;

    try {
        const result = await stripe.value.confirmCardPayment(clientSecret.value, {
            payment_method: {
                card: cardElement.value,
                billing_details: {
                    email: mainContact.value.email,
                    phone: mainContact.value.phone,
                },
            },
        });
        showPaymentDialog.value = false;

        if (result.error) {
            paymentError.value = result.error.message;
            toast.error(result.error.message);
        } else if (result.paymentIntent.status === 'succeeded') {
            toast.success('Payment successful!');
            cardElement.value.clear();
            saveBooking();

        }
    } catch (error) {
        console.error('Payment confirmation error:', error);
        toast.error('Payment failed. Please try again.');
    } finally {
        processing.value = false;
    }
};

const triggerFileUpload = (index) => {
    const input = document.getElementById(`passport-upload-${index}`)
    input?.click()
}


const handlePassportUpload = async (e, index) => {



    travellerIndex.value = index;
    const traveller = travellers.value[index];
    // Set the current traveller index
    const file = e.target.files[0];
    traveller.passportImage = file;

    if (file) {
        originalImage.value = URL.createObjectURL(file);
        resizeAndProcessImage(file);
    }
};




const resizeAndProcessImage = (file) => {
    const img = new Image();
    img.src = URL.createObjectURL(file);
    img.onload = () => {
        const canvas = document.createElement("canvas");
        const ctx = canvas.getContext("2d");

        const targetWidth = 579;
        const targetHeight = 404;
        const aspectRatio = img.width / img.height;
        let newWidth, newHeight;

        if (aspectRatio > targetWidth / targetHeight) {
            newWidth = targetWidth;
            newHeight = Math.round(targetWidth / aspectRatio);
        } else {
            newHeight = targetHeight;
            newWidth = Math.round(targetHeight * aspectRatio);
        }

        canvas.width = targetWidth;
        canvas.height = targetHeight;
        ctx.fillStyle = "#ffffff";
        ctx.fillRect(0, 0, targetWidth, targetHeight);
        ctx.drawImage(
            img,
            (targetWidth - newWidth) / 2,
            (targetHeight - newHeight) / 2,
            newWidth,
            newHeight
        );

        cropAndScan(canvas);
    };
};

const cropAndScan = (canvas) => {
    const ctx = canvas.getContext("2d");
    const mrzHeight = canvas.height * 0.3;
    const mrzCanvas = document.createElement("canvas");
    const mrzCtx = mrzCanvas.getContext("2d");
    mrzCanvas.width = canvas.width;
    mrzCanvas.height = mrzHeight;

    mrzCtx.drawImage(
        canvas,
        0, canvas.height - mrzHeight, canvas.width, mrzHeight,
        0, 0, canvas.width, mrzHeight
    );

    croppedImage.value = mrzCanvas.toDataURL("image/png");

    mrzCanvas.toBlob(blob => {
        scanWithTesseract(blob);
    }, "image/png");
};
function fetchCountry(country) {

    store.dispatch("country/" + FETCH_COUNTRIES, {
        searchQuery: country,
    });
}

const scanWithTesseract = async (imageBlob) => {
    scanning.value = true;
    progress.value = 0;

    try {
        const baseURL = window.location.origin;

        const result = await Tesseract.recognize(imageBlob, "eng", {
            tessedit_char_whitelist: "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<",
            logger: m => {
                if (m.status === "recognizing text") {
                    progress.value = Math.round(m.progress * 100);
                }
            }
        });

        //console.log("Tesseract result:", result);

        let text = result.data.text.toUpperCase();

        // 🔹 Step 1: Fix long "LL" runs → "<"
        text = text.replace(/L{2,}/g, match => "<".repeat(match.length));
        //console.log("After LL→< fix:", text);

        // 🔹 Step 2: Fix "C L C" patterns → <<< 
        text = text.replace(/C+L+C+/g, "<".repeat(3));
        //console.log("After CLC fix:", text);

        // 🔹 Step 3: Convert single C → <
        text = text.replace(/C/g, "<");
        //console.log("After C→< fix:", text);

        // 🔹 Step 4: Fix common country code misreads (5AU → SAU, 0 → O, etc.)
        text = text.replace(/\b5([A-Z]{2})\b/g, "S$1");
        text = text.replace(/\b0([A-Z]{2})\b/g, "O$1");
        //console.log("After country code fixes:", text);

        // 🔹 Step 5: Fix numbers misread as letters and vice versa
        text = text
            .replace(/O(?=\d)/g, '0')     // O before digit → 0
            .replace(/(?<=\d)O/g, '0')    // O after digit → 0
            .replace(/(?<=\d)I(?=\d)/g, '1'); // I between digits → 1
        //console.log("After digit/letter fixes:", text);

        // 🔹 Step 6: Remove invalid characters (keep MRZ set)
        text = text.replace(/[^A-Z0-9<\n]/g, "");
        //console.log("Final cleaned text:", text);

        const mrzMatch = extractMRZ(text);

        if (mrzMatch) {
            passportData.value = parseMRZ(mrzMatch);
            //console.log(passportData.value);

            // Fetch country and wait a bit for countries to update
            await fetchCountry(passportData.value.issuingCountry);
            await new Promise(resolve => setTimeout(resolve, 400)); // Wait 400ms for countries to update

            const traveller = travellers.value[travellerIndex.value];
            traveller.firstName = passportData.value.givenNames;
            traveller.lastName = passportData.value.surname;
            traveller.documentType = passportData.value.documentType === "P" ? "passport" : passportData.value.documentType === "v" ? "Visa" : "Unknown";
            traveller.documentNo = passportData.value.passportNumber;
            traveller.expiryDate = passportData.value.expiryDate;
            traveller.issueCountry = countries?.value[0]?.code || passportData.value.issuingCountry;

            traveller.dob = passportData.value.birthDate;
            traveller.gender = passportData.value.gender;
            traveller.title = traveller.gender === "M" ? "Mr" : "Miss";
            traveller.nationality = countries?.value[0]?.code;
        } else {
            errorMessage.value = "No MRZ detected in the cropped area.";
        }
    } catch (err) {
        console.error("Tesseract error:", err);
        errorMessage.value = "Error scanning image. Please try again.";
    } finally {
        scanning.value = false;
    }
};

const extractMRZ = (text) => {
    const mrzRegex = /([A-Z0-9<]{44}\n[A-Z0-9<]{44})|([A-Z0-9<]{30}\n[A-Z0-9<]{30}\n[A-Z0-9<]{30})/;
    const match = text.match(mrzRegex);

    //console.log("Extracted MRZ:", match?.[0]);
    return match ? match[0] : null;
};

const parseMRZ = (mrz) => {
    const lines = mrz.split("\n").map(line => line.trim());

    if (lines.length === 2 && lines[0].length === 44 && lines[1].length === 44) {
        return parseTD3Format(lines);
    } else if (lines.length === 3 && lines.every(line => line.length === 30)) {
        return parseTD1Format(lines);
    } else if (lines.length === 2 && lines[0].length === 36 && lines[1].length === 36) {
        return parseTD2Format(lines);
    } else {
        console.warn("Unknown MRZ format:", lines);
        return tryBestEffortParse(lines);
    }
};

const parseTD3Format = (lines) => {
    const line1 = lines[0];
    const line2 = lines[1];

    //console.log("Parsing TD3 format:", line1, line2);

    const nameParts = line1.substring(5).split('<<');
    const surname = nameParts[0].replace(/</g, '');
    const givenNames = nameParts.slice(1).join(' ').replace(/</g, ' ').trim();

    return {
        format: 'TD3',
        documentType: line1.substring(0, 1),
        issuingCountry: line1.substring(2, 5).replace(/^5/, 'S').replace(/^0/, 'O'),
        surname,
        givenNames,
        passportNumber: line2
            .substring(0, 9)
            .replace(/</g, '')
            .replace(/O(?=\d)/g, '0')
            .replace(/(?<=\d)O/g, '0')
            .replace(/(?<=\d)I(?=\d)/g, '1')
            .toUpperCase(),
        passportNumberCheckDigit: line2.substring(9, 10),
        nationality: line2.substring(10, 13).replace(/^5/, 'S').replace(/^0/, 'O'),
        birthDate: formatDateOfMrz(line2.substring(13, 19)),
        birthDateCheckDigit: line2.substring(19, 20),
        gender: line2.substring(20, 21),
        expiryDate: formatDateOfMrz(line2.substring(21, 27)),
        expiryDateCheckDigit: line2.substring(27, 28),
        personalNumber: line2.substring(28, 42).replace(/</g, ''),
        personalNumberCheckDigit: line2.substring(42, 43),
        compositeCheckDigit: line2.substring(43, 44)
    };
};



const tryBestEffortParse = (lines) => {
    const allText = lines.join('');
    const result = {
        rawText: allText,
        warning: "Non-standard MRZ format detected"
    };

    const dateMatches = allText.match(/\d{6}/g) || [];
    if (dateMatches.length >= 1) {
        result.birthDate = formatDateOfMrz(dateMatches[0]);
    }
    if (dateMatches.length >= 2) {
        result.expiryDate = formatDateOfMrz(dateMatches[1]);
    }

    const docNumMatch = allText.match(/[A-Z0-9<]{9,}/);
    if (docNumMatch) {
        result.documentNumber = docNumMatch[0].replace(/</g, '');
    }

    const nameMatch = allText.match(/([A-Z<]+)<<([A-Z<]*)/);
    if (nameMatch) {
        result.surname = nameMatch[1].replace(/</g, '');
        result.givenNames = nameMatch[2].replace(/</g, ' ').trim();
    }

    return result;
};

const formatDateOfMrz = (yyMMdd) => {
    if (!yyMMdd || yyMMdd.length !== 6 || !/^\d{6}$/.test(yyMMdd)) {
        return yyMMdd;
    }

    const year = parseInt(yyMMdd.substring(0, 2), 10);
    const month = yyMMdd.substring(2, 4);
    const day = yyMMdd.substring(4, 6);

    const currentYear = new Date().getFullYear();
    const currentCentury = Math.floor(currentYear / 100);
    const currentYearInCentury = currentYear % 100;

    let fullYear;
    if (year > currentYearInCentury + 10) {
        fullYear = (currentCentury - 1) * 100 + year;
    } else {
        fullYear = currentCentury * 100 + year;
    }

    return `${fullYear}-${month}-${day}`;
};
const ssrData = ref(ancillaries?.value?.ssrData)

// Seat Layout Data
const seatLayout = ref(ancillaries?.value?.seatLayout)

// Selection variables - traveller wise
const selectedExtras = reactive({}) // selectedExtras[tripIdx][type][journeyIdx][segmentIdx][travellerIdx]
const extraCharges = reactive({})   // extraCharges[tripIdx][type][journeyIdx][segmentIdx][travellerIdx]
const selectedSeat = reactive({})   // selectedSeat[tripIdx][journeyIdx][segmentIdx][travellerIdx]

// Initialize selection structures
function initializeSelectionStructures() {
  if (!ssrData.value?.Trips || !seatLayout.value?.Trips) return
  
  const trips = ssrData.value.Trips
  const travellers = 3 // Example: 3 travellers, adjust based on your data
  
  trips.forEach((trip, tripIdx) => {
    // Initialize selectedSeat
    if (!selectedSeat[tripIdx]) selectedSeat[tripIdx] = {}
    
    trip.Journey?.forEach((journey, journeyIdx) => {
      if (!selectedSeat[tripIdx][journeyIdx]) selectedSeat[tripIdx][journeyIdx] = {}
      
      journey.Segments?.forEach((segment, segmentIdx) => {
        if (!selectedSeat[tripIdx][journeyIdx][segmentIdx]) selectedSeat[tripIdx][journeyIdx][segmentIdx] = {}
        
        // Initialize seat selection for each traveller
        for (let travellerIdx = 0; travellerIdx < travellers; travellerIdx++) {
          if (!selectedSeat[tripIdx][journeyIdx][segmentIdx][travellerIdx]) {
            selectedSeat[tripIdx][journeyIdx][segmentIdx][travellerIdx] = null
          }
        }
      })
    })
  })
}
function saveSSRExtra(tripIdx, type, journeyIdx, segmentIdx, passengerIdx) {
  const extra = selectedExtras[tripIdx]?.[type]?.[journeyIdx]?.[segmentIdx]?.[passengerIdx]
  if (!extra) return

  // Initialize charges if they don't exist
  if (!extraCharges[tripIdx]) extraCharges[tripIdx] = {}
  if (!extraCharges[tripIdx][type]) extraCharges[tripIdx][type] = {}
  if (!extraCharges[tripIdx][type][journeyIdx]) extraCharges[tripIdx][type][journeyIdx] = {}
  if (!extraCharges[tripIdx][type][journeyIdx][segmentIdx]) extraCharges[tripIdx][type][journeyIdx][segmentIdx] = {}
  
  extraCharges[tripIdx][type][journeyIdx][segmentIdx][passengerIdx] = extra.Charge || 0
  
  console.log(`${type} saved:`, extra)
  console.log('Selected Extras:', selectedExtras)
  console.log('Extra Charges:', extraCharges)
}
// SSR Selection Handler
function handleSSRSelection(tripIdx, journeyIdx, segmentIdx, travellerIdx, ssr, type) {
  // Initialize nested structure
  if (!selectedExtras[tripIdx]) selectedExtras[tripIdx] = {}
  if (!selectedExtras[tripIdx][type]) selectedExtras[tripIdx][type] = {}
  if (!selectedExtras[tripIdx][type][journeyIdx]) selectedExtras[tripIdx][type][journeyIdx] = {}
  if (!selectedExtras[tripIdx][type][journeyIdx][segmentIdx]) selectedExtras[tripIdx][type][journeyIdx][segmentIdx] = {}
  
  // Store SSR with mandatory fields
  selectedExtras[tripIdx][type][journeyIdx][segmentIdx][travellerIdx] = {
    ...ssr,
    FUID: getFUID(tripIdx, journeyIdx, segmentIdx),
    PaxID: travellerIdx + 1,
    SSID: ssr.ID
  }
}
function removeExtraBaggage(tripIdx, journeyIdx, segmentIdx, passengerIdx) {
  delete selectedExtras[tripIdx]?.baggage?.[journeyIdx]?.[segmentIdx]?.[passengerIdx]
  delete extraCharges[tripIdx]?.baggage?.[journeyIdx]?.[segmentIdx]?.[passengerIdx]
}

function removeExtraSeat(tripIdx, journeyIdx, segmentIdx, passengerIdx) {
  delete selectedExtras[tripIdx]?.seat?.[journeyIdx]?.[segmentIdx]?.[passengerIdx]
  delete extraCharges[tripIdx]?.seat?.[journeyIdx]?.[segmentIdx]?.[passengerIdx]
  delete selectedSeat[tripIdx]?.[journeyIdx]?.[segmentIdx]
}
// Seat Selection Handler
function handleSeatSelection(tripIdx, journeyIdx, segmentIdx, travellerIdx, seat) {
  // Initialize nested structure
  if (!selectedExtras[tripIdx]) selectedExtras[tripIdx] = {}
  if (!selectedExtras[tripIdx].seat) selectedExtras[tripIdx].seat = {}
  if (!selectedExtras[tripIdx].seat[journeyIdx]) selectedExtras[tripIdx].seat[journeyIdx] = {}
  if (!selectedExtras[tripIdx].seat[journeyIdx][segmentIdx]) selectedExtras[tripIdx].seat[journeyIdx][segmentIdx] = {}
  
  // Store seat selection
  selectedExtras[tripIdx].seat[journeyIdx][segmentIdx][travellerIdx] = {
    ...seat,
    FUID: getSeatFUID(tripIdx, journeyIdx, segmentIdx),
    PaxID: travellerIdx + 1,
    SSID: seat.SSID
  }
  
  // Update selectedSeat for UI
  if (!selectedSeat[tripIdx]) selectedSeat[tripIdx] = {}
  if (!selectedSeat[tripIdx][journeyIdx]) selectedSeat[tripIdx][journeyIdx] = {}
  if (!selectedSeat[tripIdx][journeyIdx][segmentIdx]) selectedSeat[tripIdx][journeyIdx][segmentIdx] = {}
  selectedSeat[tripIdx][journeyIdx][segmentIdx][travellerIdx] = seat.SSID
}

// Save Selection
function saveSelection(tripIdx, type, journeyIdx, segmentIdx, travellerIdx) {
  const extra = selectedExtras[tripIdx]?.[type]?.[journeyIdx]?.[segmentIdx]?.[travellerIdx]
  if (!extra) return
  
  // Initialize charges structure
  if (!extraCharges[tripIdx]) extraCharges[tripIdx] = {}
  if (!extraCharges[tripIdx][type]) extraCharges[tripIdx][type] = {}
  if (!extraCharges[tripIdx][type][journeyIdx]) extraCharges[tripIdx][type][journeyIdx] = {}
  if (!extraCharges[tripIdx][type][journeyIdx][segmentIdx]) extraCharges[tripIdx][type][journeyIdx][segmentIdx] = {}
  
  extraCharges[tripIdx][type][journeyIdx][segmentIdx][travellerIdx] = extra.Charge || extra.Fare || 0
}

// Remove Selection
function removeSelection(tripIdx, type, journeyIdx, segmentIdx, travellerIdx) {
  delete selectedExtras[tripIdx]?.[type]?.[journeyIdx]?.[segmentIdx]?.[travellerIdx]
  delete extraCharges[tripIdx]?.[type]?.[journeyIdx]?.[segmentIdx]?.[travellerIdx]
  
  if (type === 'seat') {
    delete selectedSeat[tripIdx]?.[journeyIdx]?.[segmentIdx]?.[travellerIdx]
  }
}

// Helper function to get FUID
function getFUID(tripIdx, journeyIdx, segmentIdx) {
  const segment = ancillaries?.value?.ssrData?.Trips?.[tripIdx]?.Journey?.[journeyIdx]?.Segments?.[segmentIdx]
  return segment?.FUID
}

// Helper function to get Seat FUID
function getSeatFUID(tripIdx, journeyIdx, segmentIdx) {
  const segment = ancillaries?.value?.seatLayout?.Trips?.[tripIdx]?.Journey?.[journeyIdx]?.Segments?.[segmentIdx]
  return segment?.FlightNo || ''
}

// Helper function for seat layout
function getUniqueRows(seats) {
  if (!seats || !seats.length) return []
  const rows = seats.map(seat => seat.SeatNumber.replace(/[A-F]/g, ''))
  return [...new Set(rows)].sort((a, b) => parseInt(a) - parseInt(b))
}

function getSeatsByRowAndColumn(seats, row, columnIndex) {
  if (!seats || !seats.length) return []
  
  const columnLetters = ['A', 'B', 'C', null, 'D', 'E', 'F']
  const columnLetter = columnLetters[columnIndex - 1]
  
  if (!columnLetter) return []
  
  return seats.filter(seat => {
    const seatRow = seat.SeatNumber.replace(/[A-F]/g, '')
    const seatCol = seat.SeatNumber.slice(-1)
    return seatRow === row && seatCol === columnLetter
  })
}
//------------------------------------------





const formatTime = (milliseconds) => {
    const totalSeconds = Math.floor(milliseconds / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`;
};
const startCountdown = (remainingTime) => {
    if (timerInterval.value) clearInterval(timerInterval.value);
    countdown.value = formatTime(remainingTime);

    timerInterval.value = setInterval(() => {
        remainingTime -= 1000;
        if (remainingTime <= 0) {
            clearInterval(timerInterval.value);
            showDialog.value = true;
        } else {
            countdown.value = formatTime(remainingTime);
        }
    }, 1000);
};

function fetchOfferItems(){
    
}
onMounted(() => {
    selectedFares.value = route.query.fares ? JSON.parse(route.query.fares) : []

    fetchFlight();
    fetchAgentTravellers();
    initializeStripe();
    fetchBookingStatus();
    startCountdown(13 * 60 * 1000); // 13 minutes countdown
    if (user.value?.id) fetchAgent();
    fetchAgentLedger();
});
watch(flight, () => {
    initializeTravellers();
    // if(flight.value.provider.source === 'SB-NDC'){
    //     fetchOfferItems();
    // }
    sendSooperQoute();
});
const selectedTravellerAgent  = ref({});
const handleSelectedTravellerAgentChange = (index, travellerArrayIndex) => {
    selectedTravellerAgent.value = agentTravellers.value[index];
    travellers.value[travellerArrayIndex].firstName = selectedTravellerAgent.value.first_name;
    travellers.value[travellerArrayIndex].lastName = selectedTravellerAgent.value.last_name;
     travellers.value[travellerArrayIndex].gender = selectedTravellerAgent.value.gender;
     travellers.value[travellerArrayIndex].title = selectedTravellerAgent.value.title;
     travellers.value[travellerArrayIndex].dob = selectedTravellerAgent.value.date_of_birth;
      travellers.value[travellerArrayIndex].nationality = selectedTravellerAgent.value.nationality;
      travellers.value[travellerArrayIndex].documentType = selectedTravellerAgent.value.doc_type;
      travellers.value[travellerArrayIndex].documentNo = selectedTravellerAgent.value.document_no;
       travellers.value[travellerArrayIndex].expiryDate = selectedTravellerAgent.value.expiry_date;
       travellers.value[travellerArrayIndex].issueCountry = selectedTravellerAgent.value.issue_country;
};  
const agentTravellers = computed(() => store.getters["traveller/travellers"]);
function fetchAgentTravellers() {
    store.dispatch("traveller/" + FETCH_TRAVELLERS);
}

const goBackAndReload = () => {
    router.back();
}
const goBack = () => {
  router.back() // same as router.go(-1)
}
</script>

<template>
      <div v-if="showDialog" class="fixed inset-0 bg-gray-800 bg-opacity-50 flex items-center z-30 justify-center">
            <div class="bg-white p-4 rounded-lg shadow-lg w-96 text-center">
                <h2 class="text-lg font-bold">Search Expired</h2>
                <p class="mt-2">
                    Your search data has expired. Click "OK" to refresh the
                    page.
                </p>
                <button @click="goBackAndReload()" class="mt-4 px-4 py-2 bg-blue-500 text-white rounded">
                    OK
                </button>
            </div>
        </div>
    <div v-if="isLoading" class="flex items-center justify-center md:container h-[50vh] bg-white rounded-lg">
        <Spinner />
    </div>
    
    <!-- Custom System Problem Dialog -->
    <!-- <div v-if="qouteError" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex items-start justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">System Problem</h3>
                <button @click="()=> { qouteError = false; router.back(); }" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="text-sm text-gray-700 mb-6">
                There is a problem in the system. Please try again later or contact support if the issue persists.
            </div>
            <div class="flex justify-end">
                <button @click="()=> { qouteError = false; router.back(); }"
                    class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/20">
                    Close
                </button>
            </div>
        </div>
    </div> -->
    <div v-if="flight == null && !isLoading"
        class="flex flex-col gap-6 items-center justify-center md:container h-[50vh] bg-white rounded-lg">
        Nothing found.
        <Button @click="$router.back()">Back</Button>
    </div>
    <div v-if="route?.query?.flight_source == 1 && !isLoading">

        <div class = "mb-2"> <Breadcrumb>
      <BreadcrumbList>
        <!-- Home -->
        <BreadcrumbItem>
          <BreadcrumbLink @click.prevent="router.push('/DashboardFlights')">
            Flights
          </BreadcrumbLink>
        </BreadcrumbItem>

        <BreadcrumbSeparator />

        <!-- Flights (Go Back instead of fixed href) -->
        <BreadcrumbItem>
          <BreadcrumbLink class="hover:cursor-pointer" @click.prevent="goBack">
            Flights Search
          </BreadcrumbLink>
        </BreadcrumbItem>

        <BreadcrumbSeparator />

        <!-- Current Page -->
        <BreadcrumbItem>
          <BreadcrumbPage>Flight Checkout</BreadcrumbPage>
        </BreadcrumbItem>
      </BreadcrumbList>
    </Breadcrumb></div>
        <div v-if="!isLoading && flight && !showPreview">
        <!-- <pre>{{selectedExtras}}</pre>
        <pre>{{ancillaries?.seatLayout}}</pre> -->
            <div class="flex gap-x-3">
                <div class="w-3/4">
                    <div class="bg-white border border-muted mt-1 ">
                        <div class="border-b p-4">
                            <span class="text-sm font-bold">Contact Information</span>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-3 gap-3">
                                <div class="mb-3">
                                    <Label for="main-email">Email<span class="required">*</span></Label>
                                    <Input v-model="mainContact.email" id="main-email" type="email" placeholder="EMAIL"
                                        :class="{
                                            'is-invalid': errors.mainContact.email,
                                        }" />
                                    <div v-if="errors.mainContact.email" class="error-message">
                                        {{ errors.mainContact.email }}
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <Label for="main-phone">Phone<span class="required">*</span></Label>
                                    <Input v-model="mainContact.phone" id="main-phone" type="tel" placeholder="PHONE"
                                        :class="{
                                            'is-invalid': errors.mainContact.phone,
                                        }" @input="mainContact.phone = $event.target.value.toUpperCase()" />
                                    <div v-if="errors.mainContact.phone" class="error-message">
                                        {{ errors.mainContact.phone }}
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <Label>Country
                                        <span class="required">*</span></Label>
                                        <CountryDropdown :keyValue="'name'" v-model="mainContact.country" />
                                    <div v-if="errors.mainContact.country" class="error-message">
                                        {{ errors.mainContact.country }}
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-white rounded-lg">
                                    <div>
                                        <Label class="block text-sm font-medium text-gray-700 mb-1">Agency
                                            Contact</Label>
                                        <Input type="text"
                                            class="flex-1 rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                            placeholder="ENTER PHONE NUMBER" v-model="agencyContact.phone" readonly />
                                    </div>

                                    <div>
                                        <Label class="block text-sm font-medium text-gray-700 mb-1">Email</Label>
                                        <Input type="email"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                            v-model="agencyContact.email" placeholder="EMAIL" readonly />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="w-full mx-auto mt-1  bg-white shadow-sm  border-gray-200">
                        <div class="p-4 sm:p-6 flex items-center justify-between bg-gradient-to-r from-gray-50 to-white">
                            <p class="text-lg sm:text-xl font-semibold text-gray-800">
                                Traveller Details
                                <span class="text-sm font-normal text-gray-600 block sm:inline mt-2 sm:mt-0 sm:ml-2">
                                    (Use all given names and surnames exactly as they appear on your passport/ID to
                                    avoid complications.)
                                </span>
                            </p>
                            
                        </div>

                        <div v-for="(traveller, index) in travellers" :key="`traveller-${index}`"
                            class="border border-gray-100 mt-2 last:border-b-0">
                            <Accordion type="single" collapsible class="mb-0" >
                                <AccordionItem :value="`traveller-${index}`" class="border-0">
                                    <AccordionTrigger
                                        class="p-4 border bg-gray-100 hover:bg-gray-50 transition-colors duration-200 rounded-none"
                                        :aria-expanded="index === 0 ? 'true' : undefined"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                                                <span class="text-primary font-semibold text-sm">{{ index + 1 }}</span>
                                            </div>
                                            <h3 class="text-lg sm:text-xl font-semibold text-gray-800">
                                                {{ traveller.type }} Traveller {{ index + 1 }}
                                            </h3>
                                        </div>
                                    </AccordionTrigger>

                                    <AccordionContent class="bg-white border-0 p-0">
                                        <!-- Gender Selection -->
                                          

                                        <div class="flex flex-col sm:flex-row items-start gap-4 px-4 my-4">
                                            
                                            <div class="flex-1">
                                                <h4 class="text-sm font-medium text-gray-900 mb-4">Personal details</h4>
                                                <p class="text-xs text-gray-500 mb-4">
                                                    Save time by uploading a passport image to auto-fill the details
                                                    below. Ensure the image
                                                    is clear and contains all relevant information.
                                                </p>
                                                <Label class="text-sm font-medium text-gray-700 mb-2 block">Upload Passport Image</Label>
                                                <div class="flex flex-col sm:flex-row items-center gap-3">
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        ref="passportUpload"
                                                        @change="handlePassportUpload($event, index)"
                                                        class="hidden"
                                                        :id="`passport-upload-${index}`"
                                                    />
                                                    <button
                                                        type="button"
                                                        @click="triggerFileUpload(index)"
                                                        class="bg-primary text-white px-4 py-2 text-sm rounded hover:bg-primary/90 flex items-center gap-2"
                                                    >
                                                        <Upload class="w-4 h-4" />
                                                        Upload Image
                                                    </button>
                                                    <span
                                                        v-if="traveller.passportImage"
                                                        class="text-xs text-gray-600 truncate max-w-[150px] sm:max-w-[200px]"
                                                        :title="traveller.passportImage.name"
                                                    >
                                                        {{ traveller.passportImage.name }}
                                                    </span>
                                                    <span v-if="traveller.uploadError" class="text-red-500 text-xs">
                                                        {{ traveller.uploadError }}
                                                    </span>
                                                </div>
                                            </div>
                                            <div v-if="scanning" class="flex items-center gap-2 mt-2 sm:mt-0">
                                                <svg class="animate-spin h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                    </path>
                                                </svg>
                                                <span class="text-blue-500 text-sm">Scanning... {{ progress }}%</span>
                                            </div>
                                        </div>
                                       <div class="flex justify-between items-center px-4 sm:px-6 py-4">
                                                    
                                                    <div class=" bg-gray-50/50 border-b border-gray-100">
                                                        <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                                                            <span class="text-sm font-medium text-gray-700">Gender <span
                                                                    class="text-red-500">*</span></span>
                                                            <RadioGroup class="flex flex-row gap-6" default-value="comfortable"
                                                                :orientation="'horizontal'" v-model="traveller.gender"
                                                                :class="{ 'is-invalid': getErrorPath(`travellers.${index}.gender`) }">
                                                                <div class="flex items-center space-x-2">
                                                                    <RadioGroupItem id="male" value="M" class="border-gray-300" />
                                                                    <Label for="male"
                                                                        class="text-sm font-medium text-gray-700">Male</Label>
                                                                </div>
                                                                <div class="flex items-center space-x-2">
                                                                    <RadioGroupItem id="female" value="F" class="border-gray-300" />
                                                                    <Label for="female"
                                                                        class="text-sm font-medium text-gray-700">Female</Label>
                                                                </div>
                                                            </RadioGroup>
                                                        </div>
                                                        <div v-if="getErrorPath(`travellers.${index}.gender`)"
                                                            class="error-message text-xs mt-2 text-red-500">
                                                            {{ getErrorPath(`travellers.${index}.gender`) }}
                                                        </div>
                                                    </div>
                                                        <Select v-if="agentTravellers.length>0" @update:modelValue="handleSelectedTravellerAgentChange($event,index)">

                                                        <SelectTrigger
                                                            class="h-10 text-sm bg-white w-[200px] border-gray-200 focus:border-primary focus:ring-primary/20">
                                                            <SelectValue placeholder="Select Traveller " />
                                                        </SelectTrigger>
                                                        <SelectContent class="bg-white border-gray-200 w-[200px]">
                                                            <SelectGroup>
                                                                <SelectItem :value="index" v-for="(agentTraveller,index) in agentTravellers" :key="agentTraveller.id">{{ agentTraveller.title }} {{ agentTraveller.first_name }} {{ agentTraveller .last_name }}</SelectItem>

                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                       </div>

                                        <!-- Personal Information -->
                                        <div class="p-4 sm:p-6">
                                            <h4 class="text-base font-semibold text-gray-800 mb-4">Personal Information
                                            </h4>

                                            <!-- First Grid: Personal Details -->
                                            <div
                                                class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-6">
                                                <div class="space-y-2">
                                                    <Label :for="`title-${index}`"
                                                        class="block text-sm font-medium text-gray-700">
                                                        Title <span class="text-red-500">*</span>
                                                    </Label>
                                                    <Select v-model="traveller.title" :id="`title-${index}`"
                                                        :class="{ 'is-invalid': getErrorPath(`travellers.${index}.title`) }">
                                                        <SelectTrigger
                                                            class="h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20">
                                                            <SelectValue placeholder="Select title" />
                                                        </SelectTrigger>
                                                        <SelectContent class="bg-white border-gray-200">
                                                            <SelectGroup>
                                                                <SelectItem value="Mr">Mr</SelectItem>
                                                                <SelectItem value="Mrs">Mrs</SelectItem>
                                                                <SelectItem value="Ms">Ms</SelectItem>
                                                                <SelectItem value="Miss">Miss</SelectItem>
                                                                <SelectItem value="Mstr">Mstr</SelectItem>
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                                    <div v-if="getErrorPath(`travellers.${index}.title`)"
                                                        class="error-message text-xs text-red-500">
                                                        {{ getErrorPath(`travellers.${index}.title`) }}
                                                    </div>
                                                </div>

                                                <div class="space-y-2">
                                                    <Label :for="`first-name-${index}`"
                                                        class="block text-sm font-medium text-gray-700">
                                                        First Name <span class="text-red-500">*</span>
                                                    </Label>
                                                    <Input v-model="traveller.firstName" :id="`first-name-${index}`"
                                                        type="text"
                                                        :class="{ 'is-invalid': getErrorPath(`travellers.${index}.firstName`) }"
                                                        class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20"
                                                        placeholder="Enter first name"
                                                        @input="traveller.firstName = $event.target.value.toUpperCase()" />
                                                    <div v-if="getErrorPath(`travellers.${index}.firstName`)"
                                                        class="error-message text-xs text-red-500">
                                                        {{ getErrorPath(`travellers.${index}.firstName`) }}
                                                    </div>
                                                </div>

                                                <div class="space-y-2">
                                                    <Label :for="`last-name-${index}`"
                                                        class="block text-sm font-medium text-gray-700">
                                                        Last Name <span class="text-red-500">*</span>
                                                    </Label>
                                                    <Input v-model="traveller.lastName" :id="`last-name-${index}`"
                                                        type="text"
                                                        :class="{ 'is-invalid': getErrorPath(`travellers.${index}.lastName`) }"
                                                        class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20"
                                                        placeholder="Enter last name"
                                                        @input="traveller.lastName = $event.target.value.toUpperCase()" />
                                                    <div v-if="getErrorPath(`travellers.${index}.lastName`)"
                                                        class="error-message text-xs text-red-500">
                                                        {{ getErrorPath(`travellers.${index}.lastName`) }}
                                                    </div>
                                                </div>

                                                <div class="">
                                                    <Label :for="`date-of-birth-${index}`"
                                                        class=" text-sm font-medium text-gray-700">
                                                        D.O.B
                                                        <span v-if="traveller.type == 'ADT'"
                                                            class=" text-xs text-red-500 font-normal">
                                                            *Age Should be 12+
                                                        </span>
                                                        <span v-else-if="traveller.type == 'CNN'"
                                                            class=" text-xs text-red-500 font-normal">
                                                            *Age 2 to 12 years
                                                        </span>
                                                        <span v-if="traveller.type == 'INF'"
                                                            class=" text-xs text-red-500 font-normal">
                                                            *Age Less than 2
                                                        </span>
                                                    </Label>
                                                    <Calender v-model="traveller.dob" type="date" :maxValue="todayDate"
                                                        :id="`date-of-birth-${index}`" placeholder="Date Of Birth"
                                                        :class="{ 'is-invalid': getErrorPath(`travellers.${index}.dob`) }"
                                                        class="w-full h-8 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20" />
                                                    <div v-if="getErrorPath(`travellers.${index}.dob`)"
                                                        class="error-message text-xs text-red-500">
                                                        {{ getErrorPath(`travellers.${index}.dob`) }}
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                    <Label :for="`nationality-${index}`"
                                                        class="block text-sm font-medium text-gray-700 mb-1">
                                                        Nationality<span class="required">*</span>
                                                    </Label>
                                                      <CountryDropdown :keyValue="'code'" placeholder="SELECT NATIONALITY" v-model="traveller.nationality" @update:modelValue="(value) => traveller.issueCountry = value" />
                                                    <div v-if="getErrorPath(`travellers.${index}.nationality`)"
                                                        class="error-message">
                                                        {{ getErrorPath(`travellers.${index}.nationality`) }}
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Document Information -->
                                            <h4 class="text-base font-semibold text-gray-800 mb-4">Document Information
                                            </h4>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                                <div class="space-y-2">
                                                    <Label :for="`document-type-${index}`"
                                                        class="block text-sm font-medium text-gray-700">
                                                        Document Type <span class="text-red-500">*</span>
                                                    </Label>
                                                    <Select v-model="traveller.documentType"
                                                        :id="`document-type-${index}`"
                                                        :class="{ 'is-invalid': getErrorPath(`travellers.${index}.documentType`) }">
                                                        <SelectTrigger
                                                            class="h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20">
                                                            <SelectValue placeholder="Select type" />
                                                        </SelectTrigger>
                                                        <SelectContent class="bg-white border-gray-200">
                                                            <SelectGroup>
                                                                <SelectItem value="passport">Passport</SelectItem>
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                                    <div v-if="getErrorPath(`travellers.${index}.documentType`)"
                                                        class="error-message text-xs text-red-500">
                                                        {{ getErrorPath(`travellers.${index}.documentType`) }}
                                                    </div>
                                                </div>

                                                <div class="space-y-2">
                                                    <Label :for="`document-no-${index}`"
                                                        class="block text-sm font-medium text-gray-700">
                                                        Document Number <span class="text-red-500">*</span>
                                                    </Label>
                                                    <Input v-model="traveller.documentNo" :id="`document-no-${index}`"
                                                        :class="{ 'is-invalid': getErrorPath(`travellers.${index}.documentNo`) }"
                                                        type="text"
                                                        class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20"
                                                        placeholder="Enter document number" />
                                                    <div v-if="getErrorPath(`travellers.${index}.documentNo`)"
                                                        class="error-message text-xs text-red-500">
                                                        {{ getErrorPath(`travellers.${index}.documentNo`) }}
                                                    </div>
                                                </div>
                                                <div class="">
                                                    <Label :for="`expiry-date-${index}`"
                                                        class="block text-sm font-medium text-gray-700">
                                                        Expiry Date <span class="text-red-500">*</span>
                                                    </Label>
                                                    <Calender v-model="traveller.expiryDate"
                                                        :id="`expiry-date-${index}`"
                                                        :class="{ 'is-invalid': getErrorPath(`travellers.${index}.expiryDate`) }"
                                                        type="date"
                                                        class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20" />
                                                    <div v-if="getErrorPath(`travellers.${index}.expiryDate`)"
                                                        class="error-message text-xs text-red-500">
                                                        {{ getErrorPath(`travellers.${index}.expiryDate`) }}
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <Label :for="`issue-country-${index}`"
                                                        class="block text-sm font-medium text-gray-700 mb-1">Issue
                                                        Country<span class="required">*</span></Label>
                                                    <CountryDropdown :keyValue="'code'" placeholder="SELECT ISSUE COUNTRY" v-model="traveller.issueCountry" />
                                                    <div v-if="getErrorPath(`travellers.${index}.issueCountry`)"
                                                        class="error-message">
                                                        {{ getErrorPath(`travellers.${index}.issueCountry`) }}
                                                    </div>
                                                </div>
                                                <!-- <div class="mb-3">
                                        <Label :for="`issue-country-${index}`"
                                            class="block text-sm font-medium text-gray-700 mb-1">Issue Country<span
                                                class="required">*</span></Label>
                                        <Input maxLength="2" v-model="traveller.issueCountry"
                                            :id="`issue-country-${index}`" :class="{
                                                'is-invalid': getErrorPath(
                                                    `travellers.${index}.issueCountry`,
                                                ),
                                            }" type="text"
                                            class="w-full rounded-md border-gray-300 shadow-sm focus:border-primary focus:ring-primary"
                                            placeholder="ENTER ISSUE COUNTRY"
                                            @input="traveller.issueCountry = $event.target.value.toUpperCase()" />
                                        <div v-if="
                                            getErrorPath(
                                                `travellers.${index}.issueCountry`,
                                            )
                                        " class="error-message">
                                            {{
                                                getErrorPath(
                                                    `travellers.${index}.issueCountry`,
                                                )
                                            }}
                                        </div>
                                    </div> -->
                                            </div>
                                        </div>

                                        <!-- Extra Services Toggle -->
                                        <div class="flex justify-end p-4 sm:p-6  border-t border-gray-100">
                                            <Button variant="outline"
                                                class="bg-white border-none shadow-none text-primary hover:underline hover:text-primary hover:bg-white px-6 py-2 rounded-lg font-medium"
                                                @click="traveller.showAncillaries = !traveller.showAncillaries">
                                                <span class="flex items-center gap-2">
                                                    <svg v-if="traveller.showAncillaries"
                                                        xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-4 w-4"
                                                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M12 4v16m8-8H4" />
                                                    </svg>
                                                    <span>
                                                        {{ traveller.showAncillaries ? 'Hide Extra Services' : 'Add Extra Services' }}
                                                    </span>
                                                </span>
                                            </Button>
                                        </div>

                                        <!-- Extra Services Content -->
                                        <div v-if="traveller.showAncillaries" class="bg-white border-t border-gray-100">
                                            <Tabs default-value="baggage" class="w-full">
                                                <div class="px-4 sm:px-6 pt-4">
                                                    <TabsList class="grid grid-cols-3 bg-gray-100 p-1 rounded-lg">
                                                        <TabsTrigger value="baggage"
                                                            class="rounded-md data-[state=active]:bg-white data-[state=active]:text-primary data-[state=active]:shadow-sm">
                                                            Baggage
                                                        </TabsTrigger>
                                                        <TabsTrigger value="seats"
                                                            class="rounded-md data-[state=active]:bg-white data-[state=active]:text-primary data-[state=active]:shadow-sm">
                                                            Seats
                                                        </TabsTrigger>
                                                        <TabsTrigger value="meals"
                                                            class="rounded-md data-[state=active]:bg-white data-[state=active]:text-primary data-[state=active]:shadow-sm">
                                                            Meals
                                                        </TabsTrigger>
                                                    </TabsList>
                                                </div>

                                                <!-- Baggage Tab -->
                                                <!-- Baggage Tab -->
<TabsContent value="baggage" class="p-4 sm:p-6">
    <div class="mb-6">
        <h4 class="text-lg font-semibold text-gray-800 mb-2">About your baggage</h4>
        <p class="text-sm text-gray-600">
            Need additional baggage? Save time and money by purchasing extra baggage in advance
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div v-for="(trip, tripIdx) in ancillaries?.ssrData?.Trips || []" :key="tripIdx"
            class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
            <h5 class="text-sm font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                {{ trip?.From }} to {{ trip?.To }}
            </h5>

            <div v-for="(journey, journeyIdx) in trip?.Journey || []" :key="journeyIdx" class="mb-6 last:mb-0">
                <div v-for="(segment, segmentIdx) in journey?.Segments || []" :key="segmentIdx" class="mb-4">
                    <div class="font-medium text-sm text-gray-700 mb-3 bg-gray-50 px-3 py-2 rounded-lg">
                        Segment {{ segmentIdx + 1 }}: {{ segment?.VAC }} Flight
                    </div>
                    
                    <!-- Existing baggage display -->
                    <div v-if="flight?.fares" class="space-y-3">
                        <div v-for="fare in flight?.fares" :key="fare.ref_id">
                            <div v-if="selectedFares?.includes(fare?.ref_id)" class="space-y-3">
                                <!-- Carry baggage -->
                                <div v-if="fare.baggage_policies?.some(p => p.type === 'carry')">
                                    <div v-for="policy in [fare.baggage_policies.find(p => p.type === 'carry')]"
                                        :key="policy.description"
                                        class="flex items-start gap-3 p-3 bg-green-50 rounded-lg border border-green-200">
                                        <div class="pt-0.5">
                                            <CheckCircle class="w-4 h-4 text-green-600" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ policy.weight_limit }} {{ policy.weight_unit }} cabin baggage
                                            </p>
                                            <p class="text-xs text-gray-500">{{ policy.pieces }} piece</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Checked baggage -->
                                <div v-if="fare.baggage_policies?.some(p => p.type === 'checked')">
                                    <div v-for="policy in [fare.baggage_policies.find(p => p.type === 'checked')]"
                                        :key="policy.description"
                                        class="flex items-start gap-3 p-3 bg-green-50 rounded-lg border border-green-200">
                                        <div class="pt-0.5">
                                            <CheckCircle class="w-4 h-4 text-green-600" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ policy.weight_limit }} {{ policy.weight_unit }} checked baggage
                                            </p>
                                            <p class="text-xs text-gray-500">{{ policy.pieces }} piece</p>
                                        </div>
                                    </div>
                                </div>
                                <div v-else
                                    class="flex items-start gap-3 p-3 bg-red-50 rounded-lg border border-red-200">
                                    <div class="pt-0.5">
                                        <XCircle class="w-4 h-4 text-red-500" />
                                    </div>
                                    <p class="text-sm text-gray-600">Checked baggage not included</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SSR Baggage Options -->
                    <div v-if="segment?.SSR?.filter(s => s.Type === '2').length" class="mt-4">
                        <div v-if="extraCharges[tripIdx]?.baggage?.[journeyIdx]?.[segmentIdx]?.[index]"
                            class="mt-4 p-3 bg-blue-50 rounded-lg border border-blue-200 mb-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <CheckCircle class="w-4 h-4 text-blue-600" />
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            Extra baggage: {{ selectedExtras[tripIdx]?.baggage?.[journeyIdx]?.[segmentIdx]?.[index]?.Description }}
                                        </p>
                                        <p class="text-xs text-gray-600">
                                            {{ ssrData?.CurrencyCode || 'INR' }} {{ selectedExtras[tripIdx]?.baggage?.[journeyIdx]?.[segmentIdx]?.[index]?.Charge }}
                                        </p>
                                    </div>
                                </div>
                                <button
                                    class="px-3 py-1 text-xs rounded-md bg-red-100 text-red-600 hover:bg-red-200 transition-colors duration-200"
                                    @click="() => removeExtraBaggage(tripIdx, journeyIdx, segmentIdx, index)"
                                    title="Remove baggage">
                                    Remove
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                {{ selectedExtras[tripIdx]?.baggage?.[journeyIdx]?.[segmentIdx]?.[index]?.PieceDescription }}
                            </p>
                        </div>

                        <!-- Dialog Trigger -->
                        <Dialog>
                            <DialogTrigger as-child>
                                <button
                                    class="flex items-center text-primary font-medium text-sm hover:text-primary/80 transition-colors duration-200 mt-3 bg-primary/5 px-4 py-2 rounded-lg hover:bg-primary/10">
                                    <PlusCircle class="w-4 h-4 mr-2" />
                                    Add extra baggage
                                </button>
                            </DialogTrigger>
                            <DialogContent class="max-w-4xl max-h-[90vh] flex flex-col overflow-hidden bg-white">
                                <DialogHeader class="pb-4 border-b border-gray-100">
                                    <DialogTitle class="text-xl font-semibold text-gray-800">
                                        Add your extra baggage
                                    </DialogTitle>
                                    <DialogDescription class="text-sm text-gray-600">
                                        You can select here which baggage you prefer
                                    </DialogDescription>
                                </DialogHeader>
                                <div class="flex-1 overflow-y-auto mt-4 pr-2">
                                    <h3 class="text-base font-medium text-gray-700 mb-4 bg-gray-50 px-4 py-2 rounded-lg">
                                        {{ trip?.From }} to {{ trip?.To }}
                                    </h3>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                        <label v-for="ssr in segment?.SSR?.filter(s => s.Type === '2')"
                                            :key="ssr.ID"
                                            class="block border-2 rounded-xl shadow-sm p-4 cursor-pointer transition-all duration-200 hover:shadow-lg hover:-translate-y-1 bg-white"
                                            :class="{ 'border-primary ring-2 ring-primary/20 bg-primary/5': selectedExtras[tripIdx]?.baggage?.[journeyIdx]?.[segmentIdx]?.[index]?.ID === ssr.ID, 'border-gray-200': selectedExtras[tripIdx]?.baggage?.[journeyIdx]?.[segmentIdx]?.[index]?.ID !== ssr.ID }">
                                            <div class="h-32 sm:h-36 md:h-44 flex items-center justify-center rounded-lg overflow-hidden mb-4 bg-gray-50">
                                                <img src="/public/assets/baggage.jpg"
                                                    alt="Baggage Image"
                                                    class="h-full object-contain" />
                                            </div>
                                            <div class="flex items-center mb-3">
                                                <input type="radio"
                                                    class="mr-3 accent-primary w-4 h-4"
                                                    :name="'baggage_' + tripIdx + '_' + journeyIdx + '_' + segmentIdx"
                                                    :value="ssr.ID"
                                                    @change="handleSSRSelection(tripIdx, journeyIdx, segmentIdx, index, ssr, 'baggage')" />
                                                <span class="text-sm font-semibold text-gray-800">{{ ssr.Description }}</span>
                                            </div>
                                            <div class="text-sm text-gray-600 leading-relaxed mb-3">
                                                {{ ssr.PieceDescription }}
                                            </div>
                                            <div class="text-lg font-bold text-primary">
                                                {{ ssrData?.CurrencyCode || 'INR' }} {{ ssr.Charge }}
                                            </div>
                                        </label>
                                    </div>
                                </div>
                                <DialogFooter class="flex justify-end items-center mt-6 pt-4 border-t border-gray-100">
                                    <DialogClose as-child>
                                        <button
                                            class="bg-primary text-white px-6 py-2 text-sm rounded-lg hover:bg-primary/90 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors duration-200"
                                            :disabled="!selectedExtras[tripIdx]?.baggage?.[journeyIdx]?.[segmentIdx]?.[index]"
                                            @click="saveSSRExtra(tripIdx, 'baggage', journeyIdx, segmentIdx, index)">
                                            Save Selection
                                        </button>
                                    </DialogClose>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                    </div>
                    <div v-else class="text-sm text-gray-500 mt-2">
                        No extra baggage options available for this segment.
                    </div>
                </div>
            </div>
        </div>
    </div>
</TabsContent>

<!-- Seats Tab -->
<TabsContent value="seats" class="p-4 sm:p-6">
    <div v-if="ancillaries?.seatLayout?.Trips?.length">
        <div class="mb-6">
            <h4 class="text-lg font-semibold text-gray-800 mb-2">Want your own seat?</h4>
            <p class="text-sm text-gray-600">
                Customize your trip with optional extras. Select the services you want now to avoid higher charges later.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-for="(trip, tripIdx) in seatLayout?.Trips || []" :key="tripIdx"
                class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <h5 class="text-sm font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                    {{ trip?.Journey?.[0]?.Segments?.[0]?.From || 'Unknown' }} to {{ trip?.Journey?.[0]?.Segments?.[0]?.To || 'Unknown' }}
                </h5>

                <div v-for="(journey, journeyIdx) in trip?.Journey || []" :key="journeyIdx" class="mb-6 last:mb-0">
                    <div v-for="(segment, segmentIdx) in journey?.Segments || []" :key="segmentIdx" class="mb-4">
                        <div class="font-medium text-sm text-gray-700 mb-3 bg-gray-50 px-3 py-2 rounded-lg">
                            Segment: {{ segment?.FlightNo?.trim() || 'Unknown' }} - {{ segment?.AirlineName }}
                        </div>

                        <div v-if="extraCharges[tripIdx]?.seat?.[journeyIdx]?.[segmentIdx]?.[index]"
                            class="p-3 bg-blue-50 rounded-lg border border-blue-200 mb-4">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-gray-900">
                                    Seat: {{ selectedExtras[tripIdx]?.seat?.[journeyIdx]?.[segmentIdx]?.[index]?.SeatNumber }}
                                </p>
                                <button
                                    class="px-3 py-1 text-xs rounded-md bg-red-100 text-red-600 hover:bg-red-200 transition-colors duration-200"
                                    @click="() => removeExtraSeat(tripIdx, journeyIdx, segmentIdx, index)"
                                    title="Remove seat">
                                    Remove
                                </button>
                            </div>
                            <p class="text-xs text-gray-600">
                                Price: {{ seatLayout?.CurrencyCode || 'INR' }} {{ selectedExtras[tripIdx]?.seat?.[journeyIdx]?.[segmentIdx]?.[index]?.Fare || 0 }}
                            </p>
                        </div>

                        <Dialog v-if="segment?.Seats?.length">
                            <DialogTrigger as-child>
                                <button
                                    class="flex items-center text-primary font-medium text-sm hover:text-primary/80 transition-colors duration-200 bg-primary/5 px-4 py-2 rounded-lg hover:bg-primary/10">
                                    <PlusCircle class="w-4 h-4 mr-2" />
                                    Select seat
                                </button>
                            </DialogTrigger>
                            <DialogContent class="max-w-5xl max-h-[100vh] overflow-y-auto bg-white">
                                <DialogHeader class="pb-4 border-b border-gray-100">
                                    <DialogTitle class="text-xl font-semibold text-gray-800">
                                        Select Your Seat
                                    </DialogTitle>
                                    <DialogDescription class="text-sm text-gray-600">
                                        Choose your preferred seat for {{ segment?.FlightNo?.trim() || 'Unknown' }}
                                    </DialogDescription>
                                </DialogHeader>
                                <div class="mt-6">
                                    <h3 class="text-base font-medium text-gray-700 mb-6 bg-gray-50 px-4 py-2 rounded-lg">
                                        {{ segment?.FlightNo?.trim() || 'Unknown' }} - {{ segment?.AirlineName }}
                                    </h3>

                                    <div class="mb-8" v-if="segment.Seats.length">
                                        <h4 class="text-sm font-medium text-gray-700 mb-4">Seat Map</h4>
                                        <div class="bg-gray-50 border-2 border-gray-200 rounded-xl p-6">
                                            <!-- Seat grid header -->
                                            <div class="flex justify-center mb-4">
                                                <div class="grid grid-cols-7 gap-10 text-center">
                                                    <span v-for="col in ['A', 'B', 'C', ' ', 'D', 'E', 'F']"
                                                        :key="col"
                                                        class="text-sm font-semibold text-gray-600 w-8">
                                                        {{ col }}
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <!-- Seat rows -->
                                            <div class="space-y-3">
                                                <!-- Group seats by row -->
                                                <div v-for="row in getUniqueRows(segment.Seats)" :key="row"
                                                    class="flex items-center justify-center">
                                                    <span class="text-sm font-semibold text-gray-700 w-8 text-right mr-4">
                                                        {{ row }}
                                                    </span>
                                                    <div class="grid grid-cols-7">
                                                        <!-- Columns A-F -->
                                                        <div v-for="col in [1, 2, 3, 4, 5, 6, 7]" :key="col"
                                                            class="flex flex-col p-1 items-center">
                                                            <template v-for="seat in getSeatsByRowAndColumn(segment.Seats, row, col)"
                                                                :key="seat?.SSID">
                                                                <div v-if="seat" class="flex flex-col items-center">
                                                                    <!-- Check seat availability -->
                                                                    <div v-if="seat.AvailStatus && seat.SeatStatus === 'Open' && seat.Fare !== 0">
                                                                        <label class="w-16 h-16 border-2 p-1 rounded-lg cursor-pointer flex items-center justify-center text-sm font-semibold transition-all duration-200 hover:scale-105 bg-white"
                                                                            :class="{ 
                                                                                'border-green-500 bg-green-50 text-green-800': selectedExtras[tripIdx]?.seat?.[journeyIdx]?.[segmentIdx]?.[index]?.SSID !== seat.SSID, 
                                                                                'border-primary bg-primary/10 text-primary': selectedExtras[tripIdx]?.seat?.[journeyIdx]?.[segmentIdx]?.[index]?.SSID === seat.SSID 
                                                                            }">
                                                                            <input type="radio"
                                                                                class="sr-only"
                                                                                :name="'seat_' + tripIdx + '_' + journeyIdx + '_' + segmentIdx"
                                                                                :value="seat.SSID"
                                                                                @change="handleSeatSelection(tripIdx, journeyIdx, segmentIdx, index, seat)" />
                                                                            {{ seat.SeatNumber.slice(-1) }}
                                                                        </label>
                                                                        <span class="text-xs text-gray-500 leading-none mt-1 font-medium">
                                                                            {{ seat.Fare > 0 ? `${seatLayout?.CurrencyCode || 'INR'} ${seat.Fare}` : 'Free' }}
                                                                        </span>
                                                                    </div>
                                                                    <div v-else-if="!seat.AvailStatus || seat.SeatStatus !== 'Open' || seat.Fare === 0"
                                                                        class="w-16 h-16 border-2 border-red-300 bg-red-50 rounded-lg flex items-center justify-center text-sm text-red-600 font-semibold">
                                                                        ✕
                                                                    </div>
                                                                </div>
                                                                <div v-else
                                                                    class="w-16 h-16 border border-gray-200 bg-gray-100 rounded-lg flex items-center justify-center text-sm text-gray-400">
                                                                    ✕
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-6 flex flex-wrap gap-6 text-sm bg-white p-4 rounded-lg border border-gray-200">
                                        <div class="flex items-center gap-2">
                                            <div class="w-5 h-5 border-2 border-green-500 bg-green-50 rounded"></div>
                                            <span class="font-medium text-gray-700">Available</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-5 h-5 border-2 border-primary bg-primary/10 rounded"></div>
                                            <span class="font-medium text-gray-700">Selected</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-5 h-5 border border-red-300 bg-red-50 rounded"></div>
                                            <span class="font-medium text-gray-700">Unavailable</span>
                                        </div>
                                    </div>
                                </div>

                                <DialogFooter class="flex justify-between items-center mt-8 pt-4 border-t border-gray-100">
                                    <div v-if="selectedExtras[tripIdx]?.seat?.[journeyIdx]?.[segmentIdx]?.[index]"
                                        class="text-sm text-gray-600 font-medium">
                                        Selected: {{ selectedExtras[tripIdx]?.seat?.[journeyIdx]?.[segmentIdx]?.[index]?.SeatNumber }}
                                    </div>
                                    <div class="flex gap-3">
                                        <DialogClose as-child>
                                            <button
                                                class="px-4 py-2 text-sm border-2 border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-200 font-medium">
                                                Cancel
                                            </button>
                                        </DialogClose>
                                        <DialogClose as-child>
                                            <button
                                                class="bg-primary text-white px-6 py-2 text-sm rounded-lg hover:bg-primary/90 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors duration-200 font-medium"
                                                :disabled="!selectedExtras[tripIdx]?.seat?.[journeyIdx]?.[segmentIdx]?.[index]"
                                                @click="saveSSRExtra(tripIdx, 'seat', journeyIdx, segmentIdx, index)">
                                                Save Seat
                                            </button>
                                        </DialogClose>
                                    </div>
                                </DialogFooter>
                            </DialogContent>
                        </Dialog>
                        <p v-else class="text-sm text-gray-500 bg-gray-50 p-3 rounded-lg">
                            No seat selection available for this segment.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Show message if no seat selection is available -->
    <p v-else class="text-sm text-gray-500 bg-gray-50 p-3 rounded-lg">
        No seat selection services available.
    </p>
</TabsContent>

<!-- Priority Check-in Tab (New) -->
<TabsContent value="priority" class="p-4 sm:p-6">
    <div v-if="ancillaries?.ssrData?.Trips?.length">
        <div class="mb-6">
            <h4 class="text-lg font-semibold text-gray-800 mb-2">Priority Check-in</h4>
            <p class="text-sm text-gray-600">
                Get priority check-in service for faster airport processing.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div v-for="(trip, tripIdx) in ssrData?.Trips || []" :key="tripIdx"
                class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm">
                <h5 class="text-sm font-semibold text-gray-800 mb-4 pb-2 border-b border-gray-100">
                    {{ trip?.From }} to {{ trip?.To }}
                </h5>

                <div v-for="(journey, journeyIdx) in trip?.Journey || []" :key="journeyIdx" class="mb-6 last:mb-0">
                    <div v-for="(segment, segmentIdx) in journey?.Segments || []" :key="segmentIdx" class="mb-4">
                        <div class="font-medium text-sm text-gray-700 mb-3 bg-gray-50 px-3 py-2 rounded-lg">
                            Segment {{ segmentIdx + 1 }}: {{ segment?.VAC }} Flight
                        </div>

                        <!-- Selected priority check-in -->
                        <div v-if="extraCharges[tripIdx]?.priority?.[journeyIdx]?.[segmentIdx]?.[index]"
                            class="p-3 bg-blue-50 rounded-lg border border-blue-200 mb-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <CheckCircle class="w-4 h-4 text-blue-600" />
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            Priority Check-in Selected
                                        </p>
                                        <p class="text-xs text-gray-600">
                                            {{ ssrData?.CurrencyCode || 'INR' }} {{ selectedExtras[tripIdx]?.priority?.[journeyIdx]?.[segmentIdx]?.[index]?.Charge }}
                                        </p>
                                    </div>
                                </div>
                                <button
                                    class="px-3 py-1 text-xs rounded-md bg-red-100 text-red-600 hover:bg-red-200 transition-colors duration-200"
                                    @click="() => removeExtraPriority(tripIdx, journeyIdx, segmentIdx, index)"
                                    title="Remove priority check-in">
                                    Remove
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-2">
                                {{ selectedExtras[tripIdx]?.priority?.[journeyIdx]?.[segmentIdx]?.[index]?.Description }}
                            </p>
                        </div>

                        <!-- Priority check-in options -->
                        <div v-if="segment?.SSR?.filter(s => s.Type === '8').length" class="space-y-3">
                            <label v-for="ssr in segment?.SSR?.filter(s => s.Type === '8')" :key="ssr.ID"
                                class="flex items-center justify-between p-3 border-2 rounded-lg cursor-pointer transition-all duration-200 hover:shadow-md"
                                :class="{ 
                                    'border-primary bg-primary/5': selectedExtras[tripIdx]?.priority?.[journeyIdx]?.[segmentIdx]?.[index]?.ID === ssr.ID,
                                    'border-gray-200': selectedExtras[tripIdx]?.priority?.[journeyIdx]?.[segmentIdx]?.[index]?.ID !== ssr.ID 
                                }">
                                <div class="flex items-center">
                                    <input type="radio"
                                        class="mr-3 accent-primary w-4 h-4"
                                        :name="'priority_' + tripIdx + '_' + journeyIdx + '_' + segmentIdx"
                                        :value="ssr.ID"
                                        @change="handleSSRSelection(tripIdx, journeyIdx, segmentIdx, index, ssr, 'priority')" />
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ ssr.Description }}</p>
                                        <p class="text-xs text-gray-500">{{ ssr.PieceDescription }}</p>
                                    </div>
                                </div>
                                <div class="text-sm font-semibold text-primary">
                                    {{ ssrData?.CurrencyCode || 'INR' }} {{ ssr.Charge }}
                                </div>
                            </label>
                        </div>
                        <div v-else class="text-sm text-gray-500 p-3 bg-gray-50 rounded-lg">
                            No priority check-in options available for this segment.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</TabsContent>
                                            </Tabs>
                                        </div>
                                    </AccordionContent>
                                </AccordionItem>
                            </Accordion>
                        </div>
                    </div>
                </div>

                <div class=" w-2/5 mx-auto rounded space-y-2">
                    <!-- Flight Details Section -->
                    <div v-if="countdown !== null && countdown !== '0'" class="flex flex-col items-center justify-center bg-primary/10 border border-gray-200 p-4 mb-4 shadow-sm">
                        <div class="flex items-end gap-4">
                            <div class="flex flex-col items-center">
                                <span class="text-4xl sm:text-5xl md:text-6xl font-mono font-bold text-primary tracking-widest">
                                    {{ countdown.split(':')[0] }}
                                </span>
                            </div>
                            <span class="text-4xl sm:text-5xl md:text-6xl font-mono font-bold text-primary tracking-widest">:</span>
                            <div class="flex flex-col items-center">
                                <span class="text-4xl sm:text-5xl md:text-6xl font-mono font-bold text-primary tracking-widest">
                                    {{ countdown.split(':')[1] }}
                                </span>
                            </div>
                        </div>
                        <div class="mt-4 text-sm sm:text-base text-primary font-medium">
                            Please complete your booking before the timer expires.
                        </div>
                    </div>
                    <div class="bg-white  shadow-sm border border-gray-200 overflow-hidden">
                        <div class="bg-gray-50 border-b border-gray-200 p-3">
                           <div class="flex items-center justify-between"> <h2 class="text-base font-medium text-gray-900 mb-1">Flight Details</h2>
                        </div>
                            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                                <span class="font-medium">{{ flight?.leg?.flights[0]?.from?.city?.name }} → {{
                                    flight?.leg?.flights[0]?.to?.city?.name }}</span>
                                <span>{{ moment(flight?.leg?.flights[0]?.departure_at).format("DD MMM YYYY") }}</span>
                                <div class="flex items-center gap-1">
                                    <SquareCheckBig v-if="flight?.leg?.flights[0]?.is_refundable"
                                        class="w-3 h-3 text-green-500" />
                                    <SquareX v-else class="w-3 h-3 text-red-500" />
                                    <span
                                        :class="flight?.leg?.flights[0]?.is_refundable ? 'text-green-600' : 'text-red-600'">
                                        {{ flight?.leg?.flights[0]?.is_refundable ? 'Refundable' : 'Non-Refundable' }}
                                    </span>
                                </div>
                                <Dialog>
                                    <DialogTrigger as-child>
                                        <button
                                            class="px-3 py-1 text-xs font-medium text-white bg-primary rounded hover:bg-primary-dark">
                                            View Segment Details
                                        </button>
                                    </DialogTrigger>
                                    <DialogContent class="sm:max-w-[600px] bg-white">
                                        <DialogHeader>
                                            <DialogTitle>Flight Segment Details</DialogTitle>
                                            <DialogDescription>
                                                Detailed information about flight segments and layovers
                                            </DialogDescription>
                                        </DialogHeader>
                                        <div class="max-h-[60vh] overflow-y-auto">
                                            <div v-for="(flight, flightIndex) in flight?.leg?.flights"
                                                :key="flightIndex">
                                                <div v-for="(segment, segmentIndex) in flight?.segments"
                                                    :key="segmentIndex">
                                                    <!-- Layover Info -->
                                                    <div v-if="segment?.layover_time"
                                                        class=" border-l-4 border-amber-400 p-3 mb-4">
                                                        <div class="flex items-center justify-center">
                                                            <ClockIcon class="w-4 h-4 text-amber-600 mr-2" />
                                                            <span class="text-xs font-medium text-amber-800">
                                                                Layover: {{
                                                                    moment.utc(moment.duration(segment.layover_time,
                                                                        "minutes").asMilliseconds()).format("HH:mm") }}
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <!-- Flight Segment -->
                                                    <div class="p-4 border-b border-gray-100">
                                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                            <!-- Departure -->
                                                            <div class="space-y-2">
                                                                <div class="flex items-center space-x-2">
                                                                    <img class="w-8 h-8 border border-gray-200"
                                                                        :src="segment?.operating_carrier?.logo"
                                                                        alt="Airline" />
                                                                    <div>
                                                                        <div class="text-sm font-medium text-gray-900">
                                                                            {{
                                                                                segment?.operating_carrier?.name }}</div>
                                                                        <div class="text-xs text-gray-500">{{
                                                                            segment?.flight_number }}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="space-y-1">
                                                                    <div class="text-sm font-medium text-gray-900">{{
                                                                        formatDate(segment?.departure_at) }}</div>
                                                                    <div class="text-xs text-gray-500">{{
                                                                        segment?.from?.name }} ({{
                                                                            segment?.from?.iata }})</div>
                                                                    <div class="text-xs text-gray-400">Terminal: {{
                                                                        segment?.from_terminal?.[0] ?? "N/A" }}</div>
                                                                </div>
                                                            </div>
                                                            <!-- Flight Path -->
                                                            <div class="flex items-center justify-center">
                                                                <div class="w-full max-w-xs">
                                                                    <div class="flex items-center justify-between mb-1">
                                                                        <span
                                                                            class="text-xs font-medium text-gray-900">{{
                                                                                moment(segment?.departure_at).format("HH:mm")
                                                                            }}</span>
                                                                        <span
                                                                            class="text-xs font-medium text-gray-900">{{
                                                                                moment(segment?.arrival_at).format("HH:mm")
                                                                            }}</span>
                                                                    </div>
                                                                    <div class="relative">
                                                                        <div
                                                                            class="absolute left-0 top-1/2 transform -translate-y-1/2 w-2 h-2 bg-primary rounded-full">
                                                                        </div>
                                                                        <div
                                                                            class="h-0.5 bg-gradient-to-r from-primary to-primary/30 mx-1">
                                                                        </div>
                                                                        <div
                                                                            class="absolute right-0 top-1/2 transform -translate-y-1/2 w-2 h-2 bg-primary rounded-full">
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex items-center justify-between mt-1">
                                                                        <span class="text-xs text-gray-400">{{
                                                                            segment?.from?.iata
                                                                            }}</span>
                                                                        <span class="text-xs text-gray-400">{{
                                                                            segment?.to?.iata
                                                                            }}</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <!-- Arrival -->
                                                            <div class="space-y-2 text-right">
                                                                <div class="space-y-1">
                                                                    <div class="text-sm font-medium text-gray-900">{{
                                                                        formatDate(segment?.arrival_at) }}</div>
                                                                    <div class="text-xs text-gray-500">{{
                                                                        segment?.to?.name }} ({{
                                                                            segment?.to?.iata }})</div>
                                                                    <div class="text-xs text-gray-400">Terminal: {{
                                                                        segment?.to_terminal?.[0] ?? "N/A" }}</div>
                                                                </div>
                                                                <div class="text-xs text-gray-400">{{
                                                                    segment?.aircraft?.model }}</div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <DialogFooter>
                                            <DialogClose as-child>
                                                <button
                                                    class="px-4 py-2 text-sm font-medium text-gray-900 bg-gray-100 rounded hover:bg-gray-200">
                                                    Close
                                                </button>
                                            </DialogClose>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            </div>
                        </div>
                        <div class="divide-y divide-gray-100">
                            <div v-for="(flight, flightIndex) in flight?.leg?.flights" :key="flightIndex"
                                v-if="!flight?.has_layovers">
                                <div class="p-4">
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <!-- Departure -->
                                        <div class="space-y-2">
                                            <div class="flex items-center space-x-2">
                                                <img class="w-8 h-8 border border-gray-200"
                                                    :src="flight?.marketing_carrier?.logo" alt="Airline" />
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900">{{
                                                        flight?.marketing_carrier?.name }}
                                                    </div>
                                                    <div class="text-xs text-gray-500">{{
                                                        flight?.marketing_carrier?.iata }}</div>
                                                </div>
                                            </div>
                                            <div class="space-y-1">
                                                <div class="text-sm font-medium text-gray-900">{{
                                                    formatDate(flight?.departure_at) }}
                                                </div>
                                                <div class="text-xs text-gray-500">{{ flight?.from?.name }} ({{
                                                    flight?.from?.iata }})
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Flight Path -->
                                        <div class="flex items-center justify-center">
                                            <div class="w-full max-w-xs">
                                                <div class="flex items-center justify-between mb-1">
                                                    <span class="text-xs font-medium text-gray-900">{{
                                                        moment(flight?.departure_at).format("HH:mm") }}</span>
                                                    <span class="text-xs font-medium text-gray-900">{{
                                                        moment(flight?.arrival_at).format("HH:mm") }}</span>
                                                </div>
                                                <div class="relative">
                                                    <div
                                                        class="absolute left-0 top-1/2 transform -translate-y-1/2 w-2 h-2 bg-primary rounded-full">
                                                    </div>
                                                    <div class="h-0.5 bg-gradient-to-r from-primary to-primary/30 mx-1">
                                                    </div>
                                                    <div
                                                        class="absolute right-0 top-1/2 transform -translate-y-1/2 w-2 h-2 bg-primary rounded-full">
                                                    </div>
                                                </div>
                                                <div class="flex items-center justify-between mt-1">
                                                    <span class="text-xs text-gray-400">{{ flight?.from?.iata }}</span>
                                                    <span class="text-xs text-gray-400">{{ flight?.to?.iata }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Arrival -->
                                        <div class="space-y-2 text-right">
                                            <div class="space-y-1">
                                                <div class="text-sm font-medium text-gray-900">{{
                                                    formatDate(flight?.arrival_at) }}
                                                </div>
                                                <div class="text-xs text-gray-500">{{ flight?.to?.name }} ({{
                                                    flight?.to?.iata }})</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Price Details Section -->
                    <div class="bg-white border border-gray-200  shadow-sm">
                        <div class="p-4">
                            <h3 class="text-lg sm:text-xl font-semibold mb-3 sm:mb-4 text-gray-900">Price Details
                            </h3>
                            <div class="border border-gray-200 ">
                                <div v-for="(flight, flightIndex) in flight?.leg?.flights" :key="flightIndex">
                                    <div v-for="(fare, fareIndex) in flight?.fares" :key="fareIndex">
                                        <div v-if="selectedFares?.includes(fare.ref_id)" class="p-3 sm:p-4 space-y-1 ">
                                            
                                            <div class="flex justify-between items-center">
                                                <span class="text-xs sm:text-sm text-gray-600">Base Fare</span>
                                                <span class="text-xs sm:text-sm font-medium">
                                                    {{ formatAmount(calculateFinalPrice(fare?.base_price,
                                                        fare?.margin_amount,
                                                        fare?.margin_type, fare?.amount_type) +
                                                        parseFloat((agentData?.agent_data?.margin_amount
                                                            * route.query.passenger_count)) - parseFloat((agentData?.agent_data?.agent_discount
                                                            * route.query.passenger_count)) + agentMargin) }}
                                                </span>
                                            </div>

                                            <div class="flex justify-between items-center">
                                                <span class="text-xs sm:text-sm text-gray-600">Taxes</span>
                                                <span class="text-xs sm:text-sm font-medium">{{
                                                    formatAmount(calculateTaxes(fare)) }}</span>
                                            </div>
                                            <div v-if="ancillaries" class="flex justify-between items-center">
                                                <span class="text-xs sm:text-sm text-gray-600">Add-ons</span>
                                               <span class="text-xs sm:text-sm font-medium">
  {{
    formatAmount(
      ["baggage", "seat", "meal"].reduce((sum, group) => {
        const extrasGroup = selectedExtras[flightIndex]?.[group] || {};

        const groupTotal = Object.values(extrasGroup).reduce(
          (segmentSum, segment) => {
            if (!segment) return segmentSum;

            return (
              segmentSum +
              Object.values(segment).reduce((paxSum, pax) => {
                if (!pax) return paxSum;

                return (
                  paxSum +
                  Object.values(pax).reduce((itemSum, item) => {
                    // Your actual price field
                    const price =
                      item.Charge ??
                      item.SSRNetAmount ??
                      0;

                    return itemSum + Number(price);
                  }, 0)
                );
              }, 0)
            );
          },
          0
        );

        return sum + groupTotal;
      }, 0)
    )
  }}
</span>

                                            </div>

                                            <hr class="border-dashed border-gray-300" />
                                            <div
                                                class="flex justify-between items-center bg-gray-50 p-2 sm:p-3 rounded-lg">
                                                <span class="text-xs sm:text-sm font-medium text-gray-700">Amount</span>
                                                <span class="text-sm sm:text-base font-bold text-primary">
  {{
    formatAmount(
      calculateTotalFare(fare) +
      ["baggage", "seat", "meal"].reduce((sum, group) => {
        const groupData = selectedExtras[flightIndex]?.[group] || {};

        const groupTotal = Object.values(groupData).reduce(
          (segmentSum, segment) => {
            if (!segment) return segmentSum;

            return (
              segmentSum +
              Object.values(segment).reduce((paxSum, pax) => {
                if (!pax) return paxSum;

                return (
                  paxSum +
                  Object.values(pax).reduce((itemSum, item) => {
                    const price =
                      Number(item?.Charge ?? item?.SSRNetAmount ?? 0);

                    return itemSum + price;
                  }, 0)
                );
              }, 0)
            );
          },
          0
        );

        return sum + groupTotal;
      }, 0)
    )
  }}
</span>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex justify-between mt-3 sm:mt-4 items-center bg-gray-50 p-3  rounded-lg">
                                <span class="text-base sm:text-sm font-semibold text-gray-900">Total Amount</span>
                                <span class="text-sm sm:text-lg font-bold text-primary">
                                    {{ formatAmount(amount = calculateGrandTotal()) }}
                                </span>
                            </div>

                            <!-- Terms and Conditions -->
                            <div class="flex items-start gap-2 sm:gap-3 p-3 sm:p-4 mt-3 sm:mt-4 bg-gray-50 rounded-lg">
                                <Input type="checkbox" v-model="termsAccepted" id="terms" class="mt-1" />
                                <Label for="terms" class="text-xs sm:text-sm text-gray-700 leading-relaxed">
                                    I understand and agree with the Privacy Policy, the User
                                    <a href="#" @click.prevent="openTermsDialog"
                                        class="text-primary underline hover:text-blue-800">
                                        Agreement and Terms
                                    </a>
                                    of Service of limatravelstours.com
                                </Label>
                            </div>

                            <!-- Payment Buttons -->
                            <div class="space-y-2">
                                <Button @click="togglePreview()" :disabled="!termsAccepted"
                                    class="w-full bg-primary hover:bg-primary/50 text-white py-2 sm:py-2.5 rounded-lg font-medium text-xs sm:text-sm">
                                    Save & Preview
                                </Button>

                
                            </div>

                            <!-- Error Display -->
                            <div v-if="globalError"
                                class="mt-3 sm:mt-4 p-2 sm:p-3 bg-red-100 text-red-700 rounded-lg text-xs sm:text-sm">
                                {{ globalError }}
                            </div>
                        </div>
                    </div>

                    <!-- Dialogs -->
                    <!-- Low Balance Dialog -->
                    <div v-if="isLowBalanceDialogOpen"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                        @click.self="isLowBalanceDialogOpen = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Low Balance Warning</h3>
                                <button @click="isLowBalanceDialogOpen = false"
                                    class="text-gray-400 hover:text-gray-500">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <p class="text-sm text-gray-600 mb-6">
                                Your balance is insufficient to confirm this booking. Please add funds to your account.
                            </p>
                            <div class="flex justify-end space-x-3">
                                <button @click="isLowBalanceDialogOpen = false"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button @click="$router.push({ name: 'Deposits' })"
                                    class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/20">
                                    Go To Deposit
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Confirm Dialog -->
                    <div v-if="isConfirmDialogOpen"
                        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                        @click.self="isConfirmDialogOpen = false">
                        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
                            <div class="flex items-start justify-between mb-4">
                                <h3 class="text-lg font-medium text-gray-900">
                                    <span v-if="paymentMethod === 'hold'">Book Now</span>
                                    <span v-else-if="paymentMethod === 'pay'">Confirm Booking</span>
                                </h3>
                                <button @click="isConfirmDialogOpen = false" class="text-gray-400 hover:text-gray-500">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>
                            <p class="text-sm text-gray-600 mb-4">
                                <span v-if="paymentMethod === 'hold'">Are you sure you want to book this?</span>
                                <span v-else-if="paymentMethod === 'pay'">Are you sure you want to confirm this
                                    booking?</span>
                            </p>
                            <div v-if="error" class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                                {{ error }}
                            </div>
                            <div class="flex justify-end space-x-3">
                                <button @click="isConfirmDialogOpen = false"
                                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
                                    Cancel
                                </button>
                                <button @click="saveBooking"
                                    class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/20">
                                    Confirm Booking
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
        <div v-if="isTermsDialogOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
            <div class="bg-white max-w-3xl w-full rounded-lg p-6 shadow-lg overflow-y-auto max-h-[80vh]">

                <h2 class="text-lg font-semibold text-gray-800 mb-2">Agreement and Terms</h2>
                <p class="text-sm text-gray-600 mb-4">Review the information below carefully.</p>

                <div class="space-y-6 text-sm text-gray-700 leading-relaxed">

                    <div>
                        <h3 class="text-base font-semibold mb-2">Check-in Time</h3>
                        <p>
                            Check-in counters open 3 hours prior to departure of flight and close 1 hour prior to
                            departure of flight.<br>
                            You may be denied boarding if you fail to report on time.
                        </p>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold mb-2">Ticketing & Reservation</h3>
                        <p class="space-y-2">
                            <span>1. Please re-check the passenger name(s) as per the passport/identity proof, departure
                                & arrival date, time, flight number, terminal, baggage details etc.</span><br>
                            <span>2. The local authorities in certain countries may impose additional taxes (VAT,
                                tourist tax etc.), which generally has to be paid locally.</span><br>
                            <span>3. In case of international travel, please check your requirements for travel
                                documentation like valid Passport/visa/Ok to Board/travel and medical insurance with the
                                airline and relevant Embassy or Consulate before commencing your journey.</span><br>
                            <span>4. We are not responsible for any schedule change or flight cancellation by the
                                airline before and after issuance of the tickets.</span><br>
                            <span>5. Wonder Travel will not be held responsible for any loss or damage to traveler(s)
                                and their belongings due to any accident, theft, or unforeseen circumstances.</span>
                        </p>
                    </div>

                    <div>
                        <h3 class="text-base font-semibold mb-2">Amendment & Cancellation</h3>
                        <p class="space-y-2">
                            <span>1. For any amendments, cancellation, or ancillary services for flights, contact our
                                24x7 reservation team at
                                <a href="tel:+971529013757" class="underline text-blue-500 hover:text-blue-700">+971 52
                                    901 3757</a>
                                or email
                                <a href="mailto:support@flyunique.pk"
                                    class="underline text-blue-500 hover:text-blue-700">support@flyunique.pk</a>.
                            </span><br>
                            <span>2. Any amendments will be subject to the airline's terms, penalties, and fare
                                differences.</span><br>
                            <span>3. Cancellation/refund will be handled on a case-by-case basis based on airline and
                                agency policies.</span>
                        </p>
                    </div>


                </div>

                <div class="mt-6 text-right">
                    <button @click="closeTermsDialog"
                        class="bg-primary text-white px-4 py-2 rounded-sm hover:bg-primary/80">
                        Close
                    </button>
                </div>

            </div>
        </div>
    </div>
   
<div v-if="showPreview && !isLoading" class="flex flex-col min-h-screen bg-gray-50">
    <!-- Header -->
    <div class="bg-white border-b rounded-lg flex justify-between border-gray-200 p-4">
      <div><h2 class="text-xl font-semibold text-gray-800">Booking Preview</h2>
      <p class="text-sm text-gray-600">Review your booking details below before confirming.</p></div>
      <Button @click="togglePreview" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200">
        Back to Form
      </Button>
    </div>
    <div class="flex flex-col lg:flex-row gap-6 ">
      <!-- Left Section: Contact and Traveller Details -->
      <div class="w-full lg:w-3/4 space-y-4">
        <!-- Contact Information -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <div class="border-b p-4">
            <span class="text-sm font-bold text-gray-800">Contact Information</span>
          </div>
          <div class="p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
              <p class="text-sm font-medium text-gray-700">Email</p>
              <p class="text-sm text-gray-900">{{ mainContact.email || 'Not provided' }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-700">Phone</p>
              <p class="text-sm text-gray-900">{{ mainContact.phone || 'Not provided' }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-700">Country</p>
              <p class="text-sm text-gray-900">{{ mainContact.country || 'Not provided' }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-700">Agency Contact</p>
              <p class="text-sm text-gray-900">{{ agencyContact.phone || 'Not provided' }}</p>
            </div>
            <div>
              <p class="text-sm font-medium text-gray-700">Agency Email</p>
              <p class="text-sm text-gray-900">{{ agencyContact.email || 'Not provided' }}</p>
            </div>
          </div>
        </div>

        <!-- Traveller Details -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <div class="p-4 bg-gradient-to-r from-gray-50 to-white">
            <p class="text-lg font-semibold text-gray-800">Traveller Details</p>
          </div>
          <div v-for="(traveller, index) in travellers" :key="`traveller-${index}`" class="border-t border-gray-100">
            <div class="p-4">
              <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                  <span class="text-primary font-semibold text-sm">{{ index + 1 }}</span>
                </div>
                <h3 class="text-lg font-semibold text-gray-800">{{ traveller.type }} Traveller {{ index + 1 }}</h3>
              </div>
              <!-- Personal Information -->
              <div class=" grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                <div>
                  <p class="text-sm font-medium text-gray-700">Gender</p>
                  <p class="text-sm text-gray-900">{{ traveller.gender === 'M' ? 'Male' : traveller.gender === 'F' ? 'Female' : 'Not provided' }}</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-700">Title</p>
                  <p class="text-sm text-gray-900">{{ traveller.title || 'Not provided' }}</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-700">First Name</p>
                  <p class="text-sm text-gray-900">{{ traveller.firstName || 'Not provided' }}</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-700">Last Name</p>
                  <p class="text-sm text-gray-900">{{ traveller.lastName || 'Not provided' }}</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-700">Date of Birth</p>
                  <p class="text-sm text-gray-900">{{ traveller.dob ? formatDate(traveller.dob) : 'Not provided' }}</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-700">Nationality</p>
                  <p class="text-sm text-gray-900">{{ traveller.nationality || 'Not provided' }}</p>
                </div>
              </div>
              <!-- Document Information -->
              <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                  <p class="text-sm font-medium text-gray-700">Document Type</p>
                  <p class="text-sm text-gray-900">{{ traveller.documentType || 'Not provided' }}</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-700">Document Number</p>
                  <p class="text-sm text-gray-900">{{ traveller.documentNo || 'Not provided' }}</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-700">Expiry Date</p>
                  <p class="text-sm text-gray-900">{{ traveller.expiryDate ? formatDate(traveller.expiryDate) : 'Not provided' }}</p>
                </div>
                <div>
                  <p class="text-sm font-medium text-gray-700">Issue Country</p>
                  <p class="text-sm text-gray-900">{{ traveller.issueCountry || 'Not provided' }}</p>
                </div>
              </div>
              <!-- Extra Services -->
              <div v-if="traveller.showAncillaries" class="mt-4">
                <h4 class="text-base font-semibold text-gray-800 mb-2">Extra Services</h4>
                <div v-for="(flight, flightIdx) in flight?.leg?.flights" :key="flightIdx" class="mb-4">
                  <p class="text-sm font-medium text-gray-700">{{ flight?.from?.city?.name }} to {{ flight?.to?.city?.name }}</p>
                  <div v-for="(segment, segmentIdx) in flight?.segments" :key="segmentIdx" class="ml-4">
                    <p class="text-sm text-gray-600">Segment: {{ segment?.from?.name }} → {{ segment?.to?.name }}</p>
                    <div v-if="selectedExtras[flightIdx]?.baggage?.[segmentIdx]?.[index]">
                      <p class="text-sm text-gray-900">Baggage: {{ selectedExtras[flightIdx]?.baggage?.[segmentIdx]?.[index]?.title }} ({{ selectedExtras[flightIdx]?.baggage?.[segmentIdx]?.[index]?.currency }} {{ selectedExtras[flightIdx]?.baggage?.[segmentIdx]?.[index]?.amount }})</p>
                    </div>
                    <div v-if="selectedExtras[flightIdx]?.seat?.[segmentIdx]?.[index]">
                      <p class="text-sm text-gray-900">Seat: {{ selectedExtras[flightIdx]?.seat?.[segmentIdx]?.[index]?.title }} ({{ selectedExtras[flightIdx]?.seat?.[segmentIdx]?.[index]?.currency }} {{ selectedExtras[flightIdx]?.seat?.[segmentIdx]?.[index]?.amount }})</p>
                    </div>
                    <div v-if="selectedExtras[flightIdx]?.meal?.[segmentIdx]?.[index]">
                      <p class="text-sm text-gray-900">Meal: {{ selectedExtras[flightIdx]?.meal?.[segmentIdx]?.[index]?.title }} x{{ selectedExtras[flightIdx]?.meal?.[segmentIdx]?.[index]?.qty || 1 }} ({{ selectedExtras[flightIdx]?.meal?.[segmentIdx]?.[index]?.currency }} {{ selectedExtras[flightIdx]?.meal?.[segmentIdx]?.[index]?.amount }})</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Section: Flight and Price Details -->
      <div class="w-full lg:w-2/5 space-y-4">
        <!-- Flight Details -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <div class="bg-gray-50 border-b border-gray-200 p-3">
            <h2 class="text-base font-medium text-gray-900">Flight Details</h2>
            <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
              <span class="font-medium">{{ flight?.leg?.flights[0]?.from?.city?.name }} → {{ flight?.leg?.flights[0]?.to?.city?.name }}</span>
              <span>{{ moment(flight?.leg?.flights[0]?.departure_at).format("DD MMM YYYY") }}</span>
              <div class="flex items-center gap-1">
                <SquareCheckBig v-if="flight?.leg?.flights[0]?.is_refundable" class="w-3 h-3 text-green-500" />
                <SquareX v-else class="w-3 h-3 text-red-500" />
                <span :class="flight?.leg?.flights[0]?.is_refundable ? 'text-green-600' : 'text-red-600'">
                  {{ flight?.leg?.flights[0]?.is_refundable ? 'Refundable' : 'Non-Refundable' }}
                </span>
              </div>
            </div>
          </div>
          <div v-for="(flight, flightIndex) in flight?.leg?.flights" :key="flightIndex" class="p-4 border-b border-gray-100 last:border-b-0">
            <div v-for="(segment, segmentIndex) in flight?.segments" :key="segmentIndex">
              <div v-if="segment?.layover_time" class="border-l-4 border-amber-400 p-3 mb-4">
                <div class="flex items-center justify-center">
                  <ClockIcon class="w-4 h-4 text-amber-600 mr-2" />
                  <span class="text-xs font-medium text-amber-800">
                    Layover: {{ moment.utc(moment.duration(segment.layover_time, "minutes").asMilliseconds()).format("HH:mm") }}
                  </span>
                </div>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="space-y-2">
                  <div class="flex items-center space-x-2">
                    <img class="w-8 h-8 border border-gray-200" :src="segment?.operating_carrier?.logo" alt="Airline" />
                    <div>
                      <div class="text-sm font-medium text-gray-900">{{ segment?.operating_carrier?.name }}</div>
                      <div class="text-xs text-gray-500">{{ segment?.flight_number }}</div>
                    </div>
                  </div>
                  <div class="space-y-1">
                    <div class="text-sm font-medium text-gray-900">{{ formatDate(segment?.departure_at) }}</div>
                    <div class="text-xs text-gray-500">{{ segment?.from?.name }} ({{ segment?.from?.iata }})</div>
                    <div class="text-xs text-gray-400">Terminal: {{ segment?.from_terminal?.[0] ?? "N/A" }}</div>
                  </div>
                </div>
                <div class="flex items-center justify-center">
                  <div class="w-full max-w-xs">
                    <div class="flex items-center justify-between mb-1">
                      <span class="text-xs font-medium text-gray-900">{{ moment(segment?.departure_at).format("HH:mm") }}</span>
                      <span class="text-xs font-medium text-gray-900">{{ moment(segment?.arrival_at).format("HH:mm") }}</span>
                    </div>
                    <div class="relative">
                      <div class="absolute left-0 top-1/2 transform -translate-y-1/2 w-2 h-2 bg-primary rounded-full"></div>
                      <div class="h-0.5 bg-gradient-to-r from-primary to-primary/30 mx-1"></div>
                      <div class="absolute right-0 top-1/2 transform -translate-y-1/2 w-2 h-2 bg-primary rounded-full"></div>
                    </div>
                    <div class="flex items-center justify-between mt-1">
                      <span class="text-xs text-gray-400">{{ segment?.from?.iata }}</span>
                      <span class="text-xs text-gray-400">{{ segment?.to?.iata }}</span>
                    </div>
                  </div>
                </div>
                <div class="space-y-2 text-right">
                  <div class="space-y-1">
                    <div class="text-sm font-medium text-gray-900">{{ formatDate(segment?.arrival_at) }}</div>
                    <div class="text-xs text-gray-500">{{ segment?.to?.name }} ({{ segment?.to?.iata }})</div>
                    <div class="text-xs text-gray-400">Terminal: {{ segment?.to_terminal?.[0] ?? "N/A" }}</div>
                  </div>
                  <div class="text-xs text-gray-400">{{ segment?.aircraft?.model }}</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Price Details -->
        <div class="bg-white border border-gray-200 rounded-lg shadow-sm">
          <div class="p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Price Details</h3>
            <div class="border border-gray-200 rounded-lg">
              <div v-for="(flight, flightIndex) in flight?.leg?.flights" :key="flightIndex">
                <div v-for="(fare, fareIndex) in flight?.fares" :key="fareIndex">
                  <div v-if="selectedFares?.includes(fare.ref_id)" class="p-4 space-y-2">
                    <div class="flex justify-between items-center">
                      <span class="text-sm text-gray-600">Base Fare</span>
                      <span class="text-sm font-medium">{{ formatAmount(calculateFinalPrice(fare?.base_price, fare?.margin_amount, fare?.margin_type, fare?.amount_type) + parseFloat((agentData?.agent_data?.margin_amount * route.query.passenger_count)) + agentMargin) }}</span>
                    </div>
                    <div class="flex justify-between items-center">
                      <span class="text-sm text-gray-600">Taxes</span>
                      <span class="text-sm font-medium">{{ formatAmount(calculateTaxes(fare)) }}</span>
                    </div>
                    <div v-if="ancillaries" class="flex justify-between items-center">
                      <span class="text-sm text-gray-600">Add-ons</span>
                      <span class="text-xs sm:text-sm font-medium">
  {{
    formatAmount(
      ["baggage", "seat", "meal"].reduce((sum, group) => {
        const extrasGroup = selectedExtras[flightIndex]?.[group] || {};

        const groupTotal = Object.values(extrasGroup).reduce(
          (segmentSum, segment) => {
            if (!segment) return segmentSum;

            return (
              segmentSum +
              Object.values(segment).reduce((paxSum, pax) => {
                if (!pax) return paxSum;

                return (
                  paxSum +
                  Object.values(pax).reduce((itemSum, item) => {
                    // Your actual price field
                    const price =
                      item.Charge ??
                      item.SSRNetAmount ??
                      0;

                    return itemSum + Number(price);
                  }, 0)
                );
              }, 0)
            );
          },
          0
        );

        return sum + groupTotal;
      }, 0)
    )
  }}
</span>
                    </div>
                    <hr class="border-dashed border-gray-300" />
                    <div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg">
                      <span class="text-sm font-medium text-gray-700">Amount</span>
                      <span class="text-sm sm:text-base font-bold text-primary">
  {{
    formatAmount(
      calculateTotalFare(fare) +
      ["baggage", "seat", "meal"].reduce((sum, group) => {
        const groupData = extraCharges[flightIndex]?.[group] || {};

        const groupTotal = Object.values(groupData).reduce(
          (segmentSum, segment) => {
            if (!segment) return segmentSum;

            return (
              segmentSum +
              Object.values(segment).reduce((paxSum, pax) => {
                if (!pax) return paxSum;

                return (
                  paxSum +
                  Object.values(pax).reduce((itemSum, item) => {
                    const price =
                      Number(item?.Charge ?? item?.SSRNetAmount ?? 0);

                    return itemSum + price;
                  }, 0)
                );
              }, 0)
            );
          },
          0
        );

        return sum + groupTotal;
      }, 0)
    )
  }}
</span>

                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="flex justify-between items-center bg-gray-50 p-3 rounded-lg mt-4">
              <span class="text-sm font-semibold text-gray-900">Total Amount</span>
              <span class="text-lg font-bold text-primary">{{ formatAmount(calculateGrandTotal()) }}</span>
            </div>
          </div>
        </div>
         <div class="space-y-2">
                                <!-- <Button @click="handlePaymentMethod('pay')" :disabled="!termsAccepted"
                                    class="w-full bg-primary hover:bg-primary/50 text-white py-2 sm:py-2.5 rounded-lg font-medium text-xs sm:text-sm">
                                    Pay & Confirm
                                </Button> -->

                                <!-- Payment Methods -->
                                <!-- <div v-if="isPaymentMethodsVisible"
                                    class="grid grid-cols-2 gap-2 p-2 sm:p-3 bg-gray-50 rounded-lg">
                                    <div v-if="agentData.is_card_allowed"  @click="handlePaymentMethod('card')"
                                        class="cursor-pointer hover:bg-white border border-gray-200 rounded-lg p-2 sm:p-3 flex flex-col items-center gap-1.5 sm:gap-2 transition-colors">
                                        <img src="/public/assets/credit-card.png" width="20" height="20"
                                            alt="Credit Card" class="w-5 h-5 sm:w-10 sm:h-12" />
                                        <p class="text-xs font-medium">Pay with Card</p>
                                    </div>

                                    <div @click="handleConfirmDialogOpen()"
                                        class="cursor-pointer hover:bg-white border border-gray-200 rounded-lg p-2 sm:p-3 flex flex-col items-center gap-1.5 sm:gap-2 transition-colors">
                                        <img src="/public/assets/wallet.png" width="20" height="20" alt="Wallet"
                                            class="w-5 h-5 sm:w-6 sm:h-6" />
                                        <p class="text-xs font-medium">{{ isSubmitting ? "Wait..." : "Pay from Wallet"
                                            }}</p>
                                        <p class="text-xs text-gray-600">{{ formatAmount(agentLedger?.balance) }}</p>
                                    </div>
                                </div> -->

                                <Button  @click="handlePaymentMethod('hold')"   :disabled="isPaymentMethodsVisible"
                                    class="w-full bg-primary hover:bg-primary/50 text-white py-2 sm:py-2.5 rounded-lg font-medium text-xs sm:text-sm disabled:opacity-50">
                                    {{ isSubmitting ? "Saving..." : "Book & Hold" }}
                                </Button>
                            </div>
      </div>
    </div>

   
    <!-- Terms Dialog -->
    <div v-if="isTermsDialogOpen" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div class="bg-white max-w-3xl w-full rounded-lg p-6 shadow-lg overflow-y-auto max-h-[80vh]">
        <h2 class="text-lg font-semibold text-gray-800 mb-2">Agreement and Terms</h2>
        <p class="text-sm text-gray-600 mb-4">Review the information below carefully.</p>
        <div class="space-y-6 text-sm text-gray-700 leading-relaxed">
          <div>
            <h3 class="text-base font-semibold mb-2">Check-in Time</h3>
            <p>
              Check-in counters open 3 hours prior to departure of flight and close 1 hour prior to departure of flight.<br>
              You may be denied boarding if you fail to report on time.
            </p>
          </div>
          <div>
            <h3 class="text-base font-semibold mb-2">Ticketing & Reservation</h3>
            <p class="space-y-2">
              <span>1. Please re-check the passenger name(s) as per the passport/identity proof, departure & arrival date, time, flight number, terminal, baggage details etc.</span><br>
              <span>2. The local authorities in certain countries may impose additional taxes (VAT, tourist tax etc.), which generally has to be paid locally.</span><br>
              <span>3. In case of international travel, please check your requirements for travel documentation like valid Passport/visa/Ok to Board/travel and medical insurance with the airline and relevant Embassy or Consulate before commencing your journey.</span><br>
              <span>4. We are not responsible for any schedule change or flight cancellation by the airline before and after issuance of the tickets.</span><br>
              <span>5. Wonder Travel will not be held responsible for any loss or damage to traveler(s) and their belongings due to any accident, theft, or unforeseen circumstances.</span>
            </p>
          </div>
          <div>
            <h3 class="text-base font-semibold mb-2">Amendment & Cancellation</h3>
            <p class="space-y-2">
              <span>1. For any amendments, cancellation, or ancillary services for flights, contact our 24x7 reservation team at <a href="tel:+971529013757" class="underline text-blue-500 hover:text-blue-700">+971 52 901 3757</a> or email <a href="mailto:support@flyunique.pk" class="underline text-blue-500 hover:text-blue-700">support@flyunique.pk</a>.</span><br>
              <span>2. Any amendments will be subject to the airline's terms, penalties, and fare differences.</span><br>
              <span>3. Cancellation/refund will be handled on a case-by-case basis based on airline and agency policies.</span>
            </p>
          </div>
        </div>
        <div class="mt-6 text-right">
          <button @click="closeTermsDialog" class="bg-primary text-white px-4 py-2 rounded-sm hover:bg-primary/80">Close</button>
        </div>
      </div>
    </div>

    <!-- Low Balance Dialog -->
    <div v-if="isLowBalanceDialogOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" @click.self="isLowBalanceDialogOpen = false">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex items-start justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">Low Balance Warning</h3>
          <button @click="isLowBalanceDialogOpen = false" class="text-gray-400 hover:text-gray-500">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <p class="text-sm text-gray-600 mb-6">Your balance is insufficient to confirm this booking. Please add funds to your account.</p>
        <div class="flex justify-end space-x-3">
          <button @click="isLowBalanceDialogOpen = false" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
          <button @click="$router.push({ name: 'Deposits' })" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/20">Go To Deposit</button>
        </div>
      </div>
    </div>

    <!-- Confirm Dialog -->
    <div v-if="isConfirmDialogOpen" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" @click.self="isConfirmDialogOpen = false">
      <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
        <div class="flex items-start justify-between mb-4">
          <h3 class="text-lg font-medium text-gray-900">
            <span v-if="paymentMethod === 'hold'">Book Now</span>
            <span v-else-if="paymentMethod === 'pay'">Confirm Booking</span>
          </h3>
          <button @click="isConfirmDialogOpen = false" class="text-gray-400 hover:text-gray-500">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <p class="text-sm text-gray-600 mb-4">
          <span v-if="paymentMethod === 'hold'">Are you sure you want to book this?</span>
          <span v-else-if="paymentMethod === 'pay'">Are you sure you want to confirm this booking?</span>
        </p>
        <div v-if="error" class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">{{ error }}</div>
        <div class="flex justify-end space-x-3">
          <button @click="isConfirmDialogOpen = false" class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
          <button @click="saveBooking" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/20">Confirm Booking</button>
        </div>
      </div>
    </div>
  </div>
<!-- API Error Dialog for HOLD payment method -->
<!-- <div v-if="apiErrors" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
    <div class="bg-white max-w-md w-full rounded-lg p-6 shadow-lg text-center relative">
        <button
            class="absolute top-2 right-2 text-gray-400 hover:text-gray-600"
            @click="router.push({ name: 'DashboardFlights' })"
            aria-label="Close"
        >
            <XIcon class="w-5 h-5" />
        </button>
        <h2 class="text-lg font-semibold text-red-600 mb-2">Booking Error</h2>
        <p class="text-sm text-gray-700 mb-4">
            There was a problem processing your booking. Please try again or contact support.
        </p>
        <Button
            class="bg-primary text-white px-4 py-2 rounded hover:bg-primary/80"
            @click="router.push({ name: 'DashboardFlights' })"
        >
            Go to Flights
        </Button>
    </div>
</div> -->
    <div v-if="isOpen && !apiErrors" class="fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-30 p-4">
        <div
            class="bg-white rounded-2xl shadow-2xl border border-gray-100 p-8 w-full max-w-2xl transition-all duration-300">
            <div v-if="apiErrors" class="float-right cursor-pointer">
                <router-link :to="{ name: 'DashboardFlights' }" class="float-right cursor-pointer"
                    @click.native="isOpen = false">
                    <XIcon />
                </router-link>
            </div>

            <!-- Header with Spinner -->
            <div v-if="currentState < 2" class="text-center mb-8">
                <div class="flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-primary animate-spin mr-3" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                        </circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    <h2 class="text-2xl font-bold text-gray-900">Ticket Status</h2>
                </div>
                <div class="w-16 h-0.5 bg-primary mx-auto rounded-full"></div>
            </div>

            <!-- Header with Check Mark (when complete) -->
            <div v-if="currentState === 2" class="text-center mb-8">
                <div class="flex items-center justify-center mb-4">
                    <div class="w-8 h-8 bg-primary rounded-full flex items-center justify-center mr-3">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="text-2xl font-bold text-primary">Complete</h2>
                </div>
                <div class="w-16 h-0.5 bg-primary mx-auto rounded-full"></div>
            </div>

            <!-- Progress Steps -->
            <div class="relative flex items-center justify-between mb-8 px-4">

                <!-- Pending Ticket -->
                <div class="flex flex-col items-center relative z-10">
                    <div :class="[
                        'w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all duration-500',
                        currentState >= 0 ? 'bg-primary border-primary text-white shadow-lg shadow-primary/30' : 'bg-white border-gray-300 text-gray-400'
                    ]">
                        <svg v-if="currentState >= 0" class="w-4 h-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else class="text-xs font-bold">1</span>
                    </div>
                    <span class="mt-3 text-sm font-semibold text-gray-700">Pending</span>
                </div>

                <!-- Progress Line -->
                <div class="flex-1 h-0.5 bg-gray-200 mx-6 rounded-full relative">
                    <div class="h-full bg-primary rounded-full transition-all duration-800 ease-out"
                        :style="{ width: currentState >= 1 ? '100%' : '0%' }"></div>
                </div>

                <!-- Booked Ticket -->
                <div class="flex flex-col items-center relative z-10">
                    <div :class="[
                        'w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all duration-500',
                        currentState >= 1 ? 'bg-primary border-primary text-white shadow-lg shadow-primary/30' : 'bg-white border-gray-300 text-gray-400'
                    ]">
                        <svg v-if="currentState >= 1" class="w-4 h-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else class="text-xs font-bold">2</span>
                    </div>
                    <span class="mt-3 text-sm font-semibold text-gray-700">Booked</span>
                </div>

                <!-- Progress Line -->
                <div class="flex-1 h-0.5 bg-gray-200 mx-6 rounded-full relative">
                    <div class="h-full bg-primary rounded-full transition-all duration-800 ease-out"
                        :style="{ width: currentState >= 2 ? '100%' : '0%' }"></div>
                </div>

                <!-- Confirmed Ticket -->
                <div class="flex flex-col items-center relative z-10">
                    <div :class="[
                        'w-8 h-8 rounded-full flex items-center justify-center border-2 transition-all duration-500',
                        currentState === 2 ? 'bg-primary border-primary text-white shadow-lg shadow-primary/30' : 'bg-white border-gray-300 text-gray-400'
                    ]">
                        <svg v-if="currentState === 2" class="w-4 h-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span v-else class="text-xs font-bold">3</span>
                    </div>
                    <span class="mt-3 text-sm font-semibold text-gray-700">Confirmed</span>
                </div>
            </div>

            <!-- Status Message -->
            <div class="text-center  mb-8">
                <div class="bg-gray-50 rounded-xl p-6 border border-gray-100">
                    <p class="text-gray-700 font-medium text-lg">
                        {{
                            currentState === 0 ? 'Your ticket booking is pending...' :
                                currentState === 1 ? 'Your ticket is booked! Awaiting confirmation...' :
                                    'Your ticket is confirmed! Ready to go!'
                        }}
                    </p>
                </div>
            </div>

            <!-- Close Button -->
            <div class="flex justify-end">
                <button :disabled="isDisabled == true" @click="closeDialogue"
                    class="px-8 py-2.5 bg-primary hover:bg-primary/90 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 border-0 disabled:opacity-50 disabled:cursor-not-allowed">
                    View Booking
                </button>
            </div>
        </div>
    </div>
    <div v-if="showPaymentDialog" class="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex items-start justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Enter Payment Details</h3>
                <button @click="closePaymentDialog" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <!-- Payment note -->


            <div id="card-element" class=" p-3 border rounded bg-gray-50"></div>

            <p class="text-red-400 text-xs text-right my-2">3% card payment fee: {{ formatAmount(Number(amount) * 0.03)
            }}</p>
            <div v-if="paymentError" class="text-red-500 mb-2 text-sm">{{ paymentError }}</div>

            <div class="flex justify-end space-x-3">
                <Button @click="closePaymentDialog" class="bg-gray-200 text-gray-700">
                    Cancel
                </Button>
                <Button @click="handlePayment" :disabled="processing" class="bg-primary hover:bg-primary/90 text-white">
                    Pay {{ formatAmount(amount + (amount * (0.03))) }}
                </Button>
            </div>
        </div>

    </div>



</template>
<style scoped>
.booking-form {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.form-section {
    margin-bottom: 30px;
    background-color: #f9f9f9;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.traveller-card {
    background-color: white;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.form-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
    min-width: 200px;
    margin-bottom: 15px;
}

label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.required {
    color: #e53e3e;
}

#card-element {
    min-height: 60px;
    /* Increased height for better UX */
    width: 100%;
}

.form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 4px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
}

.is-invalid {
    border-color: #e53e3e;
}

.error-message {
    color: #e53e3e;
    font-size: 12px;
    margin-top: 4px;
}

.global-error {
    background-color: #fee2e2;
    color: #b91c1c;
    padding: 12px;
    border-radius: 4px;
    margin-top: 20px;
    text-align: center;
}

.form-actions {
    margin-top: 30px;
    text-align: center;
}

.btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background-color: #3b82f6;
    color: white;
    border: none;
}

.btn-primary:hover:not(:disabled) {
    background-color: #2563eb;
}

.btn:disabled {
    opacity: 0.7;
    cursor: not-allowed;
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
    }

    .form-group {
        min-width: 100%;
    }
}
</style>
<style scoped>
.animate-fade-in {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
