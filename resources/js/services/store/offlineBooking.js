import apiService from "./apiService";
import { toast } from "vue3-toastify";
import {
    DELETE_OFFLINE_BOOKING,
    FETCH_CITIES,
    FETCH_OFFLINE_BOOKING_DETAILS,
    FETCH_OFFLINE_BOOKINGS,
    SAVE_OFFLINE_BOOKING,
    SEND_OFFLINE_BOOKING_EMAIL,
    UPDATE_OFFLINE_BOOKING,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_CITIES,
    SET_API_ERROR,
    SET_OFFLINE_BOOKINGS,
    SET_OFFLINE_BOOKING_DETAILS,
} from "./mutations.type";

const state = {
    cities: [],
    offlineBookings: [],
    offlineBookingDetails: null,
    isLoading: false,
    apiErrors: []
}

const getters = {
    cities(state) {
        return state.cities;
    },
    offlineBookingDetails(state) {
        return state.offlineBookingDetails;
    },
    isLoading(state) {
        return state.isLoading;
    },
    offlineBookings(state) {
        return state.offlineBookings;
    },
    apiErrors(state) {
        return state.apiErrors;
    }
}

const actions = {
    async [FETCH_CITIES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getCities(params);
            //console.log('Cities' + JSON.stringify(response.data));
            context.commit(SET_CITIES, response.data);
        } catch (error) {
            //console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_OFFLINE_BOOKING](context, bookingData) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveOfflineBooking(bookingData);
            //console.log('Booking Saved' + JSON.stringify(response.data));
            toast('Booking saved successfully.', {
                "type": "success",
            });
            context.commit(NOT_IS_LOADING);
            return response.data;
        } catch (error) {
            //console.log(error);
            toast('Something went wrong while saving the booking.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },
    async [FETCH_OFFLINE_BOOKINGS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getOfflineBookings(params);
            context.commit(SET_OFFLINE_BOOKINGS, response.data);
            context.commit(NOT_IS_LOADING);
            return response.data;
        } catch (error) {
            //console.log(error);
            toast('Something went wrong while fetching bookings.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },

    async [FETCH_OFFLINE_BOOKING_DETAILS](context,params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getOfflineBookingDetails(params);
            context.commit(SET_OFFLINE_BOOKING_DETAILS, response.data);
            context.commit(NOT_IS_LOADING);

        } catch (error) {
            //console.log(error);
            toast('Something went wrong while fetching booking details.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },
    async [DELETE_OFFLINE_BOOKING](context,params){
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteOfflineBooking(params);
            //console.log('Booking Deleted' + JSON.stringify(response.data));
            toast('Booking deleted successfully.', {
                "type": "success",
            });
            context.commit(NOT_IS_LOADING);
            return response.data;
        } catch (error) {
            //console.log(error);
            toast('Something went wrong while deleting the booking.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },
    async [UPDATE_OFFLINE_BOOKING](context, bookingData) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateOfflineBooking(bookingData);
            //console.log('Booking Updated' + JSON.stringify(response.data));
            toast('Booking updated successfully.', {
                "type": "success",
            });
            context.commit(NOT_IS_LOADING);
            return response.data;
        } catch (error) {
            //console.log(error);
            toast('Something went wrong while updating the booking.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },
    async [SEND_OFFLINE_BOOKING_EMAIL](context, bookingData) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.sendOfflineBookingEmail(bookingData);
            //console.log('Booking Updated' + JSON.stringify(response.data));
            toast('Email sent successfully.', {
                "type": "success",
            });
            context.commit(NOT_IS_LOADING);
            return response.data;
        } catch (error) {
            //console.log(error);
            toast('Something went wrong while updating the booking.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
            context.commit(NOT_IS_LOADING);
            throw error;
        }
    },
}

const mutations = {
    [IS_LOADING](state) {
        state.isLoading = true;
    },
    [NOT_IS_LOADING](state) {
        state.isLoading = false;
    },
    [SET_API_ERROR](state, error) {
        if (
            error.response &&
            error.response.data &&
            error.response.data.errors
        ) {
            //console.log(error.response.data.errors)
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_CITIES](state, data) {
        state.cities = data.data;
        state.isLoading = false;
    },
    [SET_OFFLINE_BOOKINGS](state, data) {
        state.offlineBookings = data.data;
        state.isLoading = false;
    },
    [SET_OFFLINE_BOOKING_DETAILS](state, data) {
        state.offlineBookingDetails = data.data;
        state.isLoading = false;
    },
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
}
