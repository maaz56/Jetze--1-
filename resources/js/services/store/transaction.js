import apiService from "./apiService";
import {
    SAVE_TRANSACTION,
    FETCH_TRANSACTIONS,
    UPDATE_TRANSACTION,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_TRANSACTIONS,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    transactions: [],
    isLoading: false,
    validationErrors: []
};

const getters = {
    transactions(state) {
        return state.transactions;
    },
    isLoading(state) {
        return state.isLoading;
    },
    validationErrors(state) {
        return state.validationErrors;
    }
};

const actions = {
    async [FETCH_TRANSACTIONS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getTransaction(params);
            //console.log(response.data);
            context.commit(SET_TRANSACTIONS, response.data)
        } catch (error) {
            //console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_TRANSACTION](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveTransaction(params);
            //console.log(response.data);
            toast('Transaction saved successfully.', {
                "type": "success",
            })
        } catch (error) {
            //console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    },
    
    async [UPDATE_TRANSACTION](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateTransaction(params);
            //console.log(response.data);
            toast('Transaction updated successfully.', {
                "type": "success",
            })
        } catch (error) {
            //console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(SET_API_ERROR, error);
        }
    }
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
            //console.log(error.response.data.errors)
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_TRANSACTIONS](state, data) {
        state.transactions = data;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};
