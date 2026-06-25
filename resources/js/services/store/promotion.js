import apiService from "./apiService";
import {
    DELETE_PROMOTION,
    FETCH_PROMOTION,
    FETCH_PROMOTIONS,
    FETCH_PROMOTION_PROVIDERS,
    SAVE_PROMOTION,
    UPDATE_PROMOTION,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_PROMOTION,
    SET_PROMOTIONS,
    SET_PROMOTION_PROVIDERS,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    promotions: [],
    promotion: null,
    providers: [],
    isLoading: false,
    apiErrors: [],
};

const getters = {
    promotions(state) {
        return state.promotions;
    },
    promotion(state) {
        return state.promotion;
    },
    promotionProviders(state) {
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
    async [FETCH_PROMOTIONS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getPromotions(params);
            
            context.commit(SET_PROMOTIONS, response.data);
            return response.data;
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", { type: "error" });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [FETCH_PROMOTION](context, id) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getPromotion(id);
            context.commit(SET_PROMOTION, response.data);
            return response.data;
        } catch (error) {
            toast("Something went wrong.", { type: "error" });
            context.commit(SET_API_ERROR, error);
            throw error;
        } finally {
            context.commit(NOT_IS_LOADING);
        }
    },

    async [SAVE_PROMOTION](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.savePromotion(params);
            toast("Promotion saved successfully.", { type: "success" });
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

    async [UPDATE_PROMOTION](context, payload) {
        context.commit(IS_LOADING);
        try {
            const data = payload?.data ?? payload;
            const response = await apiService.updatePromotion(payload.id, data);
            toast("Promotion updated successfully.", { type: "success" });
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

    async [DELETE_PROMOTION](context, id) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deletePromotion(id);
            toast("Promotion deleted successfully.", { type: "success" });
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

    async [FETCH_PROMOTION_PROVIDERS](context) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getPromotionProviders();
            context.commit(SET_PROMOTION_PROVIDERS, response.data);
            return response.data;
        } catch (error) {
            console.log(error);
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
    [SET_PROMOTIONS](state, data) {
        state.promotions = data;
    },
    [SET_PROMOTION](state, data) {
        state.promotion = data;
    },
    [SET_PROMOTION_PROVIDERS](state, data) {
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
