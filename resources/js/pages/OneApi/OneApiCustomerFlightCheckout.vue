<script setup>
import Spinner from "@/components/common/Spinner.vue";
import Button from "@/components/ui/button/Button.vue";
import Input from "@/components/ui/input/Input.vue";
import Label from "@/components/ui/label/Label.vue";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
import { ArrowLeft, ArrowRight, CheckCircle, ClockIcon, Info, Package, PlusCircle, SquareCheckBig, SquareX, Upload, X, XCircle } from "lucide-vue-next";
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";

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
const todayDate = new Date();

const passengerCount = ref(0);
const currentSlide = ref(0);
const passengerFares = ref([]);
const otherChargesPerPassenger = computed(() => {
    const total = parseFloat(CustomerMargin?.value?.other_charges || 0);
    const pax = parseInt(passengerCount.value || 0);
    return pax > 0 ? total / pax : 0;
});
const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
const quote = computed(() => store.getters["flight/priceRes"]);
const ancillaries = computed(() => store.getters["flight/ancillaries"]);
const qouteError = computed(() => store.getters["flight/priceError"]);
const agentLedger = computed(() => store.getters["ledger/agentLedgerData"]);
const customerSettings = computed(() => store.getters['customer/customerSettings']);
const ancillariesResponse = computed(() => store.getters["flight/ancillariesResponse"]);




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
const autoSelectedFree = reactive({ baggage: false, seat: false, meal: false });


const cardElement = ref(null);
const clientSecret = ref('');
const publicKey = ref(import.meta.env.VITE_STRIPE_KEY);
const safepayUrl = computed(
    () => store.getters["safepay/safePayUrl"],
);
const getSelectedFare = () => {
    const selectedIds = selectedFares.value || [];
    let foundFare = null;

    flight?.value?.leg?.flights?.some((flightItem) => {
        return flightItem?.fares?.some((fare) => {
            if (selectedIds.includes(fare.ref_id)) {
                foundFare = fare;
                return true;
            }
            return false;
        });
    });

    return foundFare;
};

const fareMarginAmount = computed(() => getSelectedFare()?.margin_amount ?? 0);
const fareMarginType = computed(() => getSelectedFare()?.margin_type ?? "markup");
const fareAmountType = computed(() => getSelectedFare()?.amount_type ?? "amount");

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

