import apiService from "./apiService";
import {
    FETCH_CUSTOMER_MARGIN,
    SAVE_CUSTOMER_MARGIN,
    
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_CUSTOMER_MARGIN,
    
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
  
    b2c: [],
    isLoading: false,
    apiErrors: [],
};

const getters = {
    customerMargin(state) {
        return state.customerMargin;
    },
   
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_CUSTOMER_MARGIN](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getCustomerMargin(params);
            //console.log(JSON.stringify(response));
            context.commit(SET_CUSTOMER_MARGIN, response.data);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_CUSTOMER_MARGIN](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveCustomerMargin(params);
            console.log(JSON.stringify(response));
            toast("Margin values saved successfully", {
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
   
    [SET_CUSTOMER_MARGIN](state, data) {
        state.customerMargin = data;
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
