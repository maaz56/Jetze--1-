import apiService from "./apiService";
import {
    DELETE_USER,
    FETCH_USERS,
    SAVE_USER,
    UPDATE_USER,
    UPDATE_USER_STATUS,
    SAVE_AGENT_DATA,
    FETCH_AGENT_DATA,
    UPDATE_AGENT_DATA,
    SAVE_AGENT_MARGIN,
    SAVE_ADMIN_AGENT,
    SAVE_STAFF,
    UPDATE_STAFF,
    SAVE_AGENT_CHARGES,
    FETCH_AGENTS_CHARGES,
    FETCH_USER_SUMMARY,
    UPDATE_AGENT_CHARGE_STATUS,
    UPDATE_CARD_ALLOWANCE,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_AGENT_DATA,
    SET_API_ERROR,
    SET_STAFF,
    SET_USERS,
    SET_AGENT_CHARGES,
    SET_USERS_SUMMARY,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    users: {},
    usersSummary: null,
    isSaving: false,
    agentData: {},
    isLoading: false,
    apiErrors: [],
};

const getters = {
    users(state) {
        return state.users;
    },
    usersSummary(state) {
        return state.usersSummary;
    },
    isSaving(state) {
        return state.isSaving;
    },
    agentData(state) {
        return state.agentData;
    },
    agentCharges(state) {
        return state.agentCharges;
    },
    isLoading(state) {
        return state.isLoading;
    },
    apiErrors(state) {
        return state.apiErrors;
    },
    user: (state) => (id) => {
        if (state.users != null) {
            var user = state.users.find((user) => user.id == id);
            if (user) {
                return user;
            }
        }
        return null;
    },
};

const actions = {
    async [FETCH_USERS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getUsers(params);
            context.commit(SET_USERS, response.data);
        } catch (error) {
            //console.log(error);
            toast(error?.response?.data?.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [FETCH_USER_SUMMARY](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getUsersSummary(params);
            context.commit(SET_USERS_SUMMARY, response.data);
        } catch (error) {
            //console.log(error);
            toast(error?.response?.data?.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_AGENT_DATA](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveAgentData(params);
            toast("Admin Agent saved successfully.", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast(error?.response?.data?.message, {
                type: "success",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_ADMIN_AGENT](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveAdminAgentData(params);
            // console.log(response);
            toast("Admin Agent saved successfully.", {
                type: "success",
            });
        } catch (error) {
            console.log(error);
            toast(error?.response?.data?.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_STAFF](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveStaff(params);
            context.commit(SET_STAFF, "Staff member saved successfully");
            toast("Staff member saved successfully.", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast("Staff creation failed." + error, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [UPDATE_STAFF](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateStaff(params);
            context.commit(SET_STAFF, "Staff member updated successfully");
            toast("Staff member updated successfully.", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast("Staff creation failed.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [SAVE_AGENT_CHARGES](context, params) {
        context.state.isSaving = true;
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveAgentCharges(params);
            context.commit(
                SET_AGENT_CHARGES,
                "Charges saved successfully",
            );

            toast("Charges saved successfully.", {
                type: "success",
            });
            context.state.isSaving = false;

        } catch (error) {
            console.log(error);
            toast("Charges failed to save.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_AGENTS_CHARGES](context, params) {
        context.commit(IS_LOADING);
        try {
            //console.log(params);
            const response = await apiService.getAgentCharges(params);
            context.commit(SET_AGENT_CHARGES, response.data);
        } catch (error) {
            //console.log(error);
            toast(error?.response?.data?.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [UPDATE_AGENT_CHARGE_STATUS](context, params) {
                context.state.isSaving = true;

        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateAgentChargeStatus(params);
            toast("Agent charge status updated successfully.", {
                type: "success",
            });
            context.state.isSaving = false;
        } catch (error) {
            console.log(error);
            toast(error?.response?.data?.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_AGENT_MARGIN](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveAgentMargin(params);
            context.commit(SET_AGENT_DATA, "Agent margins saved successfully");
            toast(" Agent updated successfully.", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast(error?.response?.data?.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [FETCH_AGENT_DATA](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getAgentData(params);
            context.commit(SET_AGENT_DATA, response.data);
        } catch (error) {
            //console.log(error);
            toast(error?.response?.data?.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [UPDATE_AGENT_DATA](context, params) {
        context.commit(IS_LOADING);
        try {
            //console.log(params);
            const response = await apiService.updateAgentData(params);
            context.commit(SET_AGENT_DATA, "Agent data saved successfully");
            toast("User has been updated successfully.", {
                type: "success",
            });
        } catch (error) {
            console.log(error);
            toast(error?.response?.data?.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_USER](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveUser(params);
            context.dispatch(FETCH_USERS);
            context.commit(SET_API_SUCCESS, "User saved successfully");
            toast("User saved successfully.", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast(error?.response?.data?.message, {
                type: "success",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [UPDATE_USER_STATUS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateUserStatus(params);
            context.dispatch(FETCH_USERS);
            toast("User has been updated successfully.", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast(error?.response?.data?.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },
    async [UPDATE_CARD_ALLOWANCE](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateCardAllowance(params);
            context.dispatch(FETCH_USERS);
            toast("User has been updated successfully.", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast(error?.response?.data?.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [UPDATE_USER](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateUser(params);
            context.dispatch(FETCH_USERS);
            toast("User has been updated successfully.", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast(error?.response?.data?.message, {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [DELETE_USER](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateUser(params);
            context.dispatch(FETCH_USERS);
            toast("User has been deleted successfully.", {
                type: "success",
            });
        } catch (error) {
            //console.log(error);
            toast(error?.response?.data?.message, {
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
    [SET_USERS](state, data) {
        state.users = data.data;
        state.isLoading = false;
    },
    [SET_USERS_SUMMARY](state, data) {
        state.usersSummary = data.data;
        state.isLoading = false;
    },
    [SET_STAFF](state, data) {
        state.users = data.data;
        state.isLoading = false;
    },
    [SET_AGENT_DATA](state, data) {
        state.agentData = data;
        state.isLoading = false;
    },
    [SET_AGENT_CHARGES](state, data) {
        state.agentCharges = data;
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
