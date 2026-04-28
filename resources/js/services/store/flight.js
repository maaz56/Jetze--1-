import apiService from "./apiService";
import {
    FETCH_FLIGHT,
    SAVE_BOOKING,
    FETCH_FLIGHTS,
    FILTER_FLIGHTS,
    SEND_QUOTATION,
    FETCH_BOOKINGS,
    SAVE_BOOKING_DATA,
    FETCH_BOOKING_DETAILS,
    FETCH_PNR_DETAILS,
    CANCEL_BOOKING,
    CONFIRM_BOOKING,
    APPROVE_BOOKING,
    FETCH_BOOKING_DATA,
    SAVE_ADMIN_BOOKING,
    FETCH_ADMIN_BOOKINGS,
    FETCH_ADMIN_BOOKING,
    CUSTOMER_BOOKINGS,
    FETCH_CUSTOMER_BOOKING_DETAILS,
    SEND_PRICE_REQUEST,
    VOID_BOOKING,
    VOID_REQUEST,
    SEND_PAYMENT_REQUEST,
    SEND_EMAIL,
    FETCH_PROVIDERS,
    SORT_FLIGHTS,
    FETCH_ANCILLARIES,
    PATCH_ANCILLARIES,
    CREATE_INVOICE,
    SEND_ADD_TO_CART,
    FETCH_PNR_DATA,
    FETCH_CUSTOMER_BOOKING,
    UPDATE_BOOKING_AMOUNT,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_FILTERED_FLIGHTS,
    SET_FLIGHT,
    SET_FLIGHTS,
    SET_API_ERROR,
    SET_BOOKINGS,
    SET_BOOKING_DATA,
    SET_BOOKING_DETAILS,
    SET_PNR,
    SET_PNR_STATUS,
    SET_ADMIN_BOOKING,
    SET_ADMIN_BOOKING_DATA,
    SET_CUSTOMER_BOOKINGS,
    SET_CUSTOMER_BOOKING_DETAILS,
    SET_PROVIDERS,
    SET_ANCILLARIES,
    SET_PRICE_RESPONSE,
    SET_QOUTE_ERROR,
    SET_CART,
    SET_PNR_DATA,
    SET_ALL_CUSTOMER_BOOKING,
        SET_FARE_RULES,
        SET_ANCILLARIES_RESPONSE,

} from "./mutations.type";
import { toast } from "vue3-toastify";
import router from "@/config/router";
const state = {
    flights: null,
    flight: null,
    bookings: null,
    providers: null,
    quote: null,
    cart: null,
    ancillaries: null,
    adminBookings: null,
    adminBookingData: null,
    ancillariesResponse: null,
    pnrData: null,
     priceRes: null,
    fareRules: null,
    isConfirmed: null,
    bookingData: null,
    allCustomerBooking: null,
    availableAirlines: null,
    customerBooking: null,
    isPatchingAncillaries: null,
    isLoading: false,
    already_booked: false,
    apiErrors: false,
    validationErrors: [],
    priceError: false,
    priceErrorMessage: "",
};

