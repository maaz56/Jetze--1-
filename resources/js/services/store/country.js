import apiService from "./apiService";
import { toast } from "vue3-toastify";
import {
    FETCH_COUNTRIES,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_COUNTRIES,
    SET_API_ERROR,
} from "./mutations.type";

const state = {
    countries: [],
    isLoading: false,
    apiErrors: []
}

const getters = {
    countries(state) {
        return state.countries;
    },
    apiErrors(state) {
        return state.apiErrors;
    }
}

const actions = {
    async [FETCH_COUNTRIES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getCountries(params);
            context.commit(SET_COUNTRIES, response.data);
        } catch (error) {
            //console.log(error);
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
    [SET_COUNTRIES](state, data) {
        state.countries = data.data;
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
