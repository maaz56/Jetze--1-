import apiService from "./apiService";
import { toast } from "vue3-toastify";
import {
    FETCH_CITIES,
    FETCH_CUSTOMER_DATA,
    FETCH_CUSTOMER_SETTINGS,
    FETCH_CUSTOMERS,
    UPDATE_CUSTOMER_DATA,
    UPDATE_CUSTOMER_SETTINGS,
    UPDATE_CUSTOMER_TYPE,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_CITIES,
    SET_API_ERROR,
    SET_CUSTOMER,
    SET_CUSTOMERS,
    SET_CUSTOMER_SETTINGS,
} from "./mutations.type";

const state = {
    cities: [],
    customer: null,
    customerSettings: null,
    isLoading: false,
    apiErrors: []
}

const getters = {
    cities(state) {
        return state.cities;
    },
    customers(state) {
        return state.customers;
    },
    customerSettings(state) {
        return state.customerSettings;
    },
    customer(state) {
        return state.customer;
    },
    apiErrors(state) {
        return state.apiErrors;
    }
}

const actions = {
    async [FETCH_CUSTOMERS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getCustomers(params);
            context.commit(SET_CUSTOMERS, response.data);
        } catch (error) {
            console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_CUSTOMER_DATA](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getCustomerData(params);
            context.commit(SET_CUSTOMER, response.data.data);
        } catch (error) {
            console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_CUSTOMER_SETTINGS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getCustomerSettings(params);
            context.commit(SET_CUSTOMER_SETTINGS, response.data.data);
        } catch (error) {
            console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },
    async [UPDATE_CUSTOMER_SETTINGS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateCustomerSettings(params);
            toast('Customer settings updated successfully.', {
                "type": "success",
            })
            context.commit(SET_CUSTOMER_SETTINGS, response.data.data);
        } catch (error) {
            console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },
    async [UPDATE_CUSTOMER_TYPE](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateCustomerType(params);
            toast('Customer settings updated successfully.', {
                "type": "success",
            })
            context.commit(SET_CUSTOMER_SETTINGS, response.data.data);
        } catch (error) {
            console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },
    async [UPDATE_CUSTOMER_DATA](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateCustomerData(params);
            toast('Customer data updated successfully.', {
                "type": "success",
            })
            context.commit(SET_CUSTOMER, response.data.data);
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
    [SET_CITIES](state, data) {
        state.cities = data.data;
        state.isLoading = false;
    },
    [SET_CUSTOMERS](state, data) {
        state.customers = data;
        state.isLoading = false;
    },
    [SET_CUSTOMER](state, data) {
        state.customer = data;
        state.isLoading = false;
    },
    [SET_CUSTOMER_SETTINGS](state, data) {
        state.customerSettings = data;
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
