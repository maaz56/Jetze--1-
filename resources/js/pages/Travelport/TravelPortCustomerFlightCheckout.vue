<script setup>
import Spinner from "@/components/common/Spinner.vue";
import Button from "@/components/ui/button/Button.vue";
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { ArrowLeft, ArrowRight, CheckCircle, ClockIcon, PlusCircle, SquareCheckBig, SquareX, Upload, XCircle } from "lucide-vue-next";
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
    SEND_PRICE_REQUEST,
    FETCH_ANCILLARIES,
    PATCH_ANCILLARIES,
    FETCH_AGENT_LEDGER,
    FETCH_CUSTOMER_SETTINGS,
    FETCH_AIRPORT_MARGINS,
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
    calculateCustomerMarginAmount,
    calculateTypeMargin,
} from "@/lib/utils";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";

import { Check, ChevronsUpDown } from "lucide-vue-next";
import { calculateLayover, formatDuration } from "@/lib/utils";
import { calculateFinalPrice } from "@/lib/utils.js";

import { useRouter } from "vue-router";
import TopBar from "@/components/shared/TopBar.vue";
import Nav from "@/components/shared/Nav.vue";
import { loadStripe } from "@stripe/stripe-js";
import { toast } from "vue3-toastify";
import Tesseract from "tesseract.js";
import CountryDropdown from "@/components/common/CountryDropdown.vue";
import Calender from '@/components/common/CheckoutCalender.vue';
const already_booked = computed(() => store.getters["flight/already_booked"]);
const route = useRoute();
const store = useStore();
const authStore = useAuthStore();

const user = computed(() => authStore.user);
const user_id = computed(() => user.value?.id);
// const agentData = computed(() => store.getters["user/agentData"]);
const router = useRouter();
const termsAccepted = ref(false);
const validationErrors = ref([]);
const amount = ref(0);
const passengerCount = ref(0);
const currentSlide = ref(0);
const todayDate = new Date();
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const priceRes = computed(() => store.getters["flight/priceRes"]);
const fareRules = computed(() => store.getters["flight/fareRules"]);

const ancillaries = computed(() => store.getters["flight/ancillaries"]);
const priceError = computed(() => store.getters["flight/priceError"]);
const priceErrorMessage = computed(() => store.getters["flight/priceErrorMessage"]);
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
const isAlreadyBookedDialogOpen = ref(false);
const elements = ref(null);
const isLowBalanceDialogOpen = ref(false);
const isPaymentMethodsVisible = ref(false);
const countdown = ref(null);
const showDialog = ref(false);
const timerInterval = ref();
const customerMarginAmt = ref(0);
const isPriceChangedDialogOpen = ref(false);
const priceChangedFrom = ref(0);
const priceChangedTo = ref(0);
const pendingValidatedTotalWithMargins = ref(null);
const validatedTotalOverride = ref(null);
const lastValidatedSupplierTotal = ref(null);

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

function parsePriceValue(value) {
    if (value === null || value === undefined) return 0;
    if (typeof value === "number") return Number.isFinite(value) ? value : 0;
    const normalized = String(value)
        .trim()
        .replace(/,/g, "")
        .replace(/[^0-9.\-]/g, "");
    const num = parseFloat(normalized);
    return Number.isFinite(num) ? num : 0;
}

function getFlightSupplierTotal() {
    const candidates = [
        flight.value?.pricing?.totalPrice,
        flight.value?.pricing?.total_price,
        flight.value?.pricing?.TotalPrice,
        flight.value?.pricing?.total,
        flight.value?.totalPrice,
        flight.value?.total_price,
        flight.value?.price,
    ];

    for (const candidate of candidates) {
        const parsed = parsePriceValue(candidate);
        if (parsed > 0) return parsed;
    }

    let fallbackTotal = 0;
    flight?.value?.leg?.flights?.forEach((flightItem) => {
        flightItem?.fares?.forEach((fare) => {
            if (selectedFares.value.includes(fare.ref_id)) {
                fallbackTotal +=
                    parsePriceValue(fare.base_price) +
                    parsePriceValue(fare.surchage) +
                    parsePriceValue(fare.taxes) +
                    parsePriceValue(fare.fees) +
                    parsePriceValue(fare.service_charges) +
                    parsePriceValue(fare.ancillaries_charges);
            }
        });
    });

    return fallbackTotal;
}

