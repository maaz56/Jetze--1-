<!-- FlightAncillaries.vue -->
<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useStore } from 'vuex'
import moment from 'moment'
import { toast } from 'vue3-toastify'
import {
    Tabs, TabsContent, TabsList, TabsTrigger
} from "@/components/ui/tabs"
import Button from "@/components/ui/button/Button.vue"
import Badge from "@/components/ui/badge/Badge.vue"
import {
    FETCH_BOOKING_DETAILS,
    FETCH_ANCILLARIES,
    PATCH_ANCILLARIES,
    UPDATE_BOOKING_AMOUNT,
    FETCH_CUSTOMER_MARGIN
} from "@/services/store/actions.type"
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
import Spinner from '@/components/common/Spinner.vue'
import { calculateCustomerPrice, calculateTypeMargin, formatAmount, formatDate } from '@/lib/utils'
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from "@/components/ui/accordion";
import { ClockIcon, ChevronDown, Package, X, Check } from 'lucide-vue-next'
import { useAuthStore } from '@/services/stores/auth'
import Label from '@/components/common/Label.vue'
import Input from '@/components/common/Input.vue'


// ==================== ROUTER & STORE ====================
const router = useRouter()
const route = useRoute()
const store = useStore()

// ==================== STATE ====================
const isLoading = computed(() => store.getters["flight/isLoading"]);
const isPatchingAncillaries = computed(() => store.getters["flight/isPatchingAncillaries"]);
const isBookingDetailsLoading = ref(false)
const isSaving = ref(false)
const error = ref(null)
const showAncillariesTab = ref(true)
const activeTab = ref('seats');

const user = computed(() => useAuthStore.user);
const user_id = computed(() => user.value?.id);

// Ancillaries data
const selectedSeats = ref({})
const selectedAncillariesList = ref([])

// Booking data
const bookingDetails = ref(null)
const timerInterval = ref();
const expiryTime = ref(null);

const pnrData = ref(null)
const countdown = ref(null)
const ancillaries = ref(null)
const sooperResponse = ref(null)
const flightData = ref(null)
const selectedFares = ref([])
const customerMarginAmt = ref(0);
const passengerCount = ref(0);
const airportMargins = computed(() => store.getters["airport/airportMargin"] || {});
const savedCustomerMarkupTotal = computed(() =>
    parseFloat(bookingDetails?.value?.[0]?.agent_markup || 0),
);
const savedCustomerDiscountTotal = computed(() =>
    parseFloat(bookingDetails?.value?.[0]?.agent_discount || 0),
);
const savedOtherChargesTotal = computed(() =>
    parseFloat(bookingDetails?.value?.[0]?.agent_margin || 0),
);
const savedAirportMarginTotal = computed(() =>
    parseFloat(bookingDetails?.value?.[0]?.airport_margin_amount || 0),
);
const savedMarginTotal = computed(
    () =>
        savedCustomerMarkupTotal.value +
        savedOtherChargesTotal.value +
        savedAirportMarginTotal.value -
        savedCustomerDiscountTotal.value,
);
const marginPerFlight = computed(() => {
    const flightCount = flightData?.value?.leg?.flights?.length || 0;
    if (!flightCount) return 0;
    return savedMarginTotal.value / flightCount;
});

// Accordion state
const openAccordions = ref(['seats'])


// ==================== COMPUTED PROPERTIES ====================
const bookingId = computed(() => route.query.booking_id)
const flightProvider = computed(() => route.query.flight_provider)
const bookingSource = computed(() => route.query.booking_source)
const flightMode = computed(() => route.query.flight_mode)
const flightId = computed(() => route.query.flight_id)
const pnr = computed(() => route.query.pnr);
const seatSelectionStatus = computed(() => {
    if (!seatEligiblePassengerCount.value) {
        return { complete: true, message: "Seat selection not required for infants" }
    }
    if (!processedSeatMapData.value?.length) {
        return { complete: false, message: "No seat data available" }
    }

    const totalRequiredSeats = seatEligiblePassengerCount.value * processedSeatMapData.value.length
    const selectedCount = Object.keys(selectedSeats.value).length

    return {
        complete: selectedCount === totalRequiredSeats,
        selected: selectedCount,
        required: totalRequiredSeats,
        message: `${selectedCount} of ${totalRequiredSeats} seats selected`
    }
})
const CustomerMargin = computed(
    () => store.getters["customerMargin/customerMargin"],
);
const ancillariesData = computed(() => {
    try {
        return ancillaries.value?.ancillaries?.Body?.AirAncillaryItemsResponse?.AirAncillaryItemsResult || null
    } catch {
        return null
    }
})

const passengers = computed(() => getPassengers() || [])
const isInfantPassenger = (passenger) => {
    const rawType = passenger?.type ?? passenger?.traveler_type ?? ''
    const type = String(rawType).toUpperCase()
    return type === 'INF' || type === 'INFT' || type === 'INFANT'
}
const seatEligiblePassengers = computed(() => {
    return passengers.value.map((passenger, index) => ({ passenger, index }))
        .filter(({ passenger }) => !isInfantPassenger(passenger))
})
const seatEligiblePassengerCount = computed(() => seatEligiblePassengers.value.length)

const processedSeatMapData = computed(() => {
    if (!ancillaries.value?.seatMap) return []

    const seatMapResponse = ancillaries.value.seatMap.Body?.AirSeatMapResponse?.AirSeatMapResult?.SeatMapResponses?.SeatMapResponse

    if (!seatMapResponse) return []

    return Array.isArray(seatMapResponse) ? seatMapResponse : [seatMapResponse]
})

const ancillarySegments = computed(() => {
    try {
        const data = ancillaries.value?.ancillaries?.Body?.AirAncillaryItemsResponse?.AirAncillaryItemsResult?.AncillaryItemResponses?.AncillaryItemResponse
        return Array.isArray(data) ? data : (data ? [data] : [])
    } catch (e) {
        console.error("Failed to parse ancillary segments", e)
        return []
    }
})

const totalSeatsCost = computed(() => {
    return Object.values(selectedSeats.value).reduce((total, seat) => {
        return total + (parseFloat(seat.price) || 0)
    }, 0)
})

const extraServicesTotal = computed(() => {
    return selectedAncillariesList.value.reduce((sum, anc) => sum + anc.amount, 0)
})

const grandTotal = computed(() => {
    const baseAmount = parseFloat(bookingDetails.value?.amount) || 0
    return baseAmount + totalSeatsCost.value + extraServicesTotal.value
})

const allPassengersHaveSeats = computed(() => {
    if (!seatEligiblePassengerCount.value) return true

    for (let i = 0; i < seatEligiblePassengers.value.length; i++) {
        const pIdx = seatEligiblePassengers.value[i].index
        const travelerRef = getPassengerTravelerRef(pIdx)
        if (!travelerRef) return false

        const hasSeatInAllSegments = processedSeatMapData.value.every(segment => {
            const segmentRPH = segment.FlightSegmentInfo['@attributes'].RPH
            const key = `${segmentRPH}-${travelerRef}`
            return selectedSeats.value[key] !== undefined
        })

        if (!hasSeatInAllSegments) return false
    }
    return true
})

