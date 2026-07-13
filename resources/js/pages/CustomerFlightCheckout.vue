<script setup>
import Spinner from "@/components/common/Spinner.vue";
import Button from "@/components/ui/button/Button.vue";
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { ArrowLeft, CheckCircle, ClockIcon, PlusCircle, SquareCheckBig, SquareX, Upload, XCircle } from "lucide-vue-next";
import {
    FETCH_COUNTRIES,
    FETCH_FLIGHT,
    SAVE_BOOKING,
    SAVE_BOOKING_DATA,
    FETCH_AGENT_DATA,
    FETCH_BOOKING_STATS_SETINGS,
    FETCH_CUSTOMER_MARGIN,
    FETCH_SAFE_PAY_URL,
    SEND_PAYMENT_REQUEST,
    CONFIRM_BOOKING,
    SEND_SOOPER_QOUTE,
    FETCH_ANCILLARIES,
    PATCH_ANCILLARIES,
} from "@/services/store/actions.type";
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
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
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
import moment from "moment";
import { computed, onMounted, ref, watch, reactive, nextTick } from "vue";
import { useRoute } from "vue-router";
import { useStore } from "vuex";
import { useAuthStore } from "@/services/stores/auth";

import Badge from "@/components/ui/badge/Badge.vue";
import {
    cn,
    formatAmount,
    calculateLayoverDetails,
    adjustDateTime,
    getAdjustedDateTime,
    formatDate,
    calculateCustomerPrice,
} from "@/lib/utils";
import { Check, ChevronsUpDown } from "lucide-vue-next";
import { calculateLayover, formatDuration } from "@/lib/utils";
import { calculateFinalPrice } from "@/lib/utils.js";

import { useRouter } from "vue-router";
import TopBar from "@/components/shared/TopBar.vue";
import Nav from "@/components/shared/Nav.vue";
import { loadStripe } from "@stripe/stripe-js";
import { toast } from "vue3-toastify";
import Tesseract from "tesseract.js";

const route = useRoute();
const store = useStore();
const authStore = useAuthStore();

// const user = computed(() => authStore.user);
// const user_id = computed(() => user.value?.id);
// const agentData = computed(() => store.getters["user/agentData"]);
const router = useRouter();
const termsAccepted = ref(false);
const validationErrors = ref([]);
const amount = ref(0);
const passengerCount = ref(0);
const currentSlide = ref(0);
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const quote = computed(() => store.getters["flight/quote"]);
const ancillaries = computed(() => store.getters["flight/ancillaries"]);
const qouteError = computed(() => store.getters["flight/qouteError"]);



const isOpen = ref(false);
const baggagePrice = ref(0);
const selectedBaggageData = ref([]);
const selectedExtras = ref([]);
const selectedFares = ref([]);
const selectedExtraData = ref([]);
const extraCharges = ref([]);
const showPreview = ref();
const selectedSeat = ref([]);
const currentState = ref(0);
const pnrData = ref(null);
const paymentMethod = ref(null);
const showPaymentDialog = ref(false);
const paymentDialog = ref(false);
const isConfirmDialogOpen = ref(false)
const processing = ref(false);
const stripe = ref(null);
const paymentError = ref('');
const elements = ref(null);
const isPaymentMethodsVisible = ref(false);
const countdown = ref(null);
const showDialog = ref(false);
const timerInterval = ref();

const cardElement = ref(null);
const clientSecret = ref('');
const publicKey = ref(import.meta.env.VITE_STRIPE_KEY);
const safepayUrl = computed(
    () => store.getters["safepay/safePayUrl"],
);

// const flight = computed(() => store.getters["flight/flight"]);
const flight = ref(null);
const bookingStatusSetting = computed(
    () => store.getters["settings/bookingStatusSetting"],
);
const CustomerMargin = computed(
    () => store.getters["customerMargin/customerMargin"],
);

const countries = computed(() => {
    const countries = store.getters["country/countries"] || [];
    return countries.map((country) => ({
        value: country.name,
        label: country.name,
        code: country.code,
    }));
});
const baggageData = [
    { route: "Jeddah to Riyadh", cabinBaggage: "7 kg, 1 piece", checkedBaggage: "Not included" },
    { route: "Riyadh to Jeddah", cabinBaggage: "7 kg, 1 piece", checkedBaggage: "Not included" }
];

const baggageOptions = {
    jeddahToRiyadh: [
        { label: 'No extra Baggage', sub: '', price: 0 },
        { label: '15kg in total', sub: '1 piece only', price: 61 },
        { label: '20kg in total', sub: '1 piece only', price: 101 },
        { label: '25kg in total', sub: '1 piece only', price: 139 },
    ],
    riyadhToJeddah: [
        { label: 'No extra Baggage', sub: '', price: 0 },
        { label: '15kg in total', sub: '1 piece only', price: 61 },
        { label: '20kg in total', sub: '1 piece only', price: 101 },
        { label: '25kg in total', sub: '1 piece only', price: 139 },
    ],
};

const selectedBaggage = ref([]);



const totalPrice = computed(() => {
    const basePrice = parseFloat(flight.value?.pricing?.totalPrice || "0");
    const marginAmount = parseFloat("0");

    //console.log("Base Price:", basePrice); // Debug log
    //console.log("Margin Amount:", marginAmount); // Debug log

    return basePrice + marginAmount;
});

const isLoading = computed(() => store.getters["flight/isLoading"]);
const loading = ref(true);
const scanning = ref(false);
const error = ref(null);

const isOpenCountryDropdown = ref(false);
const mainContact = ref({
    email: "",
    phone: "",
    country: "",
});

const agencyContact = computed(() => ({
    email: "customer@gmail.com",
    phone: "1234567890",
}));

const travellers = ref([]);
const travellerIndex = ref(0);
const taxFees = ref(0);
const totalTaxFees = ref(0);
const totalBaseFare = ref(0);
const marginedAmount = ref(0);
const progress = ref(0);
const passportData = ref(null);
const errorMessage = ref(null);
const originalImage = ref(null);
const croppedImage = ref(null);
const isSubmitting = ref(false);
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
    const passengerFares =
        flight.value?.leg?.flights[0]?.fares[0]?.passenger_fares || [];

    travellers.value = passengerFares.flatMap((fare) => {
        const count = fare.total_passenger || 1;
        const type =
            fare.traveler_type === "adult"
                ? "ADT"
                : fare.traveler_type === "child"
                    ? "CNN"
                    : fare.traveler_type === "infant"
                        ? "INF"
                        : "ADT"; // Default fallback

        // Repeat for each passenger of this type
        return Array(count)
            .fill()
            .map(() => ({
                type,
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
                passportImage: null, // Initialize passportImage
                uploadError: null, // Initialize uploadError
                isOpen: false, // Initialize isOpen for accordion
            }));
    });


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
        case "ADT": // Adult
            return adjustedAge >= 12;
        case "CNN": // Child
            return adjustedAge >= 2 && adjustedAge < 12;
        case "INF": // Infant
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

const showBookingPreview = () => {
    if (!validateForm()) {
        globalError.value = "Please fix the errors in the form before proceeding";
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
    }
    showPreview.value = true;
    window.scrollTo({ top: 0, behavior: "smooth" });
};

const backToForm = () => {
    showPreview.value = false;
};

const completeBookingFromPreview = () => {
    paymentMethod.value = 'card';
    openPaymentDialog();
};

function fetchBookingStatus() {
    store.dispatch("settings/" + FETCH_BOOKING_STATS_SETINGS);
}

function fetchAncillaries() {
    store.dispatch("flight/" + FETCH_ANCILLARIES, {
        ref_id: quote.value.ref_id,
    })
}

watch(quote, () => {
    if (!quote.value) {
        router.back();
        return;
    }
    fetchAncillaries();
})

function fetchCustomerMarginValues() {
    store.dispatch("customerMargin/" + FETCH_CUSTOMER_MARGIN);
}
// function saveBooking() {
//     // fetchSafepayUrl();
//     return;

//     if (!validateForm()) {
//         globalError.value =
//             "Please fix the errors in the form before submitting";
//         // Scroll to the top to show the global error
//         window.scrollTo({ top: 0, behavior: "smooth" });
//         return;
//     }
//     isSubmitting.value = true;
//     globalError.value = "";

//     store
//         .dispatch("flight/" + SAVE_BOOKING, {
//             main_contact: mainContact.value,
//             travellers: travellers.value,
//             agency_contact: agencyContact.value,
//             agent_id: null,
//             agency_mobile: "1234567890",
//             agency_email: "customer@gmail.com",
//             amount: amount.value,
//             agent_markup: 0,
//             flight_id: route.query?.flight_id,
//             booking_status_setting: bookingStatusSetting?.value.bookingStatus,
//             flight_source: route?.query.flight_source,
//             fare_reference: route?.query?.fare_id,
//         })
//         .then(() => {
//             // Redirect to Booking Details page
//             router.push({
//                 // name: "PublicFlightDetails", // Replace with the name of your route
//                 // query: {
//                 //     booking_id: route.query.flight_id,
//                 //     booking_source: route.query.flight_source,
//                 // }, // Pass relevant query parameters if needed
//             });
//         })
//         .catch((error) => {
//             console.error("Error saving booking data:", error);
//             globalError.value = "Failed to save booking. Please try again.";
//             // Scroll to the top to show the global error
//             window.scrollTo({ top: 0, behavior: "smooth" });
//         })
//         .finally(() => {
//             isSubmitting.value = false;
//         });
// }

function fetchCountries(event, country) {

    store.dispatch("country/" + FETCH_COUNTRIES, {
        searchQuery: event.target.value ?? country,
    });
}
function fetchCountry(country) {

    store.dispatch("country/" + FETCH_COUNTRIES, {
        searchQuery: country,
    });
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
    initializeSelectedSeat();
    //console.log("Flight data fetched successfully:", flight.value);

}

function fetchSafepayUrl() {
    isSubmitting.value = true;
    store.dispatch("safepay/" + FETCH_SAFE_PAY_URL, {
        flight_id: route.query.flight_id,
        amount: amount.value,
    }).then(() => {
        if (safepayUrl.value?.url) {
            window.location.href = safepayUrl.value?.url;
            isSubmitting.value = false;
        } else {
            globalError.value = "Failed to fetch Safepay URL. Please try again.";
            isSubmitting.value = false;
        }
    }).catch((error) => {
        console.error("Error fetching Safepay URL:", error);
        globalError.value = "Failed to fetch Safepay URL. Please try again.";
        isSubmitting.value = false;
    });



};


function sendSooperQoute() {
    // Save selected flight in localStorage
    localStorage.setItem("selectedFlight", JSON.stringify(flight.value));

    const body = {
        ref_id: flight?.value?.ref_id,
        legs: flight?.value?.leg?.flights
            .map(flightItem => {
                // Match fare from selectedFares.value
                const selectedFare = flightItem.fares.find(fare =>
                    selectedFares.value.includes(fare.ref_id)
                );

                if (selectedFare) {
                    return {
                        ref_id: flight.value.leg?.ref_id,
                        flight: {
                            ref_id: flightItem.ref_id,
                            fare: {
                                ref_id: selectedFare.ref_id
                            }
                        }
                    };
                }
                return null;
            })
            .filter(item => item !== null)
    };

    store.dispatch("flight/" + SEND_SOOPER_QOUTE, body);
}