const totalAddOnesAmount = computed(() => {
    return ["baggage", "seat", "meal"].reduce((sum, group) => {
        const flightsExtras = selectedExtras.value || {};

        const groupTotal = Object.values(flightsExtras).reduce((flightSum, flightExtras) => {
            if (!flightExtras || typeof flightExtras !== "object") return flightSum;

            const groupData = flightExtras[group];
            if (!groupData || typeof groupData !== "object") return flightSum;

            return flightSum + Object.values(groupData).reduce((segmentSum, segmentGroup) => {
                if (!segmentGroup || typeof segmentGroup !== "object") return segmentSum;

                return segmentSum + Object.values(segmentGroup).reduce((itemSum, item) => {
                    if (!item || typeof item !== "object") return itemSum;

                    let price = 0;
                    if (group === "baggage") {
                        price = parseFloat(item.baggageCharge || item.amount || 0);
                    } else if (group === "seat") {
                        price = parseFloat(item.seatPrice || item.amount || 0);
                    } else if (group === "meal") {
                        price = parseFloat(item.mealCharge || item.amount || 0);
                    } else {
                        price = parseFloat(item.price || item.amount || 0);
                    }

                    return itemSum + (isNaN(price) ? 0 : price);
                }, 0);
            }, 0);
        }, 0);

        return sum + groupTotal;
    }, 0);
});



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
        isOpen: false,
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

    if (travellers.value.length > 0) {
        travellers.value[0].isOpen = true;
    }


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
        if (!traveller.issueCountry) {
            errors.travellers[index].issueCountry = "Issue country is required";
            isValid = false;
        } else if (traveller.issueCountry.length !== 2) {
            errors.travellers[index].issueCountry = "Issue country must be 2 characters (e.g., PK)";
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
        // if (!traveller.cnic && traveller.type =='ADT' ) {
        //     errors.travellers[index].cnic = "CNIC is required";
        //     isValid = false;
        // }

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


    });

    return isValid;
};
watch(
  () => travellers.value,
  (newTravelers) => {
    newTravelers.forEach(t => {
      t.issueCountry = t.nationality
    })
  },
  { deep: true }
)

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
        flight_provider: route.query.flight_provider,
        quote: quote.value,
        flight: flight.value,
    })
}
function validatePriceWithBundle() {
    store.dispatch("flight/" + SEND_PRICE_REQUEST, {
        flight: flight.value,
        flight_provider: route.query.flight_provider,
        selectedFares: selectedFares.value,
        adults: route.query.adults,
        children: route.query.children,
        infants: route.query.infants,
    })
}
watch(quote, () => {
    if (!quote.value) {
        // router.back();
        return;
    }
    console.log("New quote received:", quote.value);
    console.log(quote);
    // Ensure it's always an array
    passengerFares.value = JSON.parse(quote.value)?.Body?.OTA_AirPriceRS?.PricedItineraries?.PricedItinerary?.AirItineraryPricingInfo?.PTC_FareBreakdowns?.PTC_FareBreakdown || [];
    passengerFares.value = Array.isArray(passengerFares.value) ? passengerFares.value : [passengerFares.value];
    if (passengerFares.value.length) {
        fetchAncillaries();
    }
});

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
    validatePriceWithBundle();
    // fetchAncillaries();

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

        isSubmitting.value = true;

    store.dispatch("flight/" + PATCH_ANCILLARIES, {
        quote: quote?.value,
        flight_provider: route.query.flight_provider,
        flight: flight.value,
        selectedFares: selectedFares.value,
        adults: route.query.adults,
        children: route.query.children,
        infants: route.query.infants,
    
        // sessionID: flight.value?.req_specific?.SetCookie,
        selectedExtras: selectedExtras.value,
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
    //patchAncillaryCharges();
    if (isSubmitting.value) return;
    
    if (!validateForm()) {
        globalError.value =
            "Please fix the errors in the form before submitting";
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
        // isConfirmDialogOpen.value = true;
        patchAncillaryCharges();
    } else if (type == 'card') {
        return;
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
    isLoading.value = true;
    if (!pnr) {
        error.value = "No PNR provided.";
        return;
    }
    store.dispatch("flight/" + CONFIRM_BOOKING, {
        pnr: route.query.pnr,
        bookingId: bookingDetails.value[0].id,
        booking_uuid: pnrData.value?.data?.uuid ?? "null",
        booking_status: "ticketed",
        booking_source: route.query.booking_source,
        amount: parseFloat(pnrData?.data?.billable_price || 0),
    });

    // Close dialog after successful cancellation
    isConfirmDialogOpen.value = false;
    fetchBookingDetails();
}

watch(ancillariesResponse, () => {
    if (ancillariesResponse.value) {
        console.log(totalAddOnesAmount.value);
        saveBooking();
    }
})

watch(
    ancillaries,
    () => {
        if (!ancillaries.value) return;
        autoSelectedFree.baggage = false;
        autoSelectedFree.meal = false;
        autoSelectedFree.seat = false;
        autoSelectFreeAncillaries();
    }
);

async function saveBooking(type) {
    try {
        if (!validateForm()) {
            globalError.value = "Please fix the errors in the form before submitting";
            window.scrollTo({ top: 0, behavior: "smooth" });
            return;
        }
        // ✅ Wait for ancillary charges to finish before continuing

            // await patchAncillaryCharges();
        

        isSubmitting.value = true;
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
            agency_email: user?.value?.email || "support@flyunique.pk",
            amount: amount.value,
            flight: flight.value,
            airportMargin: savedMarginBreakdown.airportMarginTotal,
            booking_status_setting: bookingStatusSetting?.value.bookingStatus,
            flight_source: route?.query.flight_source,
            flight_mode: "B2C",
            flight_id: route?.query.flight_id,
            flight_provider: route?.query.flight_provider,
            fare_reference: selectedFares.value,
            agent_markup: savedMarginBreakdown.customerMarkupTotal,
            agent_discount: savedMarginBreakdown.customerDiscountTotal,
            agent_margin: savedMarginBreakdown.otherChargesTotal,
            add_ones_amount: totalAddOnesAmount.value,
            ancillariesResponse: ancillariesResponse.value,
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

// Use reactive for better performance with nested objects
const seatSelections = reactive({})
const showExtras = ref(false);
function getMinPriceForColor(priceRange) {
        // Return example price ranges for legend
        const ranges = {
            standard: '$0-$20',
            premium: '$21-$40',
            business: '$41-$60',
            first: '$61+'
        }
        return ranges[priceRange] || ''
    }

    function getSeatColorClass(price) {
        // Define price thresholds (adjust based on your actual prices)
        const priceNum = Number(price) || 0
        
        if (priceNum <= 500) return 'bg-green-300 hover:bg-green-200 border-green-300'
        if (priceNum <= 1000) return 'bg-yellow-300 hover:bg-yellow-200 border-yellow-300'
        if (priceNum <= 1500) return 'bg-orange-300 hover:bg-orange-200 border-orange-300'
        return 'bg-blue-500 hover:bg-blue-200 border-blue-300'
    }
     function hasBaggageOptions() {
        // Check if any segment has baggage options
        return flight?.leg?.flights?.some(flight => 
            flight.segments?.some(segment => 
                getBaggageOptionsForSegment(segment.RPH)?.length > 0
            )
        )
    };
   function getSeatAtPosition(seats, column) {
        if (!seats || !Array.isArray(seats)) return null
        return seats.find(seat => seat.seatNumber === column)
    }
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
const getSeatRowsForSegment = (segment) => {
    const seatData = getSeatsForSegment(segment);
    return seatData?.rows || [];
};

// Get seat types (characteristics) for a segment
const getSeatTypesForSegment = (segment) => {
    const seatData = getSeatsForSegment(segment);
    if (!seatData) return [];
    
    const seatTypes = new Map();
    
    seatData.rows.forEach(row => {
        row.seats.forEach(seat => {
            if (seat.seatCharacteristics && !seatTypes.has(seat.seatCharacteristics)) {
                seatTypes.set(seat.seatCharacteristics, {
                    seatCharacteristics: seat.seatCharacteristics,
                    price: seat.seatPrice,
                    currencyCode: seat.currencyCode,
                    availability: seat.seatAvailability
                });
            }
        });
    });
    
    return Array.from(seatTypes.values());
};

// Check if seat is selected for specific traveler
const isSeatSelected = (flightIdx, segmentIdx, trvIndex, seat) => {
    const tempSelected = tempSelectedSeat?.value?.[flightIdx]?.[segmentIdx]?.[trvIndex];
        if (tempSelected && tempSelected.seatNumber === seat.seatNumber && tempSelected.row === seat.row) {
            return true
        }
    const selectedSeat = selectedExtras.value[flightIdx]?.seat?.[segmentIdx]?.[trvIndex];
    if (!selectedSeat) return false;
    
    return selectedSeat.refId === seat.refId;
};

const getSeatKey = (seat) => {
    if (!seat) return '';
    return seat.refId || seat.ref_id || seat.seatRef || `${seat.row}_${seat.seatNumber || seat.seat_no || ''}`;
};

const isSeatTaken = (flightIdx, segmentIdx, seatKey, excludeTrvIndex = null) => {
    if (!seatKey) return false;

    const selectedSeatMap = selectedExtras.value?.[flightIdx]?.seat?.[segmentIdx] || {};
    for (const [idx, seat] of Object.entries(selectedSeatMap)) {
        if (String(idx) === String(excludeTrvIndex)) continue;
        if (seat && getSeatKey(seat) === seatKey) return true;
    }

    const tempSeatMap = tempSelectedSeat.value?.[flightIdx]?.[segmentIdx] || {};
    for (const [idx, seat] of Object.entries(tempSeatMap)) {
        if (String(idx) === String(excludeTrvIndex)) continue;
        if (seat && getSeatKey(seat) === seatKey) return true;
    }

    return false;
};

// Get seat selected by specific traveler
const getSeatForTraveler = (flightIdx, segmentIdx, trvIndex) => {
    return selectedExtras.value[flightIdx]?.seat?.[segmentIdx]?.[trvIndex];
};

// Get all selected seats for a segment
const getAllSelectedSeatsForSegment = (flightIdx, segmentIdx) => {
    const seatSelections = selectedExtras.value[flightIdx]?.seat?.[segmentIdx];
    if (!seatSelections) return [];
    
    return Object.values(seatSelections);
};

// Get selected seat info for specific traveler
const getSelectedSeatInfo = (flightIdx, segmentIdx, trvIndex) => {
    const selectedSeat = selectedExtras.value[flightIdx]?.seat?.[segmentIdx]?.[trvIndex];
    if (!selectedSeat) return 'No seat selected';
    
    return `Row ${selectedSeat.row}, Seat ${selectedSeat.seatNumber}`;
};

// Handle temporary seat selection in dialog for specific traveler
const handleTempSeatSelection = (flightIdx, segmentIdx, trvIndex, seat) => {
    // Initialize structure if needed
    if (!tempSelectedSeat.value[flightIdx]) {
        tempSelectedSeat.value[flightIdx] = {};
    }
    if (!tempSelectedSeat.value[flightIdx][segmentIdx]) {
        tempSelectedSeat.value[flightIdx][segmentIdx] = {};
    }

    const seatKey = getSeatKey(seat);
    if (isSeatTaken(flightIdx, segmentIdx, seatKey, trvIndex)) {
        toast.error("This seat is already selected for another passenger. Please choose a different seat.");
        return;
    }
    
    // Store selection for specific traveler
    tempSelectedSeat.value[flightIdx][segmentIdx][trvIndex] = {
        ...seat,
        seatRef: `${seat.row}_${seat.seatNumber}`,
        travelerIndex: trvIndex
    };
};

// Get temporary seat selection for specific traveler
const getTempSeatForTraveler = (flightIdx, segmentIdx, trvIndex) => {
    return tempSelectedSeat.value[flightIdx]?.[segmentIdx]?.[trvIndex];
};

// Save seat selection for specific traveler
const saveSeatSelection = (flightIdx, segmentIdx, trvIndex) => {
    const tempSeat = tempSelectedSeat.value[flightIdx]?.[segmentIdx]?.[trvIndex];
    if (!tempSeat) return;

    const seatKey = getSeatKey(tempSeat);
    if (isSeatTaken(flightIdx, segmentIdx, seatKey, trvIndex)) {
        toast.error("This seat is already selected for another passenger. Please choose a different seat.");
        return;
    }
    
    // Initialize selectedExtras structure if needed
    if (!selectedExtras.value[flightIdx]) {
        selectedExtras.value[flightIdx] = { seat: {} };
    }
    if (!selectedExtras.value[flightIdx].seat) {
        selectedExtras.value[flightIdx].seat = {};
    }
    if (!selectedExtras.value[flightIdx].seat[segmentIdx]) {
        selectedExtras.value[flightIdx].seat[segmentIdx] = {};
    }
    
    // Save to selectedExtras for specific traveler
    selectedExtras.value[flightIdx].seat[segmentIdx][trvIndex] = {
        ...tempSeat,
        title: `Seat ${tempSeat.seatNumber}`,
        description: `Row ${tempSeat.row}, Seat ${tempSeat.seatNumber}`,
        amount: tempSeat.seatPrice,
        currency: tempSeat.currencyCode,
        type: 'seat',
        travelerIndex: trvIndex,
        segmentCode: getSeatsForSegment(flight.value?.leg?.flights?.[flightIdx]?.segments?.[segmentIdx])?.segmentInfo?.segmentCode
    };
    
    // Clear temporary selection for this traveler
    delete tempSelectedSeat.value[flightIdx][segmentIdx][trvIndex];
    
    // Clean up empty objects
    if (Object.keys(tempSelectedSeat.value[flightIdx][segmentIdx]).length === 0) {
        delete tempSelectedSeat.value[flightIdx][segmentIdx];
    }
    if (Object.keys(tempSelectedSeat.value[flightIdx]).length === 0) {
        delete tempSelectedSeat.value[flightIdx];
    }
};

// Save seat selection for all travelers at once
const saveAllSeatSelections = (flightIdx, segmentIdx) => {
    const tempSelections = tempSelectedSeat.value[flightIdx]?.[segmentIdx];
    if (!tempSelections) return;
    
    Object.keys(tempSelections).forEach(trvIndex => {
        saveSeatSelection(flightIdx, segmentIdx, parseInt(trvIndex));
    });
};

// Remove seat selection for specific traveler
const removeSeat = (flightIdx, segmentIdx, trvIndex, showPrompt = true) => {
    // Remove from selectedExtras
    if (selectedExtras.value[flightIdx]?.seat?.[segmentIdx]?.[trvIndex]) {
        delete selectedExtras.value[flightIdx].seat[segmentIdx][trvIndex];
        
        // Clean up empty objects
        if (Object.keys(selectedExtras.value[flightIdx].seat[segmentIdx]).length === 0) {
            delete selectedExtras.value[flightIdx].seat[segmentIdx];
        }
        if (selectedExtras.value[flightIdx].seat && Object.keys(selectedExtras.value[flightIdx].seat).length === 0) {
            delete selectedExtras.value[flightIdx].seat;
        }
        if (Object.keys(selectedExtras.value[flightIdx]).length === 0) {
            delete selectedExtras.value[flightIdx];
        }
    }
    
    // Remove from tempSelectedSeat
    if (tempSelectedSeat.value[flightIdx]?.[segmentIdx]?.[trvIndex]) {
        delete tempSelectedSeat.value[flightIdx][segmentIdx][trvIndex];
    }

    const segment = getSegmentByKey(flightIdx, segmentIdx);
    if (!segment || isInfantTraveler(trvIndex)) return;

    const seatData = getSeatsForSegment(segment);
    const hasFreeSeat = seatData?.rows?.some(row =>
        row.seats?.some(seat => seat.seatAvailability === "VAC" && isFreePrice(seat.seatPrice))
    );

    if (showPrompt && hasFreeSeat && !selectedExtras.value[flightIdx]?.seat?.[segmentIdx]?.[trvIndex]) {
        showFreeSelectionPrompt("seat");
    }
};

// Remove all seat selections for a segment
const removeAllSeatsForSegment = (flightIdx, segmentIdx) => {
    const travelerCount = getTravelerCount();
    
    for (let i = 0; i < travelerCount; i++) {
        removeSeat(flightIdx, segmentIdx, i, false);
    }
};

// Check if all travelers have selected seats for a segment
const areAllSeatsSelectedForSegment = (flightIdx, segmentIdx) => {
    const travelerCount = getTravelerCount();
    
    for (let i = 0; i < travelerCount; i++) {
        if (!selectedExtras.value[flightIdx]?.seat?.[segmentIdx]?.[i]) {
            return false;
        }
    }
    return true;
};

// Get total seat amount for a segment (sum of all travelers)
const getTotalSeatAmountForSegment = (flightIdx, segmentIdx) => {
    const seatSelections = selectedExtras.value[flightIdx]?.seat?.[segmentIdx];
    if (!seatSelections) return 0;
    
    return Object.values(seatSelections).reduce((total, seat) => {
        return total + (parseFloat(seat.amount) || 0);
    }, 0);
};

// Copy seat selection from one traveler to another
const copySeatToAllTravelers = (flightIdx, segmentIdx, sourceTrvIndex) => {
    const sourceSeat = selectedExtras.value[flightIdx]?.seat?.[segmentIdx]?.[sourceTrvIndex];
    if (!sourceSeat) return;

    toast.error("Each passenger must have a different seat. Please select seats individually.");
    return;
};

// Format seat type display
const formatSeatType = (seatCharacteristics) => {
    if (!seatCharacteristics) return 'Standard';
    
    const price = parseFloat(seatCharacteristics);
    if (!isNaN(price)) {
        if (price === 0) return 'Standard Seat';
        if (price > 0 && price < 2000) return 'Economy Plus';
        if (price >= 2000 && price < 5000) return 'Premium';
        if (price >= 5000) return 'Extra Legroom';
    }
    
    return 'Special Seat';
};
const calculateFlightTotal = (flightIdx) => {
    const flightExtras = selectedExtras.value[flightIdx];
    if (!flightExtras) return 0;
    
    let total = 0;
    
    ['baggage', 'seat', 'meal'].forEach(serviceType => {
        const serviceGroup = flightExtras[serviceType];
        if (!serviceGroup) return;
        
        Object.values(serviceGroup).forEach(segmentGroup => {
            if (!segmentGroup) return;
            
            Object.values(segmentGroup).forEach(item => {
                if (!item) return;
                
                const amount = parseFloat(item.amount) || 0;
                const quantity = parseInt(item.quantity) || 1;
                total += amount * quantity;
            });
        });
    });
    
    return total;
};

// Get traveler count (implement based on your data structure)
const getTravelerCount = () => {
    if (travellers.value && Array.isArray(travellers.value)) {
        return travellers.value.length;
    }
    return 1; // Default to 1 if not implemented
};

const isInfantTraveler = (travelerOrIndex) => {
    if (typeof travelerOrIndex === "number") {
        return travellers.value?.[travelerOrIndex]?.type === "INF";
    }

    return travelerOrIndex?.type === "INF";
};

const isFreePrice = (value) => {
    const price = parseFloat(value || 0);
    return !isNaN(price) && price === 0;
};

const getSegmentByKey = (flightIdx, segmentKey) => {
    const segments = flight.value?.leg?.flights?.[flightIdx]?.segments || [];
    return (
        segments.find(seg => seg?.RPH === segmentKey) ||
        segments.find(seg => String(seg?.RPH) === String(segmentKey)) ||
        segments[segmentKey] ||
        null
    );
};

const showFreeSelectionPrompt = (typeLabel) => {
    toast.info(`Please add at least one free ${typeLabel} option (PKR 0).`);
};

// Initialize structures for all travelers
const initializeStructures = () => {
    const travelerCount = getTravelerCount();
    
    flight.value?.leg?.flights?.forEach((flightItem, flightIdx) => {
        if (!tempSelectedSeat.value[flightIdx]) {
            tempSelectedSeat.value[flightIdx] = {};
        }
        
        flightItem.segments?.forEach((segment, segmentIdx) => {
            if (!tempSelectedSeat.value[flightIdx][segmentIdx]) {
                tempSelectedSeat.value[flightIdx][segmentIdx] = {};
            }
            
            // Initialize selectedExtras structure
            if (!selectedExtras.value[flightIdx]) {
                selectedExtras.value[flightIdx] = { seat: {} };
            }
            if (!selectedExtras.value[flightIdx].seat) {
                selectedExtras.value[flightIdx].seat = {};
            }
            if (!selectedExtras.value[flightIdx].seat[segmentIdx]) {
                selectedExtras.value[flightIdx].seat[segmentIdx] = {};
            }
            
            // Initialize for each traveler
            for (let i = 0; i < travelerCount; i++) {
                if (!selectedExtras.value[flightIdx].seat[segmentIdx][i]) {
                    selectedExtras.value[flightIdx].seat[segmentIdx][i] = null;
                }
            }
        });
    });
};


// Normalize meal data
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
            segmentInfo: {
                segmentCode: flightSegmentInfo.SegmentCode,
                flightNumber: flightSegmentInfo.FlightNumber,
                departure: flightSegmentInfo.DepartureDateTime,
                arrival: flightSegmentInfo.ArrivalDateTime,
                rph: flightSegmentInfo.RPH,
                from: flightSegmentInfo.SegmentCode?.split('/')[0],
                to: flightSegmentInfo.SegmentCode?.split('/')[1]
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


// Check if multiple meal selection is enabled
const isMultipleMealsEnabled = computed(() => {
    return ancillaries.value?.ancillaries?.meals
        ?.Body
        ?.AA_OTA_AirMealDetailsRS
        ?.MealDetailsResponses
        ?.multipleMealSelectionEnabled === "true";
});

// Get meals for a specific segment
const getMealsForSegment = (segment) => {
    if (!segment || !normalizedMeals.value.length) return [];
    
    const segmentCode = segment.segment || segment.code || '';
    const flightNumber = segment.flightNumber;
    
    const mealData = normalizedMeals.value.find(mealItem => 
        mealItem.segmentInfo.segmentCode === segmentCode ||
        mealItem.segmentInfo.flightNumber === flightNumber
    );
    
    return mealData?.meals || [];
};

// Get meal categories for a segment
const getMealCategoriesForSegment = (segment) => {
    const meals = getMealsForSegment(segment);
    if (!meals.length) return [];
    
    const categories = new Set();
    meals.forEach(meal => {
        if (meal.mealCategoryCode) {
            categories.add(meal.mealCategoryCode);
        }
    });
    
    return Array.from(categories).sort();
};

// Get meals by category for a segment
const getMealsByCategory = (segment, category) => {
    const meals = getMealsForSegment(segment);
    return meals.filter(meal => meal.mealCategoryCode === category);
};

// Check if meal is selected for specific traveler
const isMealSelectedForTraveler = (flightIdx, segmentIdx, trvIndex, mealCode) => {
    const selectedMeal = selectedExtras.value[flightIdx]?.meal?.[segmentIdx]?.[trvIndex];
    if (!selectedMeal) return false;
    
    return selectedMeal.mealCode === mealCode;
};

// Get selected meal for specific traveler
const getSelectedMealForTraveler = (flightIdx, segmentIdx, trvIndex) => {
    return selectedExtras.value[flightIdx]?.meal?.[segmentIdx]?.[trvIndex];
};

// Get all selected meals for a segment
const getAllSelectedMealsForSegment = (flightIdx, segmentIdx) => {
    const mealSelections = selectedExtras.value[flightIdx]?.meal?.[segmentIdx];
    if (!mealSelections) return [];
    
    return Object.values(mealSelections);
};

// Get total count of selected meals for a segment
const getTotalSelectedMealsCount = (flightIdx, segmentIdx) => {
    const mealSelections = selectedExtras.value[flightIdx]?.meal?.[segmentIdx];
    if (!mealSelections) return 0;
    
    return Object.keys(mealSelections).length;
};

// Get total meal amount for a segment
const getTotalMealAmountForSegment = (flightIdx, segmentIdx) => {
    const mealSelections = selectedExtras.value[flightIdx]?.meal?.[segmentIdx];
    if (!mealSelections) return 0;
    
    return Object.values(mealSelections).reduce((total, meal) => {
        return total + (parseFloat(meal.mealCharge) || 0);
    }, 0);
};

// Handle meal selection for specific traveler
const handleMealSelection = (flightIdx, segmentIdx, trvIndex, meal) => {
    // Initialize structure if needed
    if (!selectedExtras.value[flightIdx]) {
        selectedExtras.value[flightIdx] = { meal: {} };
    }
    if (!selectedExtras.value[flightIdx].meal) {
        selectedExtras.value[flightIdx].meal = {};
    }
    if (!selectedExtras.value[flightIdx].meal[segmentIdx]) {
        selectedExtras.value[flightIdx].meal[segmentIdx] = {};
    }
    
    // Store the selected meal for this traveler
    selectedExtras.value[flightIdx].meal[segmentIdx][trvIndex] = {
        ...meal,
        title: meal.mealName,
        amount: parseFloat(meal.mealCharge) || 0,
        currency: meal.currencyCode,
        description: meal.mealDescription,
        travelerIndex: trvIndex,
        quantity: 1, // Default quantity
        refId: `${meal.mealCode}_${trvIndex}`
    };
};

// Adjust meal quantity for traveler
const adjustMealQuantity = (flightIdx, segmentIdx, trvIndex, change) => {
    const selectedMeal = selectedExtras.value[flightIdx]?.meal?.[segmentIdx]?.[trvIndex];
    if (!selectedMeal) return;
    
    const newQuantity = Math.max(1, (selectedMeal.quantity || 1) + change);
    selectedMeal.quantity = newQuantity;
    
    // Update total amount based on quantity
    selectedMeal.amount = (parseFloat(selectedMeal.mealCharge) || 0);
};

// Get meal quantity for traveler
const getMealQuantity = (flightIdx, segmentIdx, trvIndex) => {
    const selectedMeal = selectedExtras.value[flightIdx]?.meal?.[segmentIdx]?.[trvIndex];
    return selectedMeal?.quantity || 1;
};

// Remove meal for specific traveler
const removeMeal = (flightIdx, segmentIdx, trvIndex) => {
    // Remove from selectedExtras
    if (selectedExtras.value[flightIdx]?.meal?.[segmentIdx]?.[trvIndex]) {
        delete selectedExtras.value[flightIdx].meal[segmentIdx][trvIndex];
        
        // Clean up empty objects
        if (Object.keys(selectedExtras.value[flightIdx].meal[segmentIdx]).length === 0) {
            delete selectedExtras.value[flightIdx].meal[segmentIdx];
        }
        if (selectedExtras.value[flightIdx].meal && Object.keys(selectedExtras.value[flightIdx].meal).length === 0) {
            delete selectedExtras.value[flightIdx].meal;
        }
        if (Object.keys(selectedExtras.value[flightIdx]).length === 0) {
            delete selectedExtras.value[flightIdx];
        }
    }
    
    // Also remove from extraCharges if exists
    if (extraCharges.value[flightIdx]?.meal?.[segmentIdx]?.[trvIndex]) {
        delete extraCharges.value[flightIdx].meal[segmentIdx][trvIndex];
    }

    const segment = getSegmentByKey(flightIdx, segmentIdx);
    if (!segment || isInfantTraveler(trvIndex)) return;

    const hasFreeMeal = getMealsForSegment(segment)?.some(meal => isFreePrice(meal.mealCharge));
    if (hasFreeMeal && !selectedExtras.value[flightIdx]?.meal?.[segmentIdx]?.[trvIndex]) {
        showFreeSelectionPrompt("meal");
    }
};

// Save meal selections (typically called from dialog)
const saveMealSelections = (flightIdx, segmentIdx) => {
    const travelerCount = getTravelerCount();
    
    // Update extraCharges for each traveler with a meal
    if (!extraCharges.value[flightIdx]) {
        extraCharges.value[flightIdx] = { meal: {} };
    }
    if (!extraCharges.value[flightIdx].meal) {
        extraCharges.value[flightIdx].meal = {};
    }
    
    for (let i = 0; i < travelerCount; i++) {
        const selectedMeal = selectedExtras.value[flightIdx]?.meal?.[segmentIdx]?.[i];
        if (selectedMeal) {
            if (!extraCharges.value[flightIdx].meal[segmentIdx]) {
                extraCharges.value[flightIdx].meal[segmentIdx] = {};
            }
            
            extraCharges.value[flightIdx].meal[segmentIdx][i] = {
                amount: selectedMeal.amount,
                currency: selectedMeal.currency,
                description: selectedMeal.mealName,
                travelerIndex: i
            };
        }
    }
};

// Copy meal selection from one traveler to another
const copyMealToAllTravelers = (flightIdx, segmentIdx, sourceTrvIndex) => {
    const sourceMeal = selectedExtras.value[flightIdx]?.meal?.[segmentIdx]?.[sourceTrvIndex];
    if (!sourceMeal) return;
    
    const travelerCount = getTravelerCount();
    
    for (let i = 0; i < travelerCount; i++) {
        if (i !== sourceTrvIndex) {
            // Skip if traveler already has a meal
            if (!selectedExtras.value[flightIdx]?.meal?.[segmentIdx]?.[i]) {
                selectedExtras.value[flightIdx].meal[segmentIdx][i] = { 
                    ...sourceMeal,
                    travelerIndex: i,
                    title: `${sourceMeal.mealName} (Traveler ${i + 1})`,
                    refId: `${sourceMeal.mealCode}_${i}`
                };
            }
        }
    }
};

// Format amount display

// Handle image error
const handleImageError = (event) => {
    event.target.src = '/placeholder-meal.jpg';
};



// Initialize meal selections structure
const initializeMealStructures = () => {
    const travelerCount = getTravelerCount();
    
    flight.value?.leg?.flights?.forEach((flightItem, flightIdx) => {
        if (!selectedExtras.value[flightIdx]) {
            selectedExtras.value[flightIdx] = { meal: {} };
        }
        
        flightItem.segments?.forEach((segment, segmentIdx) => {
            if (!selectedExtras.value[flightIdx].meal[segmentIdx]) {
                selectedExtras.value[flightIdx].meal[segmentIdx] = {};
            }
            
            // Initialize for each traveler
            for (let i = 0; i < travelerCount; i++) {
                if (!selectedExtras.value[flightIdx].meal[segmentIdx][i]) {
                    selectedExtras.value[flightIdx].meal[segmentIdx][i] = null;
                }
            }
        });
    });
};

// Initialize on component mount
onMounted(() => {
    initializeMealStructures();
});

// Initialize on component mount if needed
onMounted(() => {
    initializeStructures();
});
// Optionally initialize on component mount
// initializeTempSeatStructure();
const normalizedBaggages = computed(() => {
    const responses = ancillaries.value?.ancillaries?.baggage
        ?.Body
        ?.AA_OTA_AirBaggageDetailsRS
        ?.BaggageDetailsResponses
        ?.OnDBaggageDetailsResponse || [];

    // Convert to array if not already
    const baggageResponses = Array.isArray(responses) ? responses : responses ? [responses] : [];

    return responses.map((item, idx) => {
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

// Helper function to get baggage options for a specific segment by RPH
const getBaggageOptionsForSegment = (segmentRPH) => {
    if (!segmentRPH || !normalizedBaggages.value.length) return [];
    
    // Find which group contains this segment
    const baggageGroup = normalizedBaggages.value.find(group => 
        group.segments?.some(seg => seg['@attributes']?.RPH === segmentRPH)
    );
    
    return baggageGroup?.baggages || [];
};

// Helper function to get all baggage options for a flight
const getBaggageOptionsForFlight = (flightIndex) => {
    if (flightIndex < normalizedBaggages.value.length) {
        return normalizedBaggages.value[flightIndex]?.baggages || [];
    }
    return [];
};

// Check if a baggage option is selected for a segment AND traveler
const isBaggageSelected = (flightIdx, segmentIdx, trvIndex, baggageCode) => {
    return selectedExtras.value[flightIdx]?.baggage?.[segmentIdx]?.[trvIndex]?.baggageCode === baggageCode;
};

// Get selected baggage for a specific traveler
const getSelectedBaggageForTraveler = (flightIdx, segmentIdx, trvIndex) => {
    return selectedExtras.value[flightIdx]?.baggage?.[segmentIdx]?.[trvIndex];
};

// Handle baggage selection for specific traveler
const handleExtraBaggageChange = (flightIdx, segmentIdx, trvIndex, baggageOption) => {
    // Initialize nested objects if they don't exist
    if (!selectedExtras.value[flightIdx]) {
        selectedExtras.value[flightIdx] = { baggage: {} };
    }
    if (!selectedExtras.value[flightIdx].baggage) {
        selectedExtras.value[flightIdx].baggage = {};
    }
    if (!selectedExtras.value[flightIdx].baggage[segmentIdx]) {
        selectedExtras.value[flightIdx].baggage[segmentIdx] = {};
    }
    
    // Store the selected baggage for specific traveler
    selectedExtras.value[flightIdx].baggage[segmentIdx][trvIndex] = {
        ...baggageOption,
        title: baggageOption.baggageCode,
        amount: Number(baggageOption.baggageCharge).toFixed(2),
        currency: baggageOption.currencyCode,
        description: baggageOption.baggageDescription,
        refId: baggageOption.baggageCode,
        travelerIndex: trvIndex
    };
};

// Remove selected baggage for specific traveler
const removeExtraBaggage = (flightIdx, segmentIdx, trvIndex) => {
    if (selectedExtras.value[flightIdx]?.baggage?.[segmentIdx]?.[trvIndex]) {
        delete selectedExtras.value[flightIdx].baggage[segmentIdx][trvIndex];
        
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
    }

    if (isInfantTraveler(trvIndex)) return;
    const hasFreeBaggage = getBaggageOptionsForSegment(segmentIdx)?.some(option => isFreePrice(option.baggageCharge));
    if (hasFreeBaggage && !selectedExtras.value[flightIdx]?.baggage?.[segmentIdx]?.[trvIndex]) {
        showFreeSelectionPrompt("baggage");
    }
};

// Get all selected baggage for a segment
const getAllSelectedBaggageForSegment = (flightIdx, segmentIdx) => {
    const baggageSelections = selectedExtras.value[flightIdx]?.baggage?.[segmentIdx];
    if (!baggageSelections) return [];
    
    return Object.values(baggageSelections);
};

// Get total baggage amount for a segment
const getTotalBaggageAmountForSegment = (flightIdx, segmentIdx) => {
    const baggageSelections = selectedExtras.value[flightIdx]?.baggage?.[segmentIdx];
    if (!baggageSelections) return 0;
    
    return Object.values(baggageSelections).reduce((total, baggage) => {
        return total + (parseFloat(baggage.amount) || 0);
    }, 0);
};

const autoSelectFreeBaggage = () => {
    if (autoSelectedFree.baggage) return;
    if (!flight.value?.leg?.flights?.length) return;
    if (!ancillaries.value?.ancillaries?.baggage) return;

    const travelerCount = getTravelerCount();
    flight.value.leg.flights.forEach((flightItem, flightIdx) => {
        flightItem.segments?.forEach((segment, segmentIdx) => {
            const segmentKey = segment?.RPH ?? segmentIdx;
            const freeOption = getBaggageOptionsForSegment(segmentKey)
                ?.find(option => isFreePrice(option.baggageCharge));
            if (!freeOption) return;

            for (let i = 0; i < travelerCount; i++) {
                if (isInfantTraveler(i)) continue;
                if (!selectedExtras.value[flightIdx]?.baggage?.[segmentKey]?.[i]) {
                    handleExtraBaggageChange(flightIdx, segmentKey, i, freeOption);
                }
            }
        });
    });

    autoSelectedFree.baggage = true;
};

const autoSelectFreeMeals = () => {
    if (autoSelectedFree.meal) return;
    if (!flight.value?.leg?.flights?.length) return;
    if (!ancillaries.value?.ancillaries?.meals) return;

    const travelerCount = getTravelerCount();
    flight.value.leg.flights.forEach((flightItem, flightIdx) => {
        flightItem.segments?.forEach((segment, segmentIdx) => {
            const segmentKey = segment?.RPH ?? segmentIdx;
            const freeMeal = getMealsForSegment(segment)?.find(meal => isFreePrice(meal.mealCharge));
            if (!freeMeal) return;

            for (let i = 0; i < travelerCount; i++) {
                if (isInfantTraveler(i)) continue;
                if (!selectedExtras.value[flightIdx]?.meal?.[segmentKey]?.[i]) {
                    handleMealSelection(flightIdx, segmentKey, i, freeMeal);
                }
            }
        });
    });

    autoSelectedFree.meal = true;
};

const autoSelectFreeSeats = () => {
    if (autoSelectedFree.seat) return;
    if (!flight.value?.leg?.flights?.length) return;
    if (!ancillaries.value?.ancillaries?.seats) return;

    const travelerCount = getTravelerCount();
    flight.value.leg.flights.forEach((flightItem, flightIdx) => {
        flightItem.segments?.forEach((segment, segmentIdx) => {
            const segmentKey = segment?.RPH ?? segmentIdx;
            const seatData = getSeatsForSegment(segment);
            if (!seatData?.rows?.length) return;

            const freeSeats = seatData.rows
                .flatMap(row => row.seats || [])
                .filter(seat => seat.seatAvailability === "VAC" && isFreePrice(seat.seatPrice));
            if (!freeSeats.length) return;

            for (let i = 0; i < travelerCount; i++) {
                if (isInfantTraveler(i)) continue;
                if (!selectedExtras.value[flightIdx]?.seat?.[segmentKey]?.[i]) {
                    const availableSeat = freeSeats.find(seat => !isSeatTaken(flightIdx, segmentKey, getSeatKey(seat), i));
                    if (!availableSeat) continue;
                    if (!selectedExtras.value[flightIdx]) selectedExtras.value[flightIdx] = { seat: {} };
                    if (!selectedExtras.value[flightIdx].seat) selectedExtras.value[flightIdx].seat = {};
                    if (!selectedExtras.value[flightIdx].seat[segmentKey]) {
                        selectedExtras.value[flightIdx].seat[segmentKey] = {};
                    }

                    selectedExtras.value[flightIdx].seat[segmentKey][i] = {
                        ...availableSeat,
                        title: `Seat ${availableSeat.seatNumber}`,
                        description: `Row ${availableSeat.row}, Seat ${availableSeat.seatNumber}`,
                        amount: availableSeat.seatPrice,
                        currency: availableSeat.currencyCode,
                        type: "seat",
                        travelerIndex: i,
                        segmentCode: seatData?.segmentInfo?.segmentCode
                    };
                }
            }
        });
    });

    autoSelectedFree.seat = true;
};

const autoSelectFreeAncillaries = () => {
    autoSelectFreeBaggage();
    autoSelectFreeMeals();
    autoSelectFreeSeats();
};

watch(
    () => [ancillaries.value, flight.value, travellers.value?.length],
    () => {
        autoSelectFreeAncillaries();
    },
    { immediate: true }
);

// Initialize selectedExtras structure for all travelers
const initializeSelectedExtras = (travelerCount) => {
    // Initialize based on your flight segments structure
    flight.value?.leg?.flights?.forEach((flightItem, flightIdx) => {
        flightItem.segments?.forEach((segment, segmentIdx) => {
            if (!selectedExtras.value[flightIdx]) {
                selectedExtras.value[flightIdx] = { baggage: {} };
            }
            if (!selectedExtras.value[flightIdx].baggage[segmentIdx]) {
                selectedExtras.value[flightIdx].baggage[segmentIdx] = {};
            }
            
            // Initialize for each traveler
            for (let i = 0; i < travelerCount; i++) {
                if (!selectedExtras.value[flightIdx].baggage[segmentIdx][i]) {
                    selectedExtras.value[flightIdx].baggage[segmentIdx][i] = null;
                }
            }
        });
    });
};

// Call initialization if needed
// initializeSelectedExtras();
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
            calculateCustomerMarginAmount(
                fare.base_price,
                CustomerMargin?.value
            )
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

    const ItinTotalFare = JSON.parse(quote.value)?.Body?.OTA_AirPriceRS?.PricedItineraries?.PricedItinerary?.AirItineraryPricingInfo?.ItinTotalFare || {};
    const customerMargin = parseFloat(calculateCustomerMargin(
        parseFloat(ItinTotalFare.BaseFare?.['@attributes']?.Amount),
    ));
    const typeMargin = parseFloat(calculateTypeMargin(
        user.value,
        airportMargins.value,
    )) * passengerCount.value;
    const airlineMargin = calculateFareMargin(
        parseFloat(ItinTotalFare.BaseFare?.['@attributes']?.Amount),
        parseFloat(fareMarginAmount.value),
        fareMarginType.value,
        fareAmountType.value
    );
   const baseFare = parseFloat(ItinTotalFare?.TotalFare?.['@attributes']?.Amount) || 0;
const airline = parseFloat(airlineMargin) || 0;
const type = parseFloat(typeMargin) || 0;
const pax = parseInt(passengerCount.value) || 0;

const billable = baseFare + (airline * pax) + (type * pax);

    const total = parseFloat(billable) + parseFloat(customerMargin * passengerCount.value || 0);
    
    return total;
}

function calculateGrandTotal() {
    let total = 0;

    total = calculateTotalFare() + totalAddOnesAmount.value;
    if (total > 0) {
        total += parseFloat(CustomerMargin?.value?.other_charges || 0);
    }
    amount.value = total;
    return amount.value;
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
            await fetchCountry(passportData.value.nationality);
            await new Promise(resolve => setTimeout(resolve, 400)); // Wait 400ms for countries to update

            const traveller = travellers.value[travellerIndex.value];
            traveller.firstName = passportData.value.givenNames;
            traveller.lastName = passportData.value.surname;
            traveller.documentType = passportData.value.documentType === "P" ? "passport" : passportData.value.documentType === "v" ? "Visa" : "Unknown";
            traveller.documentNo = passportData.value.passportNumber;
            traveller.expiryDate = passportData.value.expiryDate;
            traveller.issueCountry = countries?.value[0]?.code || passportData.value.nationality;

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
// Get seats for a specific segment by segment code


// Get unique rows for a segment
const getUniqueRows = (segmentCode) => {
    const seatGroup = getSeatsForSegment(segmentCode);
    if (!seatGroup) return [];
    
    // Get unique row numbers
    const uniqueRows = [...new Set(seatGroup.seats.map(seat => seat.row))];
    return uniqueRows.sort((a, b) => parseInt(a) - parseInt(b));
};

// Get seat by row and column position
const getSeatByPosition = (segmentCode, row, colIndex) => {
    const seatGroup = getSeatsForSegment(segmentCode);
    if (!seatGroup) return null;
    
    // Map column index to seat letter
    const colMap = ['A', 'B', 'C', 'D', 'E', 'F'];
    const colLetter = colIndex < 3 ? colMap[colIndex] : colMap[colIndex - 1];
    
    return seatGroup.seats.find(seat => 
        seat.row === row && seat.col === colLetter
    );
};

// Get selected seat info


// Format amount

// Handle seat selection
const handleSeatChange = (flightIdx, segmentIdx, seatData, type) => {
    if (!selectedExtras.value[flightIdx]) {
        selectedExtras.value[flightIdx] = {};
    }
    if (!selectedExtras.value[flightIdx][type]) {
        selectedExtras.value[flightIdx][type] = {};
    }
    
    // Store the selected seat
    selectedExtras.value[flightIdx][type][segmentIdx] = {
        ref_id: seatData.ref_id,
        title: seatData.seat_no,
        description: seatData.type || 'Standard seat',
        amount: seatData.amount,
        currency: seatData.currency,
        rawSeat: seatData
    };
    
    // Also store in selectedSeat for UI state
    if (!selectedSeat.value[flightIdx]) {
        selectedSeat.value[flightIdx] = {};
    }
    selectedSeat.value[flightIdx][segmentIdx] = seatData.ref_id;
};
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
onMounted(() => {
        authStore.fetchUser();
    selectedFares.value = route.query.fares ? JSON.parse(route.query.fares) : []
    passengerCount.value = route.query.passenger_count ? parseInt(route.query.passenger_count) : 1
    window.scrollTo({ top: 0, behavior: "smooth" });
    startCountdown(13 * 60 * 1000); // 13 minutes countdown

    initializeStripe();
    fetchMargins();
    fetchBookingStatus();
    fetchFlight();
    fetchCustomerMarginValues();
    fetchCustomerSettings();
    fetchAgentLedger();
    initializeSelectedExtras();
});

watch(flight, () => {
    initializeTravellers();
    // sendSooperQoute();
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
                                                        country.value === mainContact.country)?.label || mainContact.country || "Select Country" :
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
                                                            <Calender v-model="traveller.dob" type="date"
                                                                :maxValue="todayDate.toISOString().split('T')[0]"
                                                                :id="`date-of-birth-${trvIndex}`"
                                                                placeholder="Date Of Birth"
                                                                :class="{ 'is-invalid': getErrorPath(`travellers.${trvIndex}.dob`) }"
                                                                class="w-full h-8 text-sm bg-white text-black border-gray-200 focus:border-primary focus:ring-primary/20" />
                                                            <div v-if="getErrorPath(`travellers.${trvIndex}.dob`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${trvIndex}.dob`) }}</div>
                                                        </div>
                                                        <div class="mb-3">
                                                            <Label :for="`nationality-${trvIndex}`"
                                                                class="block text-sm font-medium text-gray-700 mb-1">
                                                                Nationality<span class="required">*</span>
                                                            </Label>

                                                            <CountryDropdown :keyValue="'code'"
                                                                placeholder="SELECT NATIONALITY"
                                                                v-model="traveller.nationality"
                                                                @update:modelValue="(value) => traveller.nationality = value" />
                                                            <div v-if="getErrorPath(`travellers.${trvIndex}.nationality`)"
                                                                class="error-message">
                                                                {{ getErrorPath(`travellers.${trvIndex}.nationality`) }}
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
                                                                {{ getErrorPath(`travellers.${trvIndex}.documentType`) }}
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
                                                                    getErrorPath(`travellers.${trvIndex}.documentNo`) }}</div>
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
                                                            <Calender v-model="traveller.expiryDate"
                                                                :id="`expiry-date-${trvIndex}`"
                                                                :class="{ 'is-invalid': getErrorPath(`travellers.${trvIndex}.expiryDate`) }"
                                                                type="date"
                                                                class="w-full h-10 text-sm bg-white border-gray-200 focus:border-primary focus:ring-primary/20" />
                                                            <div v-if="getErrorPath(`travellers.${trvIndex}.expiryDate`)"
                                                                class="text-red-500 text-xs mt-1">{{
                                                                    getErrorPath(`travellers.${trvIndex}.expiryDate`) }}</div>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Add Extra Services Button -->
<div class="pt-4 border-t border-gray-200">
    <button @click="showExtras = !showExtras" 
            class="w-full flex items-center justify-between p-3 bg-gray-50 hover:bg-gray-100  transition-colors">
        <div class="flex items-center gap-2">
            <Package class="w-5 h-5 text-primary" />
            <span class="text-sm font-medium text-gray-900">Add extra services</span>
        </div>
        <svg class="w-4 h-4 text-gray-500 transition-transform" 
             :class="{ 'rotate-180': showExtras }" 
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <!-- Extra Services Section (shown when showExtras is true) -->
   <div v-show="showExtras" class="mt-4">
    <Tabs defaultValue="baggage" class="w-full ">
        <TabsList class="flex justify-start items-end gap-2 bg-transparent p-0 w-full">
            <TabsTrigger value="baggage" class=" group relative flex-shrink-0 text-sm sm:text-base font-medium
             px-5 py-2.5
             rounded-none
             border-b-2 border-transparent
             data-[state=active]:border-primary
             data-[state=active]:bg-white
             data-[state=active]:text-primary
             data-[state=inactive]:text-gray-600
             hover:text-primary transition">
                Baggage
                
            </TabsTrigger>
            <TabsTrigger value="seats" class="text-sm group relative flex-shrink-0 text-sm sm:text-base font-medium
             px-5 py-2.5
             rounded-none
             border-b-2 border-transparent
             data-[state=active]:border-primary
             data-[state=active]:bg-white
             data-[state=active]:text-primary
             data-[state=inactive]:text-gray-600
             hover:text-primary transition">
                Seats
                
            </TabsTrigger>
            <TabsTrigger value="meals" class="text-sm group relative flex-shrink-0 text-sm sm:text-base font-medium
             px-5 py-2.5
             rounded-none
             border-b-2 border-transparent
             data-[state=active]:border-primary
             data-[state=active]:bg-white
             data-[state=active]:text-primary
             data-[state=inactive]:text-gray-600
             hover:text-primary transition">
                Meals
               
            </TabsTrigger>
        </TabsList>

        <!-- Baggage Tab -->
        <TabsContent value="baggage" class="mt-0">
            <div v-if="hasBaggageOptions" class="border border-gray-200 rounded overflow-hidden">
                <div class="bg-gray-50 p-3 border-b border-gray-200">
                    <h4 class="text-sm font-medium text-gray-900">About your baggage</h4>
                    <p class="text-xs text-gray-500 mt-1">
                        Need additional baggage? Save time and money by purchasing extra baggage in advance
                    </p>
                </div>

                <div class="p-4 space-y-4">
                    <div v-for="(flight, flightIdx) in flight?.leg?.flights" :key="flightIdx" 
                         class="border border-gray-200 rounded-lg p-4 bg-white">
                        <h5 class="text-sm font-medium text-gray-900 mb-3">
                            {{ flight?.from?.city?.name }} to {{ flight?.to?.city?.name }}
                        </h5>

                        <div v-for="(segment, segmentIdx) in flight?.segments" :key="segmentIdx" class="mb-4 last:mb-0">
                            <div class="text-xs font-medium text-gray-700 mb-3">
                                {{ segment?.from?.name }} → {{ segment?.to?.name }}
                            </div>

                            <p v-if="isInfantTraveler(trvIndex)" class="text-xs text-gray-500">
                                Baggage selection is not available for infant passengers.
                            </p>

                            <template v-if="!isInfantTraveler(trvIndex)">
                            <!-- Show included baggage -->
                            <div class="mb-4 p-3 bg-green-50 rounded border border-green-100">
                                <p class="text-xs font-medium text-green-800 mb-2">✓ Included Baggage</p>
                                <div v-for="fare in flight?.fares" :key="fare.ref_id">
                                    <div v-if="fare?.ref_id === route?.query?.fare_id || fare?.ref_id === 2" 
                                         class="space-y-2">
                                        <!-- Carry baggage -->
                                        <div v-if="fare.baggage_policies?.some(p => p.type === 'carry')" 
                                             class="flex items-center gap-2 text-xs">
                                            <CheckCircle class="w-3 h-3 text-green-600 flex-shrink-0" />
                                            <span class="text-gray-700">
                                                {{ fare.baggage_policies.find(p => p.type === 'carry').weight_limit }} 
                                                {{ fare.baggage_policies.find(p => p.type === 'carry').weight_unit }} cabin baggage
                                                ({{ fare.baggage_policies.find(p => p.type === 'carry').pieces }} piece)
                                            </span>
                                        </div>
                                        <!-- Checked baggage -->
                                        <div v-if="fare.baggage_policies?.some(p => p.type === 'checked')" 
                                             class="flex items-center gap-2 text-xs">
                                            <CheckCircle class="w-3 h-3 text-green-600 flex-shrink-0" />
                                            <span class="text-gray-700">
                                                {{ fare.baggage_policies.find(p => p.type === 'checked').weight_limit }} 
                                                {{ fare.baggage_policies.find(p => p.type === 'checked').weight_unit }} checked baggage
                                                ({{ fare.baggage_policies.find(p => p.type === 'checked').pieces }} piece)
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Extra baggage options - LIST VIEW -->
                            <div v-if="getBaggageOptionsForSegment(segment.RPH).length > 0">
                                <p class="text-xs font-medium text-gray-700 mb-3">Extra Baggage Options</p>
                                <div class="space-y-2">
                                    <div v-for="(option, optionIdx) in getBaggageOptionsForSegment(segment.RPH)"
                                         :key="optionIdx"
                                         @click="handleExtraBaggageChange(flightIdx, segment.RPH, trvIndex, option)"
                                         class="flex items-center gap-3 p-3 border rounded cursor-pointer transition-all bg-white"
                                         :class="{
                                             'border-primary bg-primary/5': isBaggageSelected(flightIdx, segment.RPH, trvIndex, option.baggageCode),
                                             'border-gray-200 hover:bg-gray-50': !isBaggageSelected(flightIdx, segment.RPH, trvIndex, option.baggageCode)
                                         }">
                                        
                                        <!-- Radio indicator (visual only) -->
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center flex-shrink-0"
                                             :class="isBaggageSelected(flightIdx, segment.RPH, trvIndex, option.baggageCode) 
                                                 ? 'border-primary bg-primary' 
                                                 : 'border-gray-300'">
                                            <div v-if="isBaggageSelected(flightIdx, segment.RPH, trvIndex, option.baggageCode)" 
                                                 class="w-2 h-2 rounded-full bg-white"></div>
                                        </div>
                                        
                                        <!-- Baggage image -->
                                        <div class="w-16 h-16 rounded bg-gray-100 flex-shrink-0 overflow-hidden border">
                                            <img src="/public/assets/baggage.jpg" 
                                                 alt="Baggage" 
                                                 class="w-full h-full object-contain" />
                                        </div>
                                        
                                        <!-- Baggage details -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="text-sm font-medium text-gray-900">
                                                    {{ option.baggageCode }}
                                                </span>
                                                <span class="text-xs text-gray-500">
                                                    {{ option.weight }}kg
                                                </span>
                                            </div>
                                            <p class="text-sm text-gray-500 line-clamp-2">
                                                {{ option.baggageDescription }}
                                            </p>
                                        </div>
                                        
                                        <!-- Price and action -->
                                        <div class="flex flex-col items-end gap-2 flex-shrink-0 min-w-[100px]">
                                            <span class="text-base font-bold text-primary whitespace-nowrap">
                                                {{ option.currencyCode }} {{ Number(option.baggageCharge).toFixed(2) }}
                                            </span>
                                            
                                            <!-- Show selected indicator or add button -->
                                            <div v-if="isBaggageSelected(flightIdx, segment.RPH, trvIndex, option.baggageCode)"
                                                 class="text-xs text-primary font-medium">
                                                Selected
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Selected baggage summary -->
                            <div v-if="getSelectedBaggageForTraveler(flightIdx, segment.RPH, trvIndex)" 
                                 class="mt-4 p-3 bg-primary/10 rounded border border-primary">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded bg-white border overflow-hidden flex-shrink-0">
                                            <img src="/public/assets/baggage.jpg" 
                                                 alt="Selected baggage" 
                                                 class="w-full h-full object-contain" />
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2">
                                                <CheckCircle class="w-4 h-4 text-blue-600" />
                                                <span class="text-sm font-medium text-gray-900">
                                                    {{ getSelectedBaggageForTraveler(flightIdx, segment.RPH, trvIndex).title || 'Extra Baggage' }}
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-600 mt-1">
                                                {{ getSelectedBaggageForTraveler(flightIdx, segment.RPH, trvIndex).description || 'Extra baggage allowance' }}
                                            </p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Traveler {{ trvIndex + 1 }} • {{ flight?.from?.city?.name }} → {{ flight?.to?.city?.name }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-base font-bold text-primary">
                                            {{ selectedExtras[flightIdx]?.baggage?.[segment.RPH]?.[trvIndex]?.currency }}
                                            {{ selectedExtras[flightIdx]?.baggage?.[segment.RPH]?.[trvIndex]?.amount }}
                                        </span>
                                        <button @click="removeExtraBaggage(flightIdx, segment.RPH, trvIndex)"
                                                class="p-1.5 text-gray-400 hover:text-red-500 transition-colors rounded-full hover:bg-red-50"
                                                title="Remove baggage">
                                            <X class="w-4 h-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-8 text-gray-500 border border-gray-200 rounded">
                No baggage options available
            </div>
        </TabsContent>

        <!-- Seats Tab -->
        <TabsContent value="seats" class="mt-0">
            <div v-if="ancillaries?.ancillaries?.seats" class="border border-gray-200 rounded overflow-hidden">
                <div class="bg-gray-50 p-3 border-b border-gray-200">
                    <h4 class="text-sm font-medium text-gray-900">Want your own seat?</h4>
                    <p class="text-xs text-gray-500 mt-1">
                        Customize your trip with optional extras. Select the services you want now.
                    </p>
                </div>
                
                <div class="p-4 space-y-4">
                    <div v-for="(flight, flightIdx) in flight?.leg?.flights" :key="flightIdx" 
                         class="border border-gray-200 rounded p-4 bg-white">
                        <h5 class="text-sm font-medium text-gray-900 mb-3">
                            {{ flight?.from?.city?.name }} to {{ flight?.to?.city?.name }}
                        </h5>

                        <div v-for="(segment, segmentIdx) in flight?.segments" :key="segmentIdx" class="mb-6 last:mb-0">
                            <div class="text-xs font-medium text-gray-700 mb-4">
                                Segment: {{ segment?.from?.name }} → {{ segment?.to?.name }}
                            </div>

                            <p v-if="isInfantTraveler(trvIndex)" class="text-xs text-gray-500">
                                Seat selection is not available for infant passengers.
                            </p>

                            <template v-if="getSeatsForSegment(segment)">
                                <template v-if="!isInfantTraveler(trvIndex)">
                                <!-- Selected seat preview (from saved selection) -->
                                <div v-if="selectedExtras[flightIdx]?.seat?.[segment.RPH]?.[trvIndex]" 
                                     class="mb-4 p-3 bg-blue-50 rounded-lg border border-blue-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <CheckCircle class="w-3 h-3 text-blue-600" />
                                            <span class="text-xs font-medium text-gray-900">
                                                Saved Seat: {{ getSelectedSeatInfo(flightIdx, segment.RPH, trvIndex) }}
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                ({{ selectedExtras[flightIdx]?.seat?.[segment.RPH]?.[trvIndex]?.currency }}
                                                {{ selectedExtras[flightIdx]?.seat?.[segment.RPH]?.[trvIndex]?.amount }})
                                            </span>
                                        </div>
                                        <button class="px-2 py-1 text-xs rounded bg-red-100 text-red-600 hover:bg-red-200"
                                                @click="removeSeat(flightIdx, segment.RPH, trvIndex)">
                                            Remove
                                        </button>
                                    </div>
                                </div>

                                <!-- Temporary seat selection preview (before saving) -->
                                <div v-if="tempSelectedSeat[flightIdx]?.[segment.RPH]?.[trvIndex] && !selectedExtras[flightIdx]?.seat?.[segment.RPH]?.[trvIndex]" 
                                     class="mb-4 p-3 bg-purple-50 rounded-lg border border-purple-200">
                                    <div class="flex items-center gap-2">
                                        <Info class="w-3 h-3 text-purple-600" />
                                        <span class="text-xs font-medium text-gray-900">
                                            Row {{ tempSelectedSeat[flightIdx][segment.RPH][trvIndex].row }}, 
                                            Seat {{ tempSelectedSeat[flightIdx][segment.RPH][trvIndex].seatNumber }}
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            ({{ formatAmount(tempSelectedSeat[flightIdx][segment.RPH][trvIndex].seatPrice) }})
                                        </span>
                                        <span class="text-xs text-purple-600 ml-2">
                                            Click "Save Seat Selection" to confirm
                                        </span>
                                    </div>
                                </div>

                                <!-- Seat Map with Horizontal Grid -->
                                <div v-if="getSeatRowsForSegment(segment).length > 0" class="mb-4">
                                    <p class="text-xs font-medium text-gray-700 mb-3">Select Your Seat</p>
                                    
                                    <!-- Color Legend with PKR ranges -->
                                    <div class="flex flex-wrap gap-4 mb-4 text-xs">
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 bg-green-300 border border-green-600 rounded"></div>
                                            <span> (≤ PKR 500)</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 bg-yellow-300 border border-yellow-600 rounded"></div>
                                            <span> (PKR 501 - 1000)</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 bg-orange-300 border border-orange-600 rounded"></div>
                                            <span> (PKR 1001 - 1500)</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 bg-blue-500 border border-blue-700 rounded"></div>
                                            <span>F (> PKR 1500)</span>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <div class="w-4 h-4 bg-gray-300 border border-gray-500 rounded"></div>
                                            <span>Reserved</span>
                                        </div>
                                    </div>

                                    <!-- Seat Map Grid -->
                                    <div class="overflow-x-auto">
                                        <div class="min-w-max p-4 bg-gray-50 rounded-lg">
                                            <template v-if="getSeatRowsForSegment(segment).length > 0">
                                                <div class="inline-block">
                                                    <!-- Header with row numbers -->
                                                    <div class="flex gap-2">
                                                        <div class="w-16 h-8 flex items-end justify-center pb-1">
                                                            <span class="text-xs font-medium text-gray-400">Row →</span>
                                                        </div>
                                                        <div v-for="row in getSeatRowsForSegment(segment)" 
                                                             :key="'header-'+row.rowNumber"
                                                             class="w-10 text-center">
                                                            <div class="h-8 flex items-end justify-center pb-1">
                                                                <span class="text-xs font-medium text-gray-700 bg-gray-200 px-2 py-1 rounded">{{ row.rowNumber }}</span>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Seat letters rows -->
                                                    <div v-for="col in ['A', 'B', 'C', 'D', 'E', 'F']" 
                                                         :key="'col-'+col"
                                                         class="flex items-center">
                                                        <div class="w-16 h-10 flex items-center">
                                                            <span class="text-xs font-medium text-gray-700 bg-gray-100 px-2 py-1 rounded">{{ col }}</span>
                                                        </div>
                                                        
                                                        <div class="flex gap-2">
                                                            <div v-for="row in getSeatRowsForSegment(segment)" 
                                                                 :key="'seat-'+row.rowNumber+'-'+col"
                                                                 class="relative group">
                                                                <template v-if="getSeatAtPosition(row.seats, col)">
                                                                    <div v-if="getSeatAtPosition(row.seats, col).seatAvailability === 'VAC'"
                                                                         @click="handleTempSeatSelection(flightIdx, segment.RPH, trvIndex, getSeatAtPosition(row.seats, col))"
                                                                         class="w-10 h-10 my-1 rounded cursor-pointer transition-all hover:scale-110 flex items-center justify-center text-xs font-medium"
                                                                         :class="getSeatColorClass(getSeatAtPosition(row.seats, col).seatPrice)"
                                                                         :style="{
                                                                             border: isSeatSelected(flightIdx, segment.RPH, trvIndex, getSeatAtPosition(row.seats, col)) 
                                                                                 ? '3px solid #3b82f6' 
                                                                                 : getSeatAtPosition(row.seats, col).seatPrice > 1500 ? '2px solid #1e40af' : '1px solid rgba(0,0,0,0.2)',
                                                                             boxShadow: getSeatAtPosition(row.seats, col).seatPrice > 1500 ? '0 2px 8px rgba(0,0,0,0.3)' : 'none'
                                                                         }">
                                                                        {{ col }}{{ row.rowNumber }}
                                                                        
                                                                        <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50 pointer-events-none shadow-lg">
                                                                            <div class="font-bold">Seat {{ col }}{{ row.rowNumber }}</div>
                                                                            <div>{{ getSeatAtPosition(row.seats, col).seatCharacteristics || 'Standard' }}</div>
                                                                            <div class="font-bold text-yellow-300">
                                                                                {{ getSeatAtPosition(row.seats, col).currencyCode || 'PKR' }} 
                                                                                {{ formatAmount(getSeatAtPosition(row.seats, col).seatPrice) }}
                                                                            </div>
                                                                        </div>
                                                                    </div>

                                                                    <div v-else-if="getSeatAtPosition(row.seats, col).seatAvailability === 'RES'"
                                                                         class="w-10 h-10 bg-gray-300 border border-gray-500 rounded flex items-center justify-center text-xs text-gray-700 cursor-not-allowed"
                                                                         title="Reserved">
                                                                        {{ col }}{{ row.rowNumber }}
                                                                    </div>

                                                                    <div v-else
                                                                         class="w-10 h-10 bg-gray-100 border border-gray-300 rounded flex items-center justify-center text-xs text-gray-400">
                                                                        X
                                                                    </div>
                                                                </template>
                                                                <div v-else class="w-10 h-10"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Save Seat Button -->
                                    <div class="mt-4 flex justify-end">
                                        <button v-if="tempSelectedSeat[flightIdx]?.[segment.RPH]?.[trvIndex]"
                                                @click="saveSeatSelection(flightIdx, segment.RPH, trvIndex)"
                                                class="bg-primary text-white px-4 py-2 text-sm rounded hover:bg-primary/90">
                                            Save Seat Selection
                                        </button>
                                    </div>
                                </div>
                                </template>
                            </template>
                            <p v-else-if="!isInfantTraveler(trvIndex)" class="text-xs text-gray-500">No seat selection available for this segment.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-8 text-gray-500 border border-gray-200 rounded">
                No seat options available
            </div>
        </TabsContent>

        <!-- Meals Tab -->
        <TabsContent value="meals" class="mt-0">
            <div v-if="ancillaries?.ancillaries?.meals" class="border border-gray-200 rounded overflow-hidden">
                <div class="bg-gray-50 p-2 border-b border-gray-200">
                    <h4 class="text-sm font-medium text-gray-900">Add Meals</h4>
                    <p class="text-xs text-gray-500">
                        Pre-book meals and enjoy discounted prices onboard.
                    </p>
                </div>

                <div class="p-3 space-y-3">
                    <div v-for="(flightItem, flightIdx) in flight?.leg?.flights" :key="flightIdx" 
                         class="border border-gray-200 rounded p-3 bg-white">
                        <h5 class="text-sm font-medium text-gray-900 mb-2">
                            {{ flightItem?.from?.city?.name }} to {{ flightItem?.to?.city?.name }}
                        </h5>

                        <div v-for="(segment, segmentIdx) in flightItem?.segments" :key="segmentIdx" class="mb-3 last:mb-0">
                            <div class="text-xs text-gray-500 mb-2">
                                {{ segment?.from?.name }} → {{ segment?.to?.name }}
                            </div>

                            <p v-if="isInfantTraveler(trvIndex)" class="text-xs text-gray-500 py-1">
                                Meal selection is not available for infant passengers.
                            </p>

                            <template v-if="!isInfantTraveler(trvIndex)">
                            <!-- Selected meal preview for each traveler -->
                            <div v-for="tIndex in getTravelerCount()" :key="tIndex" class="mb-2">
                                <div v-if="getSelectedMealForTraveler(flightIdx, segment.RPH, tIndex)" 
                                     class="p-2 bg-primary/5 rounded border border-primary/20">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <CheckCircle class="w-4 h-4 text-primary flex-shrink-0" />
                                            <div class="text-sm">
                                                <span class="font-medium text-gray-900">T{{ tIndex + 1 }}:</span>
                                                <span class="text-gray-700 ml-1">{{ getSelectedMealForTraveler(flightIdx, segment.RPH, tIndex)?.mealName }}</span>
                                                <span v-if="getSelectedMealForTraveler(flightIdx, segment.RPH, tIndex)?.quantity > 1" 
                                                      class="text-gray-500 ml-1">
                                                    (Qty: {{ getSelectedMealForTraveler(flightIdx, segment.RPH, tIndex)?.quantity }})
                                                </span>
                                                <span class="text-gray-500 ml-1">
                                                    {{ formatAmount(
                                                        (getSelectedMealForTraveler(flightIdx, segment.RPH, tIndex)?.mealCharge || 0) * 
                                                        (getSelectedMealForTraveler(flightIdx, segment.RPH, tIndex)?.quantity || 1)
                                                    ) }}
                                                </span>
                                            </div>
                                        </div>
                                        <button class="px-2 py-1 text-xs rounded bg-red-100 text-red-600 hover:bg-red-200"
                                                @click="removeMeal(flightIdx, segment.RPH, tIndex)">
                                            Remove
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Meal selection options -->
                            <div v-if="getMealsForSegment(segment)?.length > 0">
                                <!-- Traveler tabs -->
                                <div v-if="getTravelerCount() > 1" class="flex gap-2 mb-3">
                                    <button v-for="tIndex in getTravelerCount()" 
                                            :key="tIndex"
                                            @click="trvIndex = tIndex"
                                            class="px-3 py-1.5 text-sm rounded-full transition-colors"
                                            :class="trvIndex === tIndex 
                                                ? 'bg-primary text-white' 
                                                : 'bg-gray-100 text-gray-700 hover:bg-gray-200'">
                                        Traveler {{ tIndex + 1 }}
                                    </button>
                                </div>

                                <!-- Meals by category - LIST VIEW -->
                                <div v-for="category in getMealCategoriesForSegment(segment)" 
                                     :key="category"
                                     class="mb-3 last:mb-0">
                                    <h5 class="text-sm font-medium text-gray-700 mb-2">{{ category }}</h5>
                                    
                                    <div class="space-y-2">
                                        <div v-for="meal in getMealsByCategory(segment, category)" 
                                             :key="meal.mealCode"
                                             class="flex items-start gap-3 p-2 border rounded transition-all bg-white"
                                             :class="{
                                                 'border-primary bg-primary/5': isMealSelectedForTraveler(flightIdx, segment.RPH, trvIndex, meal.mealCode),
                                                 'border-gray-200 hover:bg-gray-50 cursor-pointer': !isMealSelectedForTraveler(flightIdx, segment.RPH, trvIndex, meal.mealCode)
                                             }">
                                            
                                            <div class="w-12 h-12 rounded bg-gray-100 flex-shrink-0 overflow-hidden"
                                                 @click="!isMealSelectedForTraveler(flightIdx, segment.RPH, trvIndex, meal.mealCode) && handleMealSelection(flightIdx, segment.RPH, trvIndex, meal)">
                                                <img :src="meal.mealImageLink || '/placeholder-meal.jpg'" 
                                                     :alt="meal.mealName"
                                                     class="w-full h-full object-cover"
                                                     @error="handleImageError" />
                                            </div>
                                            
                                            <div class="flex-1 min-w-0"
                                                 @click="!isMealSelectedForTraveler(flightIdx, segment.RPH, trvIndex, meal.mealCode) && handleMealSelection(flightIdx, segment.RPH, trvIndex, meal)">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="text-sm font-medium text-gray-900">
                                                        {{ meal.mealName }}
                                                    </span>
                                                    <span class="text-xs text-gray-400 whitespace-nowrap">
                                                        ({{ meal.availableMeals }} left)
                                                    </span>
                                                    <span v-if="meal.defaultMeal === 'Y'" 
                                                          class="bg-yellow-100 text-yellow-800 text-xs px-1.5 rounded whitespace-nowrap">
                                                        Default
                                                    </span>
                                                </div>
                                                <p class="text-sm text-gray-500 line-clamp-2">
                                                    {{ meal.mealDescription }}
                                                </p>
                                            </div>
                                            
                                            <div class="flex flex-col items-end gap-2 flex-shrink-0 min-w-[140px]">
                                                <span class="text-base font-bold text-primary whitespace-nowrap">
                                                    {{ formatAmount(meal.mealCharge) }}
                                                </span>
                                                
                                                <button v-if="!isMealSelectedForTraveler(flightIdx, segment.RPH, trvIndex, meal.mealCode)"
                                                        @click="handleMealSelection(flightIdx, segment.RPH, trvIndex, meal)"
                                                        class="bg-primary text-white px-4 py-1.5 text-sm rounded hover:bg-primary/90 w-1/2">
                                                    Add
                                                </button>
                                                
                                                <div v-else class="flex items-center gap-1 w-full justify-end" @click.stop>
                                                    <span class="text-sm text-gray-500">Qty:</span>
                                                    <div class="flex items-center border rounded">
                                                        <button type="button"
                                                                class="w-7 h-7 flex items-center justify-center hover:bg-gray-100 text-sm"
                                                                @click.stop="adjustMealQuantity(flightIdx, segment.RPH, trvIndex, -1)">
                                                            −
                                                        </button>
                                                        <span class="px-2 text-sm min-w-[28px] text-center">
                                                            {{ getMealQuantity(flightIdx, segment.RPH, trvIndex) }}
                                                        </span>
                                                        <button type="button"
                                                                class="w-7 h-7 flex items-center justify-center hover:bg-gray-100 text-sm"
                                                                @click.stop="adjustMealQuantity(flightIdx, segment.RPH, trvIndex, 1)">
                                                            +
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="text-sm text-gray-400 py-1">
                                No meals available
                            </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else class="text-center py-8 text-gray-500 border border-gray-200 rounded">
                No meal options available
            </div>
        </TabsContent>
    </Tabs>
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
                                    
                                </div>
                                <div v-for="(passengerFareItem, index) in passengerFares" :key="index">
    <div class="mb-4">
        <Accordion type="multiple" collapsible>
            <AccordionItem :value="`passenger-fare-${index}`" class="overflow-hidden">
                
                <!-- HEADER -->
                <AccordionTrigger
                    class="px-2 py-2 border-b grid grid-cols-[1fr_auto_auto] items-center hover:no-underline gap-1">

                    <!-- LEFT - Passenger Type & Quantity -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs sm:text-sm font-bold text-gray-600">
                            {{ passengerFareItem?.PassengerTypeQuantity?.['@attributes']?.Code || 'ADT' }}
                            X {{ passengerFareItem?.PassengerTypeQuantity?.['@attributes']?.Quantity || '1' }}
                        </span>
                    </div>

                    <!-- RIGHT - Total Amount with Margins -->
                    <span class="text-sm sm:text-base font-bold text-primary text-right whitespace-nowrap">
                        {{
                            formatAmount(
                                (parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0) +
                                parseFloat(passengerFareItem?.PassengerFare?.Surcharge?.['@attributes']?.Amount || 0) +
                                parseFloat(passengerFareItem?.PassengerFare?.Taxes?.Total?.['@attributes']?.Amount || 
                                    (() => {
                                        const taxes = passengerFareItem?.PassengerFare?.Taxes?.Tax;
                                        if (!taxes) return 0;
                                        if (Array.isArray(taxes)) {
                                            return taxes.reduce((sum, tax) => sum + parseFloat(tax['@attributes']?.Amount || 0), 0);
                                        }
                                        return parseFloat(taxes['@attributes']?.Amount || 0);
                                    })()) +
                                parseFloat(passengerFareItem?.PassengerFare?.Fees?.Total?.['@attributes']?.Amount ||
                                    (() => {
                                        const fees = passengerFareItem?.PassengerFare?.Fees?.Fee;
                                        if (!fees) return 0;
                                        if (Array.isArray(fees)) {
                                            return fees.reduce((sum, fee) => sum + parseFloat(fee['@attributes']?.Amount || 0), 0);
                                        }
                                        return parseFloat(fees['@attributes']?.Amount || 0);
                                    })()) +
                                parseFloat(passengerFareItem?.PassengerFare?.service_charges || 0) +
                                parseFloat(passengerFareItem?.PassengerFare?.ancillaries_charges || 0) +
                                ((calculateFareMargin(
                                    parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0),
                                    fareMarginAmount,
                                    fareMarginType,
                                    fareAmountType,
                                ) +
                                calculateCustomerMargin(
                                    parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0),
                                )) * (passengerFareItem?.PassengerTypeQuantity?.['@attributes']?.Quantity || 1)) +
                                (otherChargesPerPassenger * (passengerFareItem?.PassengerTypeQuantity?.['@attributes']?.Quantity || 1))
                            )
                            )}}
                    </span>
                </AccordionTrigger>

                <!-- CONTENT - Fare Breakdown -->
                <AccordionContent class="px-3 sm:px-4 sm:pr-7 pb-3 space-y-2">
                    
                    <!-- Base Fare with Margin -->
                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Base Fare</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{
                                formatAmount(
                                    parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0) +
                                    ((calculateFareMargin(
                                        parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0),
                                        fareMarginAmount,
                                        fareMarginType,
                                        fareAmountType,
                                    ) +
                                    calculateCustomerMargin(
                                        parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0),
                                    )) * (passengerFareItem?.PassengerTypeQuantity?.['@attributes']?.Quantity || 1))
                                )
                            }}
                        </span>
                    </div>

                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Other Charges</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{
                                formatAmount(
                                    otherChargesPerPassenger *
                                        (passengerFareItem?.PassengerTypeQuantity?.['@attributes']?.Quantity || 1)
                                )
                            }}
                        </span>
                    </div>

                   

                    <!-- Surcharge (if exists) -->
                    <div v-if="passengerFareItem?.PassengerFare?.Surcharge" class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Surcharge</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{ formatAmount(parseFloat(passengerFareItem?.PassengerFare?.Surcharge?.['@attributes']?.Amount || 0)) }}
                        </span>
                    </div>

                    <!-- Total Taxes (summed once) -->
                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Taxes</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{
                                formatAmount(
                                    (() => {
                                        const taxes = passengerFareItem?.PassengerFare?.Taxes?.Tax;
                                        if (!taxes) return 0;
                                        if (Array.isArray(taxes)) {
                                            return taxes.reduce((sum, tax) => sum + parseFloat(tax['@attributes']?.Amount || 0), 0);
                                        }
                                        return parseFloat(taxes['@attributes']?.Amount || 0);
                                    })()
                                )
                            }}
                        </span>
                    </div>

                    <!-- Total Fees (summed once) -->
                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Fees</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{
                                formatAmount(
                                    (() => {
                                        const fees = passengerFareItem?.PassengerFare?.Fees?.Fee;
                                        if (!fees) return 0;
                                        if (Array.isArray(fees)) {
                                            return fees.reduce((sum, fee) => sum + parseFloat(fee['@attributes']?.Amount || 0), 0);
                                        }
                                        return parseFloat(fees['@attributes']?.Amount || 0);
                                    })()
                                )
                            }}
                        </span>
                    </div>

                    <!-- Service Charges -->
                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Service Charges</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{ formatAmount(parseFloat(passengerFareItem?.PassengerFare?.service_charges || 0)) }}
                        </span>
                    </div>

                    <!-- Ancillaries Charges -->
                    <div v-if="passengerFareItem?.PassengerFare?.ancillaries_charges" class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Ancillaries Charges</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{ formatAmount(parseFloat(passengerFareItem?.PassengerFare?.ancillaries_charges || 0)) }}
                        </span>
                    </div>

                    <hr class="border-dashed border-gray-300" />

                    <!-- Total Amount with Margins Applied -->
                    <div class="flex justify-between items-center rounded">
                        <span class="text-xs sm:text-sm font-medium text-gray-700">Total Amount</span>
                        <span class="text-sm sm:text-base font-bold text-primary">
                            {{
                                formatAmount(
                                    (parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0) +
                                    parseFloat(passengerFareItem?.PassengerFare?.Surcharge?.['@attributes']?.Amount || 0) +
                                    (() => {
                                        const taxes = passengerFareItem?.PassengerFare?.Taxes?.Tax;
                                        if (!taxes) return 0;
                                        if (Array.isArray(taxes)) {
                                            return taxes.reduce((sum, tax) => sum + parseFloat(tax['@attributes']?.Amount || 0), 0);
                                        }
                                        return parseFloat(taxes['@attributes']?.Amount || 0);
                                    })() +
                                    (() => {
                                        const fees = passengerFareItem?.PassengerFare?.Fees?.Fee;
                                        if (!fees) return 0;
                                        if (Array.isArray(fees)) {
                                            return fees.reduce((sum, fee) => sum + parseFloat(fee['@attributes']?.Amount || 0), 0);
                                        }
                                        return parseFloat(fees['@attributes']?.Amount || 0);
                                    })() +
                                    parseFloat(passengerFareItem?.PassengerFare?.service_charges || 0) +
                                    parseFloat(passengerFareItem?.PassengerFare?.ancillaries_charges || 0) +
                                    ((calculateFareMargin(
                                        parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0),
                                        fareMarginAmount,
                                        fareMarginType,
                                        fareAmountType,
                                    ) +
                                    calculateCustomerMargin(
                                        parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0),
                                    )) * (passengerFareItem?.PassengerTypeQuantity?.['@attributes']?.Quantity || 1))
                                )
                                )}}
                        </span>
                    </div>

                    <!-- Currency & Pricing Info -->
                    <div class="flex justify-between items-center text-xs text-gray-400 pt-1">
                        <span>Currency: {{ passengerFareItem?.PassengerFare?.TotalFare?.['@attributes']?.CurrencyCode || 'PKR' }}</span>
                        <span>Pricing: {{ passengerFareItem?.['@attributes']?.PricingSource || 'Published' }}</span>
                    </div>
                </AccordionContent>
            </AccordionItem>
        </Accordion>
    </div>
    