const grandTotal = computed(() => calculateGrandTotal());
const displayedTotal = computed(() => validatedTotalOverride.value ?? grandTotal.value);

watch(
    displayedTotal,
    (value) => {
        amount.value = parseFloat(parsePriceValue(value).toFixed(2));
    },
    { immediate: true },
);

function acceptPriceChange() {
    if (pendingValidatedTotalWithMargins.value !== null) {
        validatedTotalOverride.value = pendingValidatedTotalWithMargins.value;
    }
    pendingValidatedTotalWithMargins.value = null;
    isPriceChangedDialogOpen.value = false;
}

function cancelPriceChange() {
    pendingValidatedTotalWithMargins.value = null;
    isPriceChangedDialogOpen.value = false;
    router.back();
}



const totalPrice = computed(() => {
    const basePrice = parseFloat(flight.value?.pricing?.totalPrice || "0");
    const marginAmount = parseFloat("0");

    //console.log("Base Price:", basePrice); // Debug log
    //console.log("Margin Amount:", marginAmount); // Debug log

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
    email: user?.customer?.email,
    phone: user?.customer?.phone,
    country: localStorage.getItem("country") || "",
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
watch(user, (newUser) => {
    mainContact.value.email = newUser?.customer?.email || "";
    mainContact.value.phone = newUser?.customer?.phone || "";
});
watch(already_booked, (newValue) => {
    if (newValue) {
        isAlreadyBookedDialogOpen.value = true;
    }
});
const initializeTravellers = () => {
    // Check if we have detailed passenger info

    // Parse query params
    const adults = parseInt(route.query.adults || 0);
    const childs = parseInt(route.query.children || 0);
    const infants = parseInt(route.query.infants || 0);
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
        // Remove all non-digit characters
        value = value.replace(/\D/g, "");

        // Apply CNIC format: 5-7-1 digits
        if (value.length > 5 && value.length <= 12) {
            value = value.replace(/(\d{5})(\d+)/, "$1-$2");
        } else if (value.length > 12) {
            value = value.replace(/(\d{5})(\d{7})(\d+)/, "$1-$2-$3");
        }

        // Limit to CNIC max length (15 including hyphens)
        return value.substring(0, 15);
    }

    // Helper to create a traveler object
    const createTraveler = (type) => ({
        type,
        isOpen: false, // Keep others closed
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

function fetchCustomerSettings() {
    store.dispatch("customer/" + FETCH_CUSTOMER_SETTINGS)
}
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
        if (traveller.documentType === 'cnic') {
            if (!traveller.cnic && traveller.type == 'ADT') {
                errors.travellers[index].cnic = "CNIC is required";
                isValid = false;
            }
        }

        if (traveller.documentType !== 'cnic') {
            if (!traveller.documentNo) {
                errors.travellers[index].documentNo = "Document number is required";
                isValid = false;
            }

        }


        if (!traveller.expiryDate) {
            errors.travellers[index].expiryDate = "Expiry date is required";
            isValid = false;
        } else if (!validateExpiryDate(traveller.expiryDate)) {
            errors.travellers[index].expiryDate =
                "Document must not be expired";
            isValid = false;
        }

        console.log("Validation errors for traveller", index, errors.travellers[index]);

    });

    return isValid;
};
function handleConfirmDialogOpen() {
    console.log("called");
    console.log("agenledger", agentLedger?.value.balance);
    //console.log("totalTicketPrice", amount?.value);
    if (agentLedger?.value.balance < amount?.value || agentLedger?.value.balance == 0) {
        isLowBalanceDialogOpen.value = true;
        toast
        return;
    }
    // if (!validateForm()) {
    //     globalError.value =
    //         "Please fix the errors in the form before submitting";
    //     // Scroll to the top to show the global error
    //     window.scrollTo({ top: 0, behavior: "smooth" });
    //     return;
    // }




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
    if (!termsAccepted.value) {
        globalError.value = "Please accept the agreement before continuing";
        return;
    }

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
        ref_id: priceRes.value.ref_id,
    })
}

