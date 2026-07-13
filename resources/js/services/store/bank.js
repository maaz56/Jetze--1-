import apiService from "./apiService";
import {
    DELETE_BANK,
    FETCH_BANKS,
    SAVE_BANK,
    UPDATE_BANK,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_BANK,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    banks: [],
    bank: {},
    isLoading: false,
    apiErrors: [],
};

const getters = {
    banks(state) {
        return state.banks;
    },
    bank: (state) => (id) => {
        if (state.banks != null) {
            var bank = state.bank.find((visa) => bank.id == id);
            if (bank) {
                return bank;
            }
        }
        return null;
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_BANKS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getBanks(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_BANK, response.data);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_BANK](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveBank(params);
            context.dispatch(FETCH_BANKS);
            toast("Bank saved successfully", {
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

    async [UPDATE_BANK](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateBank(params);
            //console.log(response.data);
            context.dispatch(FETCH_BANKS);
            toast("Bank has been updated successfully.", {
                type: "success",
            });
            return response;
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            return null;
        }
    },

    async [DELETE_BANK](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteBank(params);
            context.dispatch(FETCH_BANKS);
            toast("Bank has been deleted successfully.", {
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
    [SET_BANK](state, data) {
        state.banks = data;
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
