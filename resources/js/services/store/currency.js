import apiService from "./apiService";
import { toast } from "vue3-toastify";
import {
    DELETE_CURRENCY,
    FETCH_CITIES,
    FETCH_CURRENCIES,
    SAVE_CURRENCY,
    UPDATE_CURRENCY,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_CITIES,
    SET_API_ERROR,
    SET_CURRENCIES,
} from "./mutations.type";

const state = {
    cities: [],
    isLoading: false,
    apiErrors: []
}

const getters = {
    cities(state) {
        return state.cities;
    },
    currencies(state) {
        return state.currencies;
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    }
}

const actions = {
    async [SAVE_CURRENCY](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveCurrency(params);
            toast("Currency saved successfully.", {
                type: "success",
            });
            context.commit(NOT_IS_LOADING);
            return response;
        } catch (error) {
            console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_CURRENCIES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.fetchCurrencies(params);
            context.commit(SET_CURRENCIES, response.data);
            context.commit(NOT_IS_LOADING);
            return response;
        } catch (error) {
            console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },
    async [UPDATE_CURRENCY](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateCurrency(params);
            toast("Currency updated successfully.", {
                type: "success",
            });
            context.commit(NOT_IS_LOADING);
            return response;
        } catch (error) {
            console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },

    async [DELETE_CURRENCY](context, id) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteCurrency(id);
            toast("Currency deleted successfully.", {
                type: "success",
            });
            context.commit(NOT_IS_LOADING);
            return response;
        } catch (error) {
            console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
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
        if (
            error.response &&
            error.response.data &&
            error.response.data.errors
        ) {
            //console.log(error.response.data.errors)
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_CURRENCIES](state, data) {
        state.currencies = data.data;
        state.isLoading = false;
    },
    [SET_CITIES](state, data) {
        state.cities = data.data;
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
