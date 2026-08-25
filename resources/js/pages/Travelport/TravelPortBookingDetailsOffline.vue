    <script setup>
    import Button from "@/components/ui/button/Button.vue";
    import { Switch } from "@/components/ui/switch";
    import { MoveRight, CircleChevronRight, PlaneIcon, ClockIcon, CalendarIcon, UserIcon, EyeOff, PrinterIcon, MailIcon, Download, EyeIcon } from "lucide-vue-next";
    import html2pdf from "html2pdf.js";
    import {
        AlertDialog,
        AlertDialogAction,
        AlertDialogCancel,
        AlertDialogContent,
        AlertDialogDescription,
        AlertDialogFooter,
        AlertDialogHeader,
        AlertDialogTitle,
        AlertDialogTrigger,
    } from "@/components/ui/alert-dialog";
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogFooter,
        DialogHeader,
        DialogTitle,
        DialogTrigger,
    } from "@/components/ui/dialog";
    import { calculateLayover, formatDateTime, getFormattedDates, getDuration, calculateLayoverTime, getAdjustedDateTime } from "@/lib/utils";
    import { useRoute, useRouter } from "vue-router";
    import { useStore } from "vuex";
    import { computed, onMounted, onUnmounted, ref, watch } from "vue";
    import { useAuthStore } from "@/services/stores/auth";
    import html2canvas from "html2canvas";
    import { cn, formatAmount, calculateLayoverDetails, calculateFinalPrice } from "@/lib/utils";
    import {
        FETCH_BOOKING_DATA,
        FETCH_AGENT_DATA,
        FETCH_BOOKING_DETAILS,
        FETCH_PNR_DETAILS,
        CANCEL_BOOKING,
        CONFIRM_BOOKING,
        APPROVE_BOOKING,
        SAVE_AGENT_CHARGES,
        VOID_BOOKING,
        SEND_EMAIL,
        FETCH_CUSTOMER_MARGIN,
        SEND_REPLY,
        FETCH_MODIFY_REQUEST_DATA,
        UPDATE_STATUS,
        SAVE_REQUEST,
        UPDATE_BOOKING_AMOUNT,
    } from "@/services/store/actions.type";

    import moment from "moment";
    import Badge from "@/components/ui/badge/Badge.vue";
    import Spinner from "@/components/common/Spinner.vue";
    import Input from "@/components/common/Input.vue";
    import Label from "@/components/ui/label/Label.vue";
    import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
    import { Textarea } from "@/components/ui/textarea";
    import { ChatBubbleIcon } from "@radix-icons/vue";

    const store = useStore();
    const route = useRoute();
    const authStore = useAuthStore();

    // Loading states for individual API calls
    const isBookingDetailsLoading = ref(true);
    const isPnrDetailsLoading = ref(false);
    const isAgentLoading = ref(true);
    const error = ref(null);

    // Combined loading state
    const isLoading = computed(() =>store.getters["flight/isLoading"]);

    // Dialog states
    const showDialog = ref(false);
    const isDialogOpen = ref(false);
    const isConfirmDialogOpen = ref(false);
    const successMessage = ref('');
    const isApproving = ref(false);

    const user = computed(() => authStore.user);
    const user_id = computed(() => user.value?.id);
    const agent_id = route.query.agent_id;
    const agentData = computed(() => store.getters["user/agentData"]);
    const bookingDetails = computed(() => store.getters["flight/bookingDetails"]);
    const booking_id = route.query.booking_id;
    const pnr = route.query.pnr;
    const bookingId = ref("");
    const pnrData = ref(null);
    const isChatOpen = ref(false);
    const selectedFares = ref([]);

    const pnrDetails = computed(() => store.getters["flight/pnrData"]?.data);
    const booking = ref(null);
    const flightData = ref(null);
    const custEmail = ref(null);
    const sooperResponse = ref(null);
    const isDetailsInfoVisible = ref(true);
    const totalTicketPrice = ref(0);
    const isEmailDialogOpen = ref(false);
    const isGrandTotalDialogOpen = ref(false);
    const editableGrandTotal = ref("");
    const isUpdatingGrandTotal = ref(false);
    const ticketNo = ref("");
    const airLinePnr = ref("");

    const isChargesOpen = ref(false);
    const charges = ref('');
    const chargesDate = ref('');
    const chargesDec = ref('');
    const chargeType = ref('charge');
    const validationErrors = ref([]);
    const router = useRouter();
    const replyMessage = ref("");
    const replyLoading = ref(false);
    const modifyRequestData = computed(() => store.getters["modifyRequest/requestData"]);
    const modifyDialogOpen = ref(false);
    const selectedReason = ref("");
    const message = ref("");
    watch(modifyRequestData, (newData) => {
        if (newData) {
            isChatOpen.value = true;
        }
    });

    // Timer for expiry time display
    const now = ref(Date.now())

    let expiryTimer

    onMounted(() => {
        expiryTimer = setInterval(() => {
            now.value = Date.now()
        }, 1000)
    })

    onUnmounted(() => clearInterval(expiryTimer))

    const getRemainingTime = (expiry) => {
        if (!expiry) return 'N/A'
        const expiryTime = new Date(expiry.replace(' ', 'T')).getTime()
        const diff = expiryTime - now.value
        if (diff <= 0) return 'Expired'
        const days = Math.floor(diff / (1000 * 60 * 60 * 24))
        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))
        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60))
        const seconds = Math.floor((diff % (1000 * 60)) / 1000)
        let result = ''
        if (days > 0) result += `${days}d `
        if (hours > 0 || days > 0) result += `${hours}h `
        result += `${minutes}m ${seconds.toString().padStart(2, '0')}s`
        return result
    }

    function parseData(data){
        try {
            return JSON.parse(data);
        } catch (e) {
            console.error("Failed to parse data:", e);
            return [];
        }
    }

    const normalizePassengerType = (type) => {
        const normalized = String(type || "").toUpperCase();
        if (["CNN", "CHD", "CHILD"].includes(normalized)) return "CHD";
        if (["INF", "INFANT"].includes(normalized)) return "INF";
        return "ADT";
    };

    const getSelectedFareRefs = () => {
        const refs = parseData(bookingDetails.value?.[0]?.fare_reference);
        return Array.isArray(refs) ? refs : [refs].filter(Boolean);
    };

    const getPassengerBaggage = (traveller, baggageType) => {
        const selectedFareRefs = getSelectedFareRefs();
        const passengerType = normalizePassengerType(traveller?.type);
        const values = [];

        flightData.value?.leg?.flights?.forEach((flight) => {
            flight?.fares
                ?.filter((fare) => selectedFareRefs.includes(fare?.ref_id))
                ?.forEach((fare) => {
                    fare?.baggage_policies
                        ?.filter((policy) => {
                            return (
                                normalizePassengerType(policy?.traveler_type) === passengerType &&
                                policy?.type === baggageType
                            );
                        })
                        ?.forEach((policy) => {
                            if (policy?.description) values.push(policy.description);
                        });
                });
        });

        return [...new Set(values)].join(", ") || "N/A";
    };

    function sendEmail() {
        store.dispatch("flight/" + SEND_EMAIL, {
            email: custEmail?.value ? custEmail?.value : bookingDetails?.value?.[0]?.main_email,
            booking_id: bookingDetails.value?.[0]?.flight_id,
            booking_source: route?.query?.booking_source
        });
        isEmailDialogOpen.value = false;
        custEmail.value = null;
    }

    function sendReply(newStatus = "pending") {
        if (!replyMessage.value.trim()) return;
        replyLoading.value = true;
        const adminMessage = {
            req_id: modifyRequestData.value.id,
            sender: "admin",
            sender_id: user_id.value,
            message: replyMessage.value
        };
        store.dispatch("modifyRequest/" + SEND_REPLY, adminMessage).then(() => {
            fetchModifyRequestData();
        });
        replyMessage.value = "";
        replyLoading.value = false;
    }

    function fetchModifyRequestData() {
        const modify_request_id = route.query.modify_request_id;
        const booking_id = route.query.booking_id;
        if (modify_request_id || booking_id) {
            store.dispatch("modifyRequest/" + FETCH_MODIFY_REQUEST_DATA, {
                modify_request_id: modify_request_id,
                booking_id: booking_id,
            });
        }
    }

    function openModifyRequestDialog() {
        modifyDialogOpen.value = true;
    }

    function closeModifyDialog() {
        modifyDialogOpen.value = false;
    }

    function submitModifyRequest() {
        if (!selectedReason.value) {
            alert('Please select a reason')
            return
        }
        if (!message.value.trim()) {
            alert('Please enter a message')
            return
        }
        store.dispatch('modifyRequest/' + SAVE_REQUEST, {
            booking_id: booking_id,
            reason: selectedReason.value,
            message: message.value,
            user_id: user_id.value,
        }).then(() => {
            fetchModifyRequestData();
            isChatOpen.value = true;
        });
        closeModifyDialog();
    }

    function openChargesDialog() {
        isChargesOpen.value = true;
    }

    async function saveCharges() {
        const errors = [];

        if (!charges.value || charges.value <= 0) {
            errors.push("Amount is required and must be greater than 0.");
        }

        if (!chargesDate.value) {
            errors.push("Date is required.");
        }

        if (!chargesDec.value) {
            errors.push("Description is required.");
        }

        if (errors.length > 0) {
            validationErrors.value = errors;
            return;
        }

        validationErrors.value = [];

        try {
            store.dispatch("user/" + SAVE_AGENT_CHARGES, {
                amount: Number(totalTicketPrice.value),
                date: chargesDate.value,
                description: chargesDec.value,
                chargeType: 'charge',
                user_id: route.query.agent_id,
            });

            store.dispatch("user/" + SAVE_AGENT_CHARGES, {
                amount: Number(totalTicketPrice.value) - Number(charges.value),
                date: chargesDate.value,
                description: chargesDec.value,
                chargeType: 'refund',
                user_id: route.query.agent_id,
            });

            await store.dispatch(`flight/${VOID_BOOKING}`, {
                pnr: pnr,
                booking_uuid: pnrData.value?.data?.uuid ?? "null",
                billable_price: pnrData.value?.data?.billable_price ?? "null",
                currency: pnrData.value?.data?.currency?.code ?? "null",
                flight_provider: route.query.flight_provider,
                pnr: route.query.pnr,
                bookingId: bookingDetails.value[0].id,
                booking_status: "voided",
                booking_source: route.query.booking_source,
            });

            charges.value = '';
            chargesDate.value = '';
            chargesDec.value = '';
            chargeType.value = 'charge';

            isChargesOpen.value = false;
            fetchAgent();
            fetchBookingDetails();

        } catch (error) {
            validationErrors.value = ['Something went wrong. Please try again.'];
            console.error(error);
        }
    }

    function parsePnrResponse() {
        try {
            const pnrResponseString = bookingDetails?.value?.[0]?.pnr_response;
                    sooperResponse.value = parseData(bookingDetails?.value?.[0]?.sooper_response);

            if (pnrResponseString) {
                pnrData.value = JSON.parse(pnrResponseString);
                flightData.value = parseFlightData(bookingDetails?.value?.[0]?.flight_data);
                selectedFares.value = bookingDetails?.value?.[0]?.fare_reference ? JSON.parse(bookingDetails.value[0].fare_reference) : [];
            } else {
                pnrData.value = null;
            }
        } catch (e) {
            console.error("Failed to parse pnr_response:", e);
            pnrData.value = null;
        }
    }

    const pnrLive = computed(() =>
        pnrDetails.value?.bookingId
            ? pnrDetails.value.bookingId
            : booking.value?.pnr || '-'
    );

    async function fetchAgent() {
        if (agent_id) {
            try {
                await store.dispatch(`user/${FETCH_AGENT_DATA}`, { userId: agent_id });
            } catch (err) {
                error.value = "Failed to load user data. Please try again.";
            } finally {
                isAgentLoading.value = false;
            }
        } else {
            error.value = "No user ID provided.";
            isAgentLoading.value = false;
        }
    }

    async function fetchBookingDetails() {
        if (!booking_id) {
            error.value = "No booking ID provided.";
            isBookingDetailsLoading.value = false;
            return;
        }
        try {
            await store.dispatch(`flight/${FETCH_BOOKING_DETAILS}`, { bookingId: booking_id });
            parsePnrResponse();
            flightData.value = parseFlightData(bookingDetails?.value[0]?.flight_data);
        } catch (err) {
            error.value = "Failed to fetch booking details.";
        } finally {
            isBookingDetailsLoading.value = false;
        }
    }

    async function fetchPnrDetails() {
        if (!pnr) {
            error.value = "No PNR provided.";
            isPnrDetailsLoading.value = false;
            return;
        }
        try {
            await store.dispatch(`flight/${FETCH_PNR_DETAILS}`, { flight_provider: route.query.flight_provider, pnr: route.query.pnr });
        } catch (err) {
            error.value = "Failed to fetch PNR details.";
        } finally {
            isPnrDetailsLoading.value = false;
        }
    }

    async function fetchAllData() {
        try {
            await Promise.all([fetchBookingDetails(), fetchPnrDetails()]);
            if (route.query.modify_request_id) {
                fetchModifyRequestData();
            }
        } catch (err) {
            error.value = "Failed to load data.";
        }
    }

    function handleCancelBooking() {
        error.value = '';
        isLoading.value = true;

        try {
            if (!pnr) {
                error.value = "No PNR provided.";
                return;
            }

            store.dispatch("flight/" + CANCEL_BOOKING, {
                pnr: route.query.pnr,
                bookingId: bookingDetails.value[0].id,
                booking_status: "canceled",
                booking_source: route.query.flight_provider,
            });
            isDialogOpen.value = false;

        } catch (err) {
            error.value = err.message || 'Failed to cancel booking';
        } finally {
            isLoading.value = false;
            fetchBookingDetails();
        }
    }

    function confirmBooking() {
        error.value = '';
        isLoading.value = true;
        if (!pnr) {
            error.value = "No PNR provided.";
            return;
        }
        store.dispatch("flight/" + CONFIRM_BOOKING, {
            pnr: route.query.pnr,
            pnrData: pnrData.value,
            bookingId: bookingDetails.value[0].id,
            booking_status: "ticketed",
            flight_provider: route.query.flight_provider,
            booking_source: route.query.booking_source,
        });

        showDialog.value = false;
        fetchBookingDetails();
    }

    async function approveAction() {
        isApproving.value = true;
        error.value = '';
        try {
            await store.dispatch(`flight/${APPROVE_BOOKING}`, {
                airline_pnr: airLinePnr.value,
                ticket_number: ticketNo.value,
                booking_id: booking_id,
                status: "ticketed",
            });
            await Promise.all([
                store.dispatch(`flight/${FETCH_BOOKING_DETAILS}`, { bookingId: booking_id }),
                store.dispatch(`flight/${FETCH_PNR_DETAILS}`, { pnr: pnr })
            ]);
            successMessage.value = "Booking approved successfully!";
            airLinePnr.value = '';
            ticketNo.value = '';
        } catch (err) {
            error.value = err.message || "Failed to approve booking.";
        } finally {
            isApproving.value = false;
        }
    }

    const formatDate = (dateString) => {
        const date = new Date(dateString);
        return date.toLocaleDateString("en-US", {
            weekday: "short",
            year: "numeric",
            month: "short",
            day: "numeric",
        });
    };

    const parseFlightData = (flightDataString) => {
        try {
            // console.log(flightDataString)
            return JSON.parse(flightDataString);
        } catch (error) {
            console.error("Error parsing flight data:", error);
            return null;
        }
    };

    const normalizeToArray = (value) => {
        if (Array.isArray(value)) return value;
        if (value == null) return [];
        return [value];
    };

    const extractDocumentsFromReceipts = (reservationResponse) => {
        const receipts = normalizeToArray(reservationResponse?.Reservation?.Receipt);
        return receipts.flatMap((receipt) => normalizeToArray(receipt?.Document));
    };

    const getTicketNumber = (index) => {
        const documents = extractDocumentsFromReceipts(pnrDetails?.value?.ReservationResponse);
        const documentNumber = documents?.[index]?.Number;
        if (documentNumber) return documentNumber;

       const cachedDocuments = extractDocumentsFromReceipts(sooperResponse?.value?.result?.ReservationResponse);
    const fallbackNumber = cachedDocuments?.[index]?.Number;
    return fallbackNumber || "N/A";
    };

    // Margin and amount values
    const passengerCount = computed(() => parseInt(bookingDetails?.value?.[0]?.pessangers?.length || 1));
    const agentAmount = computed(() => parseFloat(bookingDetails?.value?.[0]?.agent_markup || 0));
    const agentDiscount = computed(() => parseFloat(bookingDetails?.value?.[0]?.agent_discount || 0));
    const margin = computed(() => parseFloat(bookingDetails?.value?.[0]?.agent_margin || 0));
    const airportMargin = computed(() => parseFloat(bookingDetails?.value?.[0]?.airport_margin_amount || 0));

    const savedMarginTotal = computed(() => {
        return (agentAmount.value + margin.value + airportMargin.value - agentDiscount.value) || 0;
    });

    const marginPerFlight = computed(() => {
        const flightCount = flightData?.value?.leg?.flights?.length || 0;
        if (!flightCount) return 0;
        return savedMarginTotal.value / flightCount;
    });

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
        const airlineMargin = calculateFinalPrice(
            parseFloat(fare.base_price || 0),
            parseFloat(fare.margin_amount),
            fare.margin_type,
            fare.amount_type
        );
        const billable = parseFloat(fare.surchage || 0) +
                        parseFloat(fare.taxes || 0) +
                        parseFloat(fare.fees || 0) +
                        parseFloat(fare.service_charges || 0) +
                        parseFloat(fare.ancillaries_charges || 0) +
                        (parseFloat(airlineMargin));
        const total = billable + parseFloat(marginPerFlight.value);
        return total;
    }

    function calculateGrandTotal() {
        let total = 0;
        flightData?.value?.leg?.flights?.forEach((flight, index) => {
            flight?.fares?.forEach(fare => {
                if (selectedFares.value.includes(fare.ref_id)) {
                    total += calculateTotalFare(fare);
                }
            });
        });
        totalTicketPrice.value = total;
        return total;
    }

    function openGrandTotalDialog() {
        editableGrandTotal.value = Number(calculateGrandTotal() || 0).toFixed(2);
        isGrandTotalDialogOpen.value = true;
    }

    async function saveGrandTotalAmount() {
        const amount = Number(editableGrandTotal.value);
        if (!Number.isFinite(amount) || amount < 0) return;

        isUpdatingGrandTotal.value = true;
        await store.dispatch("flight/" + UPDATE_BOOKING_AMOUNT, {
            booking_id: bookingDetails.value?.[0]?.id || booking_id,
            amount,
        });
        await fetchBookingDetails();
        isUpdatingGrandTotal.value = false;
        isGrandTotalDialogOpen.value = false;
    }

    function fetchCustomerMarginValues() {
        store.dispatch("customerMargin/" + FETCH_CUSTOMER_MARGIN);
    }

    const printBooking = () => {
        const printContent = document.getElementById("print-section").innerHTML;
        const printContainer = document.createElement("div");
        printContainer.id = "print-container";
        printContainer.style.display = "none";
        printContainer.innerHTML = printContent;
        document.body.appendChild(printContainer);
        printContainer.style.display = "block";
        window.print();
        printContainer.style.display = "none";
        document.body.removeChild(printContainer);
    };

    const downloadPDF = () => {
        const element = document.getElementById("print-section");
        const options = {
            margin: 2,
            filename: `booking_${booking_id}.pdf`,
            image: { type: "jpeg", quality: 0.98 },
            html2canvas: {
                scale: 2,
                logging: true,
                useCORS: true,
                windowWidth: element.scrollWidth,
                windowHeight: element.scrollHeight,
            },
            jsPDF: { unit: "mm", format: "a4", orientation: "portrait" },
        };

        html2pdf()
            .from(element)
            .set(options)
            .save()
            .then(() => {
                element.style.display = "";
                element.style.visibility = "";
                element.style.opacity = "";
            });
    };

    const toggleChatOpen = () => {
        isChatOpen.value = !isChatOpen.value;
    }

    function toggleModifyRequestStatus(status){
        store.dispatch("modifyRequest/" + UPDATE_STATUS, {
            modify_request_id: modifyRequestData.value.id,
            status: status,
        }).then(() => {
            fetchModifyRequestData();
        });
    }

    const openDialog = () => {
        showDialog.value = true;
    };

    const closeDialog = () => {
        showDialog.value = false;
    };

    function toggleDetailedInfo() {
        isDetailsInfoVisible.value = !isDetailsInfoVisible.value;
    }

    onMounted(() => {
        if (user.value == null) {
            authStore.fetchUser();
        } else {
            fetchAgent();
        }
        fetchAllData();
        fetchCustomerMarginValues();
    });
    </script>

    <template>

        <section>

            <div class="min-h-screen bg-gray-100">
                <!-- Loading Spinner -->
                <div v-if="isLoading" class="fixed inset-0 bg-white bg-opacity-75 flex items-center justify-center z-50">
                    <Spinner />
                </div>

                <div v-else class=" mx-auto min-h-screen bg-gray-100 p-4">
                    <div v-if="route?.query?.booking_source == 1">
                        <!-- Action Buttons -->
                        <div v-for="booking in bookingDetails" :key="booking?.id"
                            class="bg-white rounded-lg shadow-sm p-4 mb-4 flex flex-wrap gap-2 justify-end print:hidden">

                            <button @click="printBooking"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center gap-2">
                                <PrinterIcon class="h-4 w-4" />
                                Print
                            </button>
                            <!-- <a target="blank" :href="bookingDetails[0]?.booking_invoice?.invoice_url">
                                <button class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center gap-2">
                                    <EyeIcon class="h-4 w-4" />
                                    View Invoice
                                </button>
                            </a> -->
                            <button @click="toggleDetailedInfo"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 flex items-center gap-2">
                                <EyeOff class="h-4 w-4" />
                                <span v-if="isDetailsInfoVisible">Hide Fare Details</span>
                                <span v-else>View Fare Details</span>
                            </button>
                            <button @click="downloadPDF"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary flex items-center gap-2">
                                <Download class="h-4 w-4" />
                                Download PDF
                            </button>
                            <Dialog  :open="isChargesOpen" @update:open="isChargesOpen = $event">
                                <button @click="openChargesDialog()"
                                    :hidden="['canceled', 'booked', 'voided'].includes(booking?.status)"
                                    :disabled="['canceled', 'voided'].includes(booking?.status)"
                                    class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary/50 disabled:cursor-not-allowed flex items-center gap-2">
                                    Void Booking
                                </button>
                                <DialogContent class="sm:max-w-[425px]">
                                    <DialogHeader>
                                        <DialogTitle class="text-2xl">Add Charges</DialogTitle>
                                    </DialogHeader>
                                    <div v-if="validationErrors.length > 0">
                                        <ul class="bg-red-100 p-4 rounded-md border border-destructive list-disc list-inside text-destructive">
                                            <li v-for="err in validationErrors" :key="err.id">{{ err }}</li>
                                        </ul>
                                    </div>
                                    <form @submit.prevent="saveCharges">
                                        <div class="mb-3">
                                            <Label for="amount">Total Amount:</Label>
                                            <Input class="" type="number" v-model="totalTicketPrice" readonly id="charges" placeholder="Amount in PKR" />
                                        </div>
                                        <div class="mb-3">
                                            <Label for="amount">Amount in PKR</Label>
                                            <Input class="" type="number" v-model="charges" id="charges" placeholder="Amount in PKR" />
                                        </div>
                                        <div class="mb-3">
                                            <Label for="amount">Date</Label>
                                            <Input class="" type="date" v-model="chargesDate" id="chargesDate" />
                                        </div>
                                        <div class="mb-3">
                                            <Label for="Description">Description</Label>
                                            <Textarea class="" type="text" v-model="chargesDec" id="chargesDec" placeholder="Description" />
                                        </div>
                                        <Button type="submit" class="float-right">Save</Button>
                                    </form>
                                </DialogContent>
                            </Dialog>
                            <button
                                :disabled="['canceled', 'issued', 'ticketed', 'void_requested', 'voided'].includes(booking?.status)"
                                v-if="booking?.status === 'booked'"
                                @click="openDialog"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary/50 disabled:cursor-not-allowed flex items-center gap-2">
                                Issue Ticket
                            </button>
                            <button
                                :disabled="['canceled', 'issued', 'ticketed', 'voided'].includes(booking?.status?.toLowerCase())"
                                v-if="booking?.status === 'booked'"
                                @click="isDialogOpen = true"
                                class="px-4 py-2 text-sm font-medium text-white bg-primary/90 rounded-md hover:bg-primary/50 disabled:cursor-not-allowed flex items-center gap-2">
                                Cancel Booking
                            </button>
                            <button @click="toggleChatOpen"
                                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 flex items-center gap-2">
                                <ChatBubbleIcon class="h-4 w-4" />
                                Chat
                            </button>
                            <!-- <button :disabled="modifyRequestData" @click="openModifyRequestDialog"
                                class="px-4 py-2 text-sm font-medium text-white bg-yellow-600 rounded-md hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2">
                                Modify Request
                            </button> -->
                        </div>

                        <!-- Cancel Dialog -->
                        <div v-if="isDialogOpen"
                            class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
                            @click.self="isDialogOpen = false">
                            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                                <div class="flex items-start justify-between mb-4">
                                    <h3 class="text-lg font-medium text-gray-900">Cancel Booking</h3>
                                    <button @click="isDialogOpen = false" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">Are you sure you want to cancel this booking? This action cannot be undone.</p>
                                    <div v-if="error" class="mt-3 p-3 bg-red-100 text-red-700 rounded-md text-sm">{{ error }}</div>
                                </div>
                                <div class="mt-6 flex justify-end space-x-3">
                                    <button @click="isDialogOpen = false" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 ">Cancel</button>
                                    <button @click="handleCancelBooking" class="px-4 py-2 bg-red-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-red-700">Confirm Cancellation</button>
                                </div>
                            </div>
                        </div>

                        <!-- Confirm Dialog -->
                        <div v-if="showDialog" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50" @click.self="showDialog = false">
                            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-6 transform transition-all">
                                <div class="flex items-start justify-between mb-4">
                                    <h3 class="text-lg font-medium text-gray-900">Confirm Booking</h3>
                                    <button @click="showDialog = false" class="text-gray-400 hover:text-gray-500 focus:outline-none">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500">Are you sure you want to confirm this booking?</p>
                                    <div v-if="error" class="mt-3 p-3 bg-red-100 text-red-700 rounded-md text-sm">{{ error }}</div>
                                </div>
                                <div class="mt-6 flex justify-end space-x-3">
                                    <button @click="showDialog = false" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 ">Cancel</button>
                                    <button @click="confirmBooking" class="px-4 py-2 bg-primary border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700">Confirm Booking</button>
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
                                        <Label class="block text-sm font-medium text-gray-700 mb-1">Agency Email: {{ agentData?.agent_data.company_email }}</Label>
                                        Or enter new one
                                        <Input type="text" v-model="custEmail" class="flex-1 mt-2 rounded-md border-gray-300 shadow-sm focus:border-[#0056FF] focus:ring-[#0056FF]" placeholder="Enter email" />
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-end space-x-3">
                                    <button @click="isEmailDialogOpen = false" class="px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium text-gray-700 ">Cancel</button>
                                    <button @click="sendEmail" class="px-4 py-2 bg-green-600 border border-transparent rounded-md text-sm font-medium text-white hover:bg-green-700">Send Email</button>
                                </div>
                            </div>
                        </div>

                        <!-- Modify Request Dialog -->
                        <div v-if="modifyDialogOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50">
                            <div class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl">
                                <h2 class="mb-5 text-xl font-semibold text-gray-900">Modify Request</h2>
                                <div class="mb-4">
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Reason for Modification</label>
                                    <select v-model="selectedReason" class="w-full rounded-md border border-gray-300 p-2 focus:border-blue-500 focus:outline-none">
                                        <option value="" disabled>Select a reason</option>
                                        <option value="change_scope">Change Scope</option>
                                        <option value="extend_deadline">Extend Deadline</option>
                                        <option value="refund">Request Refund</option>
                                        <option value="cancel">Cancel Booking</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="mb-6">
                                    <label class="mb-1.5 block text-sm font-medium text-gray-700">Message</label>
                                    <textarea v-model="message" rows="4" class="w-full rounded-md border border-gray-300 p-2 focus:border-blue-500 focus:outline-none" placeholder="Provide details about your request..."></textarea>
                                </div>
                                <div class="flex justify-end space-x-3">
                                    <button @click="closeModifyDialog" class="rounded-md border border-gray-300 px-4 py-2 text-gray-700 ">Cancel</button>
                                    <button @click="submitModifyRequest" class="rounded-md bg-blue-600 px-4 py-2 text-white hover:bg-blue-700">Submit Request</button>
                                </div>
                            </div>
                        </div>

                        <!-- Main Content -->
                        <div class="flex gap-4">
                            <!-- Print Section -->
                            <div :class="isChatOpen && modifyRequestData ? 'w-8/12' : 'w-full'" id="print-section">
                                <div v-for="booking in bookingDetails" :key="booking?.id"
                                    class="bg-white rounded-lg overflow-hidden mb-4 print:shadow-none print:border print:border-gray-300 print:mb-0 print:rounded-none">

                                    <!-- Header -->
                                    <div class="p-6 text-white print:text-black print:bg-white print:border-b print:border-gray-300 ">
                                        <div class="flex justify-between items-center">
                                            <div class="w-1/4">
                                                <img v-if="agentData?.agent_data?.logo" class="h-16 w-auto print:h-12" :src="agentData?.agent_data?.logo || ''" alt="Logo" />
                                            </div>
                                            <div class="w-2/4 text-black text-center border-l-2 border-white/30 pl-4 print:border-gray-400">
                                                <h1 class="text-2xl font-extrabold tracking-tight print:text-gray-900 mb-1">E-TICKET RECEIPT</h1>
                                                <div class="mt-2">
                                                    <p class="text-sm print:text-gray-700 mb-1">
                                                        <span class="font-medium">Status:</span>
                                                        <span :class="{
                                                            'text-green-600 font-bold': booking?.status === 'booked',
                                                            'text-yellow-600 font-bold': booking?.status === 'pending',
                                                            'text-red-600 font-bold': booking?.status === 'cancelled' || booking?.status === 'failed',
                                                            'text-blue-600 font-bold': booking?.status === 'confirmed',
                                                            'capitalize font-semibold print:text-gray-900': true
                                                        }">{{ booking?.status }}</span>
                                                    </p>
                                                    <div v-if="booking?.status === 'booked'" class="text-sm opacity-90 print:text-gray-700 mt-1">
                                                        <span class="font-medium">Expiry Time: </span>
                                                        <span class="inline-flex items-center gap-2 px-3 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-700 border border-amber-300">
                                                            {{ booking?.expiry_time ? moment(booking.expiry_time).format('DD-MMM-YYYY HH:mm') : 'N/A' }}
                                                            ({{ getRemainingTime(booking?.expiry_time) }})
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="w-1/4 text-black text-right border-l-2 border-white/30 pl-4 print:border-gray-400">
                                                <p class="text-sm print:text-gray-700"><span class="font-medium">Booking Ref:</span> {{ booking?.user?.agent_data?.agent_uid }}_{{ booking?.id + 1000 }}</p>
                                                <p class="text-sm print:text-gray-700"><span class="font-medium">GDS PNR:</span>
                                                    {{ pnrDetails?.Response?.Data?.pnrDetail?.PNRN ?? pnrDetails?.bookingId ?? booking?.itinerary_ref }}
                                                </p>
                                                <p class="text-sm print:text-gray-700"><span class="font-medium">Airline PNR:</span>
                                                    {{ pnrData?.ReservationResponse?.Reservation?.Receipt?.[1]?.Confirmation?.Locator?.value || 'N/A' }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Flight Information -->
                                    <!-- <pre>{{ parseFlightData(booking.flight_data) }}</pre> -->

                                    <!-- Flight Information -->
                            <div class="p-6 border-b border-gray-200 print-border-bottom print-border-gray print-break-inside-avoid">
                                <div v-if="parseFlightData(booking?.flight_data)?.original?.leg || parseFlightData(booking?.flight_data)?.leg">
                                    <div class="w-full bg-white rounded-xl overflow-hidden shadow-sm print-shadow-none">

                                        <div
                                            v-for="(flight, flightIndex) in (parseFlightData(booking.flight_data)?.original?.leg?.flights ?? parseFlightData(booking.flight_data)?.leg?.flights)"
                                            :key="flightIndex"
                                            class="overflow-hidden"
                                        >

                                        <div v-for="(segment, segmentIndex) in flight?.segments" :key="segmentIndex">
                                        <div class="relative transition-colors duration-150">
                                            <div class="flight-segment-row grid grid-cols-5 items-center gap-4 px-5 py-4 print-flight-row print-padding-x print-padding-y">

                                                <!-- Airline -->
                                                <div class="flex w-full items-center justify-center gap-3 min-w-0 print-col-2">
                                                    <img
                                                        :src="segment?.operating_carrier?.logo"
                                                        class="h-8 max-w-24 w-auto object-contain flex-shrink-0 print-img-small"
                                                        :alt="segment?.operating_carrier?.name"
                                                    />
                                                    <div class="min-w-0">
                                                        <p class="font-semibold text-gray-900 text-xs leading-tight print-text-sm print-font-semibold">
                                                            {{ segment?.operating_carrier?.name }}
                                                        </p>
                                                        <p class="text-[10px] text-gray-500 mt-0.5 print-text-10px">
                                                            {{ segment?.operating_carrier?.iata }}-{{ segment?.flight_number || '' }}
                                                        </p>
                                                    </div>
                                                </div>

                                                <!-- Departure + Arrow + Arrival -->
                                                <div class="col-span-3 grid w-full grid-cols-3 items-center gap-4 min-w-0 print-col-4">

                                                    <!-- Departure -->
                                                    <div class="min-w-0 text-center">
                                                        <div class="flex items-baseline justify-center gap-2">
                                                            <span class="font-bold text-gray-900 text-xl leading-tight print-text-xl print-font-bold">
                                                                {{ moment.parseZone(segment?.departure_at).format('HH:mm') }}
                                                            </span>
                                                            <span class="text-[10px] text-gray-500 print-text-10px">
                                                                {{ formatDate(segment?.departure_at) }}
                                                            </span>
                                                        </div>
                                                        <div class="mt-1">
                                                            <span class="font-bold text-gray-900 text-base print-text-base print-font-bold">{{ segment?.from?.iata }}</span>
                                                            <span class="text-[10px] text-gray-500 ml-1.5 print-text-10px">{{ segment?.from?.city?.name }}</span>
                                                            <div class="text-xs text-gray-500 mt-2 print-text-10px text-b">
                                                                        {{  segment?.from?.name }}
                                                                    </div>
                                                        </div>
                                                        <div v-if="segment?.from_terminal?.Gate || segment?.departure_terminal" class="text-[10px] text-gray-500 mt-1 print-text-10px">
                                                            Terminal {{ segment?.from_terminal?.Gate || segment?.departure_terminal }}
                                                        </div>
                                                    </div>

                                                    <!-- Arrow / dashed connector -->
                                                    <div class="relative flex w-full min-w-0 items-center justify-center">
                                                        <div class="absolute left-0 right-0 top-1/2 -translate-y-1/2 border-t border-dashed border-gray-300"></div>
                                                        <div class="relative z-10 flex items-center justify-center bg-white rounded-full border border-gray-200 shadow-sm p-1.5">
                                                            <PlaneIcon class="w-3.5 h-3.5 text-gray-400 -rotate-45" />
                                                        </div>
                                                    </div>

                                                    <!-- Arrival -->
                                                    <div class="min-w-0 text-center">
                                                        <div class="flex items-baseline justify-center gap-2">
                                                            <span class="font-bold text-gray-900 text-xl leading-tight print-text-xl print-font-bold">
                                                                {{ moment.parseZone(segment?.arrival_at).format('HH:mm') }}
                                                            </span>
                                                            <span class="text-[10px] text-gray-500 print-text-10px">
                                                                {{ formatDate(segment?.arrival_at) }}
                                                            </span>
                                                        </div>
                                                        <div class="mt-1">
                                                            <span class="font-bold text-gray-900 text-base print-text-base print-font-bold">{{ segment?.to?.iata }}</span>
                                                            <span class="text-[10px] text-gray-500 ml-1.5 print-text-10px">{{ segment?.to?.city?.name }}</span>
                                                            <div class="text-xs text-gray-500 mt-2 print-text-10px text-b">
                                                                        {{  segment?.to?.name }}
                                                                    </div>
                                                        </div>
                                                        <div v-if="segment?.to_terminal?.Gate || segment?.arrival_terminal" class="text-[10px] text-gray-500 mt-1 print-text-10px">
                                                            Terminal {{ segment?.to_terminal?.Gate || segment?.arrival_terminal }}
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Status / Cabin / Baggage -->
                                                <div class="flex w-full flex-col items-center gap-1.5 text-center print-col-1">
                                                    <div>
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-green-50 text-green-700 font-medium text-[9px] uppercase tracking-wide border border-green-200">
                                                            <span :class="{
                                                                'text-green-600 font-bold': booking.status === 'booked',
                                                                'text-yellow-600 font-bold': booking.status === 'pending',
                                                                'text-red-600 font-bold': booking.status === 'cancelled' || booking.status === 'failed',
                                                                'text-blue-600 font-bold': booking.status === 'confirmed',
                                                                'capitalize font-semibold print-text-gray-900': true
                                                            }">{{ booking.status }}</span>
                                                        </span>
                                                    </div>
                                                    <div class="text-[10px] text-gray-600 print-text-10px">
                                                        <span class="font-medium">{{ segment?.cabin_class || '' }}</span>
                                                        <span class="text-gray-400 mx-1">•</span>
                                                        <span class="text-gray-500">{{ segment?.booking_class ?? segment?.rbd_code ?? '' }}</span>
                                                    </div>
                                                    <div class="text-[10px] text-gray-600 leading-tight print-text-10px">
                                                        <span class="font-medium">Baggage:</span>
                                                        <span class="text-gray-500 ml-1">
                                                            {{
                                                                flight?.fares?.[0]?.baggage_policies?.find(
                                                                    bp => bp.segment_ref_id === segment.ref_id && bp.type === 'checked'
                                                                )?.description ||
                                                                flight?.fares?.[0]?.baggage_policies?.find(
                                                                    bp => bp.segment_ref_id === segment.ref_id && bp.type === 'carry'
                                                                )?.description || 'N/A'
                                                            }}
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                                <!-- Layover -->
                                                <div
                                                    v-if="segmentIndex < flight?.segments?.length - 1"
                                                    class="relative mx-5 my-2 print-mb-sm"
                                                >
                                                    <div class="absolute inset-x-0 -top-2.5 flex justify-center">
                                                        <div class="bg-white px-3 py-0.5 rounded-full border border-gray-200 shadow-sm">
                                                            <div class="flex items-center gap-1.5">
                                                                <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                                </svg>
                                                                <span class="text-[9px] font-medium text-gray-500 print-text-9px">
                                                                    Layover {{ Math.floor((flight?.segments?.[segmentIndex + 1]?.layover_time || 0) / 60) }}h {{ (flight?.segments?.[segmentIndex + 1]?.layover_time || 0) % 60 }}m
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="border-t border-dashed border-gray-200"></div>
                                                </div>
                                            </div>

                                            <div
                                                v-if="
                                                    flightIndex === 0 &&
                                                    (
                                                        (parseFlightData(booking.flight_data)?.original?.leg?.flights?.length || 0) > 1 ||
                                                        (parseFlightData(booking.flight_data)?.leg?.flights?.length || 0) > 1
                                                    )
                                                "
                                                class="relative my-6 print-mb-sm"
                                            >
                                                <div class="absolute inset-0 flex items-center">
                                                    <div class="w-full border-t border-gray-200"></div>
                                                </div>
                                                <div class="relative flex justify-center">
                                                    <span class="bg-white px-6 py-1.5 text-xs font-semibold text-primary uppercase tracking-wider rounded-full border border-blue-200 shadow-sm print-text-xs print-font-semibold print-text-primary">
                                                        Return Journey
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                                    <!-- Passenger Details -->
                                    <div class="p-6 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid">
                                        <!-- Section Header -->
                                        <div class="flex items-center gap-2 mb-4">
                                            <UserIcon class="h-5 w-5 text-blue-600" />
                                            <h2 class="text-sm font-bold text-gray-800 uppercase tracking-wide">PASSENGER & TICKET DETAILS</h2>
                                        </div>

                                        <!-- TABLE - Same PDF Style as Flight Information: only outer border -->
                                        <div class="w-full border border-gray-300 rounded-md overflow-hidden text-[11px] shadow-sm print:shadow-none">

                                            <!-- TABLE HEADER - Single row with two columns -->
                                            <div class="bg-gray-100 border-b border-gray-300">
                                            <div class="grid grid-cols-2">
                                                <div class="px-4 py-2.5 font-semibold text-gray-700 text-xs uppercase tracking-wide border-r border-gray-300">
                                                Traveller Name
                                                </div>
                                                <div class="px-4 py-2.5 font-semibold text-gray-700 text-xs uppercase tracking-wide border-r border-gray-300">
                                                Ticket No
                                                </div>
                                               
                                            </div>
                                            </div>

                                            <!-- TABLE ROWS - Passenger list -->
                                            <div
                                            v-for="(traveller, index) in booking?.pessangers"
                                            :key="index"
                                            class="border-b border-gray-200 last:border-b-0  transition-colors"
                                            >
                                            <div class="grid grid-cols-2">
                                                <!-- Traveller Name Column -->
                                                <div class="px-4 py-2.5 border-r border-gray-200">
                                                <span class="uppercase text-gray-900 text-xs font-medium">
                                                    {{ traveller.title }} {{ traveller.first_name }} {{ traveller.last_name }}
                                                </span>
                                                <span class="text-gray-500 text-[10px] ml-1">({{ traveller.type }})</span>
                                                </div>

                                                <!-- Ticket No Column -->
                                                <div class="px-4 py-2.5 font-mono text-xs text-gray-700 border-r border-gray-200">
                                                {{ getTicketNumber(index) }}
                                                </div>
                                                
                                            </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Fare Breakdown -->

                                    <div class="p-6 border-b border-gray-200 print:border-gray-300 print:break-inside-avoid" v-if="isDetailsInfoVisible">
                                    <!-- Section Header - matching passenger section style -->
                                    <div class="flex items-center gap-2 mb-4">
                                        <h2 class="text-sm font-bold text-gray-800 uppercase tracking-wide">FARE BREAKDOWN</h2>
                                    </div>

                                    <!-- TABLE - Same PDF Style: only outer border -->
                                    <div class="w-full border border-gray-300 rounded-md overflow-hidden text-[11px] shadow-sm print:shadow-none">

                                        <!-- TABLE HEADER -->
                                        <div class="bg-gray-100 border-b border-gray-300">
                                        <div class="grid grid-cols-4">
                                            <div class="px-4 py-2.5 font-semibold text-gray-700 text-xs uppercase tracking-wide border-r border-gray-300">
                                            Sector
                                            </div>
                                            <div class="px-4 py-2.5 font-semibold text-gray-700 text-xs uppercase tracking-wide border-r border-gray-300">
                                            Subtotal
                                            </div>
                                            <div class="px-4 py-2.5 font-semibold text-gray-700 text-xs uppercase tracking-wide border-r border-gray-300">
                                            Taxes + Fees
                                            </div>
                                            <div class="px-4 py-2.5 font-semibold text-gray-700 text-xs uppercase tracking-wide">
                                            Grand Total
                                            </div>
                                        </div>
                                        </div>

                                        <!-- TABLE BODY ROWS -->
                                        <div
                                        v-for="(flight, index) in parseFlightData(bookingDetails?.[0]?.flight_data)?.leg?.flights"
                                        :key="index"
                                        >
                                        <div
                                            v-for="(fare, fareIndex) in flight.fares.filter(f => {
                                            const fareRefs = Array.isArray(parseFlightData(bookingDetails[0]?.fare_reference))
                                                ? parseFlightData(bookingDetails[0]?.fare_reference)
                                                : [parseFlightData(bookingDetails[0]?.fare_reference)];
                                            return fareRefs.includes(f.ref_id);
                                            })"
                                            :key="fareIndex"
                                            class="border-b border-gray-200 last:border-b-0  transition-colors"
                                        >
                                            <div class="grid grid-cols-4">
                                            <!-- Sector -->
                                            <div class="px-4 py-2.5 border-r border-gray-200 uppercase text-gray-900 text-xs font-medium">
                                                {{ flight.segments?.[0]?.from?.iata }} → {{ flight.segments?.[flight.segments.length - 1]?.to?.iata }}
                                            </div>
                                            <!-- Subtotal -->
                                            <div class="px-4 py-2.5 border-r border-gray-200 text-gray-700 text-xs">
                                                {{ formatAmount(calculateFinalPrice(fare?.base_price, fare?.margin_amount, fare?.margin_type, fare?.amount_type) + marginPerFlight) }}
                                            </div>
                                            <!-- Taxes + Fees -->
                                            <div class="px-4 py-2.5 border-r border-gray-200 text-gray-700 text-xs">
                                                {{ formatAmount(calculateTaxes(fare)) }}
                                            </div>
                                            <!-- Grand Total -->
                                            <div class="px-4 py-2.5 text-gray-900 text-xs font-semibold">
                                                {{ formatAmount(calculateTotalFare(fare)) }}
                                            </div>
                                            </div>
                                        </div>
                                        </div>

                                        <!-- TABLE FOOTER - Total Amount row -->
                                        <div class="border-t border-gray-300 bg-gray-50">
                                        <div class="grid grid-cols-4">
                                            <div class="col-span-3 py-3 text-right font-bold text-gray-900 text-xs">
                                            Total Amount
                                            </div>
                                            <div class="px-2 py-3 text-right font-bold text-blue-600 text-sm">
                                            <div class="flex items-center justify-end gap-2">
                                                <span>{{ formatAmount(calculateGrandTotal()) }}</span>
                                                <!--
                                                <button @click="openGrandTotalDialog" class="print:hidden rounded-md bg-primary px-3 py-1.5 text-xs font-medium text-white hover:bg-primary/90">
                                                    Edit
                                                </button>
                                                -->
                                            </div>
                                            </div>
                                        </div>
                                        </div>
                                    </div>
                                    </div>

                                    <Dialog :open="isGrandTotalDialogOpen" @update:open="isGrandTotalDialogOpen = $event">
                                        <DialogContent class="sm:max-w-[425px]">
                                            <DialogHeader>
                                                <DialogTitle>Update Grand Total</DialogTitle>
                                            </DialogHeader>
                                            <div class="space-y-2">
                                                <Label for="grandTotalAmount">Grand Total</Label>
                                                <Input id="grandTotalAmount" v-model="editableGrandTotal" type="number" min="0" step="0.01" />
                                            </div>
                                            <DialogFooter>
                                                <Button variant="secondary" @click="isGrandTotalDialogOpen = false">Cancel</Button>
                                                <Button @click="saveGrandTotalAmount" :disabled="isUpdatingGrandTotal">
                                                    {{ isUpdatingGrandTotal ? "Saving..." : "Save" }}
                                                </Button>
                                            </DialogFooter>
                                        </DialogContent>
                                    </Dialog>
                                    <!-- Baggage Details-->
                                    <div class="p-6 border-b border-gray-200 print-border-bottom print-border-gray print-break-inside-avoid print-padding bg-white" v-if="false">
                                        <div class="flex items-center gap-2 mb-4 print-mb-sm">
                                            <h2 class="text-sm font-bold text-gray-800 uppercase tracking-wide print-text-sm print-font-bold">
                                                BAGGAGE DETAILS
                                            </h2>
                                        </div>

                                        <div v-for="booking in bookingDetails" :key="booking.id">
                                            
                                            <div v-for="(flight, index) in flightData?.leg?.flights" :key="index" class="mb-6 print-mb-sm">

                                                <!-- Flight Header -->
                                                <div class="text-xs font-semibold text-gray-800 mb-2 print-font-semibold print-text-xs">
                                                    {{ flight?.from?.city?.name }}
                                                    <span class="mx-2 text-xs text-gray-500 print-text-xs">to</span>
                                                    {{ flight?.to?.city?.name }}
                                                </div>

                                                <!-- Flight Segments -->
                                                <div v-for="(segment, sIndex) in flight?.segments" :key="sIndex" class="mb-4 print-mb-sm">

                                                    <div class="text-xs font-semibold text-gray-700 mb-2 print-font-semibold print-text-10px">
                                                        {{ segment.from.iata }} → {{ segment.to.iata }}
                                                        <!-- Booking Code -->
                                                        <span
                                                            v-for="(code, cIndex) in flight?.fares?.[0]?.booking_codes?.filter(
                                                                (c) => c.segment_ref_id === segment.ref_id
                                                            )"
                                                            :key="cIndex"
                                                            class="ml-2 text-primary text-xs font-medium print-text-primary print-font-medium"
                                                        >
                                                            | {{ code.booking_code }}
                                                        </span>
                                                    </div>

                                                    <!-- Baggage Details Card -->
                                                    <div class="w-full border border-gray-300 rounded-md overflow-hidden text-[11px]  print-shadow-none print-border print-border-gray print-text-10px">

                                                        <!-- Header Row -->
                                                        <div class="bg-gray-100 border-b border-gray-300 print-bg-gray">
                                                            <div class="grid grid-cols-3 print-grid-3">
                                                                <div class="px-4 py-2.5 font-semibold text-gray-700 text-xs uppercase tracking-wide border-r border-gray-300 print-padding-xs print-font-semibold print-text-xs print-border-right">
                                                                    Pax Type
                                                                </div>
                                                                <div class="px-4 py-2.5 font-semibold text-gray-700 text-xs uppercase tracking-wide border-r border-gray-300 print-padding-xs print-font-semibold print-text-xs print-border-right">
                                                                    Check-In Baggage
                                                                </div>
                                                                <div class="px-4 py-2.5 font-semibold text-gray-700 text-xs uppercase tracking-wide print-padding-xs print-font-semibold print-text-xs">
                                                                    Cabin Baggage
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Data Rows -->
                                                        <template v-for="(fare, fIndex) in flight?.fares" :key="fIndex">
                                                            <template v-if="parseData(bookingDetails?.[0]?.fare_reference)?.includes(fare?.ref_id)">
                                                                <div
                                                                    v-for="(travelerType, tIndex) in [...new Set(fare.baggage_policies.map(bp => bp.traveler_type))]"
                                                                    :key="tIndex"
                                                                    class="border-b border-gray-200 last:border-b-0  transition-colors print-border-bottom"
                                                                >
                                                                    <div class="grid grid-cols-3 print-grid-3">
                                                                        <!-- Passenger Type -->
                                                                        <div class="px-4 py-2.5 border-r border-gray-200 uppercase text-gray-900 text-xs font-medium print-padding-xs print-border-right print-text-10px print-font-medium">
                                                                            {{ travelerType }}
                                                                        </div>

                                                                        <!-- Checked Baggage -->
                                                                        <div class="px-4 py-2.5 border-r border-gray-200 uppercase text-gray-700 text-xs print-padding-xs print-border-right print-text-10px">
                                                                            {{
                                                                                fare.baggage_policies.find(
                                                                                    bp => bp.traveler_type === travelerType && bp.type === 'checked'
                                                                                )?.description || 'N/A'
                                                                            }}
                                                                        </div>

                                                                        <!-- Cabin/Carry Baggage -->
                                                                        <div class="px-4 py-2.5 uppercase text-gray-700 text-xs print-padding-xs print-text-10px">
                                                                            {{
                                                                                fare.baggage_policies.find(
                                                                                    bp => bp.traveler_type === travelerType && bp.type === 'carry'
                                                                                )?.description || 'N/A'
                                                                            }}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </template>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Important Information -->
                                    <div class="p-6 border-b border-gray-200">
                                        <h2 class="text-lg font-bold text-gray-800 mb-4">Important Information</h2>
                                        <div class="bg-amber-50 border border-amber-200 rounded-lg p-5">
                                            <ul class="space-y-3 text-sm text-gray-700">
                                                <li class="flex items-start"><span class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-1.5 mr-3 flex-shrink-0"></span><span>Make sure you have valid travel documents before your trip (e.g. passport, visa, etc.).</span></li>
                                                <li class="flex items-start"><span class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-1.5 mr-3 flex-shrink-0"></span><span>We recommend you check-in at least 3 hours prior to departure of your domestic flight and 4 hours prior to your international flight.</span></li>
                                                <li class="flex items-start"><span class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-1.5 mr-3 flex-shrink-0"></span><span>Baggage more than specified units is subject to a charge to be paid at the airport during Check-in.</span></li>
                                                <li class="flex items-start"><span class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-1.5 mr-3 flex-shrink-0"></span><span>Please refer the Airline PNR Number when communicating with the airline regarding this booking.</span></li>
                                                <li class="flex items-start"><span class="w-1.5 h-1.5 bg-amber-500 rounded-full mt-1.5 mr-3 flex-shrink-0"></span><span class="font-medium">Disclaimer:</span> Post-ticketing modifications or cancellations will be processed in accordance with the airline's policy.</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <!-- Footer -->
                                    <div class="p-6 text-center bg-gray-50 border-t border-gray-200">
                                        <p class="text-sm text-gray-700 mb-1 font-medium">Thank you for choosing {{ agentData?.agent_data?.company_name || 'ApnaTicket Travels' }}</p>
                                        <p class="text-xs text-gray-500">For assistance, contact us at {{ agentData?.agent_data?.mobile || '+92 3111711123' }} or {{ agentData?.agent_data?.company_email || 'support@apnaticket.pk' }}</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Chat Panel - Right Side -->
                            <div v-if="modifyRequestData && isChatOpen" class="w-4/12 print:hidden">
                                <div class="bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col h-[calc(100vh-200px)] sticky top-4">
                                    <div class="p-3 border-b border-gray-200 bg-gray-50 rounded-t-lg flex-shrink-0">
                                        <h3 class="text-base font-semibold text-gray-800">Modify Request</h3>
                                    </div>
                                    <div class="p-3 border-b border-gray-200 flex-shrink-0">
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs font-medium text-gray-600">Status</span>
                                            <div class="flex items-center gap-4">
                                                <label class="inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" :checked="modifyRequestData?.status === 'approved'"
                                                        @change="toggleModifyRequestStatus(modifyRequestData?.status === 'approved' ? 'pending' : 'approved')"
                                                        class="sr-only peer" />
                                                    <div class="relative w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                                                </label>
                                                <span :class="{
                                                    'px-2 py-0.5 text-[10px] font-semibold rounded-full': true,
                                                    'bg-yellow-100 text-yellow-800': modifyRequestData?.status === 'pending',
                                                    'bg-green-100 text-green-800': modifyRequestData?.status === 'approved',
                                                    'bg-red-100 text-red-800': modifyRequestData?.status === 'rejected',
                                                    'bg-blue-100 text-blue-800': modifyRequestData?.status === 'processing'
                                                }">
                                                    {{ modifyRequestData?.status || 'Pending' }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="mt-1.5 text-xs">
                                            <span class="font-medium text-gray-600">Reason:</span>
                                            <p class="mt-0.5 text-gray-800">
                                                {{ modifyRequestData?.reason === 'change_scope' ? 'Change Scope' :
                                                    modifyRequestData?.reason === 'extend_deadline' ? 'Extend Deadline' :
                                                    modifyRequestData?.reason === 'refund' ? 'Request Refund' :
                                                    modifyRequestData?.reason === 'cancel' ? 'Cancel Booking' :
                                                    modifyRequestData?.reason || 'Not specified' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex-1 overflow-y-auto p-3 space-y-3 bg-gray-50/50">
                                        <div v-if="!parseFlightData(modifyRequestData?.messages)?.length" class="text-center text-gray-500 text-xs py-6">
                                            No messages yet.
                                        </div>
                                        <div v-else v-for="(msg, index) in parseFlightData(modifyRequestData?.messages)" :key="index" class="flex" :class="msg.sender === 'user' ? 'justify-start' : 'justify-end'">
                                            <div class="max-w-[85%] px-3 py-2 text-xs rounded-lg shadow-sm" :class="msg.sender === 'admin' ? 'bg-primary/10 text-gray-800' : 'bg-white border border-gray-200 text-gray-800'">
                                                <p class="whitespace-pre-wrap">{{ msg?.message }}</p>
                                                <p class="text-[10px] text-gray-500 mt-1">{{ moment(msg?.created_at).format('DD MMM YYYY, HH:mm') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="modifyRequestData?.status === 'pending'" class="p-3 border-t border-gray-200 bg-white rounded-b-lg flex-shrink-0">
                                        <form @submit.prevent="sendReply">
                                            <textarea v-model="replyMessage" rows="2" placeholder="Type your reply..." class="w-full px-2 py-1.5 text-xs border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-primary/20 focus:border-primary resize-none" required></textarea>
                                            <div class="mt-2 flex justify-end">
                                                <button type="submit" :disabled="replyLoading" class="px-3 py-1.5 text-xs font-medium text-white bg-primary hover:bg-primary/90 rounded-md disabled:opacity-60 flex items-center gap-1.5">
                                                    <span v-if="replyLoading" class="animate-spin h-3 w-3 border-2 border-white border-t-transparent rounded-full"></span>
                                                    Send
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </template>

    <style scoped>
    @media print {
    .hover\:bg-gray-50:hover {
        background-color: transparent !important;
    }

    .shadow-sm {
        box-shadow: none !important;
    }

    .rounded-md {
        border-radius: 0 !important;
    }

    .border {
        border-color: #ccc !important;
    }

    .bg-gray-100 {
        background-color: #f5f5f5 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .bg-green-100 {
        background-color: #e8f5e9 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    }

    /* Ensure consistent spacing */
    .space-y-1 > :not([hidden]) ~ :not([hidden]) {
    margin-top: 0.25rem;
    }

.space-y-1\.5 > :not([hidden]) ~ :not([hidden]) {
    margin-top: 0.375rem;
    }

    /* Image handling */
    img {
    background-color: #f3f4f6;
    border-radius: 4px;
    flex-shrink: 0;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
    .grid-cols-12 {
        gap: 0.75rem;
    }

    .col-span-3 {
        font-size: 10px;
    }
    }

    /* Ensure consistent vertical alignment across cells */
    .grid > div {
    word-break: break-word;
    }

    /* Improve image handling for missing logos */
    img {
    background-color: #f3f4f6;
    border-radius: 4px;
    }

    @media print {
    .text-blue-600  { color: #1d4ed8 !important; }
    .text-emerald-700 { color: #047857 !important; }
    .bg-emerald-50  { background-color: #ecfdf5 !important; }
    }
    @media print {
        @page {
            size: A4;
        }
        body * {
            visibility: hidden !important;
        }
        #print-section,
        #print-section * {
            visibility: visible !important;
        }
        #print-section {
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            background: white !important;
        }
        button, .fixed, .sticky, .print\:hidden {
            display: none !important;
        }
        .print\:break-inside-avoid {
            page-break-inside: avoid !important;
        }
        .bg-amber-50 {
            background-color: #fffbeb !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .bg-gray-50 {
            background-color: #f9fafb !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        .text-primary {
            color: #2563eb !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        img {
            max-height: 4rem !important;
            width: auto !important;
        }
    }
    </style>
