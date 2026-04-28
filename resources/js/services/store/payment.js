import apiService from "./apiService";
import { toast } from "vue3-toastify";
import {
    CHECK_PAYMENT_STATUS,
    FETCH_CITIES,
    INITIALIZE_ABHI_PAY,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_CITIES,
    SET_API_ERROR,
    SET_ABHIPAY_RESPONSE,
    SET_PAYMENT_STATUS,
} from "./mutations.type";

const state = {
    cities: [],
    abhiPayResponse: null,
    deposit: null,
    paymentStatus: null,
    isLoading: false,
    isCheckingPaymentStatus: false,
    apiErrors: []
}

const getters = {
    cities(state) {
        return state.cities;
    },
    abhiPayResponse(state) {
        return state.abhiPayResponse;
    },
    paymentStatus(state) {
        return state.paymentStatus;
    },
    deposit(state) {
        return state.deposit;
    },
    isLoading(state) {
        return state.isLoading;
    },
    isCheckingPaymentStatus(state) {
        return state.isCheckingPaymentStatus;
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
            console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },
    async [INITIALIZE_ABHI_PAY](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.initializeAbhiPay(params);
            //console.log('Cities' + JSON.stringify(response.data));
            context.commit(SET_ABHIPAY_RESPONSE, response.data);
        } catch (error) {
            console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },
    async [CHECK_PAYMENT_STATUS](context, params) {
        context.commit(IS_LOADING);
        context.state.isCheckingPaymentStatus = true;
        try {
            const response = await apiService.checkPaymentStatus(params);
            console.log('Payment Status' + JSON.stringify(response.data));
            context.state.deposit = response.data.deposit;
            context.commit(SET_PAYMENT_STATUS, response.data);
            context.state.isCheckingPaymentStatus = false;

        } catch (error) {
            console.log(error);
            toast('Failed To check Payment Status.', {
                "type": "error",
            })
            context.state.isCheckingPaymentStatus = false;

            context.commit(SET_API_ERROR, error);
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
        state.isLoading = false;
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
    [SET_ABHIPAY_RESPONSE](state, data) {
        state.abhiPayResponse = data;
        state.isLoading = false;
    },
    [SET_PAYMENT_STATUS](state, data) {
        state.paymentStatus = data;
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
