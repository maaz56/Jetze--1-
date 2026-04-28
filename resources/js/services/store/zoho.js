import apiService from "./apiService";
import { toast } from "vue3-toastify";
import { FETCH_CITIES, FETCH_KEYS, GET_ZOHO_TOKEN, SAVE_KEYS } from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_CITIES,
    SET_API_ERROR,
    SET_KEYS,
} from "./mutations.type";
import { keys } from "lodash";

const state = {
    cities: [],
    keys: null,
    isLoading: false,
    apiErrors: [],
};

const getters = {
    cities(state) {
        return state.cities;
    },
    keys(state) {
        return state.keys;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [SAVE_KEYS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveKeys(params);
            context.commit(SET_CITIES, response.data);
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [GET_ZOHO_TOKEN](context, code) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getZohoToken(code);
            if (response) {
                toast("Zoho Auth Successful", {
                    type: "success",
                });
                context.commit(SET_KEYS, response.data);
            } else {
                toast("Zoho Auth Failed", {
                    type: "error",
                });
            }
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },
    async [FETCH_KEYS](context) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.fetchKeys();
            context.commit(SET_KEYS, response.data);
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        } finally {
            context.commit(NOT_IS_LOADING);
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
            console.log(error.response.data.errors);
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_KEYS](state, data) {
        state.keys = data.data;
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
