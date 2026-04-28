import { toast } from "vue3-toastify";
import { defineStore } from "pinia";
import apiService from "@/config/axios";

export const useAirportStore = defineStore("airport", {
    state: () => ({
        airports: [],
        origins: [],
        destinations: [],
        isLoading: false,
        apiErrors: [],
    }),
    getters: {
        getAirports: (state) => state.airports,
        getOrigins: (state) => state.origins,
        getDestinations: (state) => state.destinations,
        getIsLoading: (state) => state.isLoading,
        getApiErrors: (state) => state.apiErrors,
    },
    actions: {
        async fetchAirports(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/airports", {
                    params: params,
                });
                this.airports = response.data;
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
