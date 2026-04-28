import apiService from "./apiService";
import { toast } from "vue3-toastify";
import {
    FETCH_CITIES,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_CITIES,
    SET_API_ERROR,
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
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
}