</div>
<div class="flex justify-between items-center p-3">
                        <span class="text-xs sm:text-sm text-gray-600">Add-Ones</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{ formatAmount(parseFloat(totalAddOnesAmount || 0)) }}
                        </span>
                    </div>
                                <div class="flex justify-between  items-center bg-gray-50 p-2  rounded">
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
                                                    Service of Jetze.pk
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
                                    
                                </div>
                                <div v-for="(passengerFareItem, index) in passengerFares" :key="index">
    <div class="mb-4">
        <Accordion type="multiple" collapsible>
            <AccordionItem :value="`passenger-fare-${index}`" class="overflow-hidden">
                
                <!-- HEADER -->
                <AccordionTrigger
                    class="px-2 py-2 border-b grid grid-cols-[1fr_auto_auto] items-center hover:no-underline gap-1">

                    <!-- LEFT - Passenger Type & Quantity -->
                    <div class="flex items-center gap-2">
                        <span class="text-xs sm:text-sm font-bold text-gray-600">
                            {{ passengerFareItem?.PassengerTypeQuantity?.['@attributes']?.Code || 'ADT' }}
                            X {{ passengerFareItem?.PassengerTypeQuantity?.['@attributes']?.Quantity || '1' }}
                        </span>
                    </div>

                    <!-- RIGHT - Total Amount with Margins -->
                    <span class="text-sm sm:text-base font-bold text-primary text-right whitespace-nowrap">
                        {{
                            formatAmount(
                                (parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0) +
                                parseFloat(passengerFareItem?.PassengerFare?.Surcharge?.['@attributes']?.Amount || 0) +
                                parseFloat(passengerFareItem?.PassengerFare?.Taxes?.Total?.['@attributes']?.Amount || 
                                    (() => {
                                        const taxes = passengerFareItem?.PassengerFare?.Taxes?.Tax;
                                        if (!taxes) return 0;
                                        if (Array.isArray(taxes)) {
                                            return taxes.reduce((sum, tax) => sum + parseFloat(tax['@attributes']?.Amount || 0), 0);
                                        }
                                        return parseFloat(taxes['@attributes']?.Amount || 0);
                                    })()) +
                                parseFloat(passengerFareItem?.PassengerFare?.Fees?.Total?.['@attributes']?.Amount ||
                                    (() => {
                                        const fees = passengerFareItem?.PassengerFare?.Fees?.Fee;
                                        if (!fees) return 0;
                                        if (Array.isArray(fees)) {
                                            return fees.reduce((sum, fee) => sum + parseFloat(fee['@attributes']?.Amount || 0), 0);
                                        }
                                        return parseFloat(fees['@attributes']?.Amount || 0);
                                    })()) +
                                parseFloat(passengerFareItem?.PassengerFare?.service_charges || 0) +
                                parseFloat(passengerFareItem?.PassengerFare?.ancillaries_charges || 0) +
                                ((calculateFareMargin(
                                    parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0),
                                    fareMarginAmount,
                                    fareMarginType,
                                    fareAmountType,
                                ) +
                                calculateCustomerMargin(
                                    parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0),
                                )) * (passengerFareItem?.PassengerTypeQuantity?.['@attributes']?.Quantity || 1))
                            )
                            )}}
                    </span>
                </AccordionTrigger>

                <!-- CONTENT - Fare Breakdown -->
                <AccordionContent class="px-3 sm:px-4 sm:pr-7 pb-3 space-y-2">
                    
                    <!-- Base Fare with Margin -->
                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Base Fare</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{
                                formatAmount(
                                    parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0) +
                                    ((calculateFareMargin(
                                        parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0),
                                        fareMarginAmount,
                                        fareMarginType,
                                        fareAmountType,
                                    ) +
                                    calculateCustomerMargin(
                                        parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0),
                                    )) * (passengerFareItem?.PassengerTypeQuantity?.['@attributes']?.Quantity || 1))
                                )
                            }}
                        </span>
                    </div>

                   

                    <!-- Surcharge (if exists) -->
                    <div v-if="passengerFareItem?.PassengerFare?.Surcharge" class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Surcharge</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{ formatAmount(parseFloat(passengerFareItem?.PassengerFare?.Surcharge?.['@attributes']?.Amount || 0)) }}
                        </span>
                    </div>

                    <!-- Total Taxes (summed once) -->
                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Taxes</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{
                                formatAmount(
                                    (() => {
                                        const taxes = passengerFareItem?.PassengerFare?.Taxes?.Tax;
                                        if (!taxes) return 0;
                                        if (Array.isArray(taxes)) {
                                            return taxes.reduce((sum, tax) => sum + parseFloat(tax['@attributes']?.Amount || 0), 0);
                                        }
                                        return parseFloat(taxes['@attributes']?.Amount || 0);
                                    })()
                                )
                            }}
                        </span>
                    </div>

                    <!-- Total Fees (summed once) -->
                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Fees</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{
                                formatAmount(
                                    (() => {
                                        const fees = passengerFareItem?.PassengerFare?.Fees?.Fee;
                                        if (!fees) return 0;
                                        if (Array.isArray(fees)) {
                                            return fees.reduce((sum, fee) => sum + parseFloat(fee['@attributes']?.Amount || 0), 0);
                                        }
                                        return parseFloat(fees['@attributes']?.Amount || 0);
                                    })()
                                )
                            }}
                        </span>
                    </div>

                    <!-- Service Charges -->
                    <div class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Service Charges</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{ formatAmount(parseFloat(passengerFareItem?.PassengerFare?.service_charges || 0)) }}
                        </span>
                    </div>

                    <!-- Ancillaries Charges -->
                    <div v-if="passengerFareItem?.PassengerFare?.ancillaries_charges" class="flex justify-between items-center">
                        <span class="text-xs sm:text-sm text-gray-600">Ancillaries Charges</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{ formatAmount(parseFloat(passengerFareItem?.PassengerFare?.ancillaries_charges || 0)) }}
                        </span>
                    </div>

                    <hr class="border-dashed border-gray-300" />

                    <!-- Total Amount with Margins Applied -->
                    <div class="flex justify-between items-center rounded">
                        <span class="text-xs sm:text-sm font-medium text-gray-700">Total Amount</span>
                        <span class="text-sm sm:text-base font-bold text-primary">
                            {{
                                formatAmount(
                                    (parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0) +
                                    parseFloat(passengerFareItem?.PassengerFare?.Surcharge?.['@attributes']?.Amount || 0) +
                                    (() => {
                                        const taxes = passengerFareItem?.PassengerFare?.Taxes?.Tax;
                                        if (!taxes) return 0;
                                        if (Array.isArray(taxes)) {
                                            return taxes.reduce((sum, tax) => sum + parseFloat(tax['@attributes']?.Amount || 0), 0);
                                        }
                                        return parseFloat(taxes['@attributes']?.Amount || 0);
                                    })() +
                                    (() => {
                                        const fees = passengerFareItem?.PassengerFare?.Fees?.Fee;
                                        if (!fees) return 0;
                                        if (Array.isArray(fees)) {
                                            return fees.reduce((sum, fee) => sum + parseFloat(fee['@attributes']?.Amount || 0), 0);
                                        }
                                        return parseFloat(fees['@attributes']?.Amount || 0);
                                    })() +
                                    parseFloat(passengerFareItem?.PassengerFare?.service_charges || 0) +
                                    parseFloat(passengerFareItem?.PassengerFare?.ancillaries_charges || 0) +
                                    ((calculateFareMargin(
                                        parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0),
                                        fareMarginAmount,
                                        fareMarginType,
                                        fareAmountType,
                                    ) +
                                    calculateCustomerMargin(
                                        parseFloat(passengerFareItem?.PassengerFare?.BaseFare?.['@attributes']?.Amount || 0),
                                    )) * (passengerFareItem?.PassengerTypeQuantity?.['@attributes']?.Quantity || 1))
                                )
                                )}}
                        </span>
                    </div>

                    <!-- Currency & Pricing Info -->
                    <div class="flex justify-between items-center text-xs text-gray-400 pt-1">
                        <span>Currency: {{ passengerFareItem?.PassengerFare?.TotalFare?.['@attributes']?.CurrencyCode || 'PKR' }}</span>
                        <span>Pricing: {{ passengerFareItem?.['@attributes']?.PricingSource || 'Published' }}</span>
                    </div>
                </AccordionContent>
            </AccordionItem>
        </Accordion>
    </div>
    
