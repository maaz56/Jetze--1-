import { toast } from "vue3-toastify";
import { SAVE_PROMO_IMAGE, DELETE_PROMO_IMAGE, FETCH_PROMO_IMAGES, UPDATE_BOOKING_STATUS, FETCH_BOOKING_STATS_SETINGS } from "./actions.type";
import apiService from "./apiService";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_ADMIN_BOOKING,
    SET_API_ERROR,
    SET_BOOKING_STATUS_SETTING,
    SET_PROMO_IMAGE
} from "./mutations.type";

const state = {

    promoImageData: {},
    bookingStatusSetting: {},
    isLoading: false,
    apiErrors: [],
};

const getters = {
    promoImageData(state) {
        return state.promoImageData;
    },
    bookingStatusSetting(state) {
        return state.bookingStatusSetting;
    },

    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_PROMO_IMAGES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getPromoImages(params);
            context.commit(SET_PROMO_IMAGE, response.data);

        } catch (error) {
            //console.log(error);
            // toast("Something went wrong.", {
            //     type: "error",
            // });
            context.commit(SET_API_ERROR, error);
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },


    async [SAVE_PROMO_IMAGE](context, params) {
        context.commit(IS_LOADING);
        try {

            const response = await apiService.savePromoImage(params);
            context.dispatch(FETCH_PROMO_IMAGES);
            toast("Promo Iamge saved successfully", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast(error, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [DELETE_PROMO_IMAGE](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deletePromoImage(params);
            context.dispatch(FETCH_PROMO_IMAGES);
            toast("Promo image has been deleted successfully.", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [UPDATE_BOOKING_STATUS](context, params) {
        context.commit(IS_LOADING);
        try {

            const response = await apiService.updateBookingStatus(params);
            toast("Booking Status updated successfully", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast(error, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_BOOKING_STATS_SETINGS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getBookingStatus(params);
            context.commit(SET_BOOKING_STATUS_SETTING, response.data);

        } catch (error) {
            //console.log(error);
            toast(error, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
};

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
            //console.log(error.response.data.errors);
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_BOOKING_STATUS_SETTING](state, data) {
        state.bookingStatusSetting = data;
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
