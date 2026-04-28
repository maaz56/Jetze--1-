import { toast } from "vue3-toastify";
import { SAVE_PROMO_IMAGE, DELETE_PROMO_IMAGE, FETCH_PROMO_IMAGES, UPDATE_BOOKING_STATUS, FETCH_BOOKING_STATS_SETINGS, FETCH_ACTIVITY_LOGS, DELETE_ACTIVITY_LOG } from "./actions.type";
import apiService from "./apiService";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_ACTIVITY_LOGS,
    SET_ADMIN_BOOKING,
    SET_API_ERROR,
    SET_BOOKING_STATUS_SETTING,
    SET_PROMO_IMAGE
} from "./mutations.type";

const state = {
    activityLogs:null,
    
    isLoading: false,
    apiErrors: [],
};

const getters = {
    activityLogs(state) {
        return state.activityLogs;
    },

    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_ACTIVITY_LOGS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getActivityLogs(params);
            context.commit(SET_ACTIVITY_LOGS, response.data);

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
    async [DELETE_ACTIVITY_LOG](context, logId) {
        context.commit(IS_LOADING);
        try {
            await apiService.deleteActivityLog(logId);
            toast("Activity log deleted successfully.", {
                type: "success",
            });
            context.dispatch(FETCH_ACTIVITY_LOGS);
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
    [SET_ACTIVITY_LOGS](state, data) {
        state.activityLogs = data;
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
