import {
    DELETE_UPLOADED_FILE,
    FETCH_UPLOADED_FILES,
    UPLOAD_FILE,
} from "./actions.type";
import apiService from "./apiService";
import { toast } from "vue3-toastify";
import { IS_LOADING, SET_FILES } from "./mutations.type";

const state = {
    files: JSON.parse(localStorage.getItem("uploads")) || [],
    isLoading: false,
    validationErrors: null,
};

const getters = {
    files(state) {
        return state.files;
    },
    isLoading(state) {
        return state.isLoading;
    },
    validationErrors(state) {
        return state.validationErrors;
    },
};

const actions = {
    async [FETCH_UPLOADED_FILES](context, params) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.getUploadedFiles(params);
            //console.log(JSON.stringify(response.data));
            context.commit(SET_FILES, response.data);
        } catch (error) {
            //console.log(error);
            toast(error, {
                theme: "dark",
                type: "error",
                dangerouslyHTMLString: true,
            });
        }
    },

    async [UPLOAD_FILE](context, formData) {
        context.commit(IS_LOADING);
        try {
            const response = await apiService.uploadFile(formData);
            //console.log(JSON.stringify(response.data));
            context.dispatch(FETCH_UPLOADED_FILES);
            toast("File Uploaded.", {
                theme: "dark",
                type: "success",
                dangerouslyHTMLString: true,
            });
        } catch (error) {
            //console.log(error);
            toast(error, {
                theme: "dark",
                type: "error",
                dangerouslyHTMLString: true,
            });
        }
    },

    async [DELETE_UPLOADED_FILE](context, params) {
        //console.log(params);
        context.commit(IS_LOADING);
        try {
            const response = await apiService.deleteUploadedFile(params);
            //console.log(JSON.stringify(response.data));
            context.dispatch(FETCH_UPLOADED_FILES);
            context.commit(
                SET_FILES,
                context.state.files.filter((file) => file.name !== params.name)
            );
            toast("File deleted successfully.", {
                theme: "dark",
                type: "success",
                dangerouslyHTMLString: true,
            });
        } catch (error) {
            //console.log(error);
            toast(error, {
                theme: "dark",
                type: "error",
                dangerouslyHTMLString: true,
            });
        }
    },
};

const mutations = {
    [IS_LOADING](state) {
        state.isLoading = true;
    },
    [SET_FILES](state, data) {
        let uploads = JSON.parse(localStorage.getItem("uploads")) || [];

        // Set the files array after deletion or new upload
        if (data && Array.isArray(data)) {
            uploads = data;
            localStorage.setItem("uploads", JSON.stringify(uploads));
        }

        state.files = uploads;
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