</div>
<div class="flex justify-between items-center p-3">
                        <span class="text-xs sm:text-sm text-gray-600">Add-Ones</span>
                        <span class="text-xs sm:text-sm font-medium">
                            {{ formatAmount(parseFloat(totalAddOnesAmount || 0)) }}
                        </span>
                    </div>
                                <div class="flex justify-between  items-center bg-gray-50 p-2  rounded">
                                    <span class="text-base sm:text-sm font-semibold text-gray-900">Total Amount</span>
                                    <span class="text-sm sm:text-lg font-bold text-primary">
                                        {{ formatAmount(amount = calculateGrandTotal()) }}
                                    </span>
                                </div>

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
                <button @click="patchAncillaryCharges"
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

<style scoped>
/* Tooltip positioning */
.group:hover .group-hover\:opacity-100 {
    opacity: 1;
}

/* Ensure tooltip doesn't get cut off */
.overflow-x-auto {
    overflow-x: auto;
    overflow-y: visible;
}

.min-w-max {
    min-width: max-content;
    overflow-y: visible;
}

/* Better hover effect for seats */
.group:hover .w-10.h-10 {
    transform: scale(1.1);
    z-index: 40;
    position: relative;
}

/* Ensure tooltip appears above everything */
.z-50 {
    z-index: 50;
}

/* Seat colors with better contrast */
.bg-green-400 {
    background-color: #4ade80;
}
.bg-yellow-400 {
    background-color: #facc15;
}
.bg-orange-400 {
    background-color: #fb923c;
}
.bg-blue-600 {
    background-color: #2563eb;
}

/* Hover states */
.hover\:bg-green-500:hover {
    background-color: #22c55e;
}
.hover\:bg-yellow-500:hover {
    background-color: #eab308;
}
.hover\:bg-orange-500:hover {
    background-color: #f97316;
}
.hover\:bg-blue-700:hover {
    background-color: #1d4ed8;
}

/* Selected seat border */
.border-blue-500 {
    border-color: #3b82f6;
}

/* Ensure seat numbers are visible */
.w-10.h-10 {
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 500;
}
</style>
