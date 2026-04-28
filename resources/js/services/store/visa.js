import apiService from "./apiService";
import {
    DELETE_VISA,
    DELETE_VISA_HEADER_IMAGES,
    FETCH_USERS,
    FETCH_VISA_HEADER_IMAGES,
    FETCH_VISAS,
    SAVE_VISA,
    SAVE_VISA_HEADER_IMAGES,
    UPDATE_VISA,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_VISA_HEADER_IMAGES,
    SET_VISAS,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    visas: [],
    visa: {},
    headerImages: [],
    isLoading: false,
    apiErrors: [],
};

const getters = {
    visas(state) {
        return state.visas;
    },
    visa: (state) => (id) => {
        if (state.visas != null) {
            var visa = state.visas.find((visa) => visa.id == id);
            if (visa) {
                return visa;
            }
        }
        return null;
    },
    headerImages(state) {
        return state.headerImages;
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
};

const actions = {
    async [FETCH_VISAS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getVisas(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_VISAS, response.data);
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_VISA](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveVisa(params);
            context.dispatch(FETCH_VISAS);
            toast("Visa saved successfully", {
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

    async [UPDATE_VISA](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateVisa(params);
            //console.log(response.data);
            context.dispatch(FETCH_VISAS);
            toast("User has been updated successfully.", {
                type: "success",
            });
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [DELETE_VISA](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteVisa(params);
            context.dispatch(FETCH_VISAS);
            toast("Visa has been deleted successfully.", {
                type: "success",
            });
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [FETCH_VISA_HEADER_IMAGES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getVisaHeaderImages(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_VISA_HEADER_IMAGES, response.data);
        } catch (error) {
            console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_VISA_HEADER_IMAGES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveVisaHeaderImages(params);
            context.dispatch(FETCH_VISA_HEADER_IMAGES);
            toast("Header images saved successfully", {
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

    async [DELETE_VISA_HEADER_IMAGES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteVisaHeaderImages(params);
            context.dispatch(FETCH_VISA_HEADER_IMAGES);
            toast("Visa header image deleted successfully.", {
                type: "success",
            });
        } catch (error) {
            console.log(error);
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
    [SET_VISAS](state, data) {
        state.visas = data;
        state.isLoading = false;
    },
    [SET_VISA_HEADER_IMAGES](state, data) {
        state.headerImages = data;
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