watch(priceRes, () => {
    if (!priceRes.value) {
        router.back();
        return;
    }

    const validatedSupplierTotal = parsePriceValue(
        priceRes?.value?.OfferListResponse?.OfferID?.[0]?.Price?.TotalPrice,
    );
    if (validatedSupplierTotal <= 0) return;

    if (lastValidatedSupplierTotal.value === validatedSupplierTotal) return;
    lastValidatedSupplierTotal.value = validatedSupplierTotal;

    const oldTotalWithMargins = parsePriceValue(displayedTotal.value);
    const oldSupplierTotal = parsePriceValue(getFlightSupplierTotal());
    const appliedMargins = oldSupplierTotal > 0 ? oldTotalWithMargins - oldSupplierTotal : 0;
    const newTotalWithMargins = parseFloat((validatedSupplierTotal + appliedMargins).toFixed(2));

    if (newTotalWithMargins > oldTotalWithMargins + 0.01) {
        priceChangedFrom.value = oldTotalWithMargins;
        priceChangedTo.value = newTotalWithMargins;
        pendingValidatedTotalWithMargins.value = newTotalWithMargins;
        isPriceChangedDialogOpen.value = true;
        return;
    }

    // If the validated price is lower (or equal), update silently.
    validatedTotalOverride.value = newTotalWithMargins;
    // fetchAncillaries();
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
        searchQuery: event?.target?.value ?? localStorage.getItem("country") ?? "",
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


function sendPriceRequest() {
    // Save selected flight in localStorage
    localStorage.setItem("selectedFlight", JSON.stringify(flight.value));

    const body = {
        flight_provider: 'travelport',
        flight_data: flight.value,
        selectedFares: selectedFares.value,
        adults: parseInt(route.query.adults || 0),
        children: parseInt(route.query.children || 0),
        infants: parseInt(route.query.infants || 0),

    };

    store.dispatch("flight/" + SEND_PRICE_REQUEST, body);
}

function patchAncillaryCharges() {
    store.dispatch("flight/" + PATCH_ANCILLARIES, {
        ref_id: priceRes?.value?.ref_id,
        extraCharges: extraCharges.value
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
    if (isSubmitting.value) return;

    // if (!validateForm()) {
    //     globalError.value =
    //         "Please fix the errors in the form before submitting";
    //     window.scrollTo({ top: 0, behavior: "smooth" });
    //     return;
    // }
    if (type === 'pay') {
        paymentMethod.value = 'pay';
        if (isPaymentMethodsVisible.value == true) {
            isPaymentMethodsVisible.value = false;
        } else {
            isPaymentMethodsVisible.value = true;
        }
    } else if (type == 'hold') {
        paymentMethod.value = 'hold';
        saveBooking();
        // isConfirmDialogOpen.value = true;
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
        pnr: bookingDetails?.value?.[0]?.pnr,
        pnrData: pnrData.value,
        flight_provider: route?.query.flight_provider,
        bookingId: bookingDetails?.value?.id,
        booking_status: paymentMethod.value === 'hold' ? 'booked' : paymentMethod.value === 'pay' ? 'ticketed' : paymentMethod.value === 'card' ? 'issued' : 'booked',
        booking_source: route.query.flight_source,
        amount: parseFloat(pnrData?.value?.data?.billable_price).toFixed(2),
    }).then(() => {
        isDisabled.value = false;
    });

}

async function saveBooking(type) {

    try {
        isSubmitting.value = true;

        // if (!validateForm()) {
        //     globalError.value = "Please fix the errors in the form before submitting";
        //     window.scrollTo({ top: 0, behavior: "smooth" });
        //     return;
        // }
        // ✅ Wait for ancillary charges to finish before continuing
        if (ancillaries.value) {
            await patchAncillaryCharges();
        }
        isConfirmDialogOpen.value = false;

        currentState.value = 0;

        if (paymentMethod.value === "pay" || paymentMethod.value === "card") {
            isOpen.value = true;
        }

        globalError.value = "";

        const savedMarginBreakdown = getSavedMarginBreakdown();

        await store.dispatch("flight/" + SAVE_BOOKING, {
            main_contact: mainContact.value,
            travellers: travellers.value,
            agency_contact: agencyContact.value,
            agent_id: user_id.value,
            agency_mobile: user.value?.phone || "00000",
            agency_email: user?.value?.email || "support@Jetze.pk",
            amount: amount.value,
            flight: flight.value,
            flight_id : route.query.flight_id,
            airportMargin: savedMarginBreakdown.airportMarginTotal,
            booking_status_setting: bookingStatusSetting?.value.bookingStatus,
            flight_source: route?.query.flight_source,
            flight_mode: "B2C",
            flight_provider: route?.query.flight_provider,
            fare_reference: selectedFares.value,
            agent_markup: savedMarginBreakdown.customerMarkupTotal,
            agent_discount: savedMarginBreakdown.customerDiscountTotal,
            agent_margin: savedMarginBreakdown.otherChargesTotal,
            type: paymentMethod.value || type,
            paymentMethod: paymentMethod.value || type,
            adults: parseInt(route.query.adults || 0),
            children: parseInt(route.query.children || 0),
            infants: parseInt(route.query.infants || 0),
            booking_status:
                paymentMethod.value === "hold"
                    ? "booked"
                    : paymentMethod.value === "pay"
                        ? "ticketed"
                        : paymentMethod.value === "card"
                            ? "booked"
                            : "booked",
            // body: priceRes.value,
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
            flight_mode: route.query.flight_mode,
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
        currentState.value = 1;
        parsePnrResponse();
        confirmBooking();
    }
    else if (paymentMethod.value === 'hold') {
        parsePnrResponse();
        //     //console.log("pnrData", pnrData.value);
        router.push({
            name: "CustomerPaymentView", // Replace with the name of your route
            query: {
                flight_id: route.query.flight_id,
                booking_id: bookingDetails?.value?.id,
                pnr: bookingDetails?.value?.itinerary_ref,
                flight_mode: route.query.flight_mode,
                booking_source: route.query.flight_source,
                flight_provider: route.query.flight_provider,
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

function saveExtra(flightIdx, type, segmentIdx, travellerIdx) {
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

function calculateBaseFare(fare) {
    const basePrice = parseFloat(fare?.base_price) || 0;

    const airlineMargin = calculateFareMargin(
        basePrice,
        fare?.margin_amount,
        fare?.margin_type,
        fare?.amount_type
    );

    const customerMargin = calculateCustomerMarginAmount(
        basePrice,
        CustomerMargin?.value
    );

    typeMargin.value = calculateTypeMargin(
        user.value,
        airportMargins.value,
    );

    const perPassenger =
        airlineMargin + customerMargin;

    const total =
        perPassenger * passengerCount.value +
        typeMargin.value * passengerCount.value;

    return total;
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
const calculateCustomerMargin = (price) => {
    const margin = calculateCustomerMarginAmount(price, CustomerMargin?.value);
    customerMarginAmt.value = margin;
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
    ));
    // console.log(customerMargin);
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
    // console.log(airlineMargin);
    const billable = fare.base_price + parseFloat(fare.surchage || 0) + parseFloat(fare.taxes || 0) + parseFloat(fare.fees || 0) + parseFloat(fare.service_charges || 0) + parseFloat(fare.ancillaries_charges || 0) + (parseFloat(airlineMargin) * passengerCount.value) + (parseFloat(typeMargin) * passengerCount.value);

    const total = billable + (customerMargin * passengerCount.value);
    //   console.log(total);
    return total;
}
function calculateGrandTotal() {
    let total = 0;
    let hasSelectedFare = false;

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
                hasSelectedFare = true;
                total += calculateTotalFare(fare) + extrasAmount
            }
        });
    });

    if (hasSelectedFare) {
        total += parseFloat(CustomerMargin?.value?.other_charges || 0);
    }

    return total;
}

function getSelectedFareItems() {
    const selectedItems = [];

    flight?.value?.leg?.flights?.forEach((flightItem) => {
        flightItem?.fares?.forEach((fare) => {
            if (selectedFares.value.includes(fare.ref_id)) {
                selectedItems.push(fare);
            }
        });
    });

    return selectedItems;
}

function getSavedMarginBreakdown() {
    const selectedFareItems = getSelectedFareItems();
    const passengerTotal = Number(passengerCount.value || 0);
    const airportMarginPerPassenger = parseFloat(
        calculateTypeMargin(user.value, airportMargins.value) || 0
    );
    const otherChargesTotal = parseFloat(CustomerMargin?.value?.other_charges || 0);

    let customerMarkupTotal = 0;
    let customerDiscountTotal = 0;

    selectedFareItems.forEach((fare) => {
        const customerMarginPerPassenger = parseFloat(
            calculateCustomerMarginAmount(fare.base_price, CustomerMargin?.value)
        );

        if (customerMarginPerPassenger >= 0) {
            customerMarkupTotal += customerMarginPerPassenger * passengerTotal;
        } else {
            customerDiscountTotal += Math.abs(customerMarginPerPassenger) * passengerTotal;
        }
    });

    return {
        customerMarkupTotal,
        customerDiscountTotal,
        airportMarginTotal: airportMarginPerPassenger * passengerTotal * selectedFareItems.length,
        otherChargesTotal,
    };
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

function isImportantRule(ruleName) {
    const importantRules = ['Penalties', 'Cancellation', 'Refund', 'Change', 'Surcharges', 'Advance Res/Tkt'];
    return importantRules.some(name => ruleName.toLowerCase().includes(name.toLowerCase()));
}
function formatRuleName(name) {
    // Convert camelCase/PascalCase to readable format
    return name
        .replace(/([A-Z])/g, ' $1')
        .replace(/^./, str => str.toUpperCase())
        .replace(/\s+/g, ' ')
        .trim();
}
function formatRuleValue(value) {
    if (!value) return '';

    // Format penalties string to be more readable
    if (value.includes('CHG') || value.includes('CXL') || value.includes('SAR')) {
        return value
            .replace(/\//g, '\n• ')
            .replace(/CHG/g, 'Change')
            .replace(/CXL/g, 'Cancellation')
            .replace(/BEF DEP/g, 'Before Departure')
            .replace(/AFT DEP/g, 'After Departure')
            .replace(/-/g, ': ')
            .split('\n')
            .map(line => line.trim())
            .filter(line => line)
            .join('\n');
    }

    // Format discounts
    if (value.includes('CHD') || value.includes('PCT')) {
        return value
            .replace(/CHD/g, 'Children')
            .replace(/PCT/g, '%')
            .replace(/ACC/g, 'Accompanied')
            .replace(/UNACC/g, 'Unaccompanied');
    }

    return value;
}

onMounted(() => {
    authStore.fetchUser();
    selectedFares.value = route.query.fares ? JSON.parse(route.query.fares) : []
    passengerCount.value = route.query.passenger_count ? parseInt(route.query.passenger_count) : 1
    window.scrollTo({ top: 0, behavior: "smooth" });
    startCountdown(13 * 60 * 1000); // 13 minutes countdown
    fetchCountries();
    //initializeStripe();
    fetchMargins();
    fetchBookingStatus();
    fetchFlight();
    fetchCustomerMarginValues();
    fetchCustomerSettings();
    fetchAgentLedger();
});

watch(flight, () => {
    initializeTravellers();
    sendPriceRequest();
    if (travellers.value.length > 0) {
        travellers.value[0].isOpen = true;
    }
});

watch(
  () => travellers.value,
  (newTravelers) => {
    newTravelers.forEach(t => {
      t.issueCountry = t.nationality
    })
  },
  { deep: true }
)

</script>

<template>

    <!-- Loading State -->

    <div v-if="priceError" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
            <div class="flex items-start justify-between mb-4">
                <h3 class="text-lg font-medium text-gray-900">System Problem</h3>
                <button @click="() => { priceError = false; router.back(); }" class="text-gray-400 hover:text-gray-500">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
            <div class="text-sm text-gray-700 mb-6">
                {{ priceErrorMessage || "There is a problem in the system. Please try again later or contact support if the issue persists." }}
            </div>
            <div class="flex justify-end">
                <button @click="() => { priceError = false; router.back(); }"
                    class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/20">
                    Close
                </button>
            </div>
        </div>
    </div>
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
        <div v-if="isLoading" class="min-h-screen bg-gray-50 flex items-center justify-center">
            <div class="bg-white p-6 max-w-md w-full mx-4">
                <div class="flex flex-col items-center space-y-3">
                    <Spinner />
                </div>
            </div>
        </div>
        <div v-else class="max-w-7xl  mx-auto px-3 sm:px-4">

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
                                                        country.value === mainContact.country)?.label || mainContact.country
                                                        || "Select country" :
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
                                                <div class="flex items-center  gap-6 mt-4">
                                                    <!-- Gender Selection -->
                                                    <div class="mb-4">
                                                        <Label class="text-sm font-medium text-gray-700 mb-2 block">
                                                            Gender <span class="text-red-500">*</span>
                                                        </Label>
                                                        <div class="flex gap-2">
                                                            <button type="button" @click="traveller.gender = 'M'"
                                                                :class="[
                                                                    'px-3 py-1.5 text-xs rounded-full border transition-colors',
                                                                    traveller.gender === 'M'
                                                                        ? 'border-primary bg-primary text-white'
                                                                        : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'
                                                                ]">
                                                                Male
                                                            </button>
                                                            <button type="button" @click="traveller.gender = 'F'"
                                                                :class="[
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
                                                                :key="titleOption"
                                                                @click="traveller.title = titleOption" :class="[
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
                                                            <Calender v-model="traveller.dob" type="date"
                                                                :maxValue="todayDate.toISOString().split('T')[0]"
                                                                :id="`date-of-birth-${index}`"
                                                                placeholder="Date Of Birth"
                                                                :class="{ 'is-invalid': getErrorPath(`travellers.${index}.dob`) }"
                                                                class="w-full h-8 text-sm bg-white text-black border-gray-200 focus:border-primary focus:ring-primary/20" />
                                                            <div v-if="getErrorPath(`travellers.${index}.dob`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${index}.dob`) }}</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <Label :for="`nationality-${index}`"
                                                                class="block text-sm font-medium text-gray-700 mb-1">
                                                                Nationality<span class="required">*</span>
                                                            </Label>

                                                            <CountryDropdown :keyValue="'code'"
                                                                placeholder="SELECT NATIONALITY"
                                                                v-model="traveller.nationality"
                                                                @update:modelValue="(value) => traveller.nationality = value" />
                                                            <div v-if="getErrorPath(`travellers.${index}.nationality`)"
                                                                class="error-message">
                                                                {{ getErrorPath(`travellers.${index}.nationality`) }}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Document Information -->
                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
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
                                                                        <SelectItem value="passport">Passport
                                                                        </SelectItem>
                                                                        <SelectItem value="cnic">CNIC</SelectItem>
                                                                    </SelectGroup>
                                                                </SelectContent>
                                                            </Select>
                                                            <div v-if="getErrorPath(`travellers.${index}.documentType`)"
                                                                class="error-message text-xs text-red-500">
                                                                {{ getErrorPath(`travellers.${index}.documentType`) }}
                                                            </div>
                                                        </div>

                                                        <div v-if="traveller.documentType == 'passport'"
                                                            class="space-y-2">
                                                            <Label :for="`document-no-${index}`"
                                                                class="block text-sm font-medium text-gray-700">
                                                                Document Number <span class="text-red-500">*</span>
                                                            </Label>
                                                            <Input v-model="traveller.documentNo"
                                                                :id="`document-no-${index}`"
                                                                :class="{ 'is-invalid': getErrorPath(`travellers.${index}.documentNo`) }"
                                                                type="text"
                                                                class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20"
                                                                placeholder="Enter document number" />
                                                            <div v-if="getErrorPath(`travellers.${index}.documentNo`)"
                                                                class="error-message text-xs text-red-500">
                                                                {{ getErrorPath(`travellers.${index}.documentNo`) }}
                                                            </div>
                                                        </div>
                                                        <div v-if="traveller.documentType == 'cnic'" class="space-y-2">
                                                            <Label :for="`document-no-${index}`"
                                                                class="block text-sm font-medium text-gray-700">
                                                                CNIC Number <span class="text-red-500">*</span>
                                                            </Label>
                                                            <Input v-model="traveller.cnic" :id="`cnic-no-${index}`"
                                                                type="text"
                                                                class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20"
                                                                placeholder="Enter CNIC number" />

                                                            <div v-if="getErrorPath(`travellers.${index}.cnic`)"
                                                                class="error-message text-xs text-red-500">
                                                                {{ getErrorPath(`travellers.${index}.cnic`) }}
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div>
                                                            <Label
                                                                class="text-sm font-medium text-gray-700 mb-2 block">Expiry
                                                                Date <span class="text-red-500">*</span></Label>
                                                            <Calender v-model="traveller.expiryDate"
                                                                :id="`expiry-date-${index}`"
                                                                :class="{ 'is-invalid': getErrorPath(`travellers.${index}.expiryDate`) }"
                                                                type="date"
                                                                class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20" />
                                                            <div v-if="getErrorPath(`travellers.${index}.expiryDate`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${index}.expiryDate`) }}</div>
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
                                                                <div v-if="selectedFares?.includes(fare.ref_id)"
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
                                                                                    {{ policy.description }} cabin
                                                                                    baggage
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
                                                                                    {{ policy.description }} checked
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
                                                            <Dialog v-if="ancillaries?.providers">
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
                                                                                    @click="saveExtra(flightIdx, 'seat', segmentIdx, index)">
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
                                                                                @click="saveExtra(flightIdx, 'meal', segmentIdx, index)">
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
                                                                moment.parseZone(segment?.departure_at).format("HH:mm")
                                                            }}</span>
                                                            <span class="text-xs font-medium text-gray-900">{{
                                                                moment.parseZone(segment?.arrival_at).format("HH:mm")
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
                                                                                ["baggage", "seat", "meal"].reduce((sum, group) => {
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
                                                                        ))*passengerFare?.total_passenger))
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
	                                        {{ formatAmount(displayedTotal) }}
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
                                                    Service of Jetze.com.pk
                                                </Label>
                                            </div>
                                            <Button @click="showBookingPreview" :disabled="isSubmitting || !termsAccepted"
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


    <div v-if="showPreview && flight" class="min-h-screen bg-gray-50 py-4">
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
        <div v-if="isLoading" class="min-h-screen bg-gray-50 flex items-center justify-center">
            <div class="bg-white p-6 max-w-md w-full mx-4">
                <div class="flex flex-col items-center space-y-3">
                    <Spinner />
                </div>
            </div>
        </div>
        <div v-else class="max-w-7xl mx-auto px-3 sm:px-4">
            <!-- Preview Header -->
            <div class="mb-2">
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
                                    <!-- Flight Segment -->
                                    <div class="p-4">
                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <!-- Departure -->
                                            <div class="space-y-2">
                                                
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
                                                            moment.parseZone(segment?.departure_at).format("HH:mm")
                                                            }}</span>
                                                        <span class="text-xs font-medium text-gray-900">{{
                                                            moment.parseZone(segment?.arrival_at).format("HH:mm")
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
                                                item.amount) ) }}</span>
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
                                                item.amount) ) }}</span>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Sidebar -->
                <div class="lg:col-span-2">
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
                                                        <h4 class="text-sm font-medium text-gray-900 mb-1 capitalize">
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
                                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200 transition-colors duration-200">
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
                                    class="text-xs sm:text-sm font-semibold text-gray-900 my-1 sm:my-2 flex items-center gap-1 sm:gap-2 px-2">
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
                                                <AccordionItem :value="`fare-${flightIndex}-${fareIndex}-${index}`"
                                                    class="  overflow-hidden">
                                                    <!-- HEADER -->
                                                    <AccordionTrigger
                                                        class="px-2 py-2 border-b grid grid-cols-[1fr_auto_auto] items-center  hover:no-underline gap-1">

                                                        <!-- LEFT -->
                                                        <div class="flex items-center gap-2">
                                                            <span class="text-xs sm:text-sm font-bold text-gray-600">
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
                                                    <AccordionContent class="px-3 sm:px-4 sm:pr-7 pb-3 space-y-2">
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
                                                            <span class="text-xs sm:text-sm text-gray-600">Taxes</span>
                                                            <span class="text-xs sm:text-sm font-medium">
                                                                {{ formatAmount(passengerFare?.taxes) }}
                                                            </span>
                                                        </div>

                                                        <div class="flex justify-between items-center">
                                                            <span class="text-xs sm:text-sm text-gray-600">Fees</span>
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
                                                                        ["baggage", "seat", "meal"].reduce((sum, group) => {
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
                                                                        || 0)
                                                                }}
                                                            </span>
                                                        </div>

                                                        <hr class="border-dashed border-gray-300" />

                                                        <div class="flex justify-between items-center   rounded">
                                                            <span class="text-xs sm:text-sm font-medium text-gray-700">
                                                                Amount
                                                            </span>
                                                            <span class="text-sm sm:text-base font-bold text-primary">
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
                                        <div class="flex justify-between items-center bg-gray-50 p-2 sm:px-4 rounded">
                                            <span class="text-xs sm:text-sm font-bold text-gray-700">Amount</span>
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
	                        <div class="flex justify-between mt-3 sm:mt-4 items-center bg-gray-50 p-3  rounded">
	                            <span class="text-base sm:text-sm font-semibold text-gray-900">Total Amount</span>
	                            <span class="text-sm sm:text-lg font-bold text-primary">
	                                {{ formatAmount(displayedTotal) }}
	                            </span>
	                        </div>
                        <Button @click="handlePaymentMethod('hold')" :disabled="isPaymentMethodsVisible || isSubmitting"
                            class="w-full bg-primary hover:bg-primary/50 text-white py-2 sm:py-2.5 rounded-lg font-medium text-xs sm:text-sm disabled:opacity-50">
                            <Spinner v-if="isSubmitting" class="w-4 h-4 mr-1" />
                            {{ isSubmitting ? "Saving..." : "Next Step" }}
                            <ArrowRight v-if="!isSubmitting" class="w-4 h-4" />


                        </Button>
                    </div>
                </div>
            </div>
        </div>
	    </div>
	    <div v-if="isPriceChangedDialogOpen"
	        class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
	        @click.self="cancelPriceChange">
	        <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">
	            <div class="flex items-start justify-between mb-4">
	                <h3 class="text-lg font-medium text-gray-900">Price Changed</h3>
	                <button @click="cancelPriceChange" class="text-gray-400 hover:text-gray-500">
	                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
	                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
	                            d="M6 18L18 6M6 6l12 12" />
	                    </svg>
	                </button>
	            </div>
	            <p class="text-sm text-gray-600 mb-6">
	                Price has been changed from <span class="font-semibold">{{ formatAmount(priceChangedFrom) }}</span>
	                to <span class="font-semibold">{{ formatAmount(priceChangedTo) }}</span>.
	                Do you want to continue?
	            </p>
	            <div class="flex justify-end space-x-3">
	                <button @click="cancelPriceChange"
	                    class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">
	                    Cancel
	                </button>
	                <button @click="acceptPriceChange"
	                    class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/20">
	                    Continue
	                </button>
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
    <div v-if="isAlreadyBookedDialogOpen"
    class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
    @click.self="isAlreadyBookedDialogOpen = false">

    <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6">

        <!-- Header -->
        <div class="flex items-start justify-between mb-4">
            <h3 class="text-lg font-medium text-primary">
                Booking Already Exists
            </h3>

            <button @click="isAlreadyBookedDialogOpen = false"
                class="text-gray-400 hover:text-gray-500">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Message -->
        <p class="text-sm text-gray-600 mb-5">
            This traveler already has a confirmed booking for this flight.  
            Duplicate bookings are not allowed.
        </p>

       
        <!-- Actions -->
        <div class="flex justify-end">
            <button
                @click="isAlreadyBookedDialogOpen = false"
                class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:bg-primary/80">
                OK
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
