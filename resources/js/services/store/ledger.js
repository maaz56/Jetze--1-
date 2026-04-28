import { toast } from "vue3-toastify";
import { FETCH_AGENT_LEDGER, FETCH_PROFIT_LOSS_REPORT } from "./actions.type";
import apiService from "./apiService";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_AGENT_LEDGER,
    SET_PROFIT_LOSS_REPORT,
} from "./mutations.type";

const state = {
    agentLedgerData: null,
    isLoading: false,
    apiErrors: [],
};

const getters = {
    agentLedgerData(state) {
        return state.agentLedgerData;
    },
    profitLossReport(state) {
        return state.profitLossReport;
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_AGENT_LEDGER](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getAgentStatment(params);
            // console.log(JSON.stringify(response.data));
            context.commit(SET_AGENT_LEDGER, response.data);

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
    async [FETCH_PROFIT_LOSS_REPORT](context, params) {
    context.commit(IS_LOADING);
    try {
        const response = await apiService.getProfitLossReport(params);
        console.log(JSON.stringify(response.data));
        context.commit(SET_PROFIT_LOSS_REPORT, response.data);
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
    [SET_AGENT_LEDGER](state, data) {
        state.agentLedgerData = data;
        state.isLoading = false;
    },
    [SET_PROFIT_LOSS_REPORT](state, data) {
        state.profitLossReport = data;
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