// Selected items preview
const selectedItemsPreview = computed(() => {
    const items = []

    // Add seats
    Object.entries(selectedSeats.value).forEach(([key, seat]) => {
        const passengerIndex = getPassengerIndexFromKey(key)
        const segmentKey = getSegmentFromKey(key)
        const passenger = passengers.value[passengerIndex]
        if (passenger) {
            items.push({
                type: 'seat',
                passenger: `${passenger.first_name} ${passenger.last_name}`,
                description: `Seat ${seat.row}${seat.seat}`,
                amount: seat.price,
                currency: seat.currency,
                segment: segmentKey
            })
        }
    })

    // Add ancillaries
    selectedAncillariesList.value.forEach(item => {
        const passenger = passengers.value[item.pIdx]
        if (passenger) {
            items.push({
                type: 'ancillary',
                passenger: `${passenger.first_name} ${passenger.last_name}`,
                description: item.item['@attributes'].ItemTitle,
                amount: item.amount,
                currency: item.currency,
                segment: item.segIdx + 1
            })
        }
    })

    return items
})

// ==================== METHODS ====================

function fetchCustomerMarginValues() {
    store.dispatch("customerMargin/" + FETCH_CUSTOMER_MARGIN);
}

// Timer functions
function initializeTimer() {
    const savedExpiry = localStorage.getItem('booking_expiry')
    const now = Date.now()

    if (savedExpiry) {
        expiryTime.value = parseInt(savedExpiry)
        const remaining = expiryTime.value - now

        if (remaining > 0) {
            startCountdown(remaining)
        } else {
            // Expired, set new 13 minutes
            setNewExpiry()
        }
    } else {
        setNewExpiry()
    }
}

function setNewExpiry() {
    const now = Date.now()
    expiryTime.value = now + (13 * 60 * 1000) // 13 minutes
    localStorage.setItem('booking_expiry', expiryTime.value.toString())
    startCountdown(13 * 60 * 1000)
}

function startCountdown(remainingTime) {
    if (timerInterval.value) clearInterval(timerInterval.value)

    countdown.value = formatTime(remainingTime)

    timerInterval.value = setInterval(() => {
        remainingTime -= 1000

        if (remainingTime <= 0) {
            clearInterval(timerInterval.value)
            localStorage.removeItem("booking_expiry")
            // Handle expiry - maybe show dialog or redirect
            toast.error('Booking session expired. Please start over.')
            // router.push({ name: 'FlightSearch' })
        } else {
            countdown.value = formatTime(remainingTime)
        }
    }, 1000)
}

