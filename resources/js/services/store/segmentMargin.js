import apiService from "./apiService";
import {
    DELETE_SEGMENT_MARGIN,
    FETCH_SEGMENT_MARGIN,
    FETCH_SEGMENT_MARGINS,
    FETCH_SEGMENT_MARGIN_PROVIDERS,
    SAVE_SEGMENT_MARGIN,
    UPDATE_SEGMENT_MARGIN,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_SEGMENT_MARGIN,
    SET_SEGMENT_MARGINS,
    SET_SEGMENT_MARGIN_PROVIDERS,
    SET_API_ERROR,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    margins: [],
    margin: null,
    providers: [],
    isLoading: false,
    apiErrors: [],
};

const getters = {
    segmentMargins(state) {
        return state.margins;
    },
    segmentMargin(state) {
        return state.margin;
    },
    providers(state) {
        return state.providers;
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_SEGMENT_MARGINS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getSegmentMargins(params);
            context.commit(SET_SEGMENT_MARGINS, response.data);
            return response.data;
        } catch (error) {
            toast("Something went wrong.", { type: "error" });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [FETCH_SEGMENT_MARGIN](context, id) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getSegmentMargin(id);
            context.commit(SET_SEGMENT_MARGIN, response.data);
            return response.data;
        } catch (error) {
            toast("Something went wrong.", { type: "error" });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [SAVE_SEGMENT_MARGIN](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveSegmentMargin(params);
            toast("Segment margin saved successfully.", { type: "success" });
            return response.data;
        } catch (error) {
            toast(error.response?.data?.message || "Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [UPDATE_SEGMENT_MARGIN](context, payload) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateSegmentMarginById(payload.id, payload);
            toast("Segment margin updated successfully.", { type: "success" });
            return response.data;
        } catch (error) {
            toast(error.response?.data?.message || "Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [DELETE_SEGMENT_MARGIN](context, id) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteSegmentMargin(id);
            toast("Segment margin deleted successfully.", { type: "success" });
            return response.data;
        } catch (error) {
            toast(error.response?.data?.message || "Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [FETCH_SEGMENT_MARGIN_PROVIDERS](context) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getSegmentMarginProviders();
            context.commit(SET_SEGMENT_MARGIN_PROVIDERS, response.data);
            return response.data;
        } catch (error) {
            toast("Something went wrong.", { type: "error" });
            context.commit(SET_API_ERROR, error);
            throw error;
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
        if (error.response && error.response.data && error.response.data.errors) {
            state.apiErrors = error.response.data.errors;
        }
    },
    [SET_SEGMENT_MARGINS](state, data) {
        state.margins = data;
    },
    [SET_SEGMENT_MARGIN](state, data) {
        state.margin = data;
    },
    [SET_SEGMENT_MARGIN_PROVIDERS](state, data) {
        state.providers = data;
    },
};

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
};
