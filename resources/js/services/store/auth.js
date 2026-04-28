import { toast } from "vue3-toastify";
import { FETCH_USER, FORGOT_PASSWORD, LOGIN, LOGOUT, REGISTER, RESET_PASSWORD, SHOW_LOGIN_DIALOG, VERIFY_EMAIL } from "./actions.type";
import apiService from "./apiService";
import {
    IS_LOADING,
    SET_USER,
    SET_TOKEN,
    RESET_TOKEN,
    SET_API_ERROR,
    SET_DIALOG_OPEN,
} from "./mutations.type";

const state = {
    user: null,
    token: localStorage.getItem("access_token") || null,
    isDialogOpen: false,
    isLoading: false,
    apiError: null
}

const getters = {
    token(state) {
        return state.token;
    },
    user(state) {
        return state.user;
    },
    isDialogOpen(state) {
        return state.isDialogOpen;
    },
    isAuthenticated: (state) => state.user !== '' && state.token !== null,
    isLoading(state) {
        return state.isLoading;
    },
    apiError(state) {
        return state.apiMessage;
    }
}

const actions = {
    async [FETCH_USER](context) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getUser();
            //console.log(JSON.stringify(response.data));
            context.commit(SET_USER, response.data);
            context.state.isLoading = false;
        } catch (error) {
            //console.log(error);
            context.state.isLoading = false;
        }
    },

    async [LOGIN](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.login(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_USER, response.data.user);
            context.commit(SET_TOKEN, response.data.authorisation);
            toast(response.data.message, {
                type: response.data.type,
            });
        } catch (error) {
            //console.log(error);
            context.commit(SET_API_ERROR, error);
        }   
    },

    async [REGISTER](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.register(params);
            //console.log(JSON.stringify(response.data));
            toast(response.data.message, {
                type: response.data.type,
            });
        } catch (error) {
            //console.log(error);
            context.commit(SET_API_ERROR, error);
        }
    },

    async [VERIFY_EMAIL](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.register(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_USER, response.data.user);
            context.commit(SET_TOKEN, response.data.authorisation);
            toast('Registered successfully.', {
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

    async [FORGOT_PASSWORD](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.forgotPassword(params);
            //console.log(JSON.stringify(response.data));
            toast('Your password has been changed.', {
                "type": "success",
            })
        } catch (error) {
            //console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(RESET_TOKEN);
            context.commit(SET_API_ERROR, error);
        }
    },

    async [RESET_PASSWORD](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.resetPassword(params);
            //console.log(JSON.stringify(response.data));
            toast('Your password has reset successfully.', {
                "type": "success",
            })
        } catch (error) {
            //console.log(error);
            toast('Something went wrong.', {
                "type": "error",
            })
            context.commit(RESET_TOKEN);
        }
    },

    async [LOGOUT](context) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.logout();
            //console.log(JSON.stringify(response.data));
            context.commit(RESET_TOKEN);
            toast(response.data.message, {
                type: response.data.type,
            });
        } catch (error) {
            //console.log(error);
            context.commit(SET_API_ERROR, error);
            context.commit(RESET_TOKEN);
        }
    },

    async [SHOW_LOGIN_DIALOG](context) {
        context.commit(IS_LOADING);
        try {
            console.log("Toggling login dialog");
            // Here you can add any logic needed before showing the login dialog
            context.commit(SET_DIALOG_OPEN);
        } catch (error) {
            //console.log(error);
            context.commit(SET_API_ERROR, error);
        }
    },
}

const mutations = {
    [IS_LOADING](state) {
        state.isLoading = true;
    },
    [SET_API_ERROR](state, error) {
        if (error?.response?.data?.message) {
            toast(error.response.data.message, {
                "type": error.response.data.type || "error",
            })
            state.isLoading = false;
        }
    },
    [SET_TOKEN](state, data) {
        var token = data.token;
        state.token = token;
        if (token) {
            localStorage.setItem("access_token", token);
        } else {
            toast('Failed to authenticate, please try again later.', {
                "type": "error",
                "dangerouslyHTMLString": true
            })
        }
    },
    [RESET_TOKEN](state) {
        state.token = null;
        state.user = null;
        localStorage.removeItem("access_token");
    },
    [SET_USER](state, data) {
        state.user = data;
        state.isLoading = false;
    },
    [SET_DIALOG_OPEN](state) {
        state.isDialogOpen = !state.isDialogOpen;
    },
}

export default {
    namespaced: true,
    state,
    getters,
    actions,
    mutations,
}