const formatTime = (milliseconds) => {
    const totalSeconds = Math.floor(milliseconds / 1000)
    const minutes = Math.floor(totalSeconds / 60)
    const seconds = totalSeconds % 60
    return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}`
}

// Fetch booking details
async function fetchBookingDetails() {
    if (!bookingId.value) {
        error.value = "No booking ID provided."
        return
    }

    isBookingDetailsLoading.value = true

    try {
        await store.dispatch(`flight/${FETCH_BOOKING_DETAILS}`, {
            bookingId: bookingId.value,
            bookingSource: bookingSource.value || 'sooper',
        })

        bookingDetails.value = store.getters["flight/bookingDetails"]

        if (bookingDetails.value) {
            parseResponses()
            flightData.value = parseFlightData(bookingDetails.value[0]?.flight_data)
        }
    } catch (err) {
        error.value = "Failed to fetch booking details."
        toast.error(error.value)
    } finally {
        isBookingDetailsLoading.value = false
    }
}

// Parse PNR and Sooper responses
function parseResponses() {
    try {
        if (bookingDetails.value?.[0]?.pnr_response) {
            pnrData.value = JSON.parse(bookingDetails.value[0].pnr_response)
        }
        selectedFares.value = bookingDetails.value?.[0]?.fare_reference ? JSON.parse(bookingDetails.value[0].fare_reference) : []

        if (bookingDetails.value?.[0]?.sooper_response) {
            sooperResponse.value = JSON.parse(bookingDetails.value[0].sooper_response)
        }
    } catch (e) {
        console.error("Failed to parse responses:", e)
    }
}

// Parse flight data
function parseFlightData(flightDataString) {
    if (!flightDataString) return null
    try {
        return JSON.parse(flightDataString)
    } catch {
        return null
    }
}

// Fetch ancillaries
async function fetchAncillaries() {
    if (!bookingDetails.value?.[0]?.id) return

    try {
        await store.dispatch(`flight/${FETCH_ANCILLARIES}`, {
            bookingId: bookingDetails.value[0].id,
            flight_provider: flightProvider.value,
        })

        ancillaries.value = store.getters["flight/ancillaries"]
    } catch (err) {
        console.error("Failed to fetch ancillaries:", err)
        toast.error("Failed to load ancillaries")
    }
}

// Get passengers
function getPassengers() {
    if (sooperResponse.value?.data?.booking?.booking?.providers?.[0]?.legs) {
        return sooperResponse.value.data.booking.booking.providers[0].legs
            .flatMap(l => l.passengers.map(p => p.passenger))
    }
    return bookingDetails.value?.[0]?.pessangers ?? []
}

// Get passenger traveler reference
const getPassengerTravelerRef = (pIdx) => {
    if (!pnrData.value?.Body?.AirBookResponse?.AirBookResult?.AirReservation?.TravelerInfo?.AirTraveler) return null

    const travelers = pnrData.value.Body.AirBookResponse.AirBookResult.AirReservation.TravelerInfo.AirTraveler
    const traveler = Array.isArray(travelers) ? travelers[pIdx] : travelers

    return traveler?.TravelerRefNumber?.["@attributes"]?.RPH || null
}

// Get passenger by index
const getPassengerByIndex = (index) => {
    if (!pnrData.value?.Body?.AirBookResponse?.AirBookResult?.AirReservation?.TravelerInfo?.AirTraveler) return null

    const travelers = pnrData.value.Body.AirBookResponse.AirBookResult.AirReservation.TravelerInfo.AirTraveler
    return Array.isArray(travelers) ? travelers[index] : travelers
}

// Seat selection methods
const getSelectedSeat = (segmentIndex, passengerIndex) => {
    const key = `${segmentIndex}-${passengerIndex}`
    const selected = selectedSeats.value[key]
    return selected ? `${selected.row}${selected.seat}` : null
}

const getSelectedSeatPrice = (segmentIndex, passengerIndex) => {
    const key = `${segmentIndex}-${passengerIndex}`
    return selectedSeats.value[key]?.price || 0
}

const isSeatSelected = (segmentIndex, passengerIndex, rowNumber, seatNumber) => {
    const key = `${segmentIndex}-${passengerIndex}`
    return selectedSeats.value[key]?.row === rowNumber && selectedSeats.value[key]?.seat === seatNumber
}

const getSelectedSeatFeatures = (segmentIndex, passengerIndex) => {
    const key = `${segmentIndex}-${passengerIndex}`
    return selectedSeats.value[key]?.features || ''
}

const getSeatTooltip = (seat) => {
    if (seat.Availability === 'NoSeatHere') return 'No seat'

    const features = []
    let price = 'Free'

    if (seat.Features) {
        if (seat.Features.includes('Window')) features.push('Window')
        if (seat.Features.includes('Aisle')) features.push('Aisle')
        if (seat.Features.includes('ExitRow')) features.push('Exit Row')
        if (seat.Features.includes('Overwing')) features.push('Overwing')
    }

    if (seat.Service?.Fee?.['@attributes']?.Amount) {
        price = `${seat.Service.Fee['@attributes'].Amount} ${seat.Service.Fee['@attributes'].CurrencyCode}`
    }

    const status = seat.Availability === 'SeatOccupied' ? 'Occupied' : 'Available'
    const featureText = features.length > 0 ? `Features: ${features.join(', ')}` : ''

    return `${status} | ${price}${featureText ? ` | ${featureText}` : ''}`
}
function getSeatColorClass(price) {
    // Convert to number and handle PKR amounts
    const priceNum = Number(price) || 0
    // Adjust thresholds for PKR
    if (priceNum == 0) return 'bg-green-300 hover:bg-green-200 border-green-400'
    if (priceNum <= 50) return 'bg-green-300 hover:bg-green-200 border-green-400'
    if (priceNum <= 1000) return 'bg-yellow-300 hover:bg-yellow-200 border-yellow-400'
    if (priceNum <= 1500) return 'bg-orange-300 hover:bg-orange-200 border-orange-400'
    return 'bg-blue-300 hover:bg-blue-200 border-blue-400'
}
const selectSeat = (segmentRPH, traveler, rowNumber, seatNumber, seatData) => {
    if (seatData.Availability === 'NoSeatHere' || seatData.Availability === 'SeatOccupied') {
        return
    }

    const TravelerRefNumber = traveler?.TravelerRefNumber?.["@attributes"]?.RPH || null
    const key = `${segmentRPH}-${TravelerRefNumber}`

    // If already selected, deselect it
    if (selectedSeats.value[key]?.row === rowNumber && selectedSeats.value[key]?.seat === seatNumber) {
        delete selectedSeats.value[key]
        selectedSeats.value = { ...selectedSeats.value }
    } else {
        // Select new seat
        selectedSeats.value[key] = {
            row: rowNumber,
            seat: seatNumber,
            travelerRefNumber: TravelerRefNumber,
            RPH: segmentRPH,
            price: seatData.Service?.Fee?.['@attributes']?.Amount || 0,
            currency: seatData.Service?.Fee?.['@attributes']?.CurrencyCode || 'PKR',
            features: seatData.Features ? seatData.Features.filter(f => !f.includes('Other_')).join(', ') : ''
        }
        selectedSeats.value = { ...selectedSeats.value }
    }
}

// Ancillary selection methods
function getItemsArray(items) {
    return Array.isArray(items) ? items : [items]
}

function isSegmentSelected(segIdx, pIdx, groupCode, itemCode) {
    return selectedAncillariesList.value.some(x =>
        x.segIdx === segIdx && x.pIdx === pIdx && x.groupCode === groupCode && x.itemCode === itemCode
    )
}

function toggleSegmentAncillary(segIdx, pIdx, groupCode, item, event, paxType) {
    const itemCode = item['@attributes'].ItemCode
    const amount = Number(item['@attributes'].ChargeAmount || 0)
    const currency = item['@attributes'].ChargeCurrency || 'PKR'

    const newList = [...selectedAncillariesList.value]

    if (event.target.type === 'checkbox') {
        if (event.target.checked) {
            newList.push({ segIdx, pIdx, groupCode, itemCode, item, amount, currency, paxType })
        } else {
            const idx = newList.findIndex(x =>
                x.segIdx === segIdx && x.pIdx === pIdx && x.groupCode === groupCode && x.itemCode === itemCode
            )
            if (idx > -1) newList.splice(idx, 1)
        }
    } else {
        // Radio: clear others in same group, same passenger, same segment
        for (let i = newList.length - 1; i >= 0; i--) {
            if (newList[i].segIdx === segIdx &&
                newList[i].pIdx === pIdx &&
                newList[i].groupCode === groupCode) {
                newList.splice(i, 1)
            }
        }
        newList.push({ segIdx, pIdx, groupCode, itemCode, item, amount, currency, paxType })
    }

    selectedAncillariesList.value = newList
}

function hasAnySelectionInGroup(segIdx, pIdx, groupCode) {
    return selectedAncillariesList.value.some(x =>
        x.segIdx === segIdx && x.pIdx === pIdx && x.groupCode === groupCode
    )
}

function clearSegmentGroup(segIdx, pIdx, groupCode) {
    const newList = [...selectedAncillariesList.value]
    for (let i = newList.length - 1; i >= 0; i--) {
        if (newList[i].segIdx === segIdx &&
            newList[i].pIdx === pIdx &&
            newList[i].groupCode === groupCode) {
            newList.splice(i, 1)
        }
    }
    selectedAncillariesList.value = newList
}

// Remove selected item
function removeSelectedItem(item) {
    if (item.type === 'seat') {
        // Find and remove seat
        Object.entries(selectedSeats.value).forEach(([key, seat]) => {
            const passengerIndex = getPassengerIndexFromKey(key)
            const passenger = passengers.value[passengerIndex]
            if (passenger && `${passenger.first_name} ${passenger.last_name}` === item.passenger) {
                if (seat.row + seat.seat === item.description.replace('Seat ', '')) {
                    delete selectedSeats.value[key]
                }
            }
        })
        selectedSeats.value = { ...selectedSeats.value }
    } else {
        // Remove ancillary
        const index = selectedAncillariesList.value.findIndex(a =>
            a.item['@attributes'].ItemTitle === item.description &&
            passengers.value[a.pIdx] &&
            `${passengers.value[a.pIdx].first_name} ${passengers.value[a.pIdx].last_name}` === item.passenger
        )
        if (index !== -1) {
            selectedAncillariesList.value.splice(index, 1)
            selectedAncillariesList.value = [...selectedAncillariesList.value]
        }
    }
}

// Get passenger index from key
const getPassengerIndexFromKey = (key) => {
    if (!key) return 0
    const parts = key.split('-')
    // Find passenger by travelerRefNumber
    const travelerRef = parts[1]
    for (let i = 0; i < passengers.value.length; i++) {
        if (getPassengerTravelerRef(i) === travelerRef) {
            return i
        }
    }
    return 0
}

// Get segment from key
const getSegmentFromKey = (key) => {
    const parts = key.split('-')
    return parts.length > 0 ? parts[0] : '1'
}

// Patch ancillary charges
function patchAncillaryCharges() {
    if (!bookingDetails.value?.[0]?.id) return

    const seatData = {
        selectedSeats: selectedSeats.value,
        totalCost: totalSeatsCost.value,
        segments: processedSeatMapData.value.map((segment, index) => ({
            segmentIndex: index,
            flightNumber: segment.FlightSegmentInfo['@attributes'].FlightNumber,
            departure: segment.FlightSegmentInfo.DepartureAirport['@attributes'].LocationCode,
            arrival: segment.FlightSegmentInfo.ArrivalAirport['@attributes'].LocationCode
        }))
    }

    store.dispatch(`flight/${PATCH_ANCILLARIES}`, {
        booking_id: bookingDetails.value[0].id,
        flight_provider: flightProvider.value,
        pnr: pnrData.value,
        selectedSeats: seatData,
        selectedAncillaries: selectedAncillariesList.value.map(item => ({
            segIdx: item.segIdx,
            segmentRPH: ancillarySegments.value[item.segIdx]?.FlightSegmentInfo['@attributes'].RPH,
            pIdx: item.pIdx,
            TravelerRefNumber: getPassengerTravelerRef(item.pIdx),
            groupCode: item.groupCode,
            itemCode: item.itemCode,
            amount: item.amount,
            currency: item.currency
        }))
    })
}

// Update booking amount
function updateBookingAmount() {
    if (!bookingDetails.value?.[0]?.id) return
    store.dispatch(`flight/${UPDATE_BOOKING_AMOUNT}`, {
        booking_id: bookingDetails.value[0].id,
        amount: parseFloat(bookingDetails.value[0].amount) + parseFloat(grandTotal.value),
        add_ones_amount: parseFloat(grandTotal.value)
    })
}

const canProceedToPayment = computed(() => {
    if (!passengers.value?.length) return false
    if (!processedSeatMapData.value?.length) return false
    if (!seatEligiblePassengerCount.value) return true

    for (let i = 0; i < seatEligiblePassengers.value.length; i++) {
        const pIdx = seatEligiblePassengers.value[i].index
        const travelerRef = getPassengerTravelerRef(pIdx)
        if (!travelerRef) return false

        for (let segIdx = 0; segIdx < processedSeatMapData.value.length; segIdx++) {
            const segment = processedSeatMapData.value[segIdx]
            const segmentRPH = segment.FlightSegmentInfo['@attributes'].RPH
            const key = `${segmentRPH}-${travelerRef}`

            if (!selectedSeats.value[key]) {
                return false
            }
        }
    }

    return true
})

// Function to validate seats before proceeding
const validateSeatsBeforeProceed = () => {
    const missingSeats = []

    seatEligiblePassengers.value.forEach(({ passenger, index: pIdx }) => {
        const travelerRef = getPassengerTravelerRef(pIdx)
        if (!travelerRef) return

        const missingForPassenger = []

        processedSeatMapData.value.forEach((segment, segIdx) => {
            const segmentRPH = segment.FlightSegmentInfo['@attributes'].RPH
            const key = `${segmentRPH}-${travelerRef}`
            const departure = segment.FlightSegmentInfo.DepartureAirport['@attributes'].LocationCode
            const arrival = segment.FlightSegmentInfo.ArrivalAirport['@attributes'].LocationCode

            if (!selectedSeats.value[key]) {
                missingForPassenger.push(`Flight ${segment.FlightSegmentInfo['@attributes'].FlightNumber} (${departure} → ${arrival})`)
            }
        })

        if (missingForPassenger.length > 0) {
            missingSeats.push({
                passenger: `${passenger.title} ${passenger.first_name} ${passenger.last_name}`,
                flights: missingForPassenger
            })
        }
    })

    return missingSeats
}

// Proceed to payment
function proceedToPayment() {
    const missingSeats = validateSeatsBeforeProceed()

    if (missingSeats.length > 0) {
        let errorMessage = "Please select seats for all passengers before proceeding:\n\n"
        missingSeats.forEach(item => {
            errorMessage += `${item.passenger}:\n`
            item.flights.forEach(flight => {
                errorMessage += `  • ${flight}\n`
            })
            errorMessage += '\n'
        })

        toast.error(errorMessage, {
            autoClose: false,
            closeButton: true,
            closeOnClick: false
        })

        // Open seats accordion
        if (!openAccordions.value.includes('seats')) {
            openAccordions.value.push('seats')
        }

        window.scrollTo({ top: 0, behavior: 'smooth' })
        return
    }

    isSaving.value = true;

    patchAncillaryCharges()
    updateBookingAmount()

    setTimeout(() => {
        isSaving.value = false
        localStorage.removeItem("booking_expiry") // Clear timer on successful completion

        router.push({
            name: "CustomerPaymentView",
            query: {
                flight_id: flightId.value,
                booking_id: bookingId.value,
                pnr: pnr.value,
                flight_mode: flightMode.value,
                booking_source: bookingSource.value,
                flight_provider: flightProvider.value,
            }
        })

        toast.success('Ancillaries saved successfully')
    }, 1000)
}

// Close ancillaries
function closeAncillaries() {
    showAncillariesTab.value = false
    emit('close')
}

// Format helpers
const formatDateTime = (date) => moment.parseZone(date).format('DD MMM YYYY, HH:mm')

function calculateBaseFare(fare) {
    const basePrice = parseFloat(fare?.base_price) || 0;

    const airlineMargin = calculateFareMargin(
        basePrice,
        fare?.margin_amount,
        fare?.margin_type,
        fare?.amount_type
    );

    const customerMargin = calculateCustomerPrice(
        basePrice,
        CustomerMargin?.value?.discount || 0,
        CustomerMargin?.value?.margin_amount || 0
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

function calculateTotalFare(fare) {
    const airlineMargin = calculateFareMargin(
        fare.base_price,
        fare.margin_amount,
        fare.margin_type,
        fare.amount_type
    );

    const billable =
        fare.base_price +
        parseFloat(fare.surchage || 0) +
        parseFloat(fare.taxes || 0) +
        parseFloat(fare.fees || 0) +
        parseFloat(fare.service_charges || 0) +
        parseFloat(fare.ancillaries_charges || 0) +
        parseFloat(airlineMargin) * passengerCount.value;

    return billable;
}

function calculateGrandTotal() {
    let total = 0;

    flightData?.value?.leg?.flights?.forEach((flight, index) => {
        flight?.fares?.forEach(fare => {
            if (selectedFares.value.includes(fare.ref_id)) {
                total += calculateTotalFare(fare)
            }
        });
    });

    return total + savedMarginTotal.value + grandTotal.value;
}


// ==================== WATCHERS ====================
watch(() => bookingDetails.value, (newVal) => {
    if (newVal) {
        passengerCount.value = getPassengers().length
        fetchAncillaries();
    }
}, { deep: true })

// ==================== LIFECYCLE HOOKS ====================
onMounted(() => {
    initializeTimer()
    fetchBookingDetails()
    fetchCustomerMarginValues()
    window.scrollTo({ top: 0, behavior: 'smooth' })
})

onUnmounted(() => {
    if (timerInterval.value) {
        clearInterval(timerInterval.value)
    }
})
</script>

<template>
    <div class="min-h-screen bg-gray-50 py-6">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Progress Steps -->
            <div class="bg-white border mb-10 border-gray-200 py-6 px-4">
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

            <!-- Loading State -->

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                <!-- Main Content - Left Side -->
                <div class="lg:col-span-3 space-y-4">
                    <!-- Header -->
                    <div class="mb-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <h1 class="text-2xl font-bold text-gray-900">Manage Extras</h1>
                                <p class="text-gray-500 mt-1">Select seats, baggage, and other services for your flight
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Selected Items Preview -->
                    <div v-if="selectedItemsPreview.length > 0"
                        class="bg-white rounded border border-gray-200 p-4 mb-4">
                        <h3 class="font-semibold text-gray-900 mb-3">Selected Items</h3>
                        <div class="space-y-2">
                            <div v-for="(item, index) in selectedItemsPreview" :key="index"
                                class="flex items-center justify-between py-2 px-3 bg-gray-50 rounded-lg">
                                <div class="flex items-center gap-3">
                                    <div class="w-6 h-6 rounded-full bg-primary/10 flex items-center justify-center">
                                        <Package v-if="item.type === 'ancillary'" class="w-3 h-3 text-primary" />
                                        <Check v-else class="w-3 h-3 text-primary" />

                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ item.description }}</p>
                                        <p class="text-xs text-gray-500">{{ item.passenger }} • Segment {{ item.segment
                                            }}</p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold text-primary">{{ formatAmount(item.amount)
                                        }}</span>
                                    <button @click="removeSelectedItem(item)"
                                        class="text-gray-400 hover:text-red-500 transition-colors">
                                        <X class="w-4 h-4" />
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Accordions for Seats and Extras -->
                    <Accordion type="multiple" v-model="openAccordions" class="space-y-4">
                        <!-- Seats Accordion -->
                        <AccordionItem value="seats" class="bg-white rounded border border-gray-200 overflow-hidden">
    <AccordionTrigger class="px-4 py-3 hover:no-underline">
        <div class="flex items-center justify-between w-full">
            <div class="flex items-center gap-3">
                <div
                    class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                    <Check class="w-4 h-4 text-primary" />
                </div>
                <div class="text-left">
                    <h3 class="font-semibold text-gray-900">Seat Selection</h3>
                    <p class="text-xs text-gray-500">{{ seatSelectionStatus.message }}</p>
                </div>
            </div>
            <Badge v-if="Object.keys(selectedSeats).length > 0" variant="secondary"
                class="ml-2">
                {{ Object.keys(selectedSeats).length }} selected
            </Badge>
        </div>
    </AccordionTrigger>
    <AccordionContent class="px-4 pb-4">
        <div v-if="isLoading"
            class="flex items-center justify-center py-12">
            <div class="text-center">
                <Spinner />
            </div>
        </div>

        <div v-if="!seatEligiblePassengerCount && !isLoading" class="bg-white rounded border border-gray-200 p-12 text-center">
            <p class="text-gray-500">Seat selection is not available for infants.</p>
        </div>

        <template v-else>
            <!-- Price Legend -->
            <div v-if="processedSeatMapData.length > 0 && !isLoading" class="mb-4 p-3 bg-gray-50 rounded-lg">
                <p class="text-sm font-medium text-gray-700 mb-2">Seat Price Ranges (PKR):</p>
                <div class="flex flex-wrap gap-4">
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 bg-green-300 rounded border border-green-400"></div>
                        <span class="text-xs">Free</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 bg-yellow-300 rounded border border-yellow-400"></div>
                        <span class="text-xs">501 - 1,000</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 bg-orange-300 rounded border border-orange-400"></div>
                        <span class="text-xs">1,001 - 1,500</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 bg-blue-300 rounded border border-blue-400"></div>
                        <span class="text-xs">> 1,500</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 bg-gray-200 rounded border border-gray-300"></div>
                        <span class="text-xs">Occupied</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="w-5 h-5 bg-white rounded border-2 border-primary"></div>
                        <span class="text-xs">Selected</span>
                    </div>
                </div>
            </div>

            <!-- Seat Maps -->
            <!-- Seat Maps -->
            <div v-if="processedSeatMapData.length > 0 && !isLoading" class="space-y-6">
    <div v-for="(segment, segmentIndex) in processedSeatMapData" :key="segmentIndex"
        class="border border-gray-200 rounded overflow-hidden">

        <!-- Segment Header -->
        <div class="bg-gradient-to-r from-gray-50 to-white border-b border-gray-200 p-4">
            <h4 class="text-base font-semibold text-gray-900 mb-3">
                Flight {{ segment.FlightSegmentInfo['@attributes'].FlightNumber }}
            </h4>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div>
                    <span class="text-xs text-gray-500">Departure</span>
                    <p class="font-medium">{{
                        segment.FlightSegmentInfo.DepartureAirport['@attributes'].LocationCode
                        }}</p>
                    <p class="text-xs text-gray-600">{{
                        formatDateTime(segment.FlightSegmentInfo['@attributes'].DepartureDateTime)
                        }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500">Arrival</span>
                    <p class="font-medium">{{
                        segment.FlightSegmentInfo.ArrivalAirport['@attributes'].LocationCode
                        }}</p>
                    <p class="text-xs text-gray-600">{{
                        formatDateTime(segment.FlightSegmentInfo['@attributes'].ArrivalDateTime)
                        }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500">Aircraft</span>
                    <p class="font-medium">{{
                        segment.FlightSegmentInfo.Equipment['@attributes'].AirEquipType
                        }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500">Class</span>
                    <p class="font-medium">{{
                        segment.FlightSegmentInfo['@attributes'].CabinClass }}</p>
                </div>
            </div>
        </div>

        <!-- Passenger Seat Selection -->
        <div class="p-4">
            <div v-for="pax in seatEligiblePassengers" :key="pax.index"
                class="mb-6 last:mb-0">
                <div class="bg-white border border-gray-200 rounded p-4">
                    <!-- Passenger Header -->
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <p class="font-medium text-gray-900">
                                {{ pax.passenger.title }} {{ pax.passenger.first_name }} {{
                                pax.passenger.last_name }}
                                <span class="text-sm text-gray-500 ml-2">({{
                                    pax.passenger.type }})</span>
                            </p>
                        </div>
                        <Badge
                            v-if="getSelectedSeat(segment?.FlightSegmentInfo['@attributes']?.RPH, getPassengerTravelerRef(pax.index))"
                            variant="secondary" class="bg-primary/10 text-primary">
                            Selected: {{
                                getSelectedSeat(segment?.FlightSegmentInfo['@attributes']?.RPH,
                            getPassengerTravelerRef(pax.index)) }}
                        </Badge>
                    </div>

                    <!-- Seat Map with new styling -->
                    <div class="overflow-x-auto">
                        <div class="min-w-max p-4 bg-gray-50 rounded-lg">
                            <template v-if="segment?.SeatMapDetails?.CabinClass?.RowInfo?.length > 0">
                                <div class="inline-block">
                                    <!-- Header with row numbers -->
                                    <div class="flex gap-2">
                                        <div class="w-16 h-8 flex items-end justify-center pb-1">
                                            <span class="text-xs font-medium text-gray-400">Row →</span>
                                        </div>
                                        <div v-for="row in segment?.SeatMapDetails?.CabinClass?.RowInfo" 
                                             :key="'header-'+row['@attributes']?.RowNumber"
                                             class="w-10 text-center">
                                            <div class="h-8 flex items-end justify-center pb-1">
                                                <span class="text-xs font-medium text-gray-700 bg-gray-200 px-2 py-1 rounded">{{ row['@attributes']?.RowNumber }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Seat letters rows -->
                                    <div v-for="letter in ['A', 'B', 'C', 'D', 'E', 'F']" 
                                         :key="'col-'+letter"
                                         class="flex items-center">
                                        <div class="w-16 h-10 flex items-center">
                                            <span class="text-xs font-medium text-gray-700 bg-gray-100 px-2 py-1 rounded">{{ letter }}</span>
                                        </div>
                                        
                                        <div class="flex gap-2">
                                            <div v-for="row in segment?.SeatMapDetails?.CabinClass?.RowInfo" 
                                                 :key="'seat-'+row['@attributes']?.RowNumber+'-'+letter"
                                                 class="relative group">
                                                <template v-for="seat in row.SeatInfo" :key="seat.Summary['@attributes'].SeatNumber">
                                                    <div v-if="seat.Summary['@attributes'].SeatNumber.endsWith(letter)">
                                                        <div v-if="seat.Availability === 'NoSeatHere'"
                                                            class="w-10 h-10"></div>
                                                        <div v-else-if="seat.Availability === 'SeatOccupied'"
                                                            class="w-10 h-10 bg-gray-300 border border-gray-500 rounded flex items-center justify-center text-xs text-gray-700 cursor-not-allowed"
                                                            title="Occupied">
                                                            {{ seat.Summary['@attributes'].SeatNumber }}
                                                        </div>
                                                        
                                                        <div v-else-if="isSeatSelected(segment?.FlightSegmentInfo['@attributes']?.RPH, getPassengerTravelerRef(pax.index), row['@attributes'].RowNumber, seat.Summary['@attributes'].SeatNumber)"
                                                            @click="selectSeat(segment?.FlightSegmentInfo['@attributes']?.RPH, getPassengerByIndex(pax.index), row['@attributes'].RowNumber, seat.Summary['@attributes'].SeatNumber, seat)"
                                                            class="w-10 h-10 my-1 rounded cursor-pointer transition-all hover:scale-110 flex items-center justify-center text-xs font-medium"
                                                            :style="{
                                                                border: '3px solid #3b82f6',
                                                                boxShadow: '0 2px 8px rgba(0,0,0,0.1)'
                                                            }"
                                                            :class="getSeatColorClass(seat?.Service?.Fee?.['@attributes']?.Amount || 0)">
                                                            {{ seat.Summary['@attributes'].SeatNumber }}
                                                            
                                                            <!-- Tooltip -->
                                                            <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50 pointer-events-none shadow-lg">
                                                                <div class="font-bold">Seat {{ seat.Summary['@attributes'].SeatNumber }}</div>
                                                                <div>{{ seat.Features?.join(', ') || 'Standard' }}</div>
                                                                <div class="font-bold text-yellow-300">
                                                                     {{ formatAmount(seat?.Service?.Fee?.['@attributes']?.Amount || 0) }}
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div v-else
                                                            @click="selectSeat(segment?.FlightSegmentInfo['@attributes']?.RPH, getPassengerByIndex(pax.index), row['@attributes'].RowNumber, seat.Summary['@attributes'].SeatNumber, seat)"
                                                            class="w-10 h-10 my-1 rounded cursor-pointer transition-all hover:scale-110 flex items-center justify-center text-xs font-medium"
                                                            :class="getSeatColorClass(seat?.Service?.Fee?.['@attributes']?.Amount || 0)"
                                                            :style="{
                                                                border: seat?.Service?.Fee?.['@attributes']?.Amount > 15000 ? '2px solid #1e40af' : '1px solid rgba(0,0,0,0.2)',
                                                                boxShadow: seat?.Service?.Fee?.['@attributes']?.Amount > 15000 ? '0 2px 8px rgba(0,0,0,0.3)' : 'none'
                                                            }">
                                                            {{ seat.Summary['@attributes'].SeatNumber }}
                                                            
                                                            <!-- Tooltip -->
                                                            <div class="absolute bottom-full left-1/2 transform -translate-x-1/2 mb-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap z-50 pointer-events-none shadow-lg">
                                                                <div class="font-bold">Seat {{ seat.Summary['@attributes'].SeatNumber }}</div>
                                                                <div>{{ seat.Features?.join(', ') || 'Standard' }}</div>
                                                                <div class="font-bold text-yellow-300">
                                                                     {{ formatAmount(seat?.Service?.Fee?.['@attributes']?.Amount || 0) }}
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Feature Indicators (small dots) -->
                                                        <div v-if="seat.Features && (seat.Features.includes('ExitRow') || seat.Features.includes('Overwing'))"
                                                            class="absolute -top-1 -right-1 flex">
                                                            <span v-if="seat.Features.includes('ExitRow')"
                                                                class="w-2 h-2 bg-orange-400 rounded-full ring-1 ring-white"
                                                                title="Exit Row"></span>
                                                            <span v-if="seat.Features.includes('Overwing')"
                                                                class="w-2 h-2 bg-blue-400 rounded-full ring-1 ring-white -ml-1"
                                                                title="Overwing"></span>
                                                        </div>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Seat Price Info -->
                    <div v-if="getSelectedSeatPrice(segment?.FlightSegmentInfo['@attributes']?.RPH, getPassengerTravelerRef(pax.index))"
                        class="mt-4 p-3 bg-primary/5 border border-primary/20 rounded-lg">
                        <div class="flex justify-between items-center">
                            <p class="text-sm font-medium">
                                Seat {{
                                    getSelectedSeat(segment?.FlightSegmentInfo['@attributes']?.RPH,
                                getPassengerTravelerRef(pax.index)) }}
                            </p>
                            <p class="text-sm font-semibold text-primary">
                                 {{
                                    formatAmount(getSelectedSeatPrice(segment?.FlightSegmentInfo['@attributes']?.RPH,
                                getPassengerTravelerRef(pax.index))) }}
                            </p>
                        </div>
                        <p v-if="getSelectedSeatFeatures(segment?.FlightSegmentInfo['@attributes']?.RPH, getPassengerTravelerRef(pax.index))"
                            class="text-xs text-gray-600 mt-1">
                            Features: {{
                                getSelectedSeatFeatures(segment?.FlightSegmentInfo['@attributes']?.RPH,
                            getPassengerTravelerRef(pax.index)) }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
        <div v-else-if = "!processedSeatMapData.length && !isLoading" class="bg-white rounded border border-gray-200 p-12 text-center">
            <p class="text-gray-500">No seat map data available.</p>
        </div>
        </template>
    </AccordionContent>
</AccordionItem>
                        <!-- Extras Accordion -->
                        <AccordionItem value="extras" class="bg-white rounded border border-gray-200 overflow-hidden">
                            <AccordionTrigger class="px-4 py-3 hover:no-underline">
                                <div class="flex items-center justify-between w-full">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center">
                                            <Package class="w-4 h-4 text-primary" />
                                        </div>
                                        <div class="text-left">
                                            <h3 class="font-semibold text-gray-900">Extras & Baggage</h3>
                                            <p class="text-xs text-gray-500">Add baggage, meals, and other services</p>
                                        </div>
                                    </div>
                                    <Badge v-if="selectedAncillariesList.length > 0" variant="secondary" class="ml-2">
                                        {{ selectedAncillariesList.length }} items
                                    </Badge>
                                </div>
                            </AccordionTrigger>
                            <AccordionContent class="px-4 pb-4">
                                <div v-if="isLoading"
                                    class="flex items-center justify-center py-12">
                                    <div class="text-center">
                                        
                                        <Spinner />
                                    </div>
                                </div>
                                <div v-if="!seatEligiblePassengerCount && !isLoading" class="bg-white rounded border border-gray-200 p-12 text-center">
                                    <p class="text-gray-500">Ancillaries are not available for infants.</p>
                                </div>
                                <template v-else>
                                <div v-if = "ancillarySegments && ancillarySegments.length > 0 && !isLoading" v-for="(segment, segIdx) in ancillarySegments" :key="segIdx"
                                    class="border border-gray-200 rounded overflow-hidden mb-4 last:mb-0">

                                    <!-- Segment Header -->
                                    <div class="p-4 bg-gradient-to-r from-gray-50 to-white border-b border-gray-200">
                                        <div class="flex items-center gap-3">
                                            <Badge class="bg-primary text-white">Segment {{ segIdx + 1 }}</Badge>
                                            <span class="text-sm font-medium text-gray-900">
                                                {{
                                                    segment.FlightSegmentInfo.DepartureAirport['@attributes'].LocationCode
                                                }}
                                                →
                                                {{ segment.FlightSegmentInfo.ArrivalAirport['@attributes'].LocationCode
                                                }}
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-2">
                                            {{
                                                formatDateTime(segment.FlightSegmentInfo['@attributes'].DepartureDateTime)
                                            }}
                                            • Flight {{ segment.FlightSegmentInfo['@attributes'].FlightNumber }}
                                        </div>
                                    </div>

                                    <!-- Ancillary Groups -->
                                    <div class="p-5 space-y-6">
                                        <div v-for="(group, gIdx) in getItemsArray(segment.AncillaryItemSets?.AncillaryItemSet)"
                                            :key="gIdx">
                                            <!-- Group Title -->
                                            <div class="mb-3">
                                                <h4 class="font-semibold text-gray-900">{{
                                                    group['@attributes'].GroupTitle }}</h4>
                                                <p v-if="group['@attributes'].GroupDescription"
                                                    class="text-sm text-gray-500 mt-0.5">
                                                    {{ group['@attributes'].GroupDescription }}
                                                </p>
                                            </div>

                                            <!-- Items by passenger -->
                                            <div class="space-y-4">
                                                <div v-for="pax in seatEligiblePassengers" :key="pax.index"
                                                    class="border-t border-gray-100 first:border-0 pt-4 first:pt-0">

                                                    <!-- Passenger name -->
                                                    <p class="text-sm font-medium text-gray-700 mb-3">
                                                        {{ pax.passenger.first_name }} {{ pax.passenger.last_name }}
                                                        <span class="text-xs text-gray-500 ml-2">({{ pax.passenger.type
                                                            }})</span>
                                                    </p>

                                                    <!-- Items grid -->
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                                        <label
                                                            v-for="item in getItemsArray(group.AncillaryItems?.AncillaryItem)"
                                                            :key="item['@attributes'].ItemCode"
                                                            class="relative flex items-center p-4 border rounded cursor-pointer hover:border-primary transition-all group"
                                                            :class="{
                                                                'border-primary bg-primary/5 ring-1 ring-primary': isSegmentSelected(segIdx, pax.index, group['@attributes'].GroupCode, item['@attributes'].ItemCode),
                                                                'opacity-50 bg-gray-50 cursor-not-allowed': item['@attributes'].Available === 'false',
                                                                'border-gray-200 hover:shadow-md': !isSegmentSelected(segIdx, pax.index, group['@attributes'].GroupCode, item['@attributes'].ItemCode)
                                                            }">
                                                            <div class="flex items-start gap-3 w-full">
                                                                <input
                                                                    :type="group['@attributes'].MultipleChoice === 'true' ? 'checkbox' : 'radio'"
                                                                    :name="`extras-${segIdx}-${gIdx}-${pax.index}`"
                                                                    :value="item['@attributes'].ItemCode"
                                                                    :checked="isSegmentSelected(segIdx, pax.index, group['@attributes'].GroupCode, item['@attributes'].ItemCode)"
                                                                    :disabled="item['@attributes'].Available === 'false'"
                                                                    @change="toggleSegmentAncillary(segIdx, pax.index, group['@attributes'].GroupCode, item, $event, pax.passenger.type)"
                                                                    class="mt-1 h-4 w-4 text-primary focus:ring-primary rounded" />
                                                                <div class="flex-1">
                                                                    <p class="text-sm font-medium text-gray-900">{{
                                                                        item['@attributes'].ItemTitle }}</p>
                                                                    <p class="text-xs text-gray-500 mt-1">{{
                                                                        formatAmount(item['@attributes'].ChargeAmount)
                                                                        }}</p>
                                                                </div>
                                                            </div>
                                                        </label>

                                                        <!-- None option for radio groups -->
                                                        <label v-if="group['@attributes'].MultipleChoice === 'false'"
                                                            class="relative flex items-center p-4 border border-gray-200 rounded cursor-pointer hover:border-gray-300 transition-all"
                                                            :class="{ 'border-primary bg-primary/5': !hasAnySelectionInGroup(segIdx, pax.index, group['@attributes'].GroupCode) }">
                                                            <div class="flex items-start gap-3 w-full">
                                                                <input type="radio"
                                                                    :name="`extras-${segIdx}-${gIdx}-${pax.index}`" value=""
                                                                    :checked="!hasAnySelectionInGroup(segIdx, pax.index, group['@attributes'].GroupCode)"
                                                                    @change="clearSegmentGroup(segIdx, pax.index, group['@attributes'].GroupCode)"
                                                                    class="mt-1 h-4 w-4 text-primary" />
                                                                <div class="flex-1">
                                                                    <p class="text-sm font-medium text-gray-600">None
                                                                    </p>
                                                                    <p class="text-xs text-gray-400 mt-1">Free</p>
                                                                </div>
                                                            </div>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                    <div v-else-if="ancillarySegments && ancillarySegments.length === 0 && !isLoading"
                                        class="bg-white rounded border border-gray-200 p-12 text-center">
                                        <p class="text-gray-500">No ancillaries data available.</p>
                                        </div>  
                                </template>
                            </AccordionContent>
                        </AccordionItem>
                    </Accordion>


                </div>

                <!-- Right Side - Flight Details & Price Summary -->
                <div class="lg:col-span-2">
                    <div class="sticky top-2">
                        <!-- Timer -->
                        <div v-if="countdown !== null && countdown !== '00:00'"
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

                        <!-- Flight Details -->
                        <div class="divide-y divide-gray-100 bg-white">
                            <div v-for="(flight, flightIndex) in flightData?.leg?.flights" :key="flightIndex">
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
                                                        <div class="text-xs text-gray-500">{{ segment?.flight_number }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="text-sm font-medium text-gray-900">{{
                                                        formatDate(segment?.departure_at)
                                                        }}</div>
                                                    <div class="text-xs text-gray-500">{{ segment?.from?.name }} ({{
                                                        segment?.from?.iata
                                                        }})</div>
                                                </div>
                                            </div>
                                            <!-- Flight Path -->
                                            <div class="flex items-center justify-center">
                                                <div class="w-full max-w-xs">
                                                    <div class="flex items-center justify-between mb-1">
                                                        <span class="text-xs font-medium text-gray-900">{{
                                                            moment(segment?.departure_at).format("HH:mm") }}</span>
                                                        <span class="text-xs font-medium text-gray-900">{{
                                                            moment(segment?.arrival_at).format("HH:mm") }}</span>
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
                                                        <span class="text-xs text-gray-400">{{ segment?.from?.iata
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
                                                        formatDate(segment?.arrival_at) }}
                                                    </div>
                                                    <div class="text-xs text-gray-500">{{ segment?.to?.name }} ({{
                                                        segment?.to?.iata }})
                                                    </div>
                                                </div>
                                                <div class="text-xs text-gray-400">{{ segment?.aircraft?.model }}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Price Details -->
                        <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
                            <div class="flex p-4 items-center justify-between">
                                <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Price Details</h3>
                            </div>
                            <div class="">
                                <div v-for="(flight, flightIndex) in flightData?.leg?.flights" :key="flightIndex">
                                    <div
                                        class="text-xs sm:text-sm font-semibold text-gray-900 my-1 sm:my-2 flex items-center gap-1 sm:gap-2 px-2">
                                        <div class="w-1 h-1 sm:w-1.5 sm:h-1.5 bg-primary rounded-full"></div>
                                        {{ flight.from.iata }} → {{ flight.to.iata }}
                                    </div>
                                    <div v-for="(fare, fareIndex) in flight?.fares" :key="fareIndex">
                                        <div v-if="selectedFares?.includes(fare.ref_id)" class="">
                                            <Accordion class="" type="multiple" collapsible>
                                                <template v-for="(passengerFare, index) in fare.passenger_fares"
                                                    :key="index">
                                                    <AccordionItem :value="`fare-${flightIndex}-${fareIndex}-${index}`"
                                                        class="overflow-hidden">
                                                        <AccordionTrigger
                                                            class="px-2 py-2 border-b grid grid-cols-[1fr_auto_auto] items-center hover:no-underline gap-1">
                                                            <div class="flex items-center gap-2">
                                                                <span
                                                                    class="text-xs sm:text-sm font-bold text-gray-600">
                                                                    {{ passengerFare.traveler_type }} X {{
                                                                    passengerFare.total_passenger }}
                                                                </span>
                                                            </div>
                                                            <span
                                                                class="text-sm sm:text-base font-bold text-primary text-right whitespace-nowrap">
                                                                {{ formatAmount(parseFloat(passengerFare.base_price ||
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
                                                        <AccordionContent class="px-3 sm:px-4 sm:pr-7 pb-3 space-y-2">
                                                            <div class="flex justify-between items-center">
                                                                <span class="text-xs sm:text-sm text-gray-600">Base
                                                                    Fare</span>
                                                                <span class="text-xs sm:text-sm font-medium">
                                                                    {{ formatAmount(
                                                                        ((calculateFareMargin(
                                                                            parseFloat(passengerFare?.base_price) || 0,
                                                                            fare?.margin_amount,
                                                                            fare?.margin_type,
                                                                            fare?.amount_type,
                                                                        ) +
                                                                            parseFloat(CustomerMargin?.other_charges || 0) +
                                                                            parseFloat(calculateCustomerMargin(
                                                                                passengerFare?.base_price,
                                                                                CustomerMargin?.value?.discount || 0,
                                                                                CustomerMargin?.value?.margin_amount || 0,
                                                                            ))) *
                                                                            passengerCount) +
                                                                        parseFloat(passengerFare?.base_price || 0)
                                                                    ) }}
                                                                </span>
                                                            </div>
                                                            <div class="flex justify-between items-center">
                                                                <span
                                                                    class="text-xs sm:text-sm text-gray-600">Taxes</span>
                                                                <span class="text-xs sm:text-sm font-medium">{{
                                                                    formatAmount(passengerFare?.taxes) }}</span>
                                                            </div>
                                                            <div class="flex justify-between items-center">
                                                                <span
                                                                    class="text-xs sm:text-sm text-gray-600">Fees</span>
                                                                <span class="text-xs sm:text-sm font-medium">{{
                                                                    formatAmount(passengerFare?.fees) }}</span>
                                                            </div>
                                                            <div class="flex justify-between items-center">
                                                                <span class="text-xs sm:text-sm text-gray-600">Service
                                                                    Charges</span>
                                                                <span class="text-xs sm:text-sm font-medium">{{
                                                                    formatAmount(passengerFare.service_charges)
                                                                    }}</span>
                                                            </div>
                                                            <hr class="border-dashed border-gray-300" />
                                                            <div class="flex justify-between items-center rounded">
                                                                <span
                                                                    class="text-xs sm:text-sm font-medium text-gray-700">Amount</span>
                                                                <span
                                                                    class="text-sm sm:text-base font-bold text-primary">
                                                                    {{ formatAmount(
                                                                        parseFloat(passengerFare.base_price || 0) +
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
                                                            </div>
                                                        </AccordionContent>
                                                    </AccordionItem>
                                                </template>
                                            </Accordion>
                                            <div
                                                class="flex justify-between items-center bg-gray-50 p-2 sm:px-4 rounded">
                                                <span class="text-xs sm:text-sm font-bold text-gray-700">Amount</span>
                                                <span class="text-sm sm:text-base font-bold text-primary">
                                                    {{ formatAmount(calculateTotalFare(fare) + marginPerFlight) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex p-2 justify-between items-center">
                                <span class="text-xs sm:text-sm text-gray-600">Add-ons</span>
                                <span class="text-xs sm:text-sm font-medium">{{ formatAmount(grandTotal) }}</span>
                            </div>
                            <div class="flex justify-between items-center bg-gray-50 p-2 rounded">
                                <span class="text-base sm:text-sm font-semibold text-gray-900">Total Amount</span>
                                <span class="text-sm sm:text-lg font-bold text-primary">{{ formatAmount(amount =
                                    calculateGrandTotal())
                                    }}</span>
                            </div>
                        </div>

                        <!-- Complete Booking -->
                        <div class="sticky top-4 mt-4">
                            <div class="bg-white shadow-sm border border-gray-200 overflow-hidden">
                                <div class="bg-gray-50 border-b border-gray-200 p-3">
                                    <h2 class="text-base font-medium text-gray-900">Complete Booking</h2>
                                </div>
                                <div class="p-3">
                                    <div class="space-y-3">
                                        <div class="flex items-start space-x-2">
                                            <input type="checkbox" id="terms" class="mt-1" />
                                            <label for="terms" class="text-xs text-gray-500 leading-relaxed">
                                                I understand and agree with the Privacy Policy, the User <a href="#"
                                                    class="text-primary hover:underline">Agreement and Terms</a> of
                                                Service of
                                                Jetze.pk
                                            </label>
                                        </div>
                                        <Button v-if="isPatchingAncillaries"
                                            class="w-full bg-white border-none text-sm">
                                            <Spinner/>
                                        </Button>
                                        <Button v-else @click="proceedToPayment"
                                            class="w-full bg-primary hover:bg-primary/90 text-sm">
                                            {{ "Proceed To Payment" }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.seat-map-container {
    scrollbar-width: thin;
    scrollbar-color: #cbd5e0 #f1f5f9;
}

.seat-map-container::-webkit-scrollbar {
    height: 6px;
}

.seat-map-container::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 3px;
}

.seat-map-container::-webkit-scrollbar-thumb {
    background-color: #cbd5e0;
    border-radius: 3px;
}

.seat-map-container::-webkit-scrollbar-thumb:hover {
    background-color: #94a3b8;
}
</style>
