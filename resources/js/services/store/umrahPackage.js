import apiService from "./apiService";
import {
    DELETE_UMRAH_PACKAGE,
    FETCH_USERS,
    FETCH_UMRAH_PACKAGES,
    SAVE_UMRAH_PACKAGE,
    UPDATE_UMRAH_PACKAGE,
    FETCH_UMRAH_HEADER_IMAGES,
    SAVE_UMRAH_HEADER_IMAGES,
    DELETE_UMRAH_HEADER_IMAGES,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_UMRAH_HEADER_IMAGES,
    SET_UMRAH_PACKAGES,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    umrahPackages: [],
    umrahPackage: {},
    headerImages: [],
    isLoading: false,
    apiErrors: [],
};

const getters = {
    umrahPackages(state) {
        return state.umrahPackages;
    },
    umrahPackage: (state) => (id) => {
        if (state.umrahPackages != null) {
            var umrahPackage = state.umrahPackages.find(
                (umrahPackage) => umrahPackage.id == id
            );
            if (umrahPackage) {
                return umrahPackage;
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
    async [FETCH_UMRAH_PACKAGES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getUmrahPackages(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_UMRAH_PACKAGES, response.data);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_UMRAH_PACKAGE](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveUmrahPackage(params);
            context.dispatch(FETCH_UMRAH_PACKAGES);
            toast("Umrah package saved successfully", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast(error.response.data.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [UPDATE_UMRAH_PACKAGE](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateUmrahPackage(params);
            //console.log(JSON.stringify(response.data));
            context.dispatch(FETCH_UMRAH_PACKAGES);
            toast("User has been updated successfully.", {
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

    async [DELETE_UMRAH_PACKAGE](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteUmrahPackage(params);
            context.dispatch(FETCH_UMRAH_PACKAGES);
            toast("Umrah package has been deleted successfully.", {
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

    async [FETCH_UMRAH_HEADER_IMAGES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getUmrahHeaderImages(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_UMRAH_HEADER_IMAGES, response.data);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_UMRAH_HEADER_IMAGES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveUmrahHeaderImages(params);
            context.dispatch(FETCH_UMRAH_HEADER_IMAGES);
            toast("Header images saved successfully", {
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

    async [DELETE_UMRAH_HEADER_IMAGES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteUmrahHeaderImages(params);
            context.dispatch(FETCH_UMRAH_HEADER_IMAGES);
            toast("Umrah header image deleted successfully.", {
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
    [SET_UMRAH_PACKAGES](state, data) {
        state.umrahPackages = data;
        state.isLoading = false;
    },
    [SET_UMRAH_HEADER_IMAGES](state, data) {
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
