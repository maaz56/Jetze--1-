import apiService from "@/config/axios";
import { defineStore } from "pinia";
import { toast } from "vue3-toastify";

export const useCityStore = defineStore("city", {
    state: () => ({
        cities: [],
        isLoading: false,
        apiErrors: [],
    }),
    getters: {
        getCities: (state) => state.cities,
        getApiErrors: (state) => state.apiErrors,
    },
    actions: {
        async fetchCities(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/cities", {
                    params: params,
                });
                //console.log("Cities" + JSON.stringify(response.data));
                this.cities = response.data.data;
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