const getters = {
    flights(state) {
        return state.flights;
    },
    flight(state) {
        return state.flight;
    },
    availableAirlines(state) {
        return state.availableAirlines;
    },
    providers(state) {
        return state.providers;
    },
    bookings(state) {
        return state.bookings;
    },
    bookingData(state) {
        return state.bookingData;
    },
    cart(state) {
        return state.cart;
    },
    adminBookings(state) {
        return state.adminBookings;
    },
    adminBookingData(state) {
        return state.adminBookingData;
    },
    priceRes(state) {
        return state.priceRes;
    },
    fareRules(state) {
        return state.fareRules;
    },
    ancillaries(state) {
        return state.ancillaries;
    },
    ancillariesResponse(state) {
        return state.ancillariesResponse;
    },
    isConfirmed(state) {
        return state.isConfirmed;
    },
    isPatchingAncillaries(state) {
        return state.isPatchingAncillaries;
    },  
    pnrData(state) {
        return state.pnrData;
    },
    cancelPnr(state) {
        return state.cancelPnr;
    },
    bookingDetails(state) {
        return state.bookingDetails;
    },
    customerBookings(state) {
        return state.customerBookings;
    },
    customerBooking(state) {
        return state.customerBooking;
    },
    allCustomerBooking(state) {
        return state.allCustomerBooking;
    },
    priceError(state) {
        return state.priceError;
    },
    priceErrorMessage(state) {
        return state.priceErrorMessage;
    },
    isLoading(state) {
        return state.isLoading;
    },
    already_booked(state) {
        return state?.already_booked || false;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
    validationErrors(state) {
        return state.validationErrors;
    },
};

const actions = {
    async [FETCH_FLIGHTS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getFlights(params);
            // console.log(JSON.stringify(response.data));
            context.commit(SET_FLIGHTS, response.data);
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [FETCH_FLIGHT](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getFlight(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_FLIGHT, response.data);
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_PROVIDERS](context, params) {
        context.commit(IS_LOADING);
        try {
            // console.log(params);
            const response = await apiService.getFlightProviders(params);
            console.log(JSON.stringify(response));
            context.commit(SET_PROVIDERS, response.data);
        } catch (error) {
            // console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [SORT_FLIGHTS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.sortFlights(params.flights);
            console.log(JSON.stringify(response.data));
            context.commit(SET_FILTERED_FLIGHTS, response.data);
        } catch (error) {
            // console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_BOOKING_DETAILS](context, params) {
        context.commit(IS_LOADING);
        
        try {
            const response = await apiService.getFlightBookingDetail(params);
            // //console.log(JSON.stringify(response.data));
            context.commit(SET_BOOKING_DETAILS, response.data);
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [FETCH_CUSTOMER_BOOKING_DETAILS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response =
                await apiService.getCustomerFlightBookingDetail(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_CUSTOMER_BOOKING_DETAILS, response.data);
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_PNR_DETAILS](context, params) {
        context.commit(IS_LOADING);
        //console.log("PNR Fetch : ", context);
        try {
            const response = await apiService.getPnrDetails(params);
            console.log("PNR Details: ", response);
            context.commit(SET_PNR, response.data.data);
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_PNR_DATA](context, params) {
        context.commit(IS_LOADING);
        //console.log("PNR Fetch : ", context);
        try {
            const response = await apiService.getPnrData(params);
            console.log("PNR Data: ", response);
            context.commit(SET_BOOKING_DETAILS, response?.data?.booking);
        } catch (error) {
            console.log(error);
            toast("Booking Not Found", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [CANCEL_BOOKING](context, params) {
        context.commit(IS_LOADING);
        try {
            //console.log("Cancel Booking Params: ", params);
            const response = await apiService.cancelBooking(params);
            // console.log(JSON.stringify(response.data));
            context.commit(SET_PNR_STATUS, response.data);
            context.state.isLoading = false;
            toast("Booking canceled successfully.", {
                type: "success",
            });
        } catch (error) {
            console.log(error);
            toast(error?.response?.data?.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [CONFIRM_BOOKING](context, params) {
        context.commit(IS_LOADING);
        context.state.isConfirmed = false;
        try {
            const response = await apiService.confirmBooking(params);
            context.commit(SET_PNR_STATUS, response.data);
            context.state.isConfirmed = true;

            toast("Booking confirmed successfully.", {
                type: "success",
            });
            // await context.dispatch(CREATE_INVOICE, {
            //     booking_id: response.data.booking.id,
            // });
        } catch (error) {
            console.log(error);

            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [CREATE_INVOICE](context, params) {
        try {
            const response = await apiService.createInvoice(params);
            // console.log(JSON.stringify(response.data));
            context.state.isLoading = false;
        } catch (error) {
            // console.log(error);

            context.commit(SET_API_ERROR, error);
        }
    },

    async [APPROVE_BOOKING](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.approveBooking(params);

            context.state.isLoading = false;
            await context.dispatch(CREATE_INVOICE, {
                booking_id: response.data.booking.id,
            });
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [VOID_REQUEST](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.voidRequest(params);

            context.state.isLoading = false;
            toast("Void request sent successfully.", {
                type: "success",
            });
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [VOID_BOOKING](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.voidBooking(params);

            context.state.isLoading = false;
            toast("Booking has been voided successfully.", {
                type: "success",
            });
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [FETCH_BOOKINGS](context, params) {
        context.commit(IS_LOADING);
        try {
            ////console.log(params);
            const response = await apiService.getBookings(params);
            //console.log(response.data);
            context.commit(SET_BOOKINGS, response.data);
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [FETCH_BOOKING_DATA](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getBookings(params);
            // //console.log(JSON.stringify(response.data));
            context.commit(SET_BOOKING_DATA, response.data);
            context.state.isLoading = false;
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [SAVE_BOOKING_DATA](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveBooking(params);
            // //console.log(JSON.stringify(response.data));
            context.state.isLoading = false;
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_BOOKING](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveBooking(params);
            console.log(JSON.stringify(response.data));
            context.commit(SET_BOOKING_DETAILS, response.data.booking);

            toast("Booking saved successfully.", {
                type: "success",
            });
        } catch (error) {
            console.log(error);
            if(error.response && error.response.data && error.response.data.already_booked) {
                context.state.already_booked = error.response.data.already_booked;
                toast(error.response.data.message, {
                    type: "error",
                });
                context.commit(SET_API_ERROR, error);

                return;

            }
            toast(error?.response?.data?.message || "Something went wrong.", {
                type: "error",
            });
                            context.commit(SET_API_ERROR, error);

        }
    },
    async [SAVE_ADMIN_BOOKING](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveAdminBooking(params);
            // //console.log(JSON.stringify(response.data));
            context.state.isLoading = false;
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_ADMIN_BOOKINGS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getAdminBookings(params);
            context.commit(SET_ADMIN_BOOKING, response.data);
            context.state.isLoading = false;
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [FETCH_ADMIN_BOOKING](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getAdminBooking(params);
            // //console.log(JSON.stringify(response.data));
            context.commit(SET_ADMIN_BOOKING_DATA, response.data);
            context.state.isLoading = false;
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SEND_QUOTATION](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.sendQuotation(params);
            // //console.log(response.data);
            return response.data;
        } catch (error) {
            toast("Something went wrong.", {
                type: "error",
            });

            console.log(error);
            context.commit(SET_API_ERROR, error);
        }
    },

    async [CUSTOMER_BOOKINGS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getCustomerBookings(params);
            //console.log(response.data);
            context.commit(SET_CUSTOMER_BOOKINGS, response.data);
        } catch (error) {
            console.log(error);

            context.commit(SET_API_ERROR, error);
        }
    },

    async [FETCH_CUSTOMER_BOOKING](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getCustomerBooking(params);
            console.log(JSON.stringify(response.data));
            context.commit(SET_ALL_CUSTOMER_BOOKING, response.data);
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SEND_PRICE_REQUEST](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.sendPriceRequest(params);
            // //console.log(response.data);
            context.commit(SET_PRICE_RESPONSE, response.data.price_response);
                        context.commit(SET_FARE_RULES, response.data.fare_rules);


            return response.data;
        } catch (error) {
            console.log(error);
            toast(error?.response?.data?.message || "Something went wrong.", {
                type: "error",
            });
            context.commit(SET_QOUTE_ERROR, error);
        }
    },
    async [SEND_ADD_TO_CART](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.sendAddToCart(params);
            // //console.log(response.data);
            context.commit(SET_CART, response.data);

            return response.data;
        } catch (error) {
            console.log(error);
            // toast("Something went wrong.", {
            //     type: "error",
            // });
            // router.back();
            console.log(error);
            context.commit(SET_QOUTE_ERROR, error);
        }
    },

    async [FETCH_ANCILLARIES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.fetchAncillaries(params);
            console.log(response.data);
            context.commit(SET_ANCILLARIES, response.data);
        } catch (error) {
            console.log(error);
            context.commit(SET_API_ERROR, error);
        }
    },
    async [PATCH_ANCILLARIES](context, params) {
        // context.commit(IS_LOADING);
        console.log("Patching Ancillaries with params: ");
        context.state.isPatchingAncillaries = true;
        try {
            const response = await apiService.patchAncillaries(params);
            context.commit(SET_ANCILLARIES_RESPONSE, response.data);
            console.log(response.data);
            context.state.isPatchingAncillaries = false;

            context.commit(NOT_IS_LOADING);
            return response.data;
        } catch (error) {
            console.log(error);
            context.state.isPatchingAncillaries = true;
            toast(error?.response?.data?.message || "Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SEND_PAYMENT_REQUEST](context, params) {
        // context.commit(IS_LOADING);
        try {
            const response = await apiService.sendPaymentRequest(params);
            //console.log(response.data);
            // context.commit(NOT_IS_LOADING);

            return response.data;
        } catch (error) {
            console.log(error);
            context.commit(SET_API_ERROR, error);
        }
    },
    async [SEND_EMAIL](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.sendEmail(params);
            //console.log(response.data);
            context.commit(NOT_IS_LOADING);
            toast("Email sent successfully.", {
                type: "success",
            });
        } catch (error) {
            console.log(error);
            context.commit(SET_API_ERROR, error);
        }
    },
     async [UPDATE_BOOKING_AMOUNT](context, params) {
        // context.commit(IS_LOADING);
        try {
            const response = await apiService.updateBookingAmount(params);
            //console.log(response.data);
            context.commit(NOT_IS_LOADING);
            return response.data;
        } catch (error) {
            console.log(error);
            context.commit(SET_API_ERROR, error);
            throw error;
        }
    },
};

const mutations = {
    [IS_LOADING](state) {
        state.isLoading = true;
        state.priceError = false;
        state.priceErrorMessage = "";
    },
    [NOT_IS_LOADING](state) {
        state.isLoading = false;
    },
    [SET_BOOKING_DATA](state, data) {
        state.bookingData = data;
        state.isLoading = false;
    },
    [SET_BOOKING_DETAILS](state, data) {
        state.bookingDetails = data;
        state.isLoading = false;
    },
    [SET_API_ERROR](state, error) {
        state.isLoading = false;
        state.apiErrors = true;
        if (
            error.response &&
            error.response.data &&
            error.response.data.errors
        ) {
            //console.log(error.response.data.errors);
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_QOUTE_ERROR](state, error) {
        state.isLoading = false;
        state.priceError = true;
        state.priceErrorMessage =
            error?.response?.data?.message ||
            error?.response?.data?.error ||
            "There is a problem in the system. Please try again later or contact support if the issue persists.";

        //console.log(error.response.data.errors);

        // router.back();
    },
    [SET_FLIGHTS](state, data) {
        localStorage.setItem(
            "previous_search",
            JSON.stringify(data.previous_search) ?? null,
        );
        state.flights = data.flights;
        state.availableAirlines = data.available_airlines;
        state.isLoading = false;
    },
    [SET_FLIGHT](state, data) {
        state.flight = data;
        state.isLoading = false;
    },
    [SET_BOOKINGS](state, data) {
        state.bookings = data;
        state.isLoading = false;
    },
    [SET_PROVIDERS](state, data) {
        state.providers = data;
        state.isLoading = false;
    },
    [SET_PNR](state, data) {
        state.pnrData = data;
        state.isLoading = false;
    },
    [SET_CART](state, data) {
        state.cart = data;
        state.isLoading = false;
    },
    [SET_PNR_STATUS](state, data) {
        state.cancelPnr = data;
        state.isLoading = false;
    },
    [SET_ADMIN_BOOKING](state, data) {
        state.adminBookings = data;
        state.isLoading = false;
    },
    [SET_ADMIN_BOOKING_DATA](state, data) {
        state.adminBookingData = data;
        state.isLoading = false;
    },

    [SET_FILTERED_FLIGHTS](state, filteredFlights) {
        state.flights = filteredFlights;
    },
    [SET_ALL_CUSTOMER_BOOKING](state, data) {
        console.log(data);
        state.allCustomerBooking = data;
        state.isLoading = false;
    },
    [SET_CUSTOMER_BOOKINGS](state, data) {
        state.customerBookings = data;
        state.isLoading = false;
    },
    [SET_CUSTOMER_BOOKING_DETAILS](state, data) {
        state.customerBooking = data;
        state.isLoading = false;
    },
    [SET_PRICE_RESPONSE](state, data) {
        state.priceRes = data;
        state.isLoading = false;
    },
    [SET_FARE_RULES](state, data) {
        state.fareRules = data;
        state.isLoading = false;
    },
    [SET_ANCILLARIES](state, data) {
        state.ancillaries = data;
        state.isLoading = false;
    },
    [SET_ANCILLARIES_RESPONSE](state, data) {
        state.ancillariesResponse = data;
        state.isLoading = false;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};
