import { toast } from "vue3-toastify";
import { DELETE_DEPOSIT_DATA, UPDATE_DEPOSIT_STATUS, FETCH_TOTAL_APPROVED_DEPOSIT, FETCH_DEPOSIT_DATA, SAVE_DEPOSIT_DATA, FETCH_DEPOSITS_DATA_AGENTS, FETCH_DEPOSIT_DETAILS } from "./actions.type";
import apiService from "./apiService";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_DEPOSIT_DATA,
    SET_DEPOSIT_TOTAL_DATA,



} from "./mutations.type";

const state = {
    depositData: {},
    depositDataWithAgents: {},
    totalApprovedDeposit: {},
    depositDetails: {},
    isLoading: false,
    apiErrors: [],
};

const getters = {
    depositData(state) {
        return state.depositData;
    },
    depositDataWithAgents(state) {
        return state.depositDataWithAgents;
    },
    depositDetails(state) {
        return state.depositDetails;
    },
    totalApprovedDeposit(state) {
        return state.totalApprovedDeposit;
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_DEPOSIT_DATA](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getDepositData(params);
            context.commit(SET_DEPOSIT_DATA, response.data);

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

    async [FETCH_DEPOSITS_DATA_AGENTS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getDepositDataWithAgents(params);
            context.commit(SET_DEPOSIT_DATA, response.data);

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

    async [FETCH_DEPOSIT_DETAILS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getDepositDetails(params);
            context.commit(SET_DEPOSIT_DATA, response.data);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_TOTAL_APPROVED_DEPOSIT](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getTotalApprovedDeposits(params);
            //console.log(response.data);
            context.commit(SET_DEPOSIT_TOTAL_DATA, response.data);

        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_DEPOSIT_DATA](context, params) {
        context.commit(IS_LOADING);
        try {

            const response = await apiService.saveDepositData(params);
            context.dispatch(FETCH_DEPOSIT_DATA);
            toast("Deposit saved successfully", {
                type: "success",
            });
        } catch (error) {
            console.log(error);
            toast(error, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [UPDATE_DEPOSIT_STATUS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateDepositStatus(params);
            toast('Deposit approved successfully.', {
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

    async [DELETE_DEPOSIT_DATA](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteDepositData(params);
            context.dispatch(FETCH_DEPOSIT_DATA);
            toast("Deposit has been deleted successfully.", {
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
    [SET_DEPOSIT_DATA](state, data) {
        state.depositData = data;
        state.depositDataWithAgents = data;
        state.totalApprovedDeposit = data;
        state.depositDetails = data;
        state.isLoading = false;
    },
    [SET_DEPOSIT_TOTAL_DATA](state, data) {
       
        state.totalApprovedDeposit = data;
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
