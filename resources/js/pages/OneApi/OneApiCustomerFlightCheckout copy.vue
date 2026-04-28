<script setup>
import Spinner from "@/components/common/Spinner.vue";
import Button from "@/components/ui/button/Button.vue";
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { ArrowLeft, CheckCircle, ClockIcon, Package, PlusCircle, SquareCheckBig, SquareX, Upload, Utensils, XCircle, X, Check } from "lucide-vue-next";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
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
    FETCH_AGENT_LEDGER,
    FETCH_CUSTOMER_SETTINGS,
    FETCH_AIRPORT_MARGINS,
    SEND_PRICE_REQUEST,
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
    calculateTypeMargin,
} from "@/lib/utils";
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

const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
const router = useRouter();
const termsAccepted = ref(false);
const validationErrors = ref([]);
const amount = ref(0);
const passengerCount = ref(0);
const currentSlide = ref(0);
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const quote = computed(() => store.getters["flight/priceRes"]);
const ancillaries = computed(() => store.getters["flight/ancillaries"]);
const qouteError = computed(() => store.getters["flight/priceError"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const customerSettings = computed(() => store.getters['customer/customerSettings']);

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
const typeMargin = ref(0);

const elements = ref(null);
const isLowBalanceDialogOpen = ref(false);
const isPaymentMethodsVisible = ref(false);
const countdown = ref(null);
const showDialog = ref(false);
const timerInterval = ref();
const customerMarginAmt = ref(0);
const tempSelectedSeat = ref({}) // Temporary storage for seat selection in dialog

const seatLetters = ['A', 'B', 'C', 'D', 'E', 'F'];
const openSections = ref([])

// Selection state
const tempBaggageSelection = ref({})
const tempMealSelection = ref({})
const tempSeatSelection = ref({})
const activeTravelerTab = ref(0)
const activeMealTravelerTab = ref(0);
const activeSeatTravelerTab = ref(0);
const activeBaggageTravelerTab = ref(0);
const selectedSeatsMap = ref(new Map());

const cardElement = ref(null);
const clientSecret = ref('');
const publicKey = ref(import.meta.env.VITE_STRIPE_KEY);
const safepayUrl = computed(
    () => store.getters["safepay/safePayUrl"],
);

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

const totalPrice = computed(() => {
    const basePrice = parseFloat(flight.value?.pricing?.totalPrice || "0");
    const marginAmount = parseFloat("0");
    return basePrice + marginAmount;
});

const airportMargins = computed(() => store.getters["airport/airportMargin"] || {});

// Function to fetch margins
const fetchMargins = async () => {
    await store.dispatch("airport/" + FETCH_AIRPORT_MARGINS);
};

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

// ==================== INITIALIZATION FUNCTIONS ====================

// Initialize travellers from query params
const initializeTravellers = () => {
    const adults = parseInt(route.query.adults || 0);
    const childs = parseInt(route.query.children || 0);
    const infants = parseInt(route.query.infants || 0);

    // Create traveler object
    const createTraveler = (type) => ({
        type,
        title: "",
        firstName: "",
        lastName: "",
        nationality: "",
        documentType: "passport",
        documentNo: "",
        cnic: "",
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

    // Initialize ancillary data structures
    initializeAncillaryStructures();
};

// Watch for CNIC formatting
watch(
    travellers,
    (newTravellers) => {
        newTravellers.forEach((traveller) => {
            if (traveller.type === "ADT" && traveller.cnic) {
                traveller.cnic = formatCnic(traveller.cnic);
            }
        });
    },
    { deep: true }
);

// CNIC formatter function
function formatCnic(value) {
    value = value.replace(/\D/g, "");
    if (value.length > 5 && value.length <= 12) {
        value = value.replace(/(\d{5})(\d+)/, "$1-$2");
    } else if (value.length > 12) {
        value = value.replace(/(\d{5})(\d{7})(\d+)/, "$1-$2-$3");
    }
    return value.substring(0, 15);
}

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

function fetchCustomerSettings() {
    store.dispatch("customer/" + FETCH_CUSTOMER_SETTINGS)
}

// ==================== VALIDATION FUNCTIONS ====================

const validateEmail = (email) => {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
};

const validatePhone = (phone) => {
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
    return expiryDate > today;
};

const validateDob = (dob, type) => {
    if (!validateDate(dob)) return false;
    const today = new Date();
    const birthDate = new Date(dob);
    const age = today.getFullYear() - birthDate.getFullYear();
    const hasBirthdayOccurred =
        today.getMonth() > birthDate.getMonth() ||
        (today.getMonth() === birthDate.getMonth() &&
            today.getDate() >= birthDate.getDate());
    const adjustedAge = hasBirthdayOccurred ? age : age - 1;

    switch (type) {
        case "ADT": return adjustedAge >= 12;
        case "CNN": return adjustedAge >= 2 && adjustedAge < 12;
        case "INF": return adjustedAge < 2;
        default: return true;
    }
};

// Main validation function
const validateForm = () => {
    let isValid = true;

    globalError.value = "";
    errors.mainContact.email = "";
    errors.mainContact.phone = "";
    errors.mainContact.country = "";

    errors.travellers.forEach((traveller) => {
        Object.keys(traveller).forEach((key) => {
            traveller[key] = "";
        });
    });

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
        } else if (traveller.nationality.length !== 2) {
            errors.travellers[index].nationality = "Nationality must be 2 characters (e.g., PK)";
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
            errors.travellers[index].expiryDate = "Document must not be expired";
            isValid = false;
        }
    });

    return isValid;
};

function handleConfirmDialogOpen() {
    console.log("called");
    console.log("agenledger", agentLedger?.value.balance);
    if (agentLedger?.value.balance < amount?.value || agentLedger?.value.balance == 0) {
        isLowBalanceDialogOpen.value = true;
        toast
        return;
    }
    if (!validateForm()) {
        globalError.value = "Please fix the errors in the form before submitting";
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
    }
    isConfirmDialogOpen.value = true;
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

const showBookingPreview = () => {
    if (!validateForm()) {
        globalError.value = "Please fix the errors in the form before proceeding";
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
    }
    console.log(errors);
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
        flight_provider: route.query.flight_provider,
        quote: quote.value,
        flight: flight.value,
    });
}

function validatePriceWithBundle() {
    store.dispatch("flight/" + SEND_PRICE_REQUEST, {
        flight: flight.value,
        flight_provider: route.query.flight_provider,
        selectedFares: selectedFares.value,
        adults: route.query.adults,
        children: route.query.children,
        infants: route.query.infants,
    });
}

watch(quote, () => {
    if (!quote.value) {
        router.back();
        return;
    }
    fetchAncillaries();
});

function fetchCustomerMarginValues() {
    store.dispatch("customerMargin/" + FETCH_CUSTOMER_MARGIN);
}

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
    let selectedFlight;
    selectedFlight = localStorage.getItem("selectedFlight");
    flight.value = JSON.parse(selectedFlight);
    validatePriceWithBundle();
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
}

