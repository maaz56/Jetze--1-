import apiService from "./apiService";
import {
    DELETE_PACKAGE,
    FETCH_USERS,
    FETCH_PACKAGES,
    FETCH_PACKAGE, // Add new action type if needed
    SAVE_PACKAGE,
    UPDATE_PACKAGE,
    FETCH_HOLIDAYS,
    SAVE_HOLIDAY,
    UPDATE_HOLIDAY,
    DELETE_HOLIDAY,
    FETCH_HOLIDAY_HEADER_IMAGES,
    DELETE_HOLIDAY_HEADER_IMAGES,
    SAVE_HOLIDAY_HEADER_IMAGES,
} from "./actions.type";
import {
    IS_LOADING,
    NOT_IS_LOADING,
    SET_API_ERROR,
    SET_HOLIDAY_HEADER_IMAGES,
    SET_HOLIDAYS,
} from "./mutations.type";
import { toast } from "vue3-toastify";

const state = {
    holidays: [],
    holiday: null,
    headerImages: [],
    isLoading: false,
    apiErrors: [],
};

const getters = {
    holidays(state) {
        return state.holidays;
    },
    holiday: (state) => (id) => {
        if (state.holidays != null) {
            var holiday = state.holidays.find((holiday) => holiday.id == id);
            if (holiday) {
                return holiday;
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
    async [FETCH_HOLIDAYS](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getHolidays(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_HOLIDAYS, response.data);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_HOLIDAY](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveHoliday(params);
            context.dispatch(FETCH_HOLIDAYS);
            toast("Holiday saved successfully", {
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

    async [UPDATE_HOLIDAY](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.updateHoliday(params);
            //console.log(response.data);
            context.dispatch(FETCH_HOLIDAYS);
            toast("Holiday has been updated successfully.", {
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

    async [DELETE_HOLIDAY](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteHolidays(params);
            context.dispatch(FETCH_HOLIDAYS);
            toast("Holiday has been deleted successfully.", {
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

    async [FETCH_HOLIDAY_HEADER_IMAGES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getHolidayHeaderImages(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_HOLIDAY_HEADER_IMAGES, response.data);
        } catch (error) {
            //console.log(error);
            toast("Something went wrong.", {
                type: "error",
            });
            context.commit(SET_API_ERROR, error);
        }
    },

    async [SAVE_HOLIDAY_HEADER_IMAGES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.saveHolidayHeaderImages(params);
            context.dispatch(FETCH_HOLIDAY_HEADER_IMAGES);
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

    async [DELETE_HOLIDAY_HEADER_IMAGES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteHolidayHeaderImages(params);
            context.dispatch(FETCH_HOLIDAY_HEADER_IMAGES);
            toast("Holiday header image deleted successfully.", {
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
    [SET_HOLIDAYS](state, data) {
        state.holidays = data;
        state.isLoading = false;
    },
    [SET_HOLIDAY_HEADER_IMAGES](state, data) {
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
