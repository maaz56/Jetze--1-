import apiService from "@/config/axios";
import { defineStore } from "pinia";
import { toast } from "vue3-toastify";

export const useVisaStore = defineStore("visa", {
    state: () => ({
        visas: [],
        visa: {},
        headerImages: [],
        isLoading: false,
        apiErrors: [],
    }),
    getters: {
        getVisas(state) {
            return state.visas;
        },
        getVisa: (state) => (id) => {
            return state.visas.find((visa) => visa.id == id) || null;
        },
        getHeaderImages(state) {
            return state.headerImages;
        },
        getIsLoading(state) {
            return state.isLoading;
        },
        getApiErrors(state) {
            return state.apiErrors;
        },
    },
    actions: {
        async fetchVisas(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/visas", {
                    params: params,
                });
                //console.log(JSON.stringify(response.data));
                this.visas = response.data;
            } catch (error) {
                //console.log(error);
                toast("Something went wrong.", { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async saveVisa(params) {
            this.isLoading = true;
            try {
                await apiService.post("/visas", params);
                await this.fetchVisas();
                toast("Visa saved successfully", { type: "success" });
            } catch (error) {
                //console.log(error);
                toast(error, { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async updateVisa(params) {
            this.isLoading = true;
            try {
                await apiService.put("/visas", params);
                await this.fetchVisas();
                toast("User has been updated successfully.", {
                    type: "success",
                });
            } catch (error) {
                //console.log(error);
                toast("Something went wrong.", { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async deleteVisa(params) {
            this.isLoading = true;
            try {
                await apiService.delete("/visas", {
                    params: params,
                });
                await this.fetchVisas();
                toast("Visa has been deleted successfully.", {
                    type: "success",
                });
            } catch (error) {
                //console.log(error);
                toast("Something went wrong.", { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async fetchVisaHeaderImages(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/visa-header-images");
                //console.log(JSON.stringify(response.data));
                this.headerImages = response.data;
            } catch (error) {
                //console.log(error);
                toast("Something went wrong.", { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async saveVisaHeaderImages(params) {
            this.isLoading = true;
            try {
                await apiService.post("/visa-header-images", params);
                await this.fetchVisaHeaderImages();
                toast("Header images saved successfully", { type: "success" });
            } catch (error) {
                //console.log(error);
                toast(error, { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async deleteVisaHeaderImages(params) {
            this.isLoading = true;
            try {
                await apiService.delete("/visa-header-images", {
                    params: params,
                });
                await this.fetchVisaHeaderImages();
                toast("Visa header image deleted successfully.", {
                    type: "success",
                });
            } catch (error) {
                //console.log(error);
                toast("Something went wrong.", { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },
    },
});