function patchAncillaryCharges() {
    store.dispatch("flight/" + PATCH_ANCILLARIES,{
        ref_id : quote?.value?.ref_id,
        extraCharges : extraCharges.value
    })


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
const handlePayment = async () => {
    if (!stripe.value || !cardElement.value) {
        toast.error('Payment form not ready. Please try again.');
        return;
    }

    processing.value = true;

    try {
        const payload = {
            amount: Math.round(amount.value * 100), // Stripe requires amount in the smallest currency unit
            currency: 'sar',
        };

        const response = await store.dispatch('flight/' + SEND_PAYMENT_REQUEST, payload);


        clientSecret.value = response?.clientSecret;
        if (!clientSecret.value) {
            throw new Error('No client secret received from server');
        }


        await confirmCardPayment();
    } catch (error) {
        console.error('Payment request error:', error);
        toast.error('Failed to initiate payment. Please try again.');
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

        if (result.error) {
            paymentError.value = result.error.message;
            toast.error(result.error.message);
        } else if (result.paymentIntent.status === 'succeeded') {
            toast.success('Payment successful!');
            showPaymentDialog.value = false;
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
        paymentMethod.value = 'hold';
        isConfirmDialogOpen.value = true;
    } else if (type == 'card') {
        //console.log('Opening payment dialog for card payment');
        paymentMethod.value = 'card';
        openPaymentDialog();

    }
}
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
function confirmBooking() {
    error.value = '';
    isConfirmDialogOpen.value = false;

    store.dispatch("flight/" + CONFIRM_BOOKING, {
        pnr: bookingDetails?.value[0]?.pnr,
        bookingId: bookingDetails?.value?.id,
        booking_uuid: pnrData.value?.data?.uuid,
        booking_status: paymentMethod.value === 'hold' ? 'booked' : paymentMethod.value === 'pay' ? 'ticketed' : paymentMethod.value === 'card' ? 'issued' : 'booked',
        booking_source: route.query.flight_source,
        amount: parseFloat(pnrData?.value?.data?.billable_price).toFixed(2),
    }).then(() => {
        isDisabled.value = false;
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
        if (ancillaries.value) {
            await patchAncillaryCharges();
        }


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
            agent_id: null,
            agency_mobile: "1234567890",
            agency_email: "customer@gmail.com",
            amount: amount.value,
            flight: flight.value,
            booking_status_setting: bookingStatusSetting?.value.bookingStatus,
            flight_source: route?.query.flight_source,
            flight_provider: route?.query.flight_provider,
            fare_reference: selectedFares.value,
            type: paymentMethod.value || type,
            paymentMethod: paymentMethod.value || type,
            booking_status:
                paymentMethod.value === "hold"
                    ? "booked"
                    : paymentMethod.value === "pay"
                        ? "ticketed"
                        : paymentMethod.value === "card"
                            ? "issued"
                            : "booked",
            body: quote.value,
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
        name: "CustomerBookingDetails", // Replace with the name of your route
        query: {
            booking_id: route.query.flight_id,
            booking_source: route.query.flight_source,
        }, // Pass relevant query parameters if needed
    });
};

watch(bookingDetails, () => {
    if (paymentMethod.value === 'pay') {
        currentState.value = 1;
        parsePnrResponse();
        confirmBooking();
    } else if (paymentMethod.value === 'card') {
        currentState.value = 1;
        parsePnrResponse();
        confirmBooking();
    }
    else if (paymentMethod.value === 'hold') {
        parsePnrResponse();
        //console.log("pnrData", pnrData.value);
        router.push({
            name: "PublicFlightDetails", // Replace with the name of your route
            query: {
                pnr: pnrData.value?.data?.providers[0]?.pnr,
                booking_id: route.query.flight_id,
                booking_source: route.query.flight_source,
            }, // Pass relevant query parameters if needed
        });
    }
})

watch(isLoading, () => {
    if (isLoading.value == false) {

        currentState.value = 2;


    }
});

function handleExtraChange(flightIdx, segmentId, segmentIdx, travellerIdx, extra, type) {
   
    // Ensure UI preview object exists
    if (!selectedExtras.value[flightIdx]) {
        selectedExtras.value[flightIdx] = {};
    }
    if (!selectedExtras.value[flightIdx][type]) {
        selectedExtras.value[flightIdx][type] = {};
    }

    const qty = extra?.qty ?? 1; // default 1 if not set
    const price = (extra.amount || 0) * qty;

    // UI preview-friendly data
    selectedExtras.value[flightIdx][type][segmentIdx] = {
        title: extra.title || extra.seat_no || "",
        description: extra.description || extra.type || "",
        amount: price,
        currency: extra.currency?.symbol || "",
        refId: extra.ref_id,// Store ref_id for comparison in UI
        qty: extra?.qty ?? 1,
    };

    // Ensure API data object exists
    if (!selectedExtraData.value[flightIdx]) {
        selectedExtraData.value[flightIdx] = {};
    }
    if (!selectedExtraData.value[flightIdx][type]) {
        selectedExtraData.value[flightIdx][type] = {};
    }

    // Find the passenger ref_id using the travellerIdx (index)
    const passengerRefId = ancillaries.value?.passengers?.[travellerIdx]?.ref_id || null;

    selectedExtraData.value[flightIdx][type][segmentIdx] = {
        segment_ref_id: segmentId,
        passenger_ref_id: passengerRefId,
        ref_id: extra.ref_id,
        price,
        currency: extra.currency?.code,
        qty: extra?.qty ?? 1,

    };
}

function saveExtra(flightIdx, type, segmentIdx,travellerIdx) {
    // Ensure structure exists
    if (!extraCharges.value[flightIdx]) {
        extraCharges.value[flightIdx] = {};
    }
    if (!extraCharges.value[flightIdx][type]) {
        extraCharges.value[flightIdx][type] = {};
    }

    const extraData = selectedExtraData.value[flightIdx]?.[type]?.[segmentIdx];
    if (!extraData) {
        console.warn(`No ${type} data to save for flight ${flightIdx}, segment ${segmentIdx}`);
        return;
    }
    const passengerRefId = ancillaries.value?.passengers?.[travellerIdx]?.ref_id || null;

    // Save complete extra data
    extraCharges.value[flightIdx][type][segmentIdx] = {
        passenger_ref_id: passengerRefId,
        segment_ref_id: extraData.segment_ref_id,
        ref_id: extraData.ref_id,
        price: extraData.price,
        currency: extraData.currency,
        qty: extraData.qty
    };
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
const calculateCustomerMargin = (price, discountPercentage, marginPercentage) => {
  // console.log("customer margin",{price,discountPercentage,marginPercentage})
  const total = parseFloat(price) || 0;
  const discount = (total * (parseFloat(discountPercentage) || 0)) / 100;
  const margin = (total * (parseFloat(marginPercentage) || 0)) / 100;

  // If discount is provided, return negative discount value, else return margin value
  if (discountPercentage && parseFloat(discountPercentage) > 0) {
    // console.log("Applying discount:", -discount);
    return -discount;
  }
  // console.log("Applying margin:", margin);
  return margin;
};
const calculateFareMargin = (basePrice, marginAmount, marginType, amountType) => {
  const price = parseFloat(basePrice) || 0;
  let margin = 0;

  if (marginType === "discount") {
    if (amountType === "percent") {
      margin = -((price * (parseFloat(marginAmount) || 0)) / 100);
    } else {
      margin = -(parseFloat(marginAmount) || 0);
    }
  } else if (marginType === "markup") {
    // Margin can be percent or amount
    if (amountType === "percent") {
      margin = (price * (parseFloat(marginAmount) || 0)) / 100;
    } else {
      margin = parseFloat(marginAmount) || 0;
    }
  }
  return margin;
};
function calculateTotalFare(fare) {
       const customerMargin = parseFloat(calculateCustomerMargin(
    fare.base_price,
    CustomerMargin?.value?.discount || 0,
    CustomerMargin?.value?.margin_amount || 0,
  ));
  // console.log(customerMargin);

  const airlineMargin = calculateFareMargin(
    fare.base_price,
    fare.margin_amount,
    fare.margin_type,
    fare.amount_type
  );
  // console.log(airlineMargin);
  const billable = fare.billable_price +  parseFloat(fare.surchage || 0) + parseFloat(fare.taxes || 0) + parseFloat(fare.fees || 0) + parseFloat(fare.service_charges || 0) + parseFloat(fare.ancillaries_charges || 0) + (parseFloat(airlineMargin));

  const total = billable + (customerMargin * passengerCount.value) + parseFloat(CustomerMargin?.value?.other_charges || 0);
//   console.log(total);
  return total;
}
function calculateGrandTotal() {
    let total = 0;

    flight?.value?.leg?.flights?.forEach((flight, index) => {
        flight?.fares?.forEach(fare => {
            let extrasAmount = 0;
            const extrasForFlight = extraCharges.value[index] || {};

            // Loop through groups (meal, seat, baggage, etc.)
            Object.values(extrasForFlight).forEach(extraGroup => {
                if (extraGroup) {
                    // extraGroup = { "0": { "0": { item }, "1": { item } }, "1": {...} }
                    Object.values(extraGroup).forEach(segmentGroup => {
                        if (segmentGroup) {
                            // segmentGroup = { "0": { item }, "1": { item } }
                            Object.values(segmentGroup).forEach(item => {
                                const price = item.price || item.amount || 0;
                                const qty = item.qty || 1;
                                extrasAmount += price;
                            });
                        }
                    });
                }
            });

            if (selectedFares.value.includes(fare.ref_id)) {
                total += calculateTotalFare(fare) + extrasAmount
            }
        });
    });

    return total;
}



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





function nextSlide() {
    if (this.currentSlide < this.baggageData.length - 1) {
        this.currentSlide++;
    }
};

function prevSlide() {
    if (this.currentSlide > 0) {
        this.currentSlide--;
    }
};

function renderSafepayButton() {
    if (window.safepay) {
        window.safepay
            .Button({
                env: 'sandbox',
                client: {
                    sanbox: 'sec_c9e09504-b8b9-4bc0-b2d8-ce6eba12ffa0',
                },
                style: {
                    mode: 'light',
                    size: 'small',
                    variant: 'primary',
                },
                orderId: '12344',
                source: 'website',
                payment: {
                    currency: 'PKR',
                    amount: 1000.5,
                },
                onPayment: function (data) {
                    //console.log('Payment successful:', data);
                },
                onCancel: function () {
                    //console.log('cancelled');
                },
            })
            .render('#safepay-button-container');
    } else {
        console.error('Safepay SDK not loaded!');
    }
}





const handleFile = (e, index) => {
    travellerIndex.value = index; // Set the current traveller index
    const file = e.target.files[0];
    if (file) {
        originalImage.value = URL.createObjectURL(file);
        // resizeAndProcessImage(file);
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
            traveller.nationality = passportData.value.nationality;
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

function fixCountryCode(code) {
    // Replace common misreads: 5 → S, 0 → O
    return code.replace(/^5/, "S").replace(/^0/, "O");
}
function correctPassportNumber(num) {
    // Only fix common OCR misreads where context makes it obvious
    // If first 1–2 chars are letters, keep them
    return num
        // If "0" appears between letters, likely it's an "O"
        .replace(/([A-Z])0([A-Z])/g, "$1O$2")
        // If "O" appears between digits, likely it's a zero
        .replace(/(\d)O(\d)/g, "$10$2");
}

// Props and reactive data

// Methods
function initializeSelectedSeat() {
    // console.log(flight);
    const selectedSeats = {};
    flight.value.leg.flights.forEach((flight, flightIdx) => {
        selectedSeats[flightIdx] = {};
        flight.segments.forEach((segment, segmentIdx) => {
            selectedSeats[flightIdx][segmentIdx] = null; // Initialize as null for no selection
        });
    });
    selectedSeat.value = selectedSeats;
    // console.log(selectedSeat.value);
};
const getUniqueRows = (flightIdx, segmentIdx) => {
    const segment = ancillaries.value.providers[0].legs[flightIdx].flight.segments[segmentIdx];
    const seats = ancillaries.value.providers[0].legs[flightIdx].flight.ancillaries?.seatplans
        ?.find(sp => sp.segment_ref_id === segment.ref_id)?.seats || [];
    return [...new Set(seats.map(seat => seat.row))].sort((a, b) => a - b);
}

const getSeatByPosition = (flightIdx, row, col, segmentRefId) => {
    const seats = ancillaries.value.providers[0].legs[flightIdx].flight.ancillaries?.seatplans
        ?.find(sp => sp.segment_ref_id === segmentRefId)?.seats || [];
    return seats.filter(seat => seat.row === row && seat.col === col);
}

const getSelectedSeatInfo = (flightIdx, segmentIdx) => {
    if (!selectedSeat.value[flightIdx]?.[segmentIdx]) return '';
    const segment = ancillaries.value.providers[0].legs[flightIdx].flight.segments[segmentIdx];
    const seats = ancillaries.value.providers[0].legs[flightIdx].flight.ancillaries?.seatplans
        ?.find(sp => sp.segment_ref_id === segment.ref_id)?.seats || [];
    const seat = seats.find(s => s.ref_id === selectedSeat.value[flightIdx][segmentIdx]);
    return seat ? `${seat.seat_no} - ${seat.currency.symbol} ${seat.amount.toLocaleString()}` : '';
}


const getAvailableSeats = (flightIdx) => {
    const seats = ancillaries.value.providers[0].legs[flightIdx].flight.ancillaries?.seatplans[0]?.seats
    return seats.filter(seat => seat.availability_type === 'available').sort((a, b) => {
        if (a.row !== b.row) return a.row - b.row
        return a.col - b.col
    })

}


const getSegmentRefId = (flightIdx, segmentIdx) => {
    console.log('Flight Index:', flightIdx);
    return ancillaries.value.providers[0].legs[flightIdx].flight.segments[segmentIdx]?.ref_id;
}
const formatSeatType = (type) => {
    const typeMap = {
        'extra_legroom': 'Extra Legroom',
        'window': 'Window',
        'aisle': 'Aisle',
        'seat': 'Standard'
    }
    return typeMap[type] || type
}

const handleSeatChange = (flightIdx, seat) => {
    // Handle seat selection logic
    //console.log('Seat selected:', seat)
}

const saveSeat = (flightIdx) => {
    // Handle save seat logic
    //console.log('Saving seat for flight:', flightIdx, selectedSeat.value[flightIdx])
}
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
            localStorage.removeItem("previous_search");
            showDialog.value = true;
        } else {
            countdown.value = formatTime(remainingTime);
        }
    }, 1000);
};
const formatLayoverTime = (layoverMinutes) => {
    const totalMinutes = Number(layoverMinutes);
    if (!Number.isFinite(totalMinutes) || totalMinutes < 0) return "00:00";

    const normalizedMinutes = Math.round(totalMinutes);
    const hours = Math.floor(normalizedMinutes / 60);
    const minutes = normalizedMinutes % 60;

    return `${String(hours).padStart(2, "0")}:${String(minutes).padStart(2, "0")}`;
};onMounted(() => {
    selectedFares.value = route.query.fares ? JSON.parse(route.query.fares) : []
    passengerCount.value = route.query.passenger_count ? parseInt(route.query.passenger_count) : 1
    window.scrollTo({ top: 0, behavior: "smooth" });
    startCountdown(13 * 60 * 1000); // 13 minutes countdown
    
    initializeStripe();
    fetchBookingStatus();
    fetchFlight();
    fetchCustomerMarginValues();
});

watch(flight, () => {
    initializeTravellers();
    sendSooperQoute();
});
</script>

<template>
    <!-- Loading State -->
    <div v-if="isLoading" class="min-h-screen bg-gray-50 flex items-center justify-center">
        <div class="bg-white shadow-md p-6 max-w-md w-full mx-4">
            <div class="flex flex-col items-center space-y-3">
                <Spinner />
                <p class="text-gray-500 text-sm">Loading flight details...</p>
            </div>
        </div>
    </div>
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
    <!-- No Flight Found -->
    <div v-if="flight == null && !isLoading" class="min-h-screen bg-gray-50 flex items-center justify-center">
        <div class="bg-white shadow-md p-6 max-w-md w-full mx-4 text-center">
            <div class="mb-4">
                <div class="w-12 h-12 bg-gray-100 flex items-center justify-center mx-auto mb-3">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.172 16.172a4 4 0 015.656 0M9 12h6m-6-4h6m2 5.291A7.962 7.962 0 0112 15c-2.34 0-4.47-.881-6.08-2.33">
                        </path>
                    </svg>
                </div>
                <h3 class="text-base font-medium text-gray-900 mb-1">No Flight Found</h3>
                <p class="text-gray-500 text-sm">We couldn't find the flight you're looking for.</p>
            </div>
            <Button @click="$router.back()" class="w-full">Go Back</Button>
        </div>
    </div>

    <!-- Main Content - Flight Source 1 -->
    <div v-if="route?.query?.flight_source == 1 && !showPreview" class="min-h-screen bg-gray-50 py-4">
        <div class="max-w-7xl  mx-auto px-3 sm:px-4">
            <div v-if="!isLoading && flight">
                <!-- Header -->
                <div class="mb-4">
                    <div class="bg-white shadow-sm border border-gray-200 p-4">
                        <h1 class="text-xl font-semibold text-gray-900 mb-1">Complete Your Booking</h1>
                        <p class="text-gray-500 text-sm">Please fill in the required information to proceed with your
                            flight booking.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                    <!-- Main Content -->
                    <div class="lg:col-span-3 space-y-4">
                        <!-- Price Details Card - Moved to top -->


                        <!-- Flight Details Card -->


                        <!-- Contact Information Card -->
                        <div class="bg-white shadow-sm border border-gray-200">
                            <div class="bg-gray-50 border-b border-gray-200 p-3">
                                <h2 class="text-base font-medium text-gray-900">Contact Information</h2>
                                <p class="text-xs text-gray-500 mt-1">We'll use this information to send you booking
                                    confirmations</p>
                            </div>
                            <div class="p-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                                    <div>
                                        <Label for="main-email" class="text-xs font-medium text-gray-600">Email <span
                                                class="text-red-500">*</span></Label>
                                        <Input v-model="mainContact.email" id="main-email" type="email"
                                            placeholder="Enter your email" class="mt-1 text-sm"
                                            :class="{ 'border-red-300': errors.mainContact.email }" />
                                        <div v-if="errors.mainContact.email" class="text-red-500 text-xs mt-1">{{
                                            errors.mainContact.email }}</div>
                                    </div>
                                    <div>
                                        <Label for="main-phone" class="text-xs font-medium text-gray-600">Phone <span
                                                class="text-red-500">*</span></Label>
                                        <Input v-model="mainContact.phone" id="main-phone" type="tel"
                                            placeholder="Enter your phone" class="mt-1 text-sm"
                                            :class="{ 'border-red-300': errors.mainContact.phone }" />
                                        <div v-if="errors.mainContact.phone" class="text-red-500 text-xs mt-1">{{
                                            errors.mainContact.phone }}</div>
                                    </div>
                                    <div>
                                        <Label class="text-xs font-medium text-gray-600">Country <span
                                                class="text-red-500">*</span></Label>
                                        <Popover v-model:open="isOpenCountryDropdown">
                                            <PopoverTrigger as-child>
                                                <Button variant="outline" role="combobox"
                                                    class="w-full justify-between mt-1 text-sm h-9"
                                                    :class="{ 'border-red-300': errors.mainContact.country }">
                                                    {{mainContact.country !== "" ? countries.find((country) =>
                                                        country.value === mainContact.country)?.label || "Select country" :
                                                        "Select country"}}
                                                    <ChevronsUpDown class="ml-2 h-3 w-3 shrink-0 opacity-50" />
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent class="w-full p-0">
                                                <Command>
                                                    <CommandInput @input="fetchCountries"
                                                        placeholder="Search country..." />
                                                    <CommandEmpty>No results found.</CommandEmpty>
                                                    <CommandList>
                                                        <CommandGroup>
                                                            <CommandItem v-for="country in countries"
                                                                :key="country.value" :value="country.label"
                                                                @select="(ev) => { if (typeof ev.detail.value === 'string') { mainContact.country = ev.detail.value; } open = false; }">
                                                                {{ country.label }}
                                                                <Check
                                                                    :class="cn('ml-auto h-4 w-4', mainContact.country === country.value ? 'opacity-100' : 'opacity-0')" />
                                                            </CommandItem>
                                                        </CommandGroup>
                                                    </CommandList>
                                                </Command>
                                            </PopoverContent>
                                        </Popover>
                                        <div v-if="errors.mainContact.country" class="text-red-500 text-xs mt-1">{{
                                            errors.mainContact.country }}</div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 p-3 hidden">
                                    <h3 class="text-xs font-medium text-gray-600 mb-2">Agency Contact</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <Label class="text-xs text-gray-500">Phone</Label>
                                            <Input type="text" v-model="agencyContact.phone" readonly
                                                class="mt-1 bg-gray-100 text-sm" />
                                        </div>
                                        <div>
                                            <Label class="text-xs text-gray-500">Email</Label>
                                            <Input type="email" v-model="agencyContact.email" readonly
                                                class="mt-1 bg-gray-100 text-sm" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Traveller Details Card with Accordions -->
                        <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
                            <div class="bg-gray-50 border-b border-gray-200 p-3">
                                <h2 class="text-base font-medium text-gray-900">Traveller Details</h2>
                                <p class="text-xs text-gray-500 mt-1">Use all names exactly as they appear on your
                                    passport/ID</p>
                            </div>
                            <div class="p-3 space-y-2">
                                <div v-for="(traveller, index) in travellers" :key="`traveller-${index}`"
                                    class="border border-gray-200">
                                    <!-- Accordion Header -->
                                    <button @click="traveller.isOpen = !traveller.isOpen"
                                        class="w-full bg-gray-50 p-3 text-left flex items-center justify-between hover:bg-gray-100 transition-colors">
                                        <div>
                                            <h3 class="text-sm font-medium text-gray-900">{{ traveller.type }} Traveller
                                                {{ index + 1 }}</h3>
                                            <p class="text-xs text-gray-500"
                                                v-if="traveller.firstName && traveller.lastName">
                                                {{ traveller.firstName }} {{ traveller.lastName }}
                                            </p>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-500 transition-transform"
                                            :class="{ 'rotate-180': traveller.isOpen }" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>

                                    <!-- Accordion Content -->
                                    <div v-show="traveller.isOpen" class="border-t border-gray-200">
                                        <div class="p-4">
                                            <!-- Personal Details Section -->
                                            <div class="mb-6">
                                                <h4 class="text-sm font-medium text-gray-900 mb-4">Personal details</h4>
                                                <p class="text-xs text-gray-500 mb-4">
                                                    Save time by uploading a passport image to auto-fill the details
                                                    below. Ensure the image
                                                    is clear and contains all relevant information.
                                                </p>

                                                <!-- Upload Passport Button -->
                                                <div class="flex">
                                                    <div class="mb-4">
                                                        <Label
                                                            class="text-sm font-medium text-gray-700 mb-2 block">Upload
                                                            Passport Image</Label>
                                                        <div class="flex items-center gap-3">
                                                            <input type="file" accept="image/*" ref="passportUpload"
                                                                @change="handlePassportUpload($event, index)"
                                                                class="hidden" :id="`passport-upload-${index}`" />
                                                            <button type="button" @click="triggerFileUpload(index)"
                                                                class="bg-primary text-white px-4 py-2 text-sm rounded hover:bg-primary/90 flex items-center gap-2">
                                                                <Upload class="w-4 h-4" />
                                                                Upload Image
                                                            </button>
                                                            <span v-if="traveller.passportImage"
                                                                class="text-xs text-gray-600">
                                                                {{ traveller.passportImage.name }}
                                                            </span>
                                                            <span v-if="traveller.uploadError"
                                                                class="text-red-500 text-xs">
                                                                {{ traveller.uploadError }}
                                                            </span>


                                                        </div>

                                                    </div>

                                                </div>
                                                <div v-if="scanning"
                                                    class="text-blue-500 text-sm flex items-center gap-2">
                                                    <span>Scanning... {{ progress }}%</span>
                                                </div>
                                                <!-- Gender Selection -->
                                                <div class="mb-4">
                                                    <Label class="text-sm font-medium text-gray-700 mb-2 block">
                                                        Gender <span class="text-red-500">*</span>
                                                    </Label>
                                                    <div class="flex gap-2">
                                                        <button type="button" @click="traveller.gender = 'M'" :class="[
                                                            'px-3 py-1.5 text-xs rounded-full border transition-colors',
                                                            traveller.gender === 'M'
                                                                ? 'border-primary bg-primary text-white'
                                                                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                                        ]">
                                                            Male
                                                        </button>
                                                        <button type="button" @click="traveller.gender = 'F'" :class="[
                                                            'px-3 py-1.5 text-xs rounded-full border transition-colors',
                                                            traveller.gender === 'F'
                                                                ? 'border-primary bg-primary text-white'
                                                                : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                                        ]">
                                                            Female
                                                        </button>
                                                    </div>
                                                    <div v-if="getErrorPath(`travellers.${index}.gender`)"
                                                        class="text-red-500 text-xs mt-1">
                                                        {{ getErrorPath(`travellers.${index}.gender`) }}
                                                    </div>
                                                </div>

                                                <!-- Title -->
                                                <div class="mb-4">
                                                    <Label class="text-sm font-medium text-gray-700 mb-2 block">
                                                        Title <span class="text-red-500">*</span>
                                                    </Label>
                                                    <div class="flex gap-2 flex-wrap">
                                                        <button type="button"
                                                            v-for="titleOption in ['Mr', 'Ms', 'Mrs', 'Miss', 'Mstr']"
                                                            :key="titleOption" @click="traveller.title = titleOption"
                                                            :class="[
                                                                'px-3 py-1.5 text-xs rounded-full border transition-colors',
                                                                traveller.title === titleOption
                                                                    ? 'border-primary bg-primary text-white'
                                                                    : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                                            ]">
                                                            {{ titleOption }}
                                                        </button>
                                                    </div>
                                                    <div v-if="getErrorPath(`travellers.${index}.title`)"
                                                        class="text-red-500 text-xs mt-1">
                                                        {{ getErrorPath(`travellers.${index}.title`) }}
                                                    </div>
                                                </div>

                                                <!-- Full Name -->
                                                <div class="grid grid-cols-2 gap-4">
                                                    <div class="mb-4">
                                                        <Label class="text-sm font-medium text-gray-700 mb-2 block">Full
                                                            name <span class="text-red-500">*</span></Label>
                                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                            <div>
                                                                <Input v-model="traveller.firstName" type="text"
                                                                    placeholder="First name"
                                                                    class="text-sm border-gray-300"
                                                                    :class="{ 'border-red-300': getErrorPath(`travellers.${index}.firstName`) }" />
                                                                <div v-if="getErrorPath(`travellers.${index}.firstName`)"
                                                                    class="text-red-500 text-xs mt-1">{{
                                                                        getErrorPath(`travellers.${index}.firstName`) }}
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <Input v-model="traveller.lastName" type="text"
                                                                    placeholder="Last name"
                                                                    class="text-sm border-gray-300"
                                                                    :class="{ 'border-red-300': getErrorPath(`travellers.${index}.lastName`) }" />
                                                                <div v-if="getErrorPath(`travellers.${index}.lastName`)"
                                                                    class="text-red-500 text-xs mt-1">{{
                                                                        getErrorPath(`travellers.${index}.lastName`) }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Additional Personal Information -->
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                                        <div>
                                                            <Label class="text-sm font-medium text-gray-700 mb-2 block">
                                                                Date of Birth
                                                                <span v-if="traveller.type == 'ADT'"
                                                                    class="text-red-500 text-xs">*12+
                                                                    years</span>
                                                                <span v-else-if="traveller.type == 'CNN'"
                                                                    class="text-red-500 text-xs">*2-12
                                                                    years</span>
                                                                <span v-else-if="traveller.type == 'INF'"
                                                                    class="text-red-500 text-xs">*Under
                                                                    2</span>
                                                            </Label>
                                                            <Input v-model="traveller.dob" type="date"
                                                                class="text-sm border-gray-300"
                                                                :class="{ 'border-red-300': getErrorPath(`travellers.${index}.dob`) }" />
                                                            <div v-if="getErrorPath(`travellers.${index}.dob`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${index}.dob`) }}</div>
                                                        </div>
                                                        <div>
                                                            <Label
                                                                class="text-sm font-medium text-gray-700 mb-2 block">Nationality
                                                                <span class="text-red-500">*</span></Label>
                                                            <Input v-model="traveller.nationality" type="text"
                                                                placeholder="Nationality"
                                                                class="text-sm border-gray-300"
                                                                :class="{ 'border-red-300': getErrorPath(`travellers.${index}.nationality`) }" />
                                                            <div v-if="getErrorPath(`travellers.${index}.nationality`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${index}.nationality`) }}</div>
                                                        </div>
                                                    </div>

                                                    <!-- Document Information -->
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                                        <div>
                                                            <Label class="text-sm font-medium text-gray-700 mb-2 block">
                                                                Document Type <span class="text-red-500">*</span>
                                                            </Label>
                                                            <Select v-model="traveller.documentType">
                                                                <SelectTrigger
                                                                    class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-primary">
                                                                    <SelectValue placeholder="Select document type" />
                                                                </SelectTrigger>
                                                                <SelectContent
                                                                    class="bg-white border border-gray-200 rounded-md shadow-md">
                                                                    <SelectItem value="passport"
                                                                        class="px-4 py-2 hover:bg-gray-100 cursor-pointer">
                                                                        Passport
                                                                    </SelectItem>
                                                                    <SelectItem value="id"
                                                                        class="px-4 py-2 hover:bg-gray-100 cursor-pointer">
                                                                        ID Card
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                            <div v-if="getErrorPath(`travellers.${index}.documentType`)"
                                                                class="text-red-500 text-xs mt-1">
                                                                {{ getErrorPath(`travellers.${index}.documentType`) }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <Label
                                                                class="text-sm font-medium text-gray-700 mb-2 block">Document
                                                                Number <span class="text-red-500">*</span></Label>
                                                            <Input v-model="traveller.documentNo" type="text"
                                                                placeholder="Document number"
                                                                class="text-sm border-gray-300"
                                                                :class="{ 'border-red-300': getErrorPath(`travellers.${index}.documentNo`) }" />
                                                            <div v-if="getErrorPath(`travellers.${index}.documentNo`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${index}.documentNo`) }}</div>
                                                        </div>
                                                    </div>

                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div>
                                                            <Label
                                                                class="text-sm font-medium text-gray-700 mb-2 block">Expiry
                                                                Date <span class="text-red-500">*</span></Label>
                                                            <Input v-model="traveller.expiryDate" type="date"
                                                                class="text-sm border-gray-300"
                                                                :class="{ 'border-red-300': getErrorPath(`travellers.${index}.expiryDate`) }" />
                                                            <div v-if="getErrorPath(`travellers.${index}.expiryDate`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${index}.expiryDate`) }}</div>
                                                        </div>
                                                        <div>
                                                            <Label
                                                                class="text-sm font-medium text-gray-700 mb-2 block">Issue
                                                                Country <span class="text-red-500">*</span></Label>
                                                            <Input v-model="traveller.issueCountry" type="text"
                                                                placeholder="Issue country"
                                                                class="text-sm border-gray-300"
                                                                :class="{ 'border-red-300': getErrorPath(`travellers.${index}.issueCountry`) }" />
                                                            <div v-if="getErrorPath(`travellers.${index}.issueCountry`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${index}.issueCountry`) }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>


                                            <!-- About your baggage section inside accordion -->
                                            <div class="pt-4 border-t border-gray-200">
                                                <h4 class="text-sm font-medium text-gray-900 mb-2">About your baggage
                                                </h4>
                                                <p class="text-xs text-gray-500 mb-3">
                                                    Need additional baggage? Save time and money by purchasing extra
                                                    baggage in advance
                                                </p>

                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <div v-for="(flight, flightIdx) in flight?.leg?.flights"
                                                        :key="flightIdx" class="border border-gray-200 p-3 bg-gray-50">
                                                        <h5 class="text-xs font-medium text-gray-900 mb-2">
                                                            {{ flight?.from?.city?.name }} to {{ flight?.to?.city?.name
                                                            }}
                                                        </h5>

                                                        <div v-for="(segment, segmentIdx) in flight?.segments"
                                                            :key="segmentIdx" class="mb-4">
                                                            <div class="font-semibold text-xs text-gray-700 mb-2">
                                                                Segment: {{ segment?.from?.name }} → {{
                                                                    segment?.to?.name }}
                                                            </div>

                                                            <div v-for="fare in flight?.fares" :key="fare.ref_id">
                                                                <div v-if="fare?.ref_id === route?.query?.fare_id || fare?.ref_id === route?.query?.fare_id2"
                                                                    class="space-y-2">
                                                                    <!-- Carry baggage -->
                                                                    <div
                                                                        v-if="fare.baggage_policies?.some(p => p.type === 'carry')">
                                                                        <div v-for="policy in [fare.baggage_policies.find(p => p.type === 'carry')]"
                                                                            :key="policy.description"
                                                                            class="flex items-start gap-2">
                                                                            <div class="pt-0.5">
                                                                                <CheckCircle
                                                                                    class="w-3 h-3 text-green-600" />
                                                                            </div>
                                                                            <div>
                                                                                <p
                                                                                    class="text-xs font-medium text-gray-900">
                                                                                    {{ policy.weight_limit }} {{
                                                                                        policy.weight_unit }} cabin baggage
                                                                                </p>
                                                                                <p class="text-xs text-gray-400">{{
                                                                                    policy.pieces }} piece</p>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Checked baggage -->
                                                                    <div
                                                                        v-if="fare.baggage_policies?.some(p => p.type === 'checked')">
                                                                        <div v-for="policy in [fare.baggage_policies.find(p => p.type === 'checked')]"
                                                                            :key="policy.description"
                                                                            class="flex items-start gap-2">
                                                                            <div class="pt-0.5">
                                                                                <CheckCircle
                                                                                    class="w-3 h-3 text-green-600" />
                                                                            </div>
                                                                            <div>
                                                                                <p
                                                                                    class="text-xs font-medium text-gray-900">
                                                                                    {{ policy.weight_limit }} {{
                                                                                        policy.weight_unit }} checked
                                                                                    baggage
                                                                                </p>
                                                                                <p class="text-xs text-gray-400">{{
                                                                                    policy.pieces }} piece</p>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div v-else class="flex items-start gap-2">
                                                                        <div class="pt-0.5">
                                                                            <XCircle class="w-3 h-3 text-red-500" />
                                                                        </div>
                                                                        <p class="text-xs text-gray-400">Checked baggage
                                                                            not included</p>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Selected extra baggage preview -->
                                                            <div v-if="extraCharges[flightIdx]?.baggage?.[segmentIdx]"
                                                                class="mt-2 border p-2 bg-blue-50 rounded">
                                                                <div class="flex items-center gap-2">
                                                                    <CheckCircle class="w-3 h-3 text-blue-600" />
                                                                    <p class="text-xs font-medium text-gray-900">
                                                                        Extra baggage: {{
                                                                            selectedExtras[flightIdx]?.baggage?.[segmentIdx]?.title
                                                                        }}
                                                                        ({{
                                                                            selectedExtras[flightIdx]?.baggage?.[segmentIdx]?.currency
                                                                        }}
                                                                        {{
                                                                            selectedExtras[flightIdx]?.baggage?.[segmentIdx]?.amount
                                                                        }})
                                                                    </p>
                                                                    <button
                                                                        class="ml-2 px-2 py-1 text-xs rounded bg-red-100 text-red-600 hover:bg-red-200"
                                                                        @click="() => {
                                                                            delete extraCharges[flightIdx].baggage[segmentIdx];
                                                                            delete selectedExtras[flightIdx].baggage[segmentIdx];
                                                                        }" title="Remove baggage">
                                                                        X
                                                                    </button>
                                                                </div>
                                                                <p class="text-xs text-gray-500">
                                                                    {{
                                                                        selectedExtras[flightIdx]?.baggage?.[segmentIdx]?.description
                                                                    }}
                                                                </p>
                                                            </div>

                                                            <!-- Dialog Trigger -->
                                                            <Dialog v-if="ancillaries">
                                                                <DialogTrigger as-child>
                                                                    <button
                                                                        class="flex items-center text-primary font-medium text-xs hover:underline mt-2">
                                                                        <PlusCircle class="w-3 h-3 mr-1" />
                                                                        Add extra baggage
                                                                    </button>
                                                                </DialogTrigger>

                                                                <!-- Dialog Content -->
                                                                <DialogContent
                                                                    class="max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
                                                                    <DialogHeader>
                                                                        <DialogTitle class="text-base font-medium">Add
                                                                            your extra baggage</DialogTitle>
                                                                        <DialogDescription
                                                                            class="text-xs text-gray-500">
                                                                            You can select here which baggage you prefer
                                                                        </DialogDescription>
                                                                    </DialogHeader>
                                                                    <!-- Scrollable Main Body -->
                                                                    <div class="flex-1 overflow-y-auto mt-3 pr-1">
                                                                        <h3
                                                                            class="text-sm font-medium text-gray-700 mb-3">
                                                                            {{ segment?.from?.name }} to {{
                                                                                segment?.to?.name }}
                                                                        </h3>

                                                                        <div
                                                                            class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                                                            <label
                                                                                v-for="option in ancillaries?.providers?.[0]?.legs?.[flightIdx]?.flight?.ancillaries?.baggages?.find(b => b.segment_ref_id === segment.ref_id)?.baggages"
                                                                                :key="option.ref_id"
                                                                                class="block border rounded-lg shadow-sm p-4 cursor-pointer transition-all duration-200 hover:shadow-md hover:-translate-y-1 bg-white"
                                                                                :class="{
                                                                                    'border-primary ring-2 ring-primary/30 bg-primary/5':
                                                                                        selectedExtras[flightIdx]?.baggage?.[segmentIdx]?.refId === option.ref_id
                                                                                }">
                                                                                <div
                                                                                    class="h-32 sm:h-36 md:h-44 flex items-center justify-center rounded-md overflow-hidden mb-3">
                                                                                    <img src="/public/assets/baggage.jpg"
                                                                                        alt="Baggage Image"
                                                                                        class="h-full object-contain" />
                                                                                </div>
                                                                                <div class="flex items-center mb-2">
                                                                                    <input type="radio"
                                                                                        class="mr-2 accent-primary w-4 h-4"
                                                                                        :name="'baggage_' + flightIdx + '_' + segmentIdx"
                                                                                        :value="option.ref_id" @change="handleExtraChange(
                                                                                            flightIdx,
                                                                                            segment.ref_id,
                                                                                            segmentIdx,
                                                                                            index,
                                                                                            option,
                                                                                            'baggage'
                                                                                        )" />
                                                                                    <span
                                                                                        class="text-sm font-semibold">{{
                                                                                            option.title }}</span>
                                                                                </div>
                                                                                <div
                                                                                    class="text-xs text-gray-500 leading-snug line-clamp-2">
                                                                                    {{ option.description }}
                                                                                </div>
                                                                                <div
                                                                                    class="text-sm font-bold mt-3 text-primary">
                                                                                    {{ option.currency.symbol }} {{
                                                                                        option.amount }}
                                                                                </div>
                                                                            </label>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Footer -->
                                                                    <DialogFooter
                                                                        class="flex justify-between items-center mt-4">
                                                                        <DialogClose as-child>
                                                                            <button
                                                                                class="bg-primary text-white px-4 py-2 text-sm hover:bg-primary/90 disabled:bg-gray-300"
                                                                                :disabled="!selectedExtras[flightIdx]?.baggage?.[segmentIdx]"
                                                                                @click="saveExtra(flightIdx, 'baggage', segmentIdx, index)">
                                                                                Save
                                                                            </button>
                                                                        </DialogClose>
                                                                    </DialogFooter>
                                                                </DialogContent>
                                                            </Dialog>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div v-if="ancillaries?.providers?.length"
                                                class="pt-4 border-t border-gray-200">
                                                <h4 class="text-sm font-medium text-gray-900 mb-2">Want your own seat?
                                                </h4>
                                                <p class="text-xs text-gray-500 mb-3">
                                                    Customize your trip with optional extras. Select the services you
                                                    want now to avoid higher charges later.
                                                </p>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <div v-for="(flight, flightIdx) in flight?.leg?.flights"
                                                        :key="flightIdx" class="border border-gray-200 p-3 bg-gray-50">
                                                        <h5 class="text-xs font-medium text-gray-900 mb-2">
                                                            {{ flight?.from?.city?.name || 'From City' }} to {{
                                                                flight?.to?.city?.name || 'To City' }}
                                                        </h5>

                                                        <div v-for="(segment, segmentIdx) in flight?.segments"
                                                            :key="segmentIdx" class="mb-4">
                                                            <div class="font-semibold text-xs text-gray-700 mb-2">
                                                                Segment: {{ segment?.from?.name || 'Unknown' }} → {{
                                                                    segment?.to?.name || 'Unknown' }}
                                                            </div>

                                                            <p v-if="extraCharges[flightIdx]?.seat?.[segmentIdx]"
                                                                class="text-sm font-medium text-gray-900 mb-2">
                                                                {{ getSelectedSeatInfo(flightIdx, segmentIdx) }}
                                                                <button
                                                                    class="ml-2 px-2 py-1 text-xs rounded bg-red-100 text-red-600 hover:bg-red-200"
                                                                    @click="() => {
                                                                        delete extraCharges[flightIdx].seat[segmentIdx];
                                                                        delete selectedExtras[flightIdx].seat[segmentIdx];
                                                                        delete selectedSeat[flightIdx][segmentIdx];
                                                                    }" title="Remove seat">
                                                                    X
                                                                </button>
                                                            </p>

                                                            <!-- Dialog Trigger -->
                                                            <Dialog
                                                                v-if="ancillaries.providers[0].legs[flightIdx]?.flight?.ancillaries?.seatplans?.some(sp => sp.segment_ref_id === segment.ref_id)">
                                                                <DialogTrigger as-child>
                                                                    <button
                                                                        class="flex items-center text-primary font-medium text-xs hover:underline mt-2">
                                                                        <PlusCircle class="w-3 h-3 mr-1" />
                                                                        Select seat
                                                                    </button>
                                                                </DialogTrigger>

                                                                <!-- Dialog Content -->
                                                                <DialogContent
                                                                    class="max-w-4xl max-h-[100vh] overflow-y-auto">
                                                                    <DialogHeader>
                                                                        <DialogTitle class="text-base font-medium">
                                                                            Select Your Seat</DialogTitle>
                                                                        <DialogDescription
                                                                            class="text-xs text-gray-500">
                                                                            Choose your preferred seat for {{
                                                                                segment?.from?.name || 'Unknown' }} to {{
                                                                                segment?.to?.name || 'Unknown' }}
                                                                        </DialogDescription>
                                                                    </DialogHeader>

                                                                    <!-- Main Body -->
                                                                    <div class="mt-4">
                                                                        <h3
                                                                            class="text-sm font-medium text-gray-700 mb-4">
                                                                            {{ segment?.from?.name || 'Unknown' }} to {{
                                                                                segment?.to?.name || 'Unknown' }}
                                                                        </h3>

                                                                        <!-- Seat Map -->
                                                                        <div class="mb-6"
                                                                            v-if="getUniqueRows(flightIdx, segmentIdx).length">
                                                                            <h4
                                                                                class="text-xs font-medium text-gray-700 mb-3">
                                                                                Seat Map</h4>
                                                                            <div class="bg-white border rounded-lg p-4">
                                                                                <!-- Column Headers -->
                                                                                <div class="flex justify-center mb-2">
                                                                                    <div
                                                                                        class="grid grid-cols-7 gap-14 text-center">
                                                                                        <span
                                                                                            v-for="col in ['A', 'B', 'C', '', 'D', 'E', 'F']"
                                                                                            :key="col"
                                                                                            class="text-xs font-medium text-gray-500 w-8">
                                                                                            {{ col }}
                                                                                        </span>
                                                                                    </div>
                                                                                </div>

                                                                                <!-- Seat Rows -->
                                                                                <div class="space-y-2">
                                                                                    <div v-for="row in getUniqueRows(flightIdx, segmentIdx)"
                                                                                        :key="row"
                                                                                        class="flex items-center justify-center">
                                                                                        <span
                                                                                            class="text-xs font-medium text-gray-700 w-6 text-right mr-2">
                                                                                            {{ row }}
                                                                                        </span>
                                                                                        <div
                                                                                            class="grid grid-cols-7 gap-8">
                                                                                            <div v-for="col in [1, 2, 3, 4, 5, 6, 7]"
                                                                                                :key="col"
                                                                                                class="flex flex-col p-1 items-center">
                                                                                                <template
                                                                                                    v-for="seat in getSeatByPosition(flightIdx, row, col, segment.ref_id)"
                                                                                                    :key="seat?.ref_id">
                                                                                                    <!-- Available Seat -->
                                                                                                    <div v-if="seat && seat.availability_type === 'available'"
                                                                                                        class="flex flex-col items-center">
                                                                                                        <label
                                                                                                            class="w-14 h-14 border-2 p-1 rounded cursor-pointer flex items-center justify-center text-xs font-medium transition-all duration-200 hover:scale-105"
                                                                                                            :class="{
                                                                                                                'border-green-500 bg-green-100 text-green-800': selectedSeat[flightIdx]?.[segmentIdx] !== seat.ref_id,
                                                                                                                'border-blue-500 bg-blue-100 text-blue-800': selectedSeat[flightIdx]?.[segmentIdx] === seat.ref_id
                                                                                                            }">

                                                                                                            <input
                                                                                                                type="radio"
                                                                                                                class="sr-only"
                                                                                                                :name="'seat_' + flightIdx + '_' + segmentIdx"
                                                                                                                v-model="selectedSeat[flightIdx][segmentIdx]"
                                                                                                                :value="seat.ref_id"
                                                                                                                @change="handleExtraChange(
                                                                                                                    flightIdx,
                                                                                                                    segment.ref_id,
                                                                                                                    segmentIdx,
                                                                                                                    index,
                                                                                                                    {
                                                                                                                        ref_id: seat.ref_id,
                                                                                                                        title: seat.seat_no,
                                                                                                                        description: seat.type || 'Standard seat',
                                                                                                                        amount: seat.amount || 0,
                                                                                                                        currency: seat.currency?.symbol || '',
                                                                                                                    },
                                                                                                                    'seat'
                                                                                                                )" />
                                                                                                            {{
                                                                                                                seat.seat_no.slice(-1)
                                                                                                            }}
                                                                                                        </label>
                                                                                                        <span
                                                                                                            class="text-[8px] text-gray-500 leading-none mt-0.5">
                                                                                                            {{
                                                                                                                seat.amount
                                                                                                                    ?
                                                                                                                    formatAmount(seat.amount)
                                                                                                                    : 'Free' }}
                                                                                                        </span>
                                                                                                    </div>

                                                                                                    <!-- Unavailable Seat -->
                                                                                                    <div v-else-if="seat && seat.availability_type === 'not_available'"
                                                                                                        class="w-14 h-14 border-2 border-red-500 bg-red-100 rounded flex items-center justify-center text-xs text-red-800">
                                                                                                        X
                                                                                                    </div>

                                                                                                    <!-- Empty Seat Position -->
                                                                                                    <div v-else
                                                                                                        class="w-14 h-14 border border-gray-300 bg-gray-100 rounded flex items-center justify-center text-xs text-gray-400">
                                                                                                        X
                                                                                                    </div>
                                                                                                </template>
                                                                                            </div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                        <div v-else class="text-xs text-gray-500">
                                                                            No seat map available for this segment.
                                                                        </div>

                                                                        <!-- Legend -->
                                                                        <div class="mb-4 flex flex-wrap gap-4 text-xs">
                                                                            <div class="flex items-center gap-2">
                                                                                <div
                                                                                    class="w-4 h-4 border-2 border-green-500 bg-green-100 rounded">
                                                                                </div>
                                                                                <span>Available</span>
                                                                            </div>
                                                                            <div class="flex items-center gap-2">
                                                                                <div
                                                                                    class="w-4 h-4 border-2 border-blue-500 bg-blue-100 rounded">
                                                                                </div>
                                                                                <span>Selected</span>
                                                                            </div>
                                                                            <div class="flex items-center gap-2">
                                                                                <div
                                                                                    class="w-4 h-4 border border-red-500 bg-red-100 rounded">
                                                                                </div>
                                                                                <span>Unavailable</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <!-- Footer -->
                                                                    <DialogFooter
                                                                        class="flex justify-between items-center mt-6 pt-4 border-t">
                                                                        <div v-if="selectedSeat[flightIdx]?.[segmentIdx]"
                                                                            class="text-xs text-gray-600">
                                                                            Selected: {{ getSelectedSeatInfo(flightIdx,
                                                                                segmentIdx) }}
                                                                        </div>
                                                                        <div class="flex gap-2">
                                                                            <DialogClose as-child>
                                                                                <button
                                                                                    class="px-4 py-2 text-sm border border-gray-300 rounded hover:bg-gray-50">
                                                                                    Cancel
                                                                                </button>
                                                                            </DialogClose>
                                                                            <DialogClose as-child>
                                                                                <button
                                                                                    class="bg-blue-600 text-white px-4 py-2 text-sm rounded hover:bg-blue-700 disabled:bg-gray-300 disabled:cursor-not-allowed"
                                                                                    :disabled="!selectedSeat[flightIdx]?.[segmentIdx]"
                                                                                    @click="saveExtra(flightIdx, 'seat', segmentIdx,index)">
                                                                                    Save Seat
                                                                                </button>
                                                                            </DialogClose>
                                                                        </div>
                                                                    </DialogFooter>
                                                                </DialogContent>
                                                            </Dialog>
                                                            <p v-else class="text-xs text-gray-500">No seat selection
                                                                available for this segment.</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            

                                            <div v-if="ancillaries" class="pt-4 border-t border-gray-200">
                                                <h4 class="text-sm font-medium text-gray-900 mb-2">Add Meals</h4>
                                                <p class="text-xs text-gray-500 mb-3">
                                                    Pre-book meals and enjoy discounted prices onboard.
                                                </p>
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <div v-for="(flightItem, flightIdx) in flight?.leg?.flights"
                                                        :key="flightIdx" class="border border-gray-200 p-3 bg-gray-50">
                                                        <h5 class="text-xs font-medium text-gray-900 mb-2">
                                                            {{ flightItem?.from?.city?.name }} to {{
                                                                flightItem?.to?.city?.name }}
                                                        </h5>
                                                        <div v-for="(segment, segmentIdx) in flightItem?.segments"
                                                            :key="segmentIdx" class="mb-4">
                                                            <div class="font-semibold text-xs text-gray-700 mb-2">
                                                                Segment: {{ segment?.from?.name }} → {{
                                                                    segment?.to?.name }}
                                                            </div>
                                                            <div v-if="extraCharges?.[flightIdx]?.meal?.[segmentIdx]"
                                                                class="mt-2 border p-2 bg-blue-50 rounded">
                                                                <div class="flex items-center gap-2">
                                                                    <CheckCircle class="w-3 h-3 text-blue-600" />
                                                                    <p class="text-xs font-medium text-gray-900">
                                                                        Meal: {{
                                                                            selectedExtras?.[flightIdx]?.meal?.[segmentIdx]?.title
                                                                        }}
                                                                        ({{
                                                                            selectedExtras?.[flightIdx]?.meal?.[segmentIdx]?.currency
                                                                        }}
                                                                        {{
                                                                            selectedExtras?.[flightIdx]?.meal?.[segmentIdx]?.amount
                                                                        }})
                                                                        Qty: {{
                                                                            selectedExtras?.[flightIdx]?.meal?.[segmentIdx]?.qty
                                                                            || 1 }}
                                                                    </p>
                                                                    <button
                                                                        class="ml-2 px-2 py-1 text-xs rounded bg-red-100 text-red-600 hover:bg-red-200"
                                                                        @click="() => {
                                                                            delete extraCharges[flightIdx].meal[segmentIdx];
                                                                            delete selectedExtras[flightIdx].meal[segmentIdx];
                                                                        }" title="Remove meal">
                                                                        X
                                                                    </button>
                                                                </div>
                                                                <p class="text-xs text-gray-500">
                                                                    {{
                                                                        selectedExtras?.[flightIdx]?.meal?.[segmentIdx]?.description
                                                                    }}
                                                                </p>
                                                            </div>
                                                            <Dialog v-if="ancillaries">
                                                                <DialogTrigger as-child>
                                                                    <button
                                                                        class="flex items-center text-primary font-medium text-xs hover:underline mt-2">
                                                                        <PlusCircle class="w-3 h-3 mr-1" />
                                                                        Add meal
                                                                    </button>
                                                                </DialogTrigger>
                                                                <DialogContent
                                                                    class="max-w-3xl max-h-[90vh] flex flex-col overflow-hidden">
                                                                    <DialogHeader>
                                                                        <DialogTitle class="text-base font-medium">Add
                                                                            your meal</DialogTitle>
                                                                        <DialogDescription
                                                                            class="text-xs text-gray-500">
                                                                            Select your preferred meal for this flight
                                                                            segment.
                                                                        </DialogDescription>
                                                                    </DialogHeader>
                                                                    <div class="flex-1 overflow-y-auto mt-3 pr-1">
                                                                        <h3
                                                                            class="text-sm font-medium text-gray-700 mb-3">
                                                                            {{ segment?.from?.name }} to {{
                                                                                segment?.to?.name }}
                                                                        </h3>
                                                                        <div
                                                                            class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                                                                            <label
                                                                                v-for="meal in (ancillaries?.providers?.[0]?.legs?.[flightIdx]?.flight?.ancillaries?.meals?.find(m => m.segment_ref_id === segment.ref_id)?.meals)"
                                                                                :key="meal?.ref_id"
                                                                                class="block border rounded-lg shadow-sm p-4 cursor-pointer transition-all duration-200 hover:shadow-md hover:-translate-y-1 bg-white"
                                                                                :class="{
                                                                                    'border-primary ring-2 ring-primary/30 bg-primary/5':
                                                                                        selectedExtras?.[flightIdx]?.meal?.[segmentIdx]?.ref_id === meal?.ref_id
                                                                                }">
                                                                                <img :src="meal?.image"
                                                                                    :alt="meal?.title"
                                                                                    class="h-32 object-contain rounded mb-3" />
                                                                                <div class="flex items-center mb-2">
                                                                                    <input type="radio"
                                                                                        class="mr-2 accent-primary w-4 h-4"
                                                                                        :name="'meal_' + flightIdx + '_' + segmentIdx"
                                                                                        :value="meal?.ref_id"
                                                                                        :checked="selectedExtras?.[flightIdx]?.meal?.[segmentIdx]?.ref_id === meal?.ref_id"
                                                                                        @change="handleExtraChange(
                                                                                            flightIdx,
                                                                                            segment.ref_id,
                                                                                            segmentIdx,
                                                                                            index,
                                                                                            { ...meal, qty: meal?.qty || 1 },
                                                                                            'meal'
                                                                                        )" />
                                                                                    <span
                                                                                        class="text-sm font-semibold">{{
                                                                                            meal?.title }}</span>
                                                                                </div>
                                                                                <div
                                                                                    class="text-xs text-gray-500 leading-snug line-clamp-2">
                                                                                    {{ meal?.category }}
                                                                                </div>
                                                                                <div class="text-xs text-gray-600 mb-2">
                                                                                    {{ meal?.description }}
                                                                                </div>
                                                                                <div
                                                                                    class="text-sm font-bold mt-3 text-primary">
                                                                                    {{ meal?.currency?.symbol }} {{
                                                                                        (meal?.amount || 0) * (meal?.qty ||
                                                                                            1) }}
                                                                                </div>
                                                                                <div
                                                                                    class="flex items-center gap-2 mt-2">
                                                                                    <button
                                                                                        class="w-6 h-6 rounded-full border flex items-center justify-center text-gray-600 hover:bg-gray-100"
                                                                                        @click="
                                                                                            meal.qty = Math.max(1, (meal?.qty || 1) - 1);
                                                                                        handleExtraChange(flightIdx, segment.ref_id, segmentIdx, index, { ...meal, qty: meal?.qty }, 'meal');
                                                                                        ">−</button>
                                                                                    <span class="text-sm font-medium">{{
                                                                                        meal?.qty || 1 }}</span>
                                                                                    <button
                                                                                        class="w-6 h-6 rounded-full border flex items-center justify-center text-gray-600 hover:bg-gray-100"
                                                                                        @click="
                                                                                            meal.qty = (meal?.qty || 1) + 1;
                                                                                        handleExtraChange(flightIdx, segment.ref_id, segmentIdx, index, { ...meal, qty: meal?.qty }, 'meal');
                                                                                        ">+</button>
                                                                                </div>
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                    <DialogFooter
                                                                        class="flex justify-between items-center mt-4">
                                                                        <DialogClose as-child>
                                                                            <button
                                                                                class="bg-primary text-white px-4 py-2 text-sm hover:bg-primary/90 disabled:bg-gray-300"
                                                                                :disabled="!selectedExtras?.[flightIdx]?.meal?.[segmentIdx]"
                                                                                @click="saveExtra(flightIdx, 'meal', segmentIdx,index)">
                                                                                Save
                                                                            </button>
                                                                        </DialogClose>
                                                                    </DialogFooter>
                                                                </DialogContent>
                                                            </Dialog>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Meals Section -->


                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>

                    <!-- Sidebar - Booking Actions -->
                    <div class=" lg:col-span-2">

                        <div class="sticky top-2">
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
                            <div class="divide-y divide-gray-100 bg-white">
                                    <div v-for="(flight, flightIndex) in flight?.leg?.flights" :key="flightIndex">
                                        <div v-for="(segment, segmentIndex) in flight?.segments" :key="segmentIndex">
                                            <!-- Layover Info -->
                                            <div v-if="segment?.layover_time"
                                                class="bg-amber-50 border-l-4 border-amber-400 p-3">
                                                <div class="flex items-center justify-center">
                                                    <ClockIcon   class="w-4 h-4 text-amber-600 mr-2" />
                                                    <span class="text-xs font-medium text-amber-800">
                                                        Layover: {{ moment.utc(moment.duration(segment.layover_time,
                                                            "minutes").asMilliseconds()).format("HH:mm") }}
                                                    </span>
                                                </div>
                                            </div>
                                            <!-- Flight Segment -->
                                            <div class="p-4">
                                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                    <!-- Departure -->
                                                    <div class="space-y-2">
                                                        <div class="flex items-center space-x-2">
                                                            <img class="w-8 h-8 border border-gray-200"
                                                                :src="segment?.operating_carrier?.logo" alt="Airline" />
                                                            <div>
                                                                <div class="text-sm font-medium text-gray-900">{{
                                                                    segment?.operating_carrier?.name }}</div>
                                                                <div class="text-xs text-gray-500">{{
                                                                    segment?.flight_number
                                                                    }}</div>
                                                            </div>
                                                        </div>
                                                        <div class="space-y-1">
                                                            <div class="text-sm font-medium text-gray-900">{{
                                                                formatDate(segment?.departure_at) }}</div>
                                                            <div class="text-xs text-gray-500">{{ segment?.from?.name }}
                                                                ({{
                                                                    segment?.from?.iata }})</div>
                                                            <div class="text-xs text-gray-400">Terminal: {{
                                                                segment?.from_terminal[0] ?? "N/A" }}</div>
                                                        </div>
                                                    </div>
                                                    <!-- Flight Path -->
                                                    <div class="flex items-center justify-center">
                                                        <div class="w-full max-w-xs">
                                                            <div class="flex items-center justify-between mb-1">
                                                                <span class="text-xs font-medium text-gray-900">{{
                                                                    moment(segment?.departure_at).format("HH:mm")
                                                                    }}</span>
                                                                <span class="text-xs font-medium text-gray-900">{{
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
                                                                <span class="text-xs text-gray-400">{{ segment?.to?.iata
                                                                    }}</span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <!-- Arrival -->
                                                    <div class="space-y-2 text-right">
                                                        <div class="space-y-1">
                                                            <div class="text-sm font-medium text-gray-900">{{
                                                                formatDate(segment?.arrival_at) }}</div>
                                                            <div class="text-xs text-gray-500">{{ segment?.to?.name }}
                                                                ({{
                                                                    segment?.to?.iata }})</div>
                                                            <div class="text-xs text-gray-400">Terminal: {{
                                                                segment?.to_terminal[0] ?? "N/A" }}</div>
                                                        </div>
                                                        <div class="text-xs text-gray-400">{{ segment?.aircraft?.model
                                                            }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
                                <div class="bg-gray-50 border-b border-gray-200 p-3">
                                    <h2 class="text-base font-medium text-gray-900">Price Details</h2>
                                    <p class="text-xs text-gray-500 mt-1">All prices in SAR</p>
                                </div>
                                <div class="p-3">
                                    <div class="space-y-3">
                                        <div v-for="(flight, flightIndex) in flight?.leg?.flights" :key="flightIndex">
                                            <div v-for="(fare, fareIndex) in flight?.fares" :key="fareIndex">
                                                <div v-if="selectedFares?.includes(fare.ref_id)"
                                                    class="bg-gray-50 p-3 space-y-2">
                                                    <div class="flex justify-between items-center">
                                                        <span class="text-xs text-gray-500">Base Fare</span>
                                                        <span class="text-xs font-medium"> {{
                                                            formatAmount(
                                              (calculateFareMargin(
                                                parseFloat(fare?.base_price) || 0,
                                                fare?.margin_amount,
                                                fare?.margin_type,
                                                fare?.amount_type
                                              ) + parseFloat(calculateCustomerPrice(
                                                fare?.base_price,
                                                CustomerMargin?.value?.discount || 0,
                                                CustomerMargin?.value?.margin_amount || 0,
                                              ))) * passengerCount) }}</span>
                                                    </div>
                                                    <div class="flex justify-between items-center">
                                                        <span class="text-xs text-gray-500">Taxes & Fees</span>
                                                        <span class="text-xs font-medium">{{
                                                            formatAmount(calculateTaxes(fare)) }}</span>
                                                    </div>
                                                    <div v-if="ancillaries" class="flex justify-between items-center">
                                                        <span class="text-xs text-gray-500">Add-ones</span>
                                                        <span class="text-xs font-medium">
                                                            {{formatAmount(
                                                                ["baggage", "seat", "meal"].reduce((sum, group) => {
                                                                    const extras = extraCharges[flightIndex]?.[group] || {};

                                                                    // Loop over segments → passengers/items
                                                                    const groupTotal = Object.values(extras).reduce((gSum,
                                                                        segmentGroup) => {
                                                                        if (!segmentGroup) return gSum;
                                                                        return gSum + Object.values(segmentGroup).reduce((sSum,
                                                                            item) => {
                                                                            const price = item.price || item.amount || 0;
                                                                            const qty = item.qty || 1;
                                                                            return sSum + price;
                                                                        }, 0);
                                                                    }, 0);

                                                            return sum + groupTotal;
                                                            }, 0)
                                                            ) }}


                                                        </span>

                                                    </div>
                                                    <div class="border-t border-gray-200 pt-2">
                                                        <div class="flex justify-between items-center">
                                                            <span
                                                                class="text-sm font-medium text-gray-900">Subtotal</span>
                                                            <span class="text-sm font-semibold text-primary">
                                                                {{formatAmount(
                                                                    calculateTotalFare(fare) +
                                                                    ["baggage", "seat", "meal"].reduce((sum, group) => {
                                                                        const extras = extraCharges[flightIndex]?.[group] || {};

                                                                        // Loop deeper: segment → passenger/item
                                                                        const groupTotal = Object.values(extras).reduce((gSum,
                                                                            segmentGroup) => {
                                                                            if (!segmentGroup) return gSum;
                                                                            return gSum + Object.values(segmentGroup).reduce((sSum,
                                                                                item) => {
                                                                                const price = item.price || item.amount || 0;
                                                                                const qty = item.qty || 1;
                                                                                return sSum + price;
                                                                            }, 0);
                                                                        }, 0);

                                                                        return sum + groupTotal;
                                                                }, 0)
                                                                )}}
                                                            </span>


                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="border-t border-gray-200 pt-3">
                                            <div class="bg-primary/10 p-3">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-sm font-medium text-gray-900">Total Amount</span>
                                                    <span class="text-lg font-semibold text-primary">{{
                                                        formatAmount(amount
                                                            = calculateGrandTotal()) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="sticky top-4">
                                <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
                                    <div class="bg-gray-50 border-b border-gray-200 p-3">
                                        <h2 class="text-base font-medium text-gray-900">Complete Booking</h2>
                                    </div>
                                    <div class="p-3">
                                        <div class="space-y-3">
                                            <div class="flex items-start space-x-2">
                                                <input type="checkbox" v-model="termsAccepted" id="terms"
                                                    class="mt-1" />
                                                <Label for="terms" class="text-xs text-gray-500 leading-relaxed">
                                                    I understand and agree with the Privacy Policy, the User <a href="#"
                                                        class="text-primary hover:underline">Agreement and Terms</a> of
                                                    Service of apnaTcket.com
                                                </Label>
                                            </div>
                                            <Button @click="showBookingPreview" :disabled="isSubmitting"
                                                class="w-full bg-primary hover:bg-primary/90 text-sm">
                                                {{ isSubmitting ? "Processing..." : "Save & Preview" }}
                                            </Button>
                                            <div v-if="globalError" class="text-red-500 text-xs text-center">{{
                                                globalError }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div v-if="isOpen" class="fixed inset-0 bg-black/20 backdrop-blur-sm flex items-center justify-center z-50 p-4">
            <div
                class="bg-white rounded-2xl shadow-2xl border border-gray-100 p-8 w-full max-w-2xl transition-all duration-300">

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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
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
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                            <span v-else class="text-xs font-bold">3</span>
                        </div>
                        <span class="mt-3 text-sm font-semibold text-gray-700">Confirmed</span>
                    </div>
                </div>

                <!-- Status Message -->
                <div class="text-center mb-8">
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
                        class="px-8 py-2.5 bg-primary hover:bg-primary/90 text-white font-semibold rounded-xl shadow-lg hover:shadow-xl transition-all duration-200 border-0">
                        View Booking
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content - Flight Source 2 (Other) -->
    <div v-else-if="route.query.flight_source == 2" class="min-h-screen bg-gray-50 py-4">
        <div class="max-w-6xl mx-auto px-3 sm:px-4">
            <div v-if="!isLoading && flight">
                <!-- Header -->
                <div class="mb-4">
                    <div class="bg-white shadow-sm border border-gray-200 p-4">
                        <h1 class="text-xl font-semibold text-gray-900 mb-1">Complete Your Booking</h1>
                        <p class="text-gray-500 text-sm">Please fill in the required information to proceed with your
                            flight booking.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
                    <!-- Main Content -->
                    <div class="lg:col-span-3 space-y-4">
                        <!-- Price Details Card - Moved to top -->
                        <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
                            <div class="bg-gray-50 border-b border-gray-200 p-3">
                                <h2 class="text-base font-medium text-gray-900">Price Details</h2>
                                <p class="text-xs text-gray-500 mt-1">All prices in PKR</p>
                            </div>
                            <div class="p-3">
                                <div class="space-y-3">
                                    <div v-for="(passenger, index) in flight.passengerInfo" :key="index"
                                        class="bg-gray-50 p-3">
                                        <div class="flex items-center justify-between mb-2">
                                            <span class="text-xs font-medium text-gray-700">{{
                                                passenger.passengerType }} Passenger</span>
                                            <span class="text-xs bg-primary/10 text-primary px-2 py-1">{{
                                                passenger.passengerNumber }}</span>
                                        </div>
                                        <div class="space-y-2">
                                            <div class="flex justify-between items-center">
                                                <span class="text-xs text-gray-500">Base Fare</span>
                                                <span class="text-xs font-medium">{{ formatAmount(marginedAmount =
                                                    calculateCustomerPrice(calculateFinalPrice(passenger.passengerTotalFare.equivalentAmount
                                                        * passenger.passengerNumber,
                                                        flight.legs[0].stops[0].airline?.margin_amount *
                                                        passenger.passengerNumber,
                                                        flight.legs[0].stops[0].airline.margin_type,
                                                        flight.legs[0].stops[0].airline.amount_type,) + parseFloat(0 *
                                                            passenger.passengerNumber), CustomerMargin?.discount,
                                                        CustomerMargin?.margin_amount,) +
                                                    parseFloat(CustomerMargin?.other_charges)) }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-xs text-gray-500">Taxes & Fees</span>
                                                <span class="text-xs font-medium">{{ formatAmount(taxFees =
                                                    passenger.passengerTotalFare.totalTaxAmount *
                                                    passenger.passengerNumber) }}</span>
                                            </div>
                                            <div class="border-t border-gray-200 pt-2">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-sm font-medium text-gray-900">Subtotal</span>
                                                    <span class="text-sm font-semibold text-primary">{{
                                                        formatAmount(marginedAmount + taxFees) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="border-t border-gray-200 pt-3">
                                        <div class="space-y-2 text-xs">
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Total Base Fare</span>
                                                <span>{{formatAmount(totalBaseFare =
                                                    calculateCustomerPrice(calculateFinalPrice(flight?.pricing?.totalPrice
                                                        - flight?.pricing?.totalTaxAmount,
                                                        flight?.legs[0]?.stops[0]?.airline?.margin_amount *
                                                        (flight?.passengerInfo?.reduce((total, p) => total +
                                                            p?.passengerNumber, 0)),
                                                        flight?.legs[0]?.stops[0]?.airline?.margin_type,
                                                        flight?.legs[0]?.stops[0]?.airline?.amount_type,),
                                                        CustomerMargin?.discount, CustomerMargin?.margin_amount,) +
                                                    parseFloat(CustomerMargin?.other_charges))}}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-500">Total Taxes & Fees</span>
                                                <span>{{ formatAmount(totalTaxFees = flight.pricing.totalTaxAmount
                                                    || 0) }}</span>
                                            </div>
                                        </div>
                                        <div class="bg-primary/10 p-3 mt-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-medium text-gray-900">Total Amount</span>
                                                <span class="text-lg font-semibold text-primary">{{ formatAmount(amount
                                                    = totalBaseFare + totalTaxFees) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Flight Details Card -->
                        <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
                            <div class="bg-gray-50 border-b border-gray-200 p-3">
                                <h2 class="text-base font-medium text-gray-900 mb-1">Flight Details</h2>
                                <div class="flex flex-wrap items-center gap-3 text-xs text-gray-500">
                                    <span class="font-medium">{{ flight.legs[0]?.stops[0].departure.airport?.city_name
                                    }} → {{ flight.legs[0]?.stops[flight.legs[0]?.stops.length -
                                            1].arrival?.airport?.city_name }}</span>
                                    <span>{{ moment(flight?.dates[0]?.departureDate).format("DD MMM YYYY") }}</span>
                                </div>
                            </div>
                            <div class="divide-y divide-gray-100">
                                <div v-for="(leg, legIndex) in flight.legs" :key="legIndex">
                                    <div v-for="(stop, index) in flight.legs[legIndex]?.stops" :key="index"
                                        class="relative">
                                        <div class="p-4">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                                <!-- Departure -->
                                                <div class="space-y-2">
                                                    <div class="flex items-center space-x-2">
                                                        <img class="w-8 h-8 border border-gray-200"
                                                            :src="stop.airline.logo_url" alt="Airline" />
                                                        <div>
                                                            <div class="text-sm font-medium text-gray-900">{{
                                                                stop.airline.name }}</div>
                                                            <div class="text-xs text-gray-500">{{ stop.flight_number }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="space-y-1">
                                                        <div class="text-sm font-medium text-gray-900">{{
                                                            getAdjustedDateTime(flight?.dates[legIndex]?.departureDate,
                                                                stop?.departure?.time, stop?.adjustment).date }}</div>
                                                        <div class="text-xs text-gray-500">{{
                                                            stop?.departure?.airport?.city_name }} ({{
                                                                stop?.departure?.airport?.iata_code }})</div>
                                                        <div class="text-xs text-gray-400">Terminal: {{
                                                            stop.departure.terminal ?? "N/A" }}</div>
                                                    </div>
                                                </div>
                                                <!-- Flight Path -->
                                                <div class="flex items-center justify-center">
                                                    <div class="w-full max-w-xs">
                                                        <div class="flex items-center justify-between mb-1">
                                                            <span class="text-xs font-medium text-gray-900">{{
                                                                getAdjustedDateTime(flight?.dates[legIndex]?.departureDate,
                                                                    stop?.departure?.time, stop?.adjustment).time }}</span>
                                                            <span class="text-xs font-medium text-gray-900">{{
                                                                getAdjustedDateTime(flight?.dates[legIndex]?.departureDate,
                                                                    stop?.arrival?.time, stop?.adjustment).time }}</span>
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
                                                                stop?.departure?.airport?.iata_code }}</span>
                                                            <span class="text-xs text-gray-400">{{
                                                                stop?.arrival?.airport?.iata_code }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <!-- Arrival -->
                                                <div class="space-y-2 text-right">
                                                    <div class="space-y-1">
                                                        <div class="text-sm font-medium text-gray-900">{{
                                                            getAdjustedDateTime(flight?.dates[legIndex]?.departureDate,
                                                                stop?.arrival?.time, stop?.adjustment).date }}</div>
                                                        <div class="text-xs text-gray-500">{{
                                                            stop?.arrival?.airport?.city_name }} ({{
                                                                stop?.arrival?.airport?.iata_code }})</div>
                                                        <div class="text-xs text-gray-400">Terminal: {{
                                                            stop.arrival.terminal ?? "N/A" }}</div>
                                                    </div>
                                                    <div class="text-xs text-gray-400">{{ stop.aircraft?.name }}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <!-- Layover Badge -->
                                        <div v-if="flight.legs[legIndex]?.stops.length - 1 > index"
                                            class="absolute right-1/2 bottom-0 transform translate-x-1/2 translate-y-1/2 z-10">
                                            <div
                                                class="bg-amber-100 text-amber-800 px-2 py-1 text-xs font-medium border border-amber-200">
                                                Layover: {{ calculateLayoverDetails(stop.departure.time,
                                                    stop.arrival.time) }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information Card -->
                        <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
                            <div class="bg-gray-50 border-b border-gray-200 p-3">
                                <h2 class="text-base font-medium text-gray-900">Contact Information</h2>
                                <p class="text-xs text-gray-500 mt-1">We'll use this information to send you booking
                                    confirmations</p>
                            </div>
                            <div class="p-4">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                                    <div>
                                        <Label for="main-email" class="text-xs font-medium text-gray-600">Email <span
                                                class="text-red-500">*</span></Label>
                                        <Input v-model="mainContact.email" id="main-email" type="email"
                                            placeholder="Enter your email" class="mt-1 text-sm"
                                            :class="{ 'border-red-300': errors.mainContact.email }" />
                                        <div v-if="errors.mainContact.email" class="text-red-500 text-xs mt-1">{{
                                            errors.mainContact.email }}</div>
                                    </div>
                                    <div>
                                        <Label for="main-phone" class="text-xs font-medium text-gray-600">Phone <span
                                                class="text-red-500">*</span></Label>
                                        <Input v-model="mainContact.phone" id="main-phone" type="tel"
                                            placeholder="Enter your phone" class="mt-1 text-sm"
                                            :class="{ 'border-red-300': errors.mainContact.phone }" />
                                        <div v-if="errors.mainContact.phone" class="text-red-500 text-xs mt-1">{{
                                            errors.mainContact.phone }}</div>
                                    </div>
                                    <div>
                                        <Label class="text-xs font-medium text-gray-600">Country <span
                                                class="text-red-500">*</span></Label>
                                        <Popover v-model:open="isOpenCountryDropdown">
                                            <PopoverTrigger as-child>
                                                <Button variant="outline" role="combobox"
                                                    class="w-full justify-between mt-1 text-sm h-9"
                                                    :class="{ 'border-red-300': errors.mainContact.country }">
                                                    {{mainContact.country !== "" ? countries.find((country) =>
                                                        country.value === mainContact.country)?.label || "Select country" :
                                                        "Select country"}}
                                                    <ChevronsUpDown class="ml-2 h-3 w-3 shrink-0 opacity-50" />
                                                </Button>
                                            </PopoverTrigger>
                                            <PopoverContent class="w-full p-0">
                                                <Command>
                                                    <CommandInput @input="fetchCountries"
                                                        placeholder="Search country..." />
                                                    <CommandEmpty>No results found.</CommandEmpty>
                                                    <CommandList>
                                                        <CommandGroup>
                                                            <CommandItem v-for="country in countries"
                                                                :key="country.value" :value="country.label"
                                                                @select="(ev) => { if (typeof ev.detail.value === 'string') { mainContact.country = ev.detail.value; } open = false; }">
                                                                {{ country.label }}
                                                                <Check
                                                                    :class="cn('ml-auto h-4 w-4', mainContact.country === country.value ? 'opacity-100' : 'opacity-0')" />
                                                            </CommandItem>
                                                        </CommandGroup>
                                                    </CommandList>
                                                </Command>
                                            </PopoverContent>
                                        </Popover>
                                        <div v-if="errors.mainContact.country" class="text-red-500 text-xs mt-1">{{
                                            errors.mainContact.country }}</div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 p-3">
                                    <h3 class="text-xs font-medium text-gray-600 mb-2">Agency Contact</h3>
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <Label class="text-xs text-gray-500">Phone</Label>
                                            <Input type="text" v-model="agencyContact.phone" readonly
                                                class="mt-1 bg-gray-100 text-sm" />
                                        </div>
                                        <div>
                                            <Label class="text-xs text-gray-500">Email</Label>
                                            <Input type="email" v-model="agencyContact.email" readonly
                                                class="mt-1 bg-gray-100 text-sm" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Traveller Details Card with Accordions -->
                        <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
                            <div class="bg-gray-50 border-b border-gray-200 p-3">
                                <h2 class="text-base font-medium text-gray-900">Traveller Details</h2>
                                <p class="text-xs text-gray-500 mt-1">Use all names exactly as they appear on your
                                    passport/ID</p>
                            </div>
                            <div class="p-3 space-y-3">
                                <div v-for="(traveller, index) in travellers" :key="`traveller-${index}`"
                                    class="border border-gray-200 overflow-hidden">
                                    <!-- Accordion Header -->
                                    <button @click="traveller.isOpen = !traveller.isOpen"
                                        class="w-full bg-gray-50 p-3 text-left flex items-center justify-between hover:bg-gray-100 transition-colors">
                                        <div>
                                            <h3 class="text-sm font-medium text-gray-900">{{ traveller.type }} Traveller
                                                {{ index + 1 }}</h3>
                                            <p class="text-xs text-gray-500"
                                                v-if="traveller.firstName && traveller.lastName">
                                                {{ traveller.firstName }} {{ traveller.lastName }}
                                            </p>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-500 transition-transform"
                                            :class="{ 'rotate-180': traveller.isOpen }" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </button>

                                    <!-- Accordion Content -->
                                    <div v-show="traveller.isOpen" class="border-t border-gray-200">
                                        <div class="p-3">

                                            <!-- Gender Selection -->
                                            <div class="mb-3">
                                                <RadioGroup class="flex gap-4" v-model="traveller.gender"
                                                    :class="{ 'border-red-300': getErrorPath(`travellers.${index}.gender`) }">
                                                    <Label class="text-xs text-gray-600">Gender <span
                                                            class="text-red-500">*</span></Label>
                                                    <div class="flex items-center space-x-2">
                                                        <RadioGroupItem :id="`male-${index}`" value="M" />
                                                        <Label :for="`male-${index}`" class="text-xs">Male</Label>
                                                    </div>
                                                    <div class="flex items-center space-x-2">
                                                        <RadioGroupItem :id="`female-${index}`" value="F" />
                                                        <Label :for="`female-${index}`" class="text-xs">Female</Label>
                                                    </div>
                                                </RadioGroup>
                                                <div v-if="getErrorPath(`travellers.${index}.gender`)"
                                                    class="text-red-500 text-xs mt-1">{{
                                                        getErrorPath(`travellers.${index}.gender`) }}</div>
                                            </div>

                                            <!-- Personal Information -->
                                            <div class="space-y-3">
                                                <div class="grid grid-cols-1 md:grid-cols-5 gap-3">
                                                    <div>
                                                        <Label :for="`title-${index}`"
                                                            class="text-xs font-medium text-gray-600">Title <span
                                                                class="text-red-500">*</span></Label>
                                                        <Select v-model="traveller.title" :id="`title-${index}`"
                                                            :class="{ 'border-red-300': getErrorPath(`travellers.${index}.title`) }">
                                                            <SelectTrigger class="mt-1 h-8 text-sm">
                                                                <SelectValue placeholder="Select" />
                                                            </SelectTrigger>
                                                            <SelectContent>
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
                                                            class="text-red-500 text-xs mt-1">{{
                                                                getErrorPath(`travellers.${index}.title`) }}</div>
                                                    </div>
                                                    <div>
                                                        <Label :for="`first-name-${index}`"
                                                            class="text-xs font-medium text-gray-600">First Name <span
                                                                class="text-red-500">*</span></Label>
                                                        <Input v-model="traveller.firstName" :id="`first-name-${index}`"
                                                            type="text" placeholder="First name"
                                                            class="mt-1 h-8 text-sm"
                                                            :class="{ 'border-red-300': getErrorPath(`travellers.${index}.firstName`) }" />
                                                        <div v-if="getErrorPath(`travellers.${index}.firstName`)"
                                                            class="text-red-500 text-xs mt-1">{{
                                                                getErrorPath(`travellers.${index}.firstName`) }}</div>
                                                    </div>
                                                    <div>
                                                        <Label :for="`last-name-${index}`"
                                                            class="text-xs font-medium text-gray-600">Last Name <span
                                                                class="text-red-500">*</span></Label>
                                                        <Input v-model="traveller.lastName" :id="`last-name-${index}`"
                                                            type="text" placeholder="Last name" class="mt-1 h-8 text-sm"
                                                            :class="{ 'border-red-300': getErrorPath(`travellers.${index}.lastName`) }" />
                                                        <div v-if="getErrorPath(`travellers.${index}.lastName`)"
                                                            class="text-red-500 text-xs mt-1">{{
                                                                getErrorPath(`travellers.${index}.lastName`) }}</div>
                                                    </div>
                                                    <div>
                                                        <Label :for="`date-of-birth-${index}`"
                                                            class="text-xs font-medium text-gray-600">
                                                            Date of Birth
                                                            <span v-if="traveller.type == 'ADT'"
                                                                class="text-red-500 text-xs">*12+ years</span>
                                                            <span v-else-if="traveller.type == 'CNN'"
                                                                class="text-red-500 text-xs">*2-12 years</span>
                                                            <span v-else-if="traveller.type == 'INF'"
                                                                class="text-red-500 text-xs">*Under 2</span>
                                                        </Label>
                                                        <Input v-model="traveller.dob" type="date"
                                                            :id="`date-of-birth-${index}`" class="mt-1 h-8 text-sm"
                                                            :class="{ 'border-red-300': getErrorPath(`travellers.${index}.dob`) }" />
                                                        <div v-if="getErrorPath(`travellers.${index}.dob`)"
                                                            class="text-red-500 text-xs mt-1">{{
                                                                getErrorPath(`travellers.${index}.dob`) }}</div>
                                                    </div>
                                                    <div>
                                                        <Label :for="`nationality-${index}`"
                                                            class="text-xs font-medium text-gray-600">Nationality <span
                                                                class="text-red-500">*</span></Label>
                                                        <Input v-model="traveller.nationality"
                                                            :id="`nationality-${index}`" type="text"
                                                            placeholder="Nationality" class="mt-1 h-8 text-sm"
                                                            :class="{ 'border-red-300': getErrorPath(`travellers.${index}.nationality`) }" />
                                                        <div v-if="getErrorPath(`travellers.${index}.nationality`)"
                                                            class="text-red-500 text-xs mt-1">{{
                                                                getErrorPath(`travellers.${index}.nationality`) }}</div>
                                                    </div>
                                                </div>

                                                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                                                    <div>
                                                        <Label :for="`document-type-${index}`"
                                                            class="text-xs font-medium text-gray-600">Document Type
                                                            <span class="text-red-500">*</span></Label>
                                                        <Select v-model="traveller.documentType"
                                                            :id="`document-type-${index}`"
                                                            :class="{ 'border-red-300': getErrorPath(`travellers.${index}.documentType`) }">
                                                            <SelectTrigger class="mt-1 h-8 text-sm">
                                                                <SelectValue placeholder="Select" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                <SelectGroup>
                                                                    <SelectItem value="passport">Passport</SelectItem>
                                                                    <SelectItem value="id">ID Card</SelectItem>
                                                                </SelectGroup>
                                                            </SelectContent>
                                                        </Select>
                                                        <div v-if="getErrorPath(`travellers.${index}.documentType`)"
                                                            class="text-red-500 text-xs mt-1">{{
                                                                getErrorPath(`travellers.${index}.documentType`) }}</div>
                                                    </div>
                                                    <div>
                                                        <Label :for="`document-no-${index}`"
                                                            class="text-xs font-medium text-gray-600">Document Number
                                                            <span class="text-red-500">*</span></Label>
                                                        <Input v-model="traveller.documentNo"
                                                            :id="`document-no-${index}`" type="text"
                                                            placeholder="Document number" class="mt-1 h-8 text-sm"
                                                            :class="{ 'border-red-300': getErrorPath(`travellers.${index}.documentNo`) }" />
                                                        <div v-if="getErrorPath(`travellers.${index}.documentNo`)"
                                                            class="text-red-500 text-xs mt-1">{{
                                                                getErrorPath(`travellers.${index}.documentNo`) }}</div>
                                                    </div>
                                                    <div>
                                                        <Label :for="`expiry-date-${index}`"
                                                            class="text-xs font-medium text-gray-600">Expiry Date <span
                                                                class="text-red-500">*</span></Label>
                                                        <Input v-model="traveller.expiryDate"
                                                            :id="`expiry-date-${index}`" type="date"
                                                            class="mt-1 h-8 text-sm"
                                                            :class="{ 'border-red-300': getErrorPath(`travellers.${index}.expiryDate`) }" />
                                                        <div v-if="getErrorPath(`travellers.${index}.expiryDate`)"
                                                            class="text-red-500 text-xs mt-1">{{
                                                                getErrorPath(`travellers.${index}.expiryDate`) }}</div>
                                                    </div>
                                                    <div>
                                                        <Label :for="`issue-country-${index}`"
                                                            class="text-xs font-medium text-gray-600">Issue Country
                                                            <span class="text-red-500">*</span></Label>
                                                        <Input v-model="traveller.issueCountry"
                                                            :id="`issue-country-${index}`" type="text"
                                                            placeholder="Issue country" class="mt-1 h-8 text-sm"
                                                            :class="{ 'border-red-300': getErrorPath(`travellers.${index}.issueCountry`) }" />
                                                        <div v-if="getErrorPath(`travellers.${index}.issueCountry`)"
                                                            class="text-red-500 text-xs mt-1">{{
                                                                getErrorPath(`travellers.${index}.issueCountry`) }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar - Booking Actions -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-4">
                            <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
                                <div class="bg-gray-50 border-b border-gray-200 p-3">
                                    <h2 class="text-base font-medium text-gray-900">Complete Booking</h2>
                                </div>
                                <div class="p-3">
                                    <div class="space-y-3">
                                        <div class="flex items-start space-x-2">
                                            <Input type="checkbox" v-model="termsAccepted" id="terms" class="mt-1" />
                                            <Label for="terms" class="text-xs text-gray-500 leading-relaxed">
                                                I understand and agree with the Privacy Policy, the User <a href="#"
                                                    class="text-primary hover:underline">Agreement and Terms</a> of
                                                Service of apnaTcket.com
                                            </Label>
                                        </div>
                                        <Button @click="saveBooking" :disabled="!termsAccepted || isSubmitting"
                                            class="w-full bg-primary hover:bg-primary/90 text-sm">
                                            {{ isSubmitting ? "Processing..." : "Complete Booking" }}
                                        </Button>
                                        <div v-if="globalError" class="text-red-500 text-xs text-center">{{ globalError
                                        }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div v-if="showPreview && !isLoading && flight" class="min-h-screen bg-gray-50 py-4">
        <div class="max-w-7xl mx-auto px-3 sm:px-4">
            <!-- Preview Header -->
            <div class="mb-4">
                <div class="bg-white shadow-sm border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <h1 class="text-xl font-semibold text-gray-900 mb-1">Booking Preview</h1>
                            <p class="text-gray-500 text-sm">Please review your booking details before completing the
                                payment</p>
                        </div>
                        <Button @click="backToForm" variant="outline" class="flex items-center gap-2">
                            <ArrowLeft class="w-4 h-4" />
                            Back to Form
                        </Button>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                <!-- Preview Content -->
                <div class="lg:col-span-3 space-y-4">
                    <!-- Flight Details Preview -->
                    <div class="bg-white shadow-sm border border-gray-200">
                        <div class="bg-gray-50 border-b border-gray-200 p-3">
                            <h2 class="text-base font-medium text-gray-900">Flight Details</h2>
                        </div>
                        <div class="p-4">

                            <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600 mb-4">


                            </div>

                            <!-- Flight segments preview -->
                            <div v-for="(flight, flightIndex) in flight?.leg?.flights" :key="flightIndex">
                                <div v-for="(segment, segmentIndex) in flight?.segments" :key="segmentIndex">
                                    <!-- Layover Info -->
                                    <div v-if="segment?.layover_time"
                                        class="bg-amber-50 border-l-4 border-amber-400 p-3">
                                        <div class="flex items-center justify-center">
                                            <ClockIcon class="w-4 h-4 text-amber-600 mr-2" />
                                            <span class="text-xs font-medium text-amber-800">
                                                Layover: {{ moment.utc(moment.duration(segment.layover_time,
                                                    "minutes").asMilliseconds()).format("HH:mm") }}
                                            </span>
                                        </div>
                                    </div>
                                    <!-- Flight Segment -->
                                    <div class="p-4">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <!-- Departure -->
                                            <div class="space-y-2">
                                                <div class="flex items-center space-x-2">
                                                    <img class="w-8 h-8 border border-gray-200"
                                                        :src="segment?.operating_carrier?.logo" alt="Airline" />
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900">{{
                                                            segment?.operating_carrier?.name }}</div>
                                                        <div class="text-xs text-gray-500">{{
                                                            segment?.flight_number
                                                        }}</div>
                                                    </div>
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="text-sm font-medium text-gray-900">{{
                                                        formatDate(segment?.departure_at) }}</div>
                                                    <div class="text-xs text-gray-500">{{ segment?.from?.name }}
                                                        ({{
                                                            segment?.from?.iata }})</div>
                                                    <div class="text-xs text-gray-400">Terminal: {{
                                                        segment?.from_terminal[0] ?? "N/A" }}</div>
                                                </div>
                                            </div>
                                            <!-- Flight Path -->
                                            <div class="flex items-center justify-center">
                                                <div class="w-full max-w-xs">
                                                    <div class="flex items-center justify-between mb-1">
                                                        <span class="text-xs font-medium text-gray-900">{{
                                                            moment(segment?.departure_at).format("HH:mm")
                                                        }}</span>
                                                        <span class="text-xs font-medium text-gray-900">{{
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
                                                        <span class="text-xs text-gray-400">{{ segment?.to?.iata
                                                        }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <!-- Arrival -->
                                            <div class="space-y-2 text-right">
                                                <div class="space-y-1">
                                                    <div class="text-sm font-medium text-gray-900">{{
                                                        formatDate(segment?.arrival_at) }}</div>
                                                    <div class="text-xs text-gray-500">{{ segment?.to?.name }}
                                                        ({{
                                                            segment?.to?.iata }})</div>
                                                    <div class="text-xs text-gray-400">Terminal: {{
                                                        segment?.to_terminal[0] ?? "N/A" }}</div>
                                                </div>
                                                <div class="text-xs text-gray-400">{{ segment?.aircraft?.model
                                                }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Contact Information Preview -->
                    <div class="bg-white shadow-sm border border-gray-200">
                        <div class="bg-gray-50 border-b border-gray-200 p-3">
                            <h2 class="text-base font-medium text-gray-900">Contact Information</h2>
                        </div>
                        <div class="p-4">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                                <div>
                                    <div class="text-gray-500">Email</div>
                                    <div class="font-medium">{{ mainContact.email }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Phone</div>
                                    <div class="font-medium">{{ mainContact.phone }}</div>
                                </div>
                                <div>
                                    <div class="text-gray-500">Country</div>
                                    <div class="font-medium">{{ mainContact.country }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Travellers Preview -->
                    <div class="bg-white shadow-sm border border-gray-200">
                        <div class="bg-gray-50 border-b border-gray-200 p-3">
                            <h2 class="text-base font-medium text-gray-900">Traveller Details</h2>
                        </div>
                        <div class="p-4 space-y-4">
                            <div v-for="(traveller, index) in travellers" :key="index"
                                class="border border-gray-200 p-3">
                                <h3 class="text-sm font-medium text-gray-900 mb-3">{{ traveller.type }} Traveller {{
                                    index +
                                    1 }}</h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                                    <div>
                                        <div class="text-gray-500">Name</div>
                                        <div class="font-medium">{{ traveller.title }} {{ traveller.firstName }} {{
                                            traveller.lastName }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500">Gender</div>
                                        <div class="font-medium">{{ traveller.gender === 'M' ? 'Male' : 'Female' }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500">Date of Birth</div>
                                        <div class="font-medium">{{ moment(traveller.dob).format("DD MMM YYYY") }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500">Nationality</div>
                                        <div class="font-medium">{{ traveller.nationality }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500">Document Type</div>
                                        <div class="font-medium">{{ traveller.documentType }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500">Document Number</div>
                                        <div class="font-medium">{{ traveller.documentNo }}</div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500">Expiry Date</div>
                                        <div class="font-medium">{{ moment(traveller.expiryDate).format("DD MMM YYYY")
                                            }}
                                        </div>
                                    </div>
                                    <div>
                                        <div class="text-gray-500">Issue Country</div>
                                        <div class="font-medium">{{ traveller.issueCountry }}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Extras Preview -->
                    <div v-if="Object.keys(extraCharges).length > 0" class="bg-white  border border-gray-200 ">
                        <div class="bg-gray-100 px-6 py-4 border-b border-gray-200">
                            <h2 class="text-xl font-bold text-gray-900">Selected Flight Extras</h2>
                        </div>
                        <div class="p-6 border border-gray-200">
                            <div v-for="(flightExtras, flightIdx) in extraCharges" :key="flightIdx"
                                class="mb-8 last:mb-0">
                                <h3 class="text-base font-semibold text-gray-800 mb-4">Flight {{ parseInt(flightIdx) + 1
                                }}
                                </h3>
                               <div class="space-y-4">
  <!-- Baggage -->
  <div v-if="flightExtras.baggage">
    <div v-for="(item, i) in selectedExtras[flightIdx]?.baggage" :key="'baggage-' + i"
      class="flex justify-between items-center text-sm bg-gray-50 p-3 border border-gray-200">
      <span class="text-gray-700 font-medium">Extra Baggage: {{ item.title }}</span>
      <span class="font-semibold text-gray-900">{{ formatAmount((item.price || item.amount) ) }}</span>
    </div>
  </div>

  <!-- Seat -->
  <div v-if="flightExtras.seat">
    <div v-for="(item, i) in selectedExtras[flightIdx]?.seat" :key="'seat-' + i"
      class="flex justify-between items-center text-sm bg-gray-50 p-3 border border-gray-200">
      <span class="text-gray-700 font-medium">Seat Selection: {{ item.title }}</span>
      <span class="font-semibold text-gray-900">{{ formatAmount((item.price || item.amount) * (item.qty || 1)) }}</span>
    </div>
  </div>

  <!-- Meal -->
  <div v-if="flightExtras.meal">
    <div v-for="(item, i) in selectedExtras[flightIdx]?.meal" :key="'meal-' + i"
      class="flex justify-between items-center text-sm bg-gray-50 p-3 border border-gray-200">
      <span class="text-gray-700 font-medium">
        Meal: {{ item.title }} (Qty: {{ item.qty || 1 }})
      </span>
      <span class="font-semibold text-gray-900">{{ formatAmount((item.price || item.amount) ) }}</span>
    </div>
  </div>
</div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Sidebar -->
                <div class="lg:col-span-2">
                    <div class="sticky top-2 space-y-4">
                        <!-- Price Summary -->
                        <div class="bg-white shadow-sm border border-gray-200">
                            <div class="bg-gray-50 border-b border-gray-200 p-3">
                                <h2 class="text-base font-medium text-gray-900">Price Summary</h2>
                                <p class="text-xs text-gray-500 mt-1">All prices in SAR</p>
                            </div>
                            <div class="p-3">
                                <div class="space-y-3">
                                    <div v-for="(flight, flightIndex) in flight?.leg?.flights" :key="flightIndex">
                                        <div v-for="(fare, fareIndex) in flight?.fares" :key="fareIndex">
                                            <div v-if="selectedFares?.includes(fare.ref_id)"
                                                class="bg-gray-50 p-3 space-y-2">
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs text-gray-500">Base Fare</span>
                                                    <span class="text-xs font-medium">{{
                                                            formatAmount(
                                              (calculateFareMargin(
                                                parseFloat(fare?.base_price) || 0,
                                                fare?.margin_amount,
                                                fare?.margin_type,
                                                fare?.amount_type
                                              ) + parseFloat(calculateCustomerPrice(
                                                fare?.base_price,
                                                CustomerMargin?.value?.discount || 0,
                                                CustomerMargin?.value?.margin_amount || 0,
                                              ))) * passengerCount) }}</span>
                                                </div>
                                                <div class="flex justify-between items-center">
                                                    <span class="text-xs text-gray-500">Taxes & Fees</span>
                                                    <span class="text-xs font-medium">{{
                                                        formatAmount(calculateTaxes(fare))
                                                        }}</span>
                                                </div>
                                                <div v-if="ancillaries" class="flex justify-between items-center">
                                                    <span class="text-xs text-gray-500">Add-ons</span>
                                                    <span class="text-xs font-medium">
                                                         {{formatAmount(
                                                                    calculateTotalFare(fare) +
                                                                    ["baggage", "seat", "meal"].reduce((sum, group) => {
                                                                        const extras = extraCharges[flightIndex]?.[group] || {};

                                                                        // Loop deeper: segment → passenger/item
                                                                        const groupTotal = Object.values(extras).reduce((gSum,
                                                                            segmentGroup) => {
                                                                            if (!segmentGroup) return gSum;
                                                                            return gSum + Object.values(segmentGroup).reduce((sSum,
                                                                                item) => {
                                                                                const price = item.price || item.amount || 0;
                                                                                const qty = item.qty || 1;
                                                                                return sSum + price;
                                                                            }, 0);
                                                                        }, 0);

                                                                        return sum + groupTotal;
                                                                }, 0)
                                                                )}}
                                                    </span>
                                                </div>
                                                <div class="border-t border-gray-200 pt-2">
                                                    <div class="flex justify-between items-center">
                                                        <span class="text-sm font-medium text-gray-900">Subtotal</span>
                                                         <span class="text-sm font-semibold text-primary">
                                                                {{formatAmount(
                                                                    calculateTotalFare(fare) +
                                                                    ["baggage", "seat", "meal"].reduce((sum, group) => {
                                                                        const extras = extraCharges[flightIndex]?.[group] || {};

                                                                        // Loop deeper: segment → passenger/item
                                                                        const groupTotal = Object.values(extras).reduce((gSum,
                                                                            segmentGroup) => {
                                                                            if (!segmentGroup) return gSum;
                                                                            return gSum + Object.values(segmentGroup).reduce((sSum,
                                                                                item) => {
                                                                                const price = item.price || item.amount || 0;
                                                                                const qty = item.qty || 1;
                                                                                return sSum + price;
                                                                            }, 0);
                                                                        }, 0);

                                                                        return sum + groupTotal;
                                                                }, 0)
                                                                )}}
                                                            </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="border-t border-gray-200 pt-3">
                                        <div class="bg-primary/10 p-3">
                                            <div class="flex justify-between items-center">
                                                <span class="text-sm font-medium text-gray-900">Total Amount</span>
                                                <span class="text-lg font-semibold text-primary">{{ formatAmount(amount
                                                    =
                                                    calculateGrandTotal()) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Complete Booking -->
                        <div class="bg-white shadow-sm border border-gray-200">
                            <div class="bg-gray-50 border-b border-gray-200 p-3">
                                <h2 class="text-base font-medium text-gray-900">Complete Booking</h2>
                            </div>
                            <div class="p-3">
                                <div class="space-y-3">
                                    <div class="flex items-start space-x-2">
                                        <Input type="checkbox" v-model="termsAccepted" id="terms-preview"
                                            class="mt-1" />
                                        <Label for="terms-preview" class="text-xs text-gray-500 leading-relaxed">
                                            I understand and agree with the Privacy Policy, the User
                                            <a href="#" class="text-primary hover:underline">Agreement and Terms</a>
                                            of Service of apnaTcket.com
                                        </Label>
                                    </div>
                                    <Button @click="completeBookingFromPreview"
                                        :disabled="!termsAccepted || isSubmitting"
                                        class="w-full bg-primary hover:bg-primary/90 text-sm">
                                        {{ isSubmitting ? "Processing..." : "Complete Booking" }}
                                    </Button>
                                    <div v-if="globalError" class="text-red-500 text-xs text-center">{{ globalError }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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

            <div id="card-element" class="mb-4 p-3 border rounded bg-gray-50"></div>
            <div v-if="paymentError" class="text-red-500 mb-2 text-sm">{{ paymentError }}</div>

            <div class="flex justify-end space-x-3">
                <Button @click="closePaymentDialog" class="bg-gray-200 text-gray-700">
                    Cancel
                </Button>
                <Button @click="handlePayment" :disabled="processing" class="bg-primary hover:bg-primary/90 text-white">
                    <Spinner v-if="processing" class="mr-2" />
                    Pay {{ formatAmount(amount) }}
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

```