function sendSooperQoute() {
    localStorage.setItem("selectedFlight", JSON.stringify(flight.value));

    const body = {
        ref_id: flight?.value?.ref_id,
        legs: flight?.value?.leg?.flights
            .map(flightItem => {
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
    store.dispatch("flight/" + PATCH_ANCILLARIES, {
        quote: quote?.value,
        flight_provider: route.query.flight_provider,
        flight: flight.value,
        selectedExtras: selectedExtras.value,
        extraCharges: extraCharges.value
    });
}

function parsePnrResponse() {
    try {
        const pnrResponseString = bookingDetails?.value?.pnr_response;
        if (pnrResponseString) {
            pnrData.value = JSON.parse(pnrResponseString);
        } else {
            pnrData.value = null;
        }
    } catch (e) {
        console.error("Failed to parse pnr_response:", e);
        pnrData.value = null;
    }
}

const openPaymentDialog = async () => {
    showPaymentDialog.value = true;
    await nextTick();

    if (!stripe.value || !cardElement.value) {
        await initializeStripe();
    }

    const container = document.getElementById('card-element');
    if (container && !container.hasChildNodes()) {
        cardElement.value.mount('#card-element');
    } else if (!container) {
        console.error('Card element container not found');
        toast.error('Payment form not available. Please try again.');
        showPaymentDialog.value = false;
    }
};

const closePaymentDialog = () => {
    showPaymentDialog.value = false;
    paymentError.value = '';
    if (cardElement.value) {
        cardElement.value.unmount();
        cardElement.value.clear();
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
            amount: Math.round(amount.value * 100),
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
        globalError.value = "Please fix the errors in the form before submitting";
        window.scrollTo({ top: 0, behavior: "smooth" });
        return;
    }

    if (type === 'pay') {
        return;
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
        return;
        paymentMethod.value = 'card';
        openPaymentDialog();
    }
}

const initializeStripe = async () => {
    try {
        if (stripe.value) {
            return;
        }

        stripe.value = await loadStripe(publicKey.value);
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
            agent_id: user_id.value,
            agency_mobile: user.value?.phone || "00000",
            agency_email: user?.value?.email || "support@flyunique.pk",
            amount: amount.value,
            flight: flight.value,
            airportMargin: typeMargin.value,
            booking_status_setting: bookingStatusSetting?.value.bookingStatus,
            flight_source: route?.query.flight_source,
            flight_mode: "B2C",
            flight_id: route?.query.flight_id,
            flight_provider: route?.query.flight_provider,
            fare_reference: selectedFares.value,
            agent_markup: customerMarginAmt.value,
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
        name: "BookingsDetails",
        query: {
            flight_id: route.query.flight_id,
            booking_source: route.query.flight_source,
            flight_mode: route.query.flight_mode,
            flight_provider: route.query.flight_provider,
        },
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
    } else if (paymentMethod.value === 'hold') {
        parsePnrResponse();
        router.push({
            name: "BookingsDetails",
            query: {
                flight_id: route.query.flight_id,
                booking_id: bookingDetails?.value?.id,
                pnr: pnrData?.value?.bookingId,
                flight_mode: route.query.flight_mode,
                booking_source: route.query.flight_source,
                flight_provider: route.query.flight_provider,
            },
        });
    }
});

watch(isLoading, () => {
    if (isLoading.value == false) {
        currentState.value = 2;
    }
});

// ==================== ANCILLARY FUNCTIONS FROM FIRST LAYOUT ====================

// Initialize all ancillary data structures
const initializeAncillaryStructures = () => {
    try {
        const travelerCount = travellers.value?.length || 0;

        // Initialize open sections
        openSections.value = Array(travelerCount).fill([]);

        // Initialize temp selections
        if (!tempBaggageSelection.value) tempBaggageSelection.value = {};
        if (!tempMealSelection.value) tempMealSelection.value = {};
        if (!tempSeatSelection.value) tempSeatSelection.value = {};

        // Initialize selectedExtras with the same structure as first layout
        if (!selectedExtras.value) selectedExtras.value = [];

        // Initialize active traveler tabs
        activeTravelerTab.value = 0;
        activeSeatTravelerTab.value = 0;
        activeMealTravelerTab.value = 0;
        activeBaggageTravelerTab.value = 0;
    } catch (error) {
        console.error('Error initializing ancillary structures:', error);
    }
};

// Watch for traveller changes
watch(() => travellers?.value?.length, (newCount) => {
    if (newCount && newCount > 0) {
        initializeAncillaryStructures();
    }
}, { immediate: true });

// ==================== SEAT FUNCTIONS (from first layout) ====================

// Process seat map data from API
const normalizedSeats = computed(() => {
    let responses = ancillaries.value?.ancillaries?.seats
        ?.Body
        ?.OTA_AirSeatMapRS
        ?.SeatMapResponses
        ?.SeatMapResponse || [];
    
    // Convert to array if not already
    if (!Array.isArray(responses)) {
        responses = responses ? [responses] : [];
    }

    return responses.map((response, idx) => {
        const flightSegmentInfo = response.FlightSegmentInfo?.['@attributes'] || {};
        const cabinClass = response.SeatMapDetails?.CabinClass;
        const airRows = cabinClass?.AirRows?.AirRow || [];
        
        // Parse rows and seats
        const rows = Array.isArray(airRows) ? airRows : [airRows];
        
        return {
            id: idx,
            segmentInfo: {
                segmentCode: flightSegmentInfo.SegmentCode,
                flightNumber: flightSegmentInfo.FlightNumber,
                departure: flightSegmentInfo.DepartureDateTime,
                arrival: flightSegmentInfo.ArrivalDateTime,
                from: flightSegmentInfo.SegmentCode?.split('/')[0],
                to: flightSegmentInfo.SegmentCode?.split('/')[1]
            },
            rows: rows.map(row => {
                const rowAttrs = row['@attributes'] || {};
                const airSeats = row.AirSeats?.AirSeat || [];
                const seats = Array.isArray(airSeats) ? airSeats : [airSeats];
                
                return {
                    rowNumber: rowAttrs.RowNumber,
                    maxSeats: rowAttrs.MaxNumberOfSeats,
                    seats: seats.map(seat => {
                        const seatAttrs = seat['@attributes'] || {};
                        return {
                            seatNumber: seatAttrs.SeatNumber,
                            seatAvailability: seatAttrs.SeatAvailability, // VAC or RES
                            seatPrice: parseFloat(seatAttrs.SeatCharacteristics) || 0,
                            currencyCode: seatAttrs.CurrencyCode || 'PKR',
                            seatCharacteristics: seatAttrs.SeatCharacteristics,
                            row: rowAttrs.RowNumber,
                            refId: `${flightSegmentInfo.FlightNumber}_${rowAttrs.RowNumber}_${seatAttrs.SeatNumber}`
                        };
                    })
                };
            })
        };
    });
});

// Check if seat data exists
const hasSeatData = computed(() => normalizedSeats.value?.length > 0);

// Get seats for a specific segment
const getSeatsForSegment = (segment) => {
    if (!segment || !normalizedSeats.value.length) return null;
    // Try to match by segment code
    const segmentCode = segment.segment || segment.code || '';
    const seatData = normalizedSeats.value.find(seatItem => 
        seatItem.segmentInfo.segmentCode === segmentCode ||
        seatItem.segmentInfo.flightNumber === segment.flightNumber
    );
    
    return seatData;
};

// Get seat rows for a segment
const getSeatRows = (segment) => {
    const seatData = getSeatsForSegment(segment);
    return seatData?.rows || [];
};

// Get seat in specific row by letter
const getSeatInRow = (row, letter) => {
    try {
        if (!row?.seats || !letter) return [];

        return row.seats.filter(seat =>
            seat.seatNumber === letter
        );
    } catch (error) {
        console.error('Error getting seat in row:', error);
        return [];
    }
};

// Get selected seat for a specific traveler
const getSeatForTraveler = (segmentIdx, travelerIndex) => {
    if (!segmentIdx === undefined || travelerIndex === undefined) return null;
    try {
        return selectedExtras.value?.[0]?.seat?.[segmentIdx]?.[travelerIndex] || null;
    } catch (error) {
        console.error('Error getting selected seat:', error);
        return null;
    }
};

// Check if a specific seat is selected for a traveler
const isSeatSelected = (segmentIdx, travelerIndex, seat) => {
    try {
        if (!seat) return false;

        const seatNumber = seat.seatNumber;
        const rowNumber = seat.row;

        const selected = getSeatForTraveler(segmentIdx, travelerIndex);
        return selected?.seatNumber === seatNumber &&
            selected?.rowNumber === rowNumber?.toString();
    } catch (error) {
        console.error('Error checking seat selection:', error);
        return false;
    }
};

// Check if seat is selected by any traveler (excluding current)
const isSeatSelectedByAnyTraveler = (segmentIdx, rowNumber, seatNumber, excludeTravelerIndex = -1) => {
    try {
        if (!seatNumber || !rowNumber || segmentIdx === undefined) return false;
        
        const segmentSeats = selectedExtras.value?.[0]?.seat?.[segmentIdx];
        if (!segmentSeats) return false;
        
        for (const [travelerIdx, seatData] of Object.entries(segmentSeats)) {
            if (parseInt(travelerIdx) !== excludeTravelerIndex && 
                seatData?.rowNumber === rowNumber?.toString() && 
                seatData?.seatNumber === seatNumber) {
                return true;
            }
        }
        return false;
    } catch (error) {
        console.error('Error checking seat selection by any traveler:', error);
        return false;
    }
};

// Check if seat is selected by specific traveler
const isSeatSelectedByTraveler = (segmentIdx, travelerIndex, rowNumber, seatNumber) => {
    try {
        const selected = getSeatForTraveler(segmentIdx, travelerIndex);
        return selected?.rowNumber === rowNumber?.toString() &&
            selected?.seatNumber === seatNumber;
    } catch (error) {
        console.error('Error checking seat selection by traveler:', error);
        return false;
    }
};

// Get seat status (selected, taken, available)
const getSeatStatus = (segmentIdx, travelerIndex, rowNumber, seatNumber) => {
    try {
        if (isSeatSelectedByTraveler(segmentIdx, travelerIndex, rowNumber, seatNumber)) {
            return 'selected';
        } else if (isSeatSelectedByAnyTraveler(segmentIdx, rowNumber, seatNumber, travelerIndex)) {
            return 'taken';
        } else {
            return 'available';
        }
    } catch (error) {
        console.error('Error getting seat status:', error);
        return 'available';
    }
};

// Get seat class for styling
const getSeatClass = (segmentIdx, travelerIndex, rowNumber, seatNumber, seatAvailability) => {
    try {
        const status = getSeatStatus(segmentIdx, travelerIndex, rowNumber, seatNumber);

        if (status === 'selected') {
            return 'ring-2 ring-primary ring-offset-1 bg-primary/10';
        } else if (status === 'taken') {
            return 'opacity-40 cursor-not-allowed';
        } else if (seatAvailability === 'VAC') {
            return 'cursor-pointer hover:scale-110 hover:opacity-100 opacity-60';
        } else {
            return 'opacity-30 cursor-not-allowed';
        }
    } catch (error) {
        console.error('Error getting seat class:', error);
        return '';
    }
};

// Get seat title for tooltip
const getSeatTitle = (segmentIdx, travelerIndex, rowNumber, seatNumber, seatAvailability) => {
    try {
        const status = getSeatStatus(segmentIdx, travelerIndex, rowNumber, seatNumber);

        if (status === 'selected') {
            return `Your selected seat - Row ${rowNumber}, Seat ${seatNumber}`;
        } else if (status === 'taken') {
            return `Seat taken by another traveler - Row ${rowNumber}, Seat ${seatNumber}`;
        } else if (seatAvailability === 'VAC') {
            return `Available - Row ${rowNumber}, Seat ${seatNumber}`;
        } else {
            return `Reserved - Row ${rowNumber}, Seat ${seatNumber}`;
        }
    } catch (error) {
        console.error('Error getting seat title:', error);
        return '';
    }
};

// Select a seat for a traveler
const selectSeat = (segmentIdx, travelerIndex, rowNumber, seat) => {
    try {
        if (!seat || travelerIndex === undefined) {
            toast.error('Invalid seat selection');
            return;
        }
        
        const seatNumber = seat.seatNumber;
        const rowNumStr = rowNumber?.toString();
        
        if (!seatNumber || !rowNumStr) {
            toast.error('Invalid seat data');
            return;
        }
        
        // Get the segment RPH from the flight data
        const segment = flight.value?.leg?.flights?.[0]?.segments?.[segmentIdx];
        const segmentRPH = segment?.RPH || segment?.rph || segment?.ref_id || segmentIdx.toString();
        
        // Check if traveler already has a seat selected
        const currentSeat = getSeatForTraveler(segmentIdx, travelerIndex);
        if (currentSeat) {
            if (!confirm(`Traveler ${travelerIndex + 1} already has seat ${currentSeat.seatNumber} selected. Replace it?`)) {
                return;
            }
        }
        
        // Check if seat is already taken by another traveler
        if (isSeatSelectedByAnyTraveler(segmentIdx, rowNumStr, seatNumber, travelerIndex)) {
            toast.error(`Seat ${seatNumber} in row ${rowNumStr} is already taken by another traveler`);
            return;
        }
        
        // Check if seat is available (VAC)
        if (seat.seatAvailability !== 'VAC') {
            toast.error(`Seat ${seatNumber} is not available`);
            return;
        }
        
        // Create seat data
        const seatData = {
            seatNumber: seatNumber,
            rowNumber: rowNumStr,
            price: seat.seatPrice || 0,
            currency: seat.currencyCode || 'PKR',
            availability: seat.seatAvailability || '',
            travelerIndex: travelerIndex,
            segmentRPH: segmentRPH,
            refId: seat.refId,
            selectedAt: new Date().toISOString()
        };
        
        // Ensure structure exists for this flight and segment
        if (!selectedExtras.value[0]) {
            selectedExtras.value[0] = { baggage: {}, seat: {}, meal: {} };
        }
        if (!selectedExtras.value[0].seat) {
            selectedExtras.value[0].seat = {};
        }
        if (!selectedExtras.value[0].seat[segmentIdx]) {
            selectedExtras.value[0].seat[segmentIdx] = {};
        }
        
        // Save the selection with traveler index as key
        selectedExtras.value[0].seat[segmentIdx][travelerIndex] = seatData;
        
        // Trigger reactivity
        selectedExtras.value = [...selectedExtras.value];
        
        toast.success(`Seat ${seatNumber} (Row ${rowNumStr}) selected for Traveler ${travelerIndex + 1}`);
    } catch (error) {
        console.error('Error selecting seat:', error);
        toast.error('Failed to select seat');
    }
};

// Remove seat selection
const removeSeat = (segmentIdx, travelerIndex) => {
    try {
        if (!selectedExtras.value[0]?.seat?.[segmentIdx]?.[travelerIndex]) {
            return;
        }
        
        const seatData = selectedExtras.value[0].seat[segmentIdx][travelerIndex];
        const seatNumber = seatData.seatNumber;
        const rowNumber = seatData.rowNumber;
        
        delete selectedExtras.value[0].seat[segmentIdx][travelerIndex];
        
        // Clean up empty objects
        if (Object.keys(selectedExtras.value[0].seat[segmentIdx]).length === 0) {
            delete selectedExtras.value[0].seat[segmentIdx];
        }
        
        // Trigger reactivity
        selectedExtras.value = [...selectedExtras.value];
        
        toast.success(`Seat ${seatNumber} (Row ${rowNumber}) removed for Traveler ${travelerIndex + 1}`);
    } catch (error) {
        console.error('Error removing seat:', error);
        toast.error('Failed to remove seat');
    }
};

// Get seat count for a traveler
const getSeatCount = (travelerIndex) => {
    if (travelerIndex === undefined || travelerIndex === null) return 0;
    try {
        let count = 0;
        if (selectedExtras.value[0]?.seat) {
            Object.values(selectedExtras.value[0].seat).forEach(segment => {
                if (segment?.[travelerIndex]) count++;
            });
        }
        return count;
    } catch (error) {
        console.error('Error getting seat count:', error);
        return 0;
    }
};

// Total seats selected across all travelers
const totalSeatsSelected = computed(() => {
    let total = 0;
    travellers.value?.forEach((_, index) => {
        if (travellers.value[index].type !== 'INF') {
            total += getSeatCount(index);
        }
    });
    return total;
});

// ==================== BAGGAGE FUNCTIONS (from first layout) ====================

// Process baggage data
const normalizedBaggages = computed(() => {
    const responses = ancillaries.value?.ancillaries?.baggage
        ?.Body
        ?.AA_OTA_AirBaggageDetailsRS
        ?.BaggageDetailsResponses
        ?.OnDBaggageDetailsResponse || [];

    // Convert to array if not already
    const baggageResponses = Array.isArray(responses) ? responses : responses ? [responses] : [];

    return baggageResponses.map((item, idx) => {
        const segments = Array.isArray(item.OnDFlightSegmentInfo)
            ? item.OnDFlightSegmentInfo
            : item.OnDFlightSegmentInfo
                ? [item.OnDFlightSegmentInfo]
                : [];

        const baggages = Array.isArray(item.Baggage)
            ? item.Baggage
            : item.Baggage
                ? [item.Baggage]
                : [];

        return {
            id: idx,
            segments,
            baggages
        };
    });
});

// Check if baggage data exists
const hasBaggageData = computed(() => normalizedBaggages.value?.length > 0);

// Get baggage options for a specific segment by RPH
const getBaggageOptionsForSegment = (segmentRPH) => {
    if (!segmentRPH || !normalizedBaggages.value.length) return [];
    
    // Find which group contains this segment
    const baggageGroup = normalizedBaggages.value.find(group => 
        group.segments?.some(seg => seg['@attributes']?.RPH === segmentRPH)
    );
    
    return baggageGroup?.baggages || [];
};

// Get selected baggage for a specific traveler
const getSelectedBaggageForTraveler = (flightIdx, segmentRPH, travelerIndex) => {
    if (!segmentRPH || travelerIndex === undefined) return null;
    try {
        return selectedExtras.value?.[flightIdx]?.baggage?.[segmentRPH]?.[travelerIndex] || null;
    } catch (error) {
        console.error('Error getting selected baggage:', error);
        return null;
    }
};

// Check if a baggage option is selected for a segment AND traveler
const isBaggageSelected = (flightIdx, segmentIdx, travelerIndex, baggageCode) => {
    try {
        return selectedExtras.value?.[flightIdx]?.baggage?.[segmentIdx]?.[travelerIndex]?.baggageCode === baggageCode;
    } catch (error) {
        console.error('Error checking baggage selection:', error);
        return false;
    }
};

// Handle baggage selection for specific traveler
const handleExtraBaggageChange = (flightIdx, segmentIdx, travelerIndex, baggageOption) => {
    try {
        if (!baggageOption || travelerIndex === undefined) return;

        // Initialize temp selection structure
        if (!tempBaggageSelection.value[flightIdx]) {
            tempBaggageSelection.value[flightIdx] = { baggage: {} };
        }
        if (!tempBaggageSelection.value[flightIdx].baggage) {
            tempBaggageSelection.value[flightIdx].baggage = {};
        }
        if (!tempBaggageSelection.value[flightIdx].baggage[segmentIdx]) {
            tempBaggageSelection.value[flightIdx].baggage[segmentIdx] = {};
        }

        // Store the selected baggage for specific traveler in temp
        tempBaggageSelection.value[flightIdx].baggage[segmentIdx][travelerIndex] = {
            ...baggageOption,
            title: baggageOption.baggageCode,
            amount: Number(baggageOption.baggageCharge).toFixed(2),
            currency: baggageOption.currencyCode,
            description: baggageOption.baggageDescription,
            baggageCode: baggageOption.baggageCode,
            travelerIndex: travelerIndex,
            segmentRPH: segmentIdx
        };

        // Trigger reactivity
        tempBaggageSelection.value = { ...tempBaggageSelection.value };
    } catch (error) {
        console.error('Error handling baggage change:', error);
    }
};

// Save baggage selection
const saveBaggageSelection = (flightIdx, segmentIdx, travelerIndex) => {
    try {
        const selection = tempBaggageSelection.value[flightIdx]?.baggage?.[segmentIdx]?.[travelerIndex];
        if (!selection) return;

        // Ensure selected extras structure exists
        if (!selectedExtras.value[flightIdx]) {
            selectedExtras.value[flightIdx] = { baggage: {}, seat: {}, meal: {} };
        }
        if (!selectedExtras.value[flightIdx].baggage) {
            selectedExtras.value[flightIdx].baggage = {};
        }
        if (!selectedExtras.value[flightIdx].baggage[segmentIdx]) {
            selectedExtras.value[flightIdx].baggage[segmentIdx] = {};
        }

        // Save the selection
        selectedExtras.value[flightIdx].baggage[segmentIdx][travelerIndex] = selection;

        // Clear temp selection
        delete tempBaggageSelection.value[flightIdx]?.baggage?.[segmentIdx]?.[travelerIndex];

        // Clean up empty temp objects
        if (Object.keys(tempBaggageSelection.value[flightIdx]?.baggage?.[segmentIdx] || {}).length === 0) {
            delete tempBaggageSelection.value[flightIdx]?.baggage?.[segmentIdx];
        }

        // Trigger reactivity
        selectedExtras.value = [...selectedExtras.value];
        tempBaggageSelection.value = { ...tempBaggageSelection.value };

        toast.success('Baggage selected successfully');
    } catch (error) {
        console.error('Error saving baggage selection:', error);
        toast.error('Failed to save baggage selection');
    }
};

// Remove baggage selection for specific traveler
const removeExtraBaggage = (flightIdx, segmentIdx, travelerIndex) => {
    try {
        if (selectedExtras.value[flightIdx]?.baggage?.[segmentIdx]?.[travelerIndex]) {
            delete selectedExtras.value[flightIdx].baggage[segmentIdx][travelerIndex];
            
            // Clean up empty objects
            if (Object.keys(selectedExtras.value[flightIdx].baggage[segmentIdx]).length === 0) {
                delete selectedExtras.value[flightIdx].baggage[segmentIdx];
            }
            if (Object.keys(selectedExtras.value[flightIdx].baggage).length === 0) {
                delete selectedExtras.value[flightIdx].baggage;
            }
            if (Object.keys(selectedExtras.value[flightIdx]).length === 0) {
                delete selectedExtras.value[flightIdx];
            }
            
            // Trigger reactivity
            selectedExtras.value = [...selectedExtras.value];
            
            toast.success('Baggage removed');
        }
    } catch (error) {
        console.error('Error removing baggage:', error);
        toast.error('Failed to remove baggage');
    }
};

// Get baggage count for a traveler
const getBaggageCount = (travelerIndex) => {
    if (travelerIndex === undefined || travelerIndex === null) return 0;
    try {
        let count = 0;
        if (selectedExtras.value[0]?.baggage) {
            Object.values(selectedExtras.value[0].baggage).forEach(segment => {
                if (segment?.[travelerIndex]) count++;
            });
        }
        return count;
    } catch (error) {
        console.error('Error getting baggage count:', error);
        return 0;
    }
};

// Total baggage selected across all travelers
const totalBaggageSelected = computed(() => {
    let total = 0;
    travellers.value?.forEach((_, index) => {
        if (travellers.value[index].type !== 'INF') {
            total += getBaggageCount(index);
        }
    });
    return total;
});

// ==================== MEAL FUNCTIONS (from first layout) ====================

// Process meal data
const normalizedMeals = computed(() => {
    const responses = ancillaries.value?.ancillaries?.meals
        ?.Body
        ?.AA_OTA_AirMealDetailsRS
        ?.MealDetailsResponses
        ?.MealDetailsResponse || [];

    // Ensure responses is an array
    const responsesArray = Array.isArray(responses) ? responses : [responses].filter(Boolean);

    return responsesArray.map((response, idx) => {
        const flightSegmentInfo = response.FlightSegmentInfo?.['@attributes'] || {};
        let meals = response.Meal || [];
        
        // Ensure meals is an array
        meals = Array.isArray(meals) ? meals : [meals].filter(Boolean);
        
        return {
            id: idx,
            flightInfo: {
                segmentCode: flightSegmentInfo.SegmentCode,
                flightNumber: flightSegmentInfo.FlightNumber,
                departure: flightSegmentInfo.DepartureDateTime,
                arrival: flightSegmentInfo.ArrivalDateTime,
                rph: flightSegmentInfo.RPH,
                from: flightSegmentInfo.SegmentCode?.split('/')[0],
                to: flightSegmentInfo.SegmentCode?.split('/')[1],
                departureTime: flightSegmentInfo.DepartureDateTime,
                arrivalTime: flightSegmentInfo.ArrivalDateTime
            },
            meals: meals.map(meal => ({
                mealCode: meal.mealCode,
                mealDescription: meal.mealDescription,
                mealCharge: parseFloat(meal.mealCharge) || 0,
                mealName: meal.mealName,
                defaultMeal: meal.defaultMeal,
                availableMeals: parseInt(meal.availableMeals) || 0,
                soldMeals: parseInt(meal.soldMeals) || 0,
                allocatedMeals: parseInt(meal.allocatedMeals) || 0,
                mealImageLink: meal.mealImageLink,
                mealCategoryCode: meal.mealCategoryCode,
                currencyCode: meal.currencyCode || 'PKR'
            }))
        };
    });
});

// Check if meal data exists
const hasMealsData = computed(() => normalizedMeals.value?.length > 0);

// Get meals for a specific segment
const getMealsForSegment = (segment) => {
    if (!segment || !normalizedMeals.value.length) return [];
    
    const segmentCode = segment.segment || segment.code || '';
    const flightNumber = segment.flightNumber;
    
    const mealData = normalizedMeals.value.find(mealItem => 
        mealItem.flightInfo.segmentCode === segmentCode ||
        mealItem.flightInfo.flightNumber === flightNumber
    );
    
    return mealData?.meals || [];
};

// Group meals by category
const groupByCategory = (meals) => {
    try {
        if (!meals) return {};

        const mealArray = Array.isArray(meals) ? meals : (meals ? [meals] : []);

        return mealArray.reduce((groups, meal) => {
            if (!meal) return groups;

            const category = meal.mealCategoryCode || 'Other';
            if (!groups[category]) groups[category] = [];
            groups[category].push(meal);
            return groups;
        }, {});
    } catch (error) {
        console.error('Error grouping meals by category:', error);
        return {};
    }
};

// Get selected meal for a traveler
const getSelectedMealForTraveler = (segmentIdx, travelerIndex) => {
    if (segmentIdx === undefined || travelerIndex === undefined) return null;
    try {
        return selectedExtras.value?.[0]?.meal?.[segmentIdx]?.[travelerIndex] || null;
    } catch (error) {
        console.error('Error getting selected meal:', error);
        return null;
    }
};

// Check if meal is selected for a traveler
const isMealSelectedForTraveler = (segmentIdx, travelerIndex, mealCode) => {
    try {
        return selectedExtras.value?.[0]?.meal?.[segmentIdx]?.[travelerIndex]?.mealCode === mealCode;
    } catch (error) {
        console.error('Error checking meal selection:', error);
        return false;
    }
};

// Select a meal for a traveler
const selectMeal = (segmentIdx, travelerIndex, meal) => {
    try {
        if (!meal || travelerIndex === undefined) {
            toast.error('Invalid meal selection');
            return;
        }
        
        // Get the segment RPH from the flight data
        const segment = flight.value?.leg?.flights?.[0]?.segments?.[segmentIdx];
        const segmentRPH = segment?.RPH || segment?.rph || segment?.ref_id || segmentIdx.toString();
        
        // Check if traveler already has a meal selected
        const currentMeal = getSelectedMealForTraveler(segmentIdx, travelerIndex);
        if (currentMeal) {
            if (!confirm(`Traveler ${travelerIndex + 1} already has a meal selected. Replace it?`)) {
                return;
            }
        }
        
        const mealData = {
            mealCode: meal.mealCode,
            mealName: meal.mealName,
            mealDescription: meal.mealDescription,
            mealCharge: meal.mealCharge,
            currencyCode: meal.currencyCode || 'PKR',
            travelerIndex: travelerIndex,
            segmentRPH: segmentRPH,
            selectedAt: new Date().toISOString()
        };
        
        // Ensure structure exists
        if (!selectedExtras.value[0]) {
            selectedExtras.value[0] = { baggage: {}, seat: {}, meal: {} };
        }
        if (!selectedExtras.value[0].meal) {
            selectedExtras.value[0].meal = {};
        }
        if (!selectedExtras.value[0].meal[segmentIdx]) {
            selectedExtras.value[0].meal[segmentIdx] = {};
        }
        
        // Save the selection
        selectedExtras.value[0].meal[segmentIdx][travelerIndex] = mealData;
        
        // Trigger reactivity
        selectedExtras.value = [...selectedExtras.value];
        
        toast.success(`${meal.mealName} selected for Traveler ${travelerIndex + 1}`);
    } catch (error) {
        console.error('Error selecting meal:', error);
        toast.error('Failed to select meal');
    }
};

// Remove meal selection
const removeMeal = (segmentIdx, travelerIndex) => {
    try {
        if (!selectedExtras.value[0]?.meal?.[segmentIdx]?.[travelerIndex]) {
            return;
        }
        
        delete selectedExtras.value[0].meal[segmentIdx][travelerIndex];
        
        // Clean up empty objects
        if (Object.keys(selectedExtras.value[0].meal[segmentIdx]).length === 0) {
            delete selectedExtras.value[0].meal[segmentIdx];
        }
        
        // Trigger reactivity
        selectedExtras.value = [...selectedExtras.value];
        
        toast.success(`Meal removed for Traveler ${travelerIndex + 1}`);
    } catch (error) {
        console.error('Error removing meal:', error);
        toast.error('Failed to remove meal');
    }
};

// Get meal count for a traveler
const getMealCount = (travelerIndex) => {
    if (travelerIndex === undefined || travelerIndex === null) return 0;
    try {
        let count = 0;
        if (selectedExtras.value[0]?.meal) {
            Object.values(selectedExtras.value[0].meal).forEach(segment => {
                if (segment?.[travelerIndex]) count++;
            });
        }
        return count;
    } catch (error) {
        console.error('Error getting meal count:', error);
        return 0;
    }
};

// Total meals selected across all travelers
const totalMealsSelected = computed(() => {
    let total = 0;
    travellers.value?.forEach((_, index) => {
        if (travellers.value[index].type !== 'INF') {
            total += getMealCount(index);
        }
    });
    return total;
});

// ==================== UTILITY FUNCTIONS ====================

// Get included baggage info
const getIncludedBaggage = (flight, segment) => {
    try {
        return flight?.includedBaggage || segment?.includedBaggage || '20kg Checked + 7kg Cabin';
    } catch (error) {
        console.error('Error getting included baggage:', error);
        return 'Baggage information unavailable';
    }
};

// Format date time
const formatDateTime = (dateTime) => {
    try {
        if (!dateTime) return '';
        return moment(dateTime).format('MMM D, HH:mm');
    } catch (error) {
        console.error('Error formatting date:', error);
        return '';
    }
};

// Format amount
const formatAmountValue = (amount) => {
    try {
        if (amount === undefined || amount === null) return '0.00';
        const num = typeof amount === 'string' ? parseFloat(amount) : amount;
        return isNaN(num) ? '0.00' : num.toFixed(2);
    } catch (error) {
        console.error('Error formatting amount:', error);
        return '0.00';
    }
};

// Handle image error
const handleImageError = (e) => {
    try {
        if (e?.target) {
            e.target.src = '/placeholder-meal.jpg';
        }
    } catch (error) {
        console.error('Error handling image error:', error);
    }
};

// ==================== FARE CALCULATION FUNCTIONS ====================

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
        if (amountType === "percent") {
            margin = (price * (parseFloat(marginAmount) || 0)) / 100;
        } else {
            margin = parseFloat(marginAmount) || 0;
        }
    }
    return margin;
};

