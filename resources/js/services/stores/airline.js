import { toast } from "vue3-toastify";
import { defineStore } from "pinia";
import apiService from "@/config/axios";

export const useAirlineStore = defineStore("airline", {
    state: () => ({
        airlines: [],
        isLoading: false,
        apiErrors: [],
    }),
    getters: {
        getAirlines: (state) => state.airlines,
        getIsLoading: (state) => state.isLoading,
        getApiErrors: (state) => state.apiErrors,
    },
    actions: {
        async fetchAirlines(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/airlines", {
                    params: params,
                });
                this.airlines = response.data;
            } catch (error) {
                //console.log(error);
                toast("Something went wrong.", {
                    type: "error",
                });
                if (
                    error.response &&
                    error.response.data &&
                    error.response.data.errors
                ) {
                    //console.log(error.response.data.errors);
                    this.apiErrors = error.response.data.errors;
                }
            } finally {
                this.isLoading = false;
            }
        },
    },
});