const calculateCustomerMargin = (price, discountPercentage, marginPercentage) => {
    const total = parseFloat(price) || 0;
    const discount = (total * (parseFloat(discountPercentage) || 0)) / 100;
    const margin = (total * (parseFloat(marginPercentage) || 0)) / 100;

    if (discountPercentage && parseFloat(discountPercentage) > 0) {
        return -discount;
    }
    customerMarginAmt.value = margin;
    return margin;
};

function calculateTotalFare(fare) {
    const customerMargin = parseFloat(calculateCustomerMargin(
        fare.base_price,
        CustomerMargin?.value?.discount || 0,
        CustomerMargin?.value?.margin_amount || 0,
    ));
    const typeMargin = parseFloat(calculateTypeMargin(
        user.value,
        airportMargins.value,
    )) * passengerCount.value;

    const airlineMargin = calculateFareMargin(
        fare.base_price,
        fare.margin_amount,
        fare.margin_type,
        fare.amount_type
    );

    const billable = fare.base_price +
        parseFloat(fare.surchage || 0) +
        parseFloat(fare.taxes || 0) +
        parseFloat(fare.fees || 0) +
        parseFloat(fare.service_charges || 0) +
        parseFloat(fare.ancillaries_charges || 0) +
        (parseFloat(airlineMargin) * passengerCount.value) +
        (parseFloat(typeMargin) * passengerCount.value);

    const total = billable + (customerMargin * passengerCount.value) + parseFloat(CustomerMargin?.value?.other_charges || 0);
    return total;
}

function calculateGrandTotal() {
    let total = 0;

    flight?.value?.leg?.flights?.forEach((flight, index) => {
        flight?.fares?.forEach(fare => {
            let extrasAmount = 0;
            const extrasForFlight = extraCharges.value[index] || {};

            Object.values(extrasForFlight).forEach(extraGroup => {
                if (extraGroup) {
                    Object.values(extraGroup).forEach(segmentGroup => {
                        if (segmentGroup) {
                            Object.values(segmentGroup).forEach(item => {
                                const price = item.price || item.amount || 0;
                                extrasAmount += price;
                            });
                        }
                    });
                }
            });

            if (selectedFares.value.includes(fare.ref_id)) {
                total += calculateTotalFare(fare) + extrasAmount;
            }
        });
    });

    return total;
}

// ==================== COUNTDOWN FUNCTIONS ====================

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

// ==================== PASSPORT SCANNING FUNCTIONS ====================

const triggerFileUpload = (index) => {
    const input = document.getElementById(`passport-upload-${index}`);
    input?.click();
};

const handlePassportUpload = async (e, index) => {
    travellerIndex.value = index;
    const traveller = travellers.value[index];
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

        let text = result.data.text.toUpperCase();

        // Fix common OCR errors
        text = text.replace(/L{2,}/g, match => "<".repeat(match.length));
        text = text.replace(/C+L+C+/g, "<".repeat(3));
        text = text.replace(/C/g, "<");
        text = text.replace(/\b5([A-Z]{2})\b/g, "S$1");
        text = text.replace(/\b0([A-Z]{2})\b/g, "O$1");
        text = text
            .replace(/O(?=\d)/g, '0')
            .replace(/(?<=\d)O/g, '0')
            .replace(/(?<=\d)I(?=\d)/g, '1');
        text = text.replace(/[^A-Z0-9<\n]/g, "");

        const mrzMatch = extractMRZ(text);

        if (mrzMatch) {
            passportData.value = parseMRZ(mrzMatch);

            await fetchCountry(passportData.value.issuingCountry);
            await new Promise(resolve => setTimeout(resolve, 400));

            const traveller = travellers.value[travellerIndex.value];
            traveller.firstName = passportData.value.givenNames;
            traveller.lastName = passportData.value.surname;
            traveller.documentType = passportData.value.documentType === "P" ? "passport" : "Unknown";
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

// ==================== INITIALIZATION ====================

const initializeSelectedExtras = () => {
    try {
        if (!flight.value?.leg?.flights) return;
        
        const newSelectedExtras = [];
        
        flight.value.leg.flights.forEach((flightItem, flightIdx) => {
            newSelectedExtras[flightIdx] = {
                baggage: {},
                seat: {},
                meal: {}
            };
            
            // Initialize with segment RPH from the flight segments
            flightItem.segments?.forEach((segment, segmentIdx) => {
                // The segment RPH is what we need for FlightRefNumberRPHList
                const segmentRPH = segment.RPH || segment.rph || segment.ref_id || segmentIdx.toString();
                
                newSelectedExtras[flightIdx].baggage[segmentRPH] = {};
                newSelectedExtras[flightIdx].seat[segmentIdx] = {};
                newSelectedExtras[flightIdx].meal[segmentIdx] = {};
            });
        });
        
        selectedExtras.value = newSelectedExtras;
    } catch (error) {
        console.error('Error initializing selected extras:', error);
    }
};

// ==================== LIFECYCLE HOOKS ====================

onMounted(() => {
    selectedFares.value = route.query.fares ? JSON.parse(route.query.fares) : [];
    passengerCount.value = route.query.passenger_count ? parseInt(route.query.passenger_count) : 1;
    window.scrollTo({ top: 0, behavior: "smooth" });
    startCountdown(13 * 60 * 1000); // 13 minutes countdown

    initializeStripe();
    fetchMargins();
    fetchBookingStatus();
    fetchFlight();
    fetchCustomerMarginValues();
    fetchCustomerSettings();
    fetchAgentLedger();
    initializeAncillaryStructures();
});

watch(flight, () => {
    initializeTravellers();
    initializeSelectedExtras();
}, { immediate: true });
</script>
<template>
    <!-- Loading State -->
    
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
    <div v-if="route?.query?.flight_source == 1 && !showPreview " class="min-h-screen bg-gray-50 py-4">
        <div class="max-w-7xl mx-auto px-3 sm:px-4">
            <div class="bg-white border my-2 border-gray-200 py-6 px-4">


                <!-- Progress Steps -->
                <div class="relative flex items-center justify-between mx-auto px-4">

                    <!-- Step 1 - Completed -->
                    <div class="flex flex-col items-center relative z-10">
                        <div
                            class="w-8 h-8 rounded-full bg-primary border-primary text-white shadow-lg shadow-primary/30 flex items-center justify-center border-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <span class="mt-3 text-sm font-semibold text-gray-700">Information</span>
                    </div>

                    <!-- Line -->
                    <div class="flex-1 h-0.5 bg-gray-200 mx-6 rounded-full"></div>

                    <!-- Step 2 - Pending -->
                    <div class="flex flex-col items-center relative z-10">
                        <div
                            class="w-8 h-8 rounded-full bg-white border-2 border-gray-300 text-gray-400 flex items-center justify-center">
                            <span class="text-xs font-bold">2</span>
                        </div>
                        <span class="mt-3 text-sm font-semibold text-gray-500">Payment</span>
                    </div>

                    <!-- Line -->
                    <div class="flex-1 h-0.5 bg-gray-200 mx-6 rounded-full"></div>

                    <!-- Step 3 - Pending -->
                    <div class="flex flex-col items-center relative z-10">
                        <div
                            class="w-8 h-8 rounded-full bg-white border-2 border-gray-300 text-gray-400 flex items-center justify-center">
                            <span class="text-xs font-bold">3</span>
                        </div>
                        <span class="mt-3 text-sm font-semibold text-gray-500">E-Ticket</span>
                    </div>

                </div>
            </div>
        </div>
        <div class="max-w-7xl  mx-auto px-3 sm:px-4">
            <div v-if="isLoading" class="min-h-screen bg-gray-50 flex items-center justify-center">
        <div class="bg-white shadow-md p-6 max-w-md w-full mx-4">
            <div class="flex flex-col items-center space-y-3">
                <Spinner />
                <p class="text-gray-500 text-sm">Loading flight details...</p>
            </div>
        </div>
    </div>
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
                                <div v-for="(traveller, trvIndex) in travellers" :key="`traveller-${trvIndex}`"
                                    class="border border-gray-200">
                                    <!-- Accordion Header -->
                                    <button @click="traveller.isOpen = !traveller.isOpen"
                                        class="w-full bg-gray-50 p-3 text-left flex items-center justify-between hover:bg-gray-100 transition-colors">
                                        <div>
                                            <h3 class="text-sm font-medium text-gray-900">{{ traveller.type }} Traveller
                                                {{ trvIndex + 1 }}</h3>
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
                                                                @change="handlePassportUpload($event, trvIndex)"
                                                                class="hidden" :id="`passport-upload-${trvIndex}`" />
                                                            <button type="button" @click="triggerFileUpload(trvIndex)"
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
                                                    <div v-if="getErrorPath(`travellers.${trvIndex}.gender`)"
                                                        class="text-red-500 text-xs mt-1">
                                                        {{ getErrorPath(`travellers.${trvIndex}.gender`) }}
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
                                                    <div v-if="getErrorPath(`travellers.${trvIndex}.title`)"
                                                        class="text-red-500 text-xs mt-1">
                                                        {{ getErrorPath(`travellers.${trvIndex}.title`) }}
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
                                                                    :class="{ 'border-red-300': getErrorPath(`travellers.${trvIndex}.firstName`) }" />
                                                                <div v-if="getErrorPath(`travellers.${trvIndex}.firstName`)"
                                                                    class="text-red-500 text-xs mt-1">{{
                                                                        getErrorPath(`travellers.${trvIndex}.firstName`) }}
                                                                </div>
                                                            </div>
                                                            <div>
                                                                <Input v-model="traveller.lastName" type="text"
                                                                    placeholder="Last name"
                                                                    class="text-sm border-gray-300"
                                                                    :class="{ 'border-red-300': getErrorPath(`travellers.${trvIndex}.lastName`) }" />
                                                                <div v-if="getErrorPath(`travellers.${trvIndex}.lastName`)"
                                                                    class="text-red-500 text-xs mt-1">{{
                                                                        getErrorPath(`travellers.${trvIndex}.lastName`) }}
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
                                                                :class="{ 'border-red-300': getErrorPath(`travellers.${trvIndex}.dob`) }" />
                                                            <div v-if="getErrorPath(`travellers.${trvIndex}.dob`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${trvIndex}.dob`) }}</div>
                                                        </div>
                                                        <div>
                                                            <Label
                                                                class="text-sm font-medium text-gray-700 mb-2 block">Nationality
                                                                <span class="text-red-500">*</span></Label>
                                                            <Input v-model="traveller.nationality" type="text"
                                                                placeholder="Nationality"
                                                                class="text-sm border-gray-300"
                                                                :class="{ 'border-red-300': getErrorPath(`travellers.${trvIndex}.nationality`) }" />
                                                            <div v-if="getErrorPath(`travellers.${trvIndex}.nationality`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${trvIndex}.nationality`) }}
                                                            </div>
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
                                                            <div v-if="getErrorPath(`travellers.${trvIndex}.documentType`)"
                                                                class="text-red-500 text-xs mt-1">
                                                                {{ getErrorPath(`travellers.${trvIndex}.documentType`)
                                                                }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <Label
                                                                class="text-sm font-medium text-gray-700 mb-2 block">Document
                                                                Number <span class="text-red-500">*</span></Label>
                                                            <Input v-model="traveller.documentNo" type="text"
                                                                placeholder="Document number"
                                                                class="text-sm border-gray-300"
                                                                :class="{ 'border-red-300': getErrorPath(`travellers.${trvIndex}.documentNo`) }" />
                                                            <div v-if="getErrorPath(`travellers.${trvIndex}.documentNo`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${trvIndex}.documentNo`) }}
                                                            </div>
                                                        </div>
                                                        <!-- <div v-if="traveller.type == 'ADT'" class="space-y-2">
                                                                                                        <Label :for="`document-no-${trvIndex}`"
                                                                                                            class="block text-sm font-medium text-gray-700">
                                                                                                            CNIC Number <span class="text-red-500">*</span>
                                                                                                        </Label>
                                                                                                        <Input v-model="traveller.cnic" :id="`cnic-no-${trvIndex}`" type="text"
                                                                                                            class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20"
                                                                                                            placeholder="Enter CNIC number"
                                                                                                            
                                                                                                            />
                                                    
                                                                                                        <div v-if="getErrorPath(`travellers.${trvIndex}.cnic`)"
                                                                                                            class="error-message text-xs text-red-500">
                                                                                                            {{ getErrorPath(`travellers.${trvIndex}.cnic`) }}
                                                                                                        </div>
                                                                                                    </div> -->
                                                    </div>

                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div>
                                                            <Label
                                                                class="text-sm font-medium text-gray-700 mb-2 block">Expiry
                                                                Date <span class="text-red-500">*</span></Label>
                                                            <Input v-model="traveller.expiryDate" type="date"
                                                                class="text-sm border-gray-300"
                                                                :class="{ 'border-red-300': getErrorPath(`travellers.${trvIndex}.expiryDate`) }" />
                                                            <div v-if="getErrorPath(`travellers.${trvIndex}.expiryDate`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${trvIndex}.expiryDate`) }}
                                                            </div>
                                                        </div>
                                                        <div>
                                                            <Label
                                                                class="text-sm font-medium text-gray-700 mb-2 block">Issue
                                                                Country <span class="text-red-500">*</span></Label>
                                                            <Input v-model="traveller.issueCountry" type="text"
                                                                placeholder="Issue country"
                                                                class="text-sm border-gray-300"
                                                                :class="{ 'border-red-300': getErrorPath(`travellers.${trvIndex}.issueCountry`) }" />
                                                            <div v-if="getErrorPath(`travellers.${trvIndex}.issueCountry`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${trvIndex}.issueCountry`) }}
                                                            </div>
                                                        </div>
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
                    <div class=" lg:col-span-2">

                        <div class="sticky top-2">
                            <div v-if="countdown !== null && countdown !== '0'"
                                class="flex flex-col items-center justify-center bg-primary/10 border border-gray-200 p-4 mb-4 shadow-sm">
                                <div class="flex items-end gap-4">
                                    <div class="flex flex-col items-center">
                                        <span
                                            class="text-4xl sm:text-5xl md:text-6xl font-mono font-bold text-primary tracking-widest">
                                            {{ countdown.split(':')[0] }}
                                        </span>
                                    </div>
                                    <span
                                        class="text-4xl sm:text-5xl md:text-6xl font-mono font-bold text-primary tracking-widest">:</span>
                                    <div class="flex flex-col items-center">
                                        <span
                                            class="text-4xl sm:text-5xl md:text-6xl font-mono font-bold text-primary tracking-widest">
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
                                                            segment?.from_terminal?.[0] ?? "N/A" }}</div>
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
                                                            segment?.to_terminal?.[0] ?? "N/A" }}</div>
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
                                <div class="flex p-4 items-center justify-between ">
                                    <h3 class="text-lg sm:text-xl font-semibold  text-gray-900">Price Details
                                    </h3>
                                    <Dialog>
                                        <DialogTrigger as-child>
                                            <button
                                                class="px-3 py-1 text-xs font-medium text-white bg-primary rounded hover:bg-primary-dark">
                                                View Fare Rules
                                            </button>
                                        </DialogTrigger>
                                        <DialogContent class="sm:max-w-[600px] bg-white">
                                            <DialogHeader>
                                                <DialogTitle class="text-lg font-semibold text-gray-900">Fare Rules
                                                </DialogTitle>
                                                <DialogDescription class="text-sm text-gray-600">
                                                    Terms and conditions for your booking
                                                </DialogDescription>
                                            </DialogHeader>

                                            <div class="max-h-[60vh] overflow-y-auto">
                                                <!-- Rules List -->
                                                <div class="space-y-4">
                                                    <div v-for="(rule, index) in fareRules?.FareRuleListResponse?.FareRule?.[0]?.TextFareRule"
                                                        :key="index" :class="[
                                                            'border-l-4 pl-4 py-2',
                                                            isImportantRule(rule?.name) ? 'border-amber-400 bg-amber-50' : 'border-primary bg-gray-50'
                                                        ]">
                                                        <div class="flex items-start">
                                                            <div class="flex-1">
                                                                <h4
                                                                    class="text-sm font-medium text-gray-900 mb-1 capitalize">
                                                                    {{ formatRuleName(rule?.name) }}
                                                                </h4>
                                                                <p class="text-sm text-gray-700 whitespace-pre-line">
                                                                    {{ formatRuleValue(rule?.value) }}
                                                                </p>
                                                            </div>
                                                            <div v-if="isImportantRule(rule?.name)" class="ml-2">
                                                                <svg class="w-5 h-5 text-amber-500" fill="currentColor"
                                                                    viewBox="0 0 20 20">
                                                                    <path fill-rule="evenodd"
                                                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                                        clip-rule="evenodd" />
                                                                </svg>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Flight Reference -->
                                                <!-- <div v-if="fareRules?.FareRuleListResponse?.ReferenceList?.[0]?.Flight" class="mt-6">
                <div class="flex items-center mb-3">
                    <div class="h-px flex-1 bg-gray-200"></div>
                    <span class="px-3 text-xs font-medium text-gray-500">APPLIES TO FLIGHTS</span>
                    <div class="h-px flex-1 bg-gray-200"></div>
                </div>
                
                <div class="space-y-2">
                    <div v-for="flight in fareRules?.FareRuleListResponse?.ReferenceList?.[0]?.Flight" 
                         :key="flight.id"
                         class="flex items-center justify-between text-sm p-2 hover:bg-gray-50 rounded">
                        <div class="flex items-center space-x-3">
                            <span class="font-medium text-gray-900">
                                {{ flight.carrier }}{{ flight.number }}
                            </span>
                            <span class="text-gray-600">
                                {{ flight.Departure.location }} → {{ flight.Arrival.location }}
                            </span>
                        </div>
                       
                    </div>
                </div>
            </div> -->
                                            </div>

                                            <DialogFooter class="pt-4 border-t border-gray-200">
                                                <DialogClose as-child>
                                                    <button
                                                        class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors duration-200">
                                                        Close
                                                    </button>
                                                </DialogClose>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                </div>
                                <div class="">
                                    <div v-for="(flight, flightIndex) in flight?.leg?.flights" :key="flightIndex">
                                        <div
                                            class="text-xs sm:text-sm font-semibold text-gray-900 my-1 sm:my-2 flex items-center gap-1 sm:gap-2">
                                            <div class="w-1 h-1 sm:w-1.5 sm:h-1.5 bg-primary rounded-full"></div>
                                            {{
                                                flight.from
                                                    .iata
                                            }}
                                            →
                                            {{
                                                flight.to.iata
                                            }}

                                        </div>
                                        <div v-for="(fare, fareIndex) in flight?.fares" :key="fareIndex">
                                            <div v-if="selectedFares?.includes(fare.ref_id)" class="">
                                                <Accordion class="" type="multiple" collapsible>
                                                    <template v-for="(passengerFare, index) in fare.passenger_fares"
                                                        :key="index">
                                                        <AccordionItem
                                                            :value="`fare-${flightIndex}-${fareIndex}-${index}`"
                                                            class="  overflow-hidden">
                                                            <!-- HEADER -->
                                                            <AccordionTrigger
                                                                class="px-3 sm:px-4 py-2 grid grid-cols-[1fr_auto_auto] items-center gap-3  hover:no-underline">
                                                                <!-- LEFT -->
                                                                <div class="flex items-center gap-2">
                                                                    <span
                                                                        class="text-xs sm:text-sm font-bold text-gray-600">
                                                                        {{ passengerFare.traveler_type }}
                                                                        X {{ passengerFare.total_passenger }}
                                                                    </span>
                                                                </div>

                                                                <!-- AMOUNT (perfectly right-aligned before icon) -->
                                                                <span
                                                                    class="text-sm sm:text-base font-bold text-primary text-right whitespace-nowrap">
                                                                    {{
                                                                        formatAmount(parseFloat(passengerFare.base_price ||
                                                                            0) +
                                                                            parseFloat(passengerFare.surchage || 0) +
                                                                            parseFloat(passengerFare.taxes || 0) +
                                                                            parseFloat(passengerFare.fees || 0) +
                                                                            parseFloat(passengerFare.service_charges || 0) +
                                                                            parseFloat(passengerFare.ancillaries_charges || 0) +
                                                                            ((calculateFareMargin(
                                                                                parseFloat(passengerFare.base_price) || 0,
                                                                                fare.margin_amount,
                                                                                fare.margin_type,
                                                                                fare.amount_type,
                                                                            ) +
                                                                                calculateCustomerMargin(
                                                                                    parseFloat(passengerFare.base_price) || 0,
                                                                                )) * passengerFare?.total_passenger))
                                                                    }}
                                                                </span>
                                                            </AccordionTrigger>


                                                            <!-- CONTENT -->
                                                            <AccordionContent class="px-3 sm:px-4 pb-3 space-y-2">
                                                                <div class="flex justify-between items-center">
                                                                    <span class="text-xs sm:text-sm text-gray-600">Base
                                                                        Fare</span>
                                                                    <span class="text-xs sm:text-sm font-medium">
                                                                        {{
                                                                            formatAmount(
                                                                                ((calculateFareMargin(
                                                                                    parseFloat(
                                                                                        passengerFare?.base_price,
                                                                                    ) || 0,
                                                                                    fare?.margin_amount,
                                                                                    fare?.margin_type,
                                                                                    fare?.amount_type,
                                                                                ) +
                                                                                    parseFloat(
                                                                                        CustomerMargin?.other_charges || 0,
                                                                                    ) +
                                                                                    parseFloat(
                                                                                        calculateCustomerMargin(
                                                                                            passengerFare?.base_price,
                                                                                            CustomerMargin?.value?.discount || 0,
                                                                                            CustomerMargin?.value?.margin_amount || 0,
                                                                                        ),
                                                                                    )) *
                                                                                    passengerCount) +
                                                                                parseFloat(passengerFare?.base_price || 0)
                                                                            )
                                                                        }}
                                                                    </span>
                                                                </div>

                                                                <div class="flex justify-between items-center">
                                                                    <span
                                                                        class="text-xs sm:text-sm text-gray-600">Taxes</span>
                                                                    <span class="text-xs sm:text-sm font-medium">
                                                                        {{ formatAmount(passengerFare?.taxes) }}
                                                                    </span>
                                                                </div>

                                                                <div class="flex justify-between items-center">
                                                                    <span
                                                                        class="text-xs sm:text-sm text-gray-600">Fees</span>
                                                                    <span class="text-xs sm:text-sm font-medium">
                                                                        {{ formatAmount(passengerFare?.fees) }}
                                                                    </span>
                                                                </div>

                                                                <div class="flex justify-between items-center">
                                                                    <span class="text-xs sm:text-sm text-gray-600">
                                                                        Service Charges
                                                                    </span>
                                                                    <span class="text-xs sm:text-sm font-medium">
                                                                        {{ formatAmount(passengerFare.service_charges)
                                                                        }}
                                                                    </span>
                                                                </div>

                                                                <div v-if="ancillaries"
                                                                    class="flex justify-between items-center">
                                                                    <span
                                                                        class="text-xs sm:text-sm text-gray-600">Add-ons</span>
                                                                    <span class="text-xs sm:text-sm font-medium">
                                                                        {{
                                                                            formatAmount(
                                                                                ["baggage", "seat", "meal"].reduce((sum, group)=> {
                                                                                    const extras =
                                                                                        extraCharges[flightIndex]?.[group] || {};

                                                                                    const groupTotal = Object.values(extras).reduce(
                                                                                        (gSum, segmentGroup) => {
                                                                                            if (!segmentGroup) return gSum;
                                                                                            return (
                                                                                                gSum +
                                                                                                Object.values(segmentGroup).reduce(
                                                                                                    (sSum, item) => {
                                                                                                        const price =
                                                                                                            item.price ||
                                                                                                            item.amount ||
                                                                                                            0;
                                                                                                        return sSum + price;
                                                                                                    },
                                                                                                    0
                                                                                                )
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
                                                                    <span
                                                                        class="text-xs sm:text-sm font-medium text-gray-700">
                                                                        Amount
                                                                    </span>
                                                                    <span
                                                                        class="text-sm sm:text-base font-bold text-primary">
                                                                        {{
                                                                            formatAmount(
                                                                                parseFloat(passengerFare.base_price || 0) +
                                                                                parseFloat(passengerFare.surchage || 0) +
                                                                                parseFloat(passengerFare.taxes || 0) +
                                                                                parseFloat(passengerFare.fees || 0) +
                                                                                parseFloat(passengerFare.service_charges || 0) +
                                                                                parseFloat(passengerFare.ancillaries_charges ||
                                                                                    0) +
                                                                                ((calculateFareMargin(
                                                                                    parseFloat(passengerFare.base_price) || 0,
                                                                                    fare.margin_amount,
                                                                                    fare.margin_type,
                                                                                    fare.amount_type,
                                                                                ) +
                                                                                    calculateCustomerMargin(
                                                                                        parseFloat(passengerFare.base_price) || 0,
                                                                                    )) * passengerFare?.total_passenger))
                                                                        }}
                                                                    </span>
                                                                </div>
                                                            </AccordionContent>
                                                        </AccordionItem>
                                                    </template>
                                                </Accordion>
                                                <div
                                                    class="flex justify-between items-center bg-gray-50 p-2 sm:p-3 rounded">
                                                    <span
                                                        class="text-xs sm:text-sm font-medium text-gray-700">Amount</span>
                                                    <span class="text-sm sm:text-base font-bold text-primary">
                                                        {{formatAmount(
                                                            calculateTotalFare(fare) +
                                                            ["baggage", "seat", "meal"].reduce((sum, group) => {
                                                                const extras = extraCharges[flightIndex]?.[group] || {};

                                                                // Loop deeper: segment → passenger/item
                                                                const groupTotal = Object.values(extras).reduce((gSum,
                                                                    segmentGroup) => {
                                                                    if (!segmentGroup) return gSum;
                                                                    return gSum + Object.values(segmentGroup).reduce((sSum, item) => {
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
                                <div class="flex justify-between mt-3 sm:mt-4 items-center bg-gray-50 p-3  rounded-lg">
                                    <span class="text-base sm:text-sm font-semibold text-gray-900">Total Amount</span>
                                    <span class="text-sm sm:text-lg font-bold text-primary">
                                        {{ formatAmount(amount = calculateGrandTotal()) }}
                                    </span>
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
                                                <Input type="checkbox" v-model="termsAccepted" id="terms"
                                                    class="mt-1" />
                                                <Label for="terms" class="text-xs text-gray-500 leading-relaxed">
                                                    I understand and agree with the Privacy Policy, the User <a href="#"
                                                        class="text-primary hover:underline">Agreement and Terms</a> of
                                                    Service of Jetze Policies
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
                                                        segment?.from_terminal?.[0] ?? "N/A" }}</div>
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
                                                        segment?.to_terminal?.[0] ?? "N/A" }}</div>
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
                                        <div v-for="(item, i) in selectedExtras[flightIdx]?.baggage"
                                            :key="'baggage-' + i"
                                            class="flex justify-between items-center text-sm bg-gray-50 p-3 border border-gray-200">
                                            <span class="text-gray-700 font-medium">Extra Baggage: {{ item.title
                                            }}</span>
                                            <span class="font-semibold text-gray-900">{{ formatAmount((item.price ||
                                                item.amount)) }}</span>
                                        </div>
                                    </div>

                                    <!-- Seat -->
                                    <div v-if="flightExtras.seat">
                                        <div v-for="(item, i) in selectedExtras[flightIdx]?.seat" :key="'seat-' + i"
                                            class="flex justify-between items-center text-sm bg-gray-50 p-3 border border-gray-200">
                                            <span class="text-gray-700 font-medium">Seat Selection: {{ item.title
                                            }}</span>
                                            <span class="font-semibold text-gray-900">{{ formatAmount((item.price ||
                                                item.amount) * (item.qty || 1)) }}</span>
                                        </div>
                                    </div>

                                    <!-- Meal -->
                                    <div v-if="flightExtras.meal">
                                        <div v-for="(item, i) in selectedExtras[flightIdx]?.meal" :key="'meal-' + i"
                                            class="flex justify-between items-center text-sm bg-gray-50 p-3 border border-gray-200">
                                            <span class="text-gray-700 font-medium">
                                                Meal: {{ item.title }} (Qty: {{ item.qty || 1 }})
                                            </span>
                                            <span class="font-semibold text-gray-900">{{ formatAmount((item.price ||
                                                item.amount)) }}</span>
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
                                                    <span class="text-xs font-medium"> {{
                                                        formatAmount(
                                                            calculateBaseFare(fare)) }}</span>
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
                            <div class="space-y-2">
                                <Button @click="handlePaymentMethod('pay')" :disabled="!termsAccepted"
                                    class="w-full bg-primary hover:bg-primary/50 text-white py-2 sm:py-2.5 rounded-lg font-medium text-xs sm:text-sm">
                                    Pay & Confirm
                                </Button>

                                <!-- Payment Methods -->
                                <div v-if="isPaymentMethodsVisible"
                                    class="grid grid-cols-2 gap-2 p-2 sm:p-3 bg-gray-50 rounded-lg">
                                    <div v-if="customerSettings?.is_card_allowed" @click="handlePaymentMethod('card')"
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
                                </div>

                                <Button @click="handlePaymentMethod('hold')" :disabled="isPaymentMethodsVisible"
                                    class="w-full bg-primary hover:bg-primary/50 text-white py-2 sm:py-2.5 rounded-lg font-medium text-xs sm:text-sm disabled:opacity-50">
                                    {{ isSubmitting ? "Saving..." : "Book & Hold" }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto ">
        <div class="bg-white border mb-10 border-gray-200 py-6 px-4">
            <div class="relative flex items-center justify-between mx-auto px-4">
                <!-- Step 1 - Completed -->
                <div class="flex flex-col items-center relative z-10">
                    <div
                        class="w-8 h-8 rounded-full bg-primary border-primary text-white shadow-lg shadow-primary/30 flex items-center justify-center border-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <span class="mt-3 text-sm font-semibold text-gray-700">Information</span>
                </div>

                <!-- Line (Filled) -->
                <div class="flex-1 h-0.5 bg-gray-200 mx-6 rounded-full relative">
                    <div class="absolute left-0 top-0 h-full w-full bg-primary rounded-full"></div>
                </div>

                <!-- Step 2 - Current -->
                <div class="flex flex-col items-center relative z-10">
                    <div
                        class="w-8 h-8 rounded-full bg-primary border-primary text-white shadow-lg shadow-primary/30 flex items-center justify-center border-2">
                        <span class="text-xs font-bold">2</span>
                    </div>
                    <span class="mt-3 text-sm font-semibold text-gray-700">Addons</span>
                </div>

                <!-- Line (Empty) -->
                <div class="flex-1 h-0.5 bg-gray-200 mx-6 rounded-full"></div>

                <!-- Step 3 - Pending -->
                <div class="flex flex-col items-center relative z-10">
                    <div
                        class="w-8 h-8 rounded-full bg-white border-2 border-gray-300 text-gray-400 flex items-center justify-center">
                        <span class="text-xs font-bold">3</span>
                    </div>
                    <span class="mt-3 text-sm font-semibold text-gray-500">Payment</span>
                </div>

                <!-- Line (Empty) -->
                <div class="flex-1 h-0.5 bg-gray-200 mx-6 rounded-full"></div>

                <!-- Step 4 - Pending -->
                <div class="flex flex-col items-center relative z-10">
                    <div
                        class="w-8 h-8 rounded-full bg-white border-2 border-gray-300 text-gray-400 flex items-center justify-center">
                        <span class="text-xs font-bold">4</span>
                    </div>
                    <span class="mt-3 text-sm font-semibold text-gray-500">E-Ticket</span>
                </div>
            </div>
        </div>
       
       <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
    <!-- Main Content - Left Side -->
    <div class="lg:col-span-3 space-y-4">
        <!-- Header -->
        <div class="mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Manage Extras</h1>
                    <p class="text-gray-500 mt-1">Select seats, baggage, and other services for your flight</p>
                </div>
            </div>
        </div>

        <!-- Single Accordion for all travelers -->
        <div class="bg-white rounded border border-gray-200 overflow-hidden">
            <div class="p-6">
                <Accordion type="multiple" v-model="openSections[0]" class="space-y-4">

                    <!-- SEATS SECTION -->
                    <AccordionItem v-if="hasSeatData" value="seats"
                        class="bg-white rounded border border-gray-200 overflow-hidden">
                        <AccordionTrigger class="px-4 py-3 hover:no-underline">
                            <div class="flex items-center justify-between w-full">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                                        <Check class="w-4 h-4 text-primary" />
                                    </div>
                                    <div class="text-left">
                                        <h3 class="font-semibold text-gray-900">Seat Selection</h3>
                                        <p class="text-xs text-gray-500">Choose seats for all travelers</p>
                                    </div>
                                </div>
                                <Badge v-if="totalSeatsSelected > 0" variant="secondary" class="ml-2">
                                    {{ totalSeatsSelected }} seats selected
                                </Badge>
                            </div>
                        </AccordionTrigger>
                        <AccordionContent class="px-4 pb-4">
                            <div v-if="isLoading" class="flex items-center justify-center py-12">
                                <Spinner />
                            </div>

                            <div v-else-if="processedSeatMapData.length > 0" class="space-y-6">
                                <div v-for="(segment, segmentIdx) in processedSeatMapData" :key="segmentIdx"
                                    class="border border-gray-200 rounded overflow-hidden">

                                    <!-- Segment Header -->
                                    <div class="bg-gradient-to-r from-gray-50 to-white border-b border-gray-200 p-4">
                                        <h4 class="text-base font-semibold text-gray-900 mb-3">
                                            Flight {{ segment.FlightSegmentInfo?.['@attributes']?.FlightNumber || 'N/A' }}
                                        </h4>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div>
                                                <span class="text-xs text-gray-500">Departure</span>
                                                <p class="font-medium">{{
                                                    segment.FlightSegmentInfo?.DepartureAirport?.['@attributes']?.LocationCode
                                                    || 'N/A' }}</p>
                                                <p class="text-xs text-gray-600">{{
                                                    formatDateTime(segment.FlightSegmentInfo?.['@attributes']?.DepartureDateTime)
                                                    }}</p>
                                            </div>
                                            <div>
                                                <span class="text-xs text-gray-500">Arrival</span>
                                                <p class="font-medium">{{
                                                    segment.FlightSegmentInfo?.ArrivalAirport?.['@attributes']?.LocationCode
                                                    || 'N/A' }}</p>
                                                <p class="text-xs text-gray-600">{{
                                                    formatDateTime(segment.FlightSegmentInfo?.['@attributes']?.ArrivalDateTime)
                                                    }}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Seat Map for all travelers -->
                                    <div class="p-4">
                                        <!-- Traveler selection tabs for seats -->
                                        <div class="mb-4 border-b border-gray-200">
                                            <div class="flex flex-wrap gap-2">
                                                <button v-for="(traveller, travelerIndex) in travellers.filter(t => t.type !== 'INF')"
                                                    :key="travelerIndex" @click="activeSeatTravelerTab = travelerIndex"
                                                    class="px-3 py-2 text-sm font-medium rounded-t-lg transition-colors"
                                                    :class="activeSeatTravelerTab === travelerIndex
                                                        ? 'bg-primary/10 text-primary border-b-2 border-primary'
                                                        : 'text-gray-500 hover:text-gray-700'">
                                                    <div class="flex items-center gap-2">
                                                        <span>{{ traveller.type }} {{ travelerIndex + 1 }}</span>
                                                        <Badge v-if="getSeatForTraveler(segmentIdx, travelerIndex)"
                                                            variant="secondary" class="bg-green-100 text-green-700 text-xs px-1.5">
                                                            <img src="/public/armchair.png" class="w-3 h-3 opacity-70" alt="">
                                                        </Badge>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Selected seat preview for current traveler -->
                                        <div v-for="(traveller, travelerIndex) in travellers.filter(t => t.type !== 'INF')"
                                            :key="travelerIndex" v-show="activeSeatTravelerTab === travelerIndex">
                                            <div v-if="getSeatForTraveler(segmentIdx, travelerIndex)"
                                                class="mb-4 p-3 bg-primary/5 border border-primary/20 rounded-lg">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-3">
                                                        <img src="/public/armchair.png" class="w-5 h-5 text-primary" alt="">
                                                        <div>
                                                            <p class="text-sm font-medium">
                                                                Seat {{ getSeatForTraveler(segmentIdx, travelerIndex).seatNumber }}
                                                            </p>
                                                            <p class="text-xs text-gray-600">
                                                                Row {{ getSeatForTraveler(segmentIdx, travelerIndex).rowNumber }} •
                                                                {{ traveller.title }} {{ traveller.firstName }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-3">
                                                        <span class="text-sm font-semibold text-primary">
                                                            {{ formatAmount(getSeatForTraveler(segmentIdx, travelerIndex).price) }}
                                                        </span>
                                                        <button @click="removeSeat(segmentIdx, travelerIndex)"
                                                            class="text-gray-400 hover:text-red-500">
                                                            <X class="w-4 h-4" />
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Seat Legend -->
                                        <div class="flex flex-wrap gap-4 mb-4 text-xs">
                                            <div class="flex items-center gap-1">
                                                <div class="w-4 h-4 bg-green-100 border border-green-500 rounded"></div>
                                                <span>Available (VAC)</span>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <div class="w-4 h-4 bg-gray-200 border border-gray-400 rounded"></div>
                                                <span>Reserved (RES)</span>
                                            </div>
                                            <div class="flex items-center gap-1">
                                                <div class="w-4 h-4 bg-primary/20 border-2 border-primary rounded flex items-center justify-center">
                                                    <img src="/public/armchair.png" class="w-3 h-3 opacity-70" alt="">
                                                </div>
                                                <span>Selected</span>
                                            </div>
                                        </div>

                                        <!-- Seat Map Grid -->
                                        <div class="seat-map-container overflow-x-auto pb-2">
                                            <div class="inline-block min-w-full">
                                                <!-- Column Headers -->
                                                <div class="flex pl-8 mb-2">
                                                    <div class="w-8"></div>
                                                    <div v-for="col in ['A', 'B', 'C', 'D', 'E', 'F']" :key="col"
                                                        class="w-8 text-center text-xs font-medium text-gray-500">
                                                        {{ col }}
                                                    </div>
                                                </div>

                                                <!-- Seat Rows -->
                                                <div v-for="row in getSeatRows(segment)" :key="row?.['@attributes']?.RowNumber"
                                                    class="flex items-center mb-1 hover:bg-gray-50/50 rounded">
                                                    <div class="w-8 text-sm font-medium text-gray-600">
                                                        {{ row?.['@attributes']?.RowNumber }}
                                                    </div>

                                                    <div v-for="col in ['A', 'B', 'C', 'D', 'E', 'F']" :key="col"
                                                        class="w-8 flex justify-center">
                                                        <div v-for="seat in getSeatInRow(row, col)" :key="seat?.['@attributes']?.SeatNumber"
                                                            class="relative">
                                                            <!-- Available Seat -->
                                                            <button v-if="seat?.['@attributes']?.SeatAvailability === 'VAC'"
                                                                @click="selectSeat(segmentIdx, activeSeatTravelerTab, row?.['@attributes']?.RowNumber, seat)"
                                                                class="relative transition-all duration-200 rounded-full p-1 focus:outline-none focus:ring-2 focus:ring-primary/50"
                                                                :class="getSeatClass(segmentIdx, activeSeatTravelerTab, row?.['@attributes']?.RowNumber, seat?.['@attributes']?.SeatNumber, seat?.['@attributes']?.SeatAvailability)"
                                                                :title="getSeatTitle(segmentIdx, activeSeatTravelerTab, row?.['@attributes']?.RowNumber, seat?.['@attributes']?.SeatNumber, seat?.['@attributes']?.SeatAvailability)">
                                                                <img src="/public/armchair.png" class="w-7 h-7" :class="{
                                                                    'opacity-100': getSeatStatus(segmentIdx, activeSeatTravelerTab, row?.['@attributes']?.RowNumber, seat?.['@attributes']?.SeatNumber) === 'selected',
                                                                    'opacity-60': getSeatStatus(segmentIdx, activeSeatTravelerTab, row?.['@attributes']?.RowNumber, seat?.['@attributes']?.SeatNumber) === 'available',
                                                                    'opacity-30 grayscale': getSeatStatus(segmentIdx, activeSeatTravelerTab, row?.['@attributes']?.RowNumber, seat?.['@attributes']?.SeatNumber) === 'taken'
                                                                }" :alt="'Seat ' + seat?.['@attributes']?.SeatNumber">
                                                                <span class="absolute -bottom-4 left-1/2 transform -translate-x-1/2 text-[10px] font-medium"
                                                                    :class="{
                                                                        'text-primary': getSeatStatus(segmentIdx, activeSeatTravelerTab, row?.['@attributes']?.RowNumber, seat?.['@attributes']?.SeatNumber) === 'selected',
                                                                        'text-gray-600': getSeatStatus(segmentIdx, activeSeatTravelerTab, row?.['@attributes']?.RowNumber, seat?.['@attributes']?.SeatNumber) !== 'selected'
                                                                    }">
                                                                    {{ seat?.['@attributes']?.SeatNumber }}
                                                                </span>
                                                            </button>

                                                            <!-- Reserved Seat -->
                                                            <div v-else-if="seat?.['@attributes']?.SeatAvailability === 'RES'"
                                                                class="relative p-1 opacity-30 cursor-not-allowed group"
                                                                :title="'Reserved - Row ' + (row?.['@attributes']?.RowNumber) + ', Seat ' + seat?.['@attributes']?.SeatNumber">
                                                                <img src="/public/armchair.png" class="w-7 h-7 grayscale"
                                                                    alt="Reserved seat">
                                                                <span class="absolute -bottom-4 left-1/2 transform -translate-x-1/2 text-[10px] font-medium text-gray-400">
                                                                    {{ seat?.['@attributes']?.SeatNumber }}
                                                                </span>
                                                            </div>

                                                            <!-- Empty space (no seat) -->
                                                            <div v-else class="w-8 h-8"></div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Seat Price Info -->
                                        <div class="mt-4 text-xs text-gray-500">
                                            <p>Seat prices vary by row. Click on an available seat to select for the active traveler.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="bg-white rounded border border-gray-200 p-12 text-center">
                                <p class="text-gray-500">No seat map data available.</p>
                            </div>
                        </AccordionContent>
                    </AccordionItem>

                    <!-- BAGGAGE SECTION -->
                    <AccordionItem v-if="hasBaggageData" value="baggage"
                        class="bg-white rounded border border-gray-200 overflow-hidden">
                        <AccordionTrigger class="px-4 py-3 hover:no-underline">
                            <div class="flex items-center justify-between w-full">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                                        <Package class="w-4 h-4 text-primary" />
                                    </div>
                                    <div class="text-left">
                                        <h3 class="font-semibold text-gray-900">Extra Baggage</h3>
                                        <p class="text-xs text-gray-500">Add additional baggage allowance for all travelers</p>
                                    </div>
                                </div>
                                <Badge v-if="totalBaggageSelected > 0" variant="secondary" class="ml-2">
                                    {{ totalBaggageSelected }} items selected
                                </Badge>
                            </div>
                        </AccordionTrigger>
                        <AccordionContent class="px-4 pb-4">
                            <div v-if="isLoading" class="flex items-center justify-center py-12">
                                <Spinner />
                            </div>

                            <div v-else-if="flight?.leg?.flights" class="space-y-4">
                                <div v-for="(flightItem, flightIdx) in flight.leg.flights" :key="flightIdx"
                                    class="border border-gray-200 rounded-lg overflow-hidden">

                                    <div v-for="(segment, segmentIdx) in flightItem.segments" :key="segmentIdx"
                                        class="border-b last:border-b-0">
                                        <!-- Segment Header -->
                                        <div class="bg-gray-50 p-3">
                                            <div class="flex justify-between items-center">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900">
                                                        {{ segment.from?.iata || segment.from?.code }} → {{ segment.to?.iata || segment.to?.code }}
                                                    </p>
                                                    <p class="text-xs text-gray-500">
                                                        {{ formatDateTime(segment.departure_at) }}
                                                    </p>
                                                </div>
                                                <!-- Included baggage info -->
                                                <div v-if="getIncludedBaggage(flightItem, segment)"
                                                    class="text-xs text-green-600 bg-green-50 px-2 py-1 rounded">
                                                    ✓ {{ getIncludedBaggage(flightItem, segment) }}
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Traveler baggage selection tabs -->
                                        <div class="px-4 pt-3 border-b border-gray-200">
                                            <div class="flex flex-wrap gap-2">
                                                <button v-for="(traveller, travelerIndex) in travellers.filter(t => t.type !== 'INF')"
                                                    :key="travelerIndex" @click="activeBaggageTravelerTab = travelerIndex"
                                                    class="px-3 py-1.5 text-xs font-medium rounded-t-lg transition-colors"
                                                    :class="activeBaggageTravelerTab === travelerIndex
                                                        ? 'bg-primary/10 text-primary border-b-2 border-primary'
                                                        : 'text-gray-500 hover:text-gray-700'">
                                                    <div class="flex items-center gap-1">
                                                        <span>{{ traveller.type }} {{ travelerIndex + 1 }}</span>
                                                        <Badge v-if="getSelectedBaggageForTraveler(flightIdx, segment.RPH, travelerIndex)"
                                                            variant="secondary" class="bg-blue-100 text-blue-700 text-xs px-1">
                                                            ✓
                                                        </Badge>
                                                    </div>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Baggage Options -->
                                        <div class="p-4">
                                            <!-- Selected baggage preview for current traveler -->
                                            <div v-for="(traveller, travelerIndex) in travellers.filter(t => t.type !== 'INF')"
                                                :key="travelerIndex" v-show="activeBaggageTravelerTab === travelerIndex">
                                                <div v-if="getSelectedBaggageForTraveler(flightIdx, segment.RPH, travelerIndex)"
                                                    class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded-lg">
                                                    <div class="flex items-center justify-between">
                                                        <div class="flex items-center gap-2">
                                                            <CheckCircle class="w-4 h-4 text-blue-600" />
                                                            <div>
                                                                <p class="text-sm font-medium text-gray-900">
                                                                    {{ getSelectedBaggageForTraveler(flightIdx, segment.RPH, travelerIndex).baggageCode }}
                                                                </p>
                                                                <p class="text-xs text-gray-600">
                                                                    {{ getSelectedBaggageForTraveler(flightIdx, segment.RPH, travelerIndex).baggageDescription }}
                                                                </p>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center gap-3">
                                                            <span class="text-sm font-semibold text-primary">
                                                                {{ formatAmount(getSelectedBaggageForTraveler(flightIdx, segment.RPH, travelerIndex).baggageCharge) }}
                                                            </span>
                                                            <button @click="removeExtraBaggage(flightIdx, segment.RPH, travelerIndex)"
                                                                class="text-gray-400 hover:text-red-500">
                                                                <X class="w-4 h-4" />
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Add baggage dialog -->
                                            <Dialog v-if="getBaggageOptionsForSegment(segment.RPH).length > 0">
                                                <DialogTrigger as-child>
                                                    <button class="flex items-center text-primary text-sm hover:underline">
                                                        <PlusCircle class="w-4 h-4 mr-1" />
                                                        Add extra baggage for {{ travellers[activeBaggageTravelerTab]?.type }} {{ activeBaggageTravelerTab + 1 }}
                                                    </button>
                                                </DialogTrigger>

                                                <DialogContent class="max-w-3xl max-h-[90vh] overflow-y-auto">
                                                    <DialogHeader>
                                                        <DialogTitle>Select Extra Baggage</DialogTitle>
                                                        <DialogDescription>
                                                            Choose baggage for {{ segment.from?.name || segment.from?.code }} to {{ segment.to?.name || segment.to?.code }} -
                                                            {{ travellers[activeBaggageTravelerTab]?.type }} Traveler {{ activeBaggageTravelerTab + 1 }}
                                                        </DialogDescription>
                                                    </DialogHeader>

                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 my-4">
                                                        <label v-for="(option, optIdx) in getBaggageOptionsForSegment(segment.RPH)"
                                                            :key="optIdx"
                                                            class="border rounded-lg p-4 cursor-pointer hover:border-primary transition-all"
                                                            :class="{
                                                                'border-primary bg-primary/5': isBaggageSelected(flightIdx, segment.RPH, activeBaggageTravelerTab, option.baggageCode)
                                                            }">
                                                            <div class="flex items-start gap-3">
                                                                <input type="radio"
                                                                    :name="`baggage_${flightIdx}_${segment.RPH}_${activeBaggageTravelerTab}`"
                                                                    :value="option.baggageCode"
                                                                    :checked="isBaggageSelected(flightIdx, segment.RPH, activeBaggageTravelerTab, option.baggageCode)"
                                                                    @change="handleExtraBaggageChange(flightIdx, segment.RPH, activeBaggageTravelerTab, option)"
                                                                    class="mt-1" />
                                                                <div class="flex-1">
                                                                    <div class="h-24 mb-2 flex items-center justify-center bg-gray-100 rounded">
                                                                        <img src="/public/assets/baggage.jpg" alt="Baggage"
                                                                            class="h-full object-contain" />
                                                                    </div>
                                                                    <p class="font-medium">{{ option.baggageCode }}</p>
                                                                    <p class="text-sm text-gray-500">{{ option.baggageDescription }}</p>
                                                                    <p class="text-primary font-semibold mt-2">
                                                                        {{ option.currencyCode }} {{ formatAmount(option.baggageCharge) }}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>

                                                    <DialogFooter>
                                                        <DialogClose as-child>
                                                            <Button variant="outline">Cancel</Button>
                                                        </DialogClose>
                                                        <DialogClose as-child>
                                                            <Button @click="saveBaggageSelection(flightIdx, segment.RPH, activeBaggageTravelerTab)"
                                                                :disabled="!tempBaggageSelection[flightIdx]?.[segment.RPH]?.[activeBaggageTravelerTab]">
                                                                Save
                                                            </Button>
                                                        </DialogClose>
                                                    </DialogFooter>
                                                </DialogContent>
                                            </Dialog>

                                            <div v-else class="text-xs text-gray-500">
                                                No extra baggage options available for this segment.
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </AccordionContent>
                    </AccordionItem>

                    <!-- MEALS SECTION -->
                    <AccordionItem v-if="hasMealsData" value="meals"
                        class="bg-white rounded border border-gray-200 overflow-hidden">
                        <AccordionTrigger class="px-4 py-3 hover:no-underline">
                            <div class="flex items-center justify-between w-full">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                                        <Utensils class="w-4 h-4 text-primary" />
                                    </div>
                                    <div class="text-left">
                                        <h3 class="font-semibold text-gray-900">Meals</h3>
                                        <p class="text-xs text-gray-500">Pre-book meals for all travelers</p>
                                    </div>
                                </div>
                                <Badge v-if="totalMealsSelected > 0" variant="secondary" class="ml-2">
                                    {{ totalMealsSelected }} meals selected
                                </Badge>
                            </div>
                        </AccordionTrigger>
                        <AccordionContent class="px-4 pb-4">
                            <div v-if="isLoading" class="flex items-center justify-center py-12">
                                <Spinner />
                            </div>

                            <div v-else-if="normalizedMeals.length > 0" class="space-y-4">
                                <div v-for="(mealGroup, groupIdx) in normalizedMeals" :key="groupIdx"
                                    class="border border-gray-200 rounded-lg overflow-hidden">

                                    <!-- Flight Segment Info -->
                                    <div class="bg-gray-50 p-3 border-b">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">
                                                    {{ mealGroup.flightInfo?.departure }} → {{ mealGroup.flightInfo?.arrival }}
                                                </p>
                                                <p class="text-xs text-gray-500">
                                                    Flight {{ mealGroup.flightInfo?.flightNumber }} • {{ formatDateTime(mealGroup.flightInfo?.departureTime) }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Traveler meal selection tabs -->
                                    <div class="px-4 pt-3 border-b border-gray-200">
                                        <div class="flex flex-wrap gap-2">
                                            <button v-for="(traveller, travelerIndex) in travellers.filter(t => t.type !== 'INF')"
                                                :key="travelerIndex" @click="activeMealTravelerTab = travelerIndex"
                                                class="px-3 py-1.5 text-xs font-medium rounded-t-lg transition-colors"
                                                :class="activeMealTravelerTab === travelerIndex
                                                    ? 'bg-primary/10 text-primary border-b-2 border-primary'
                                                    : 'text-gray-500 hover:text-gray-700'">
                                                <div class="flex items-center gap-1">
                                                    <span>{{ traveller.type }} {{ travelerIndex + 1 }}</span>
                                                    <Badge v-if="getSelectedMealForTraveler(groupIdx, travelerIndex)"
                                                        variant="secondary" class="bg-green-100 text-green-700 text-xs px-1">
                                                        ✓
                                                    </Badge>
                                                </div>
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Selected meal preview for current traveler -->
                                    <div class="px-4 pt-3">
                                        <div v-for="(traveller, travelerIndex) in travellers.filter(t => t.type !== 'INF')"
                                            :key="travelerIndex" v-show="activeMealTravelerTab === travelerIndex">
                                            <div v-if="getSelectedMealForTraveler(groupIdx, travelerIndex)"
                                                class="mb-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-2">
                                                        <CheckCircle class="w-4 h-4 text-green-600" />
                                                        <div>
                                                            <p class="text-sm font-medium text-gray-900">
                                                                {{ getSelectedMealForTraveler(groupIdx, travelerIndex).mealName }}
                                                            </p>
                                                            <p class="text-xs text-gray-600">
                                                                {{ getSelectedMealForTraveler(groupIdx, travelerIndex).mealDescription }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-3">
                                                        <span class="text-sm font-semibold text-primary">
                                                            {{ formatAmount(getSelectedMealForTraveler(groupIdx, travelerIndex).mealCharge) }}
                                                        </span>
                                                        <button @click="removeMeal(groupIdx, travelerIndex)"
                                                            class="text-gray-400 hover:text-red-500">
                                                            <X class="w-4 h-4" />
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Meal Categories -->
                                    <div class="p-4">
                                        <div v-for="(meals, category) in groupByCategory(mealGroup.meals)" :key="category"
                                            class="mb-6 last:mb-0">
                                            <h4 class="text-sm font-semibold text-gray-900 mb-3">{{ category || 'Other' }}</h4>

                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                                <label v-for="meal in meals" :key="meal.mealCode"
                                                    class="border rounded-lg overflow-hidden cursor-pointer transition-all hover:shadow-lg"
                                                    :class="{
                                                        'border-green-500 ring-2 ring-green-200':
                                                            isMealSelectedForTraveler(groupIdx, activeMealTravelerTab, meal.mealCode)
                                                    }">
                                                    <div class="h-40 bg-gray-100">
                                                        <img :src="meal.mealImageLink || '/placeholder-meal.jpg'"
                                                            :alt="meal.mealName" class="w-full h-full object-cover"
                                                            @error="handleImageError" />
                                                    </div>
                                                    <div class="p-4">
                                                        <div class="flex items-start gap-2 mb-2">
                                                            <input type="radio"
                                                                :name="`meal_${groupIdx}_${activeMealTravelerTab}`"
                                                                :value="meal.mealCode"
                                                                :checked="isMealSelectedForTraveler(groupIdx, activeMealTravelerTab, meal.mealCode)"
                                                                @change="selectMeal(groupIdx, activeMealTravelerTab, meal)"
                                                                class="mt-1" />
                                                            <div>
                                                                <p class="font-medium">{{ meal.mealName }}</p>
                                                                <p class="text-sm text-gray-500 line-clamp-2">{{ meal.mealDescription }}</p>
                                                            </div>
                                                        </div>
                                                        <p class="text-primary font-semibold mt-2">
                                                            {{ formatAmount(meal.mealCharge) }}
                                                        </p>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div v-else class="bg-white rounded border border-gray-200 p-12 text-center">
                                <p class="text-gray-500">No meal options available.</p>
                            </div>
                        </AccordionContent>
                    </AccordionItem>
                </Accordion>
            </div>
        </div>
    </div>

    <!-- Right Side - Flight Details & Price Summary -->
    <div class="lg:col-span-2">
        <div class="sticky top-6 space-y-6">
            <!-- Summary Card -->
            <div class="bg-white rounded shadow-sm p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-slate-900">Flight summary</h2>
                    <span class="text-xs font-semibold text-primary bg-amber-100 px-3 py-1 rounded-full">
                        Flight details
                    </span>
                </div>

                <!-- Flight Route -->
                <div class="">
                    <div v-for="(flightItem, flightIdx) in flight?.leg?.flights" :key="flightIdx" class="mb-8 last:mb-0">
                        <!-- Route Header -->
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <img :src="flightItem.marketing_carrier?.logo" :alt="flightItem.marketing_carrier?.name"
                                    class="h-8 w-8 rounded-full border border-gray-200" />
                                <div>
                                    <p class="font-medium text-gray-900">{{ flightItem.marketing_carrier?.name }}</p>
                                    <p class="text-xs text-gray-500">Flight {{ flightItem.segments?.[0]?.flight_number }}</p>
                                </div>
                            </div>
                            <span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded-full">
                                Cabin Class: {{ flightItem.segments?.[0]?.cabin_class === 'E' ? 'Economy' :
                                    flightItem.segments?.[0]?.cabin_class || 'Economy' }}
                            </span>
                        </div>

                        <!-- Flight Segments -->
                        <div v-for="(segment, sIdx) in flightItem.segments" :key="sIdx" class="relative mb-2">
                            <div class="flex flex-col md:flex-row md:items-center gap-4 p-4 bg-gray-50 rounded">
                                <!-- Departure -->
                                <div class="flex-1">
                                    <div class="flex items-start gap-3">
                                        <div class="w-1 h-1 bg-primary rounded-full mt-2"></div>
                                        <div>
                                            <p class="text-sm font-semibold text-gray-900">
                                                {{ formatDateTime(segment.departure_at) }}
                                            </p>
                                            <p class="text-base font-bold text-gray-900 mt-1">
                                                {{ segment.from?.iata || segment.from?.code }}
                                            </p>
                                            <p class="text-xs text-gray-600">{{ segment.from?.name }}</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Flight Path -->
                                <div class="flex-1 flex flex-col items-center">
                                    <p class="text-xs text-gray-500 mb-1">{{ formatDuration(segment.flight_time) }}</p>
                                    <div class="w-full flex items-center">
                                        <div class="w-2 h-2 bg-primary rounded-full"></div>
                                        <div class="flex-1 h-0.5 bg-gradient-to-r from-primary to-primary/30"></div>
                                        <div class="w-2 h-2 bg-primary rounded-full"></div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Direct</p>
                                </div>

                                <!-- Arrival -->
                                <div class="flex-1 text-right">
                                    <div class="flex items-start justify-end gap-3">
                                        <div class="text-right">
                                            <p class="text-sm font-semibold text-gray-900">
                                                {{ formatDateTime(segment.arrival_at) }}
                                            </p>
                                            <p class="text-base font-bold text-gray-900 mt-1">
                                                {{ segment.to?.iata || segment.to?.code }}
                                            </p>
                                            <p class="text-xs text-gray-600">{{ segment.to?.name }}</p>
                                        </div>
                                        <div class="w-1 h-1 bg-primary rounded-full mt-2"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Fare Breakdown -->
                <div class="lg:col-span-1">
                    <div class="bg-white shadow-sm border border-gray-200 p-2 overflow-hidden">
                        <div class="flex p-4 items-center justify-between">
                            <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Price Details</h3>
                        </div>
                        <div class="">
                            <div v-for="(flightItem, flightIndex) in flight?.leg?.flights" :key="flightIndex">
                                <div class="text-xs sm:text-sm font-semibold text-gray-900 my-1 sm:my-2 flex items-center gap-1 sm:gap-2 px-2">
                                    <div class="w-1 h-1 sm:w-1.5 sm:h-1.5 bg-primary rounded-full"></div>
                                    {{ flightItem.from.iata }} → {{ flightItem.to.iata }}
                                </div>
                                <div v-for="(fare, fareIndex) in flightItem?.fares" :key="fareIndex">
                                    <div v-if="selectedFares?.includes(fare.ref_id)" class="">
                                        <Accordion class="" type="multiple" collapsible>
                                            <template v-for="(passengerFare, index) in fare.passenger_fares" :key="index">
                                                <AccordionItem :value="`fare-${flightIndex}-${fareIndex}-${index}`"
                                                    class="overflow-hidden">
                                                    <AccordionTrigger
                                                        class="px-2 py-2 border-b grid grid-cols-[1fr_auto_auto] items-center hover:no-underline gap-1">
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-xs sm:text-sm font-bold text-gray-600">
                                                                {{ passengerFare.traveler_type }} X {{ passengerFare.total_passenger }}
                                                            </span>
                                                        </div>
                                                        <span class="text-sm sm:text-base font-bold text-primary text-right whitespace-nowrap">
                                                            {{ formatAmount(calculateTotalFare(fare)) }}
                                                        </span>
                                                    </AccordionTrigger>
                                                </AccordionItem>
                                            </template>
                                        </Accordion>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex p-2 justify-between items-center">
                            <span class="text-xs sm:text-sm text-gray-600">Add-ons</span>
                            <span class="text-xs sm:text-sm font-medium">{{
                                formatAmount(calculateGrandTotal() - (flight?.pricing?.totalPrice || 0)) }}</span>
                        </div>
                        <div class="flex justify-between items-center bg-gray-50 p-2 rounded">
                            <span class="text-base sm:text-sm font-semibold text-gray-900">Total Amount</span>
                            <span class="text-sm sm:text-lg font-bold text-primary">{{ formatAmount(calculateGrandTotal()) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
    </div>
    <div v-if="isLowBalanceDialogOpen"
        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
        @click.self="isLowBalanceDialogOpen = false">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex items-start justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">Low Balance Warning</h3>
                <button @click="isLowBalanceDialogOpen = false" class="text-gray-400 hover:text-gray-500">
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
