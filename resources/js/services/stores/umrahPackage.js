import { toast } from "vue3-toastify";
import { defineStore } from "pinia";
import apiService from "@/config/axios";

export const useUmrahPackageStore = defineStore("umrahPackage", {
    state: () => ({
        umrahPackages: [],
        umrahPackage: {},
        headerImages: [],
        isLoading: false,
        apiErrors: [],
    }),
    getters: {
        getUmrahPackages(state) {
            return state.umrahPackages;
        },
        getUmrahPackage: (state) => (id) => {
            return (
                state.umrahPackages.find(
                    (umrahPackage) => umrahPackage.id == id,
                ) || null
            );
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
        async fetchUmrahPackages(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/umrah-packages", {
                    params: params,
                });
                //console.log(JSON.stringify(response.data));
                this.umrahPackages = response.data;
            } catch (error) {
                //console.log(error);
                toast("Something went wrong.", { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async saveUmrahPackage(params) {
            this.isLoading = true;
            try {
                await apiService.post("/umrah-packages", params);
                await this.fetchUmrahPackages();
                toast("Umrah package saved successfully", { type: "success" });
            } catch (error) {
                //console.log(error);
                toast(error, { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async updateUmrahPackage(params) {
            this.isLoading = true;
            try {
                await apiService.put("/umrah-packages", params);
                //console.log(JSON.stringify(response.data));
                await this.fetchUmrahPackages();
                toast("Umrah package has been updated successfully.", {
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

        async deleteUmrahPackage(params) {
            this.isLoading = true;
            try {
                await apiService.delete("/umrah-packages", {
                    params: params,
                });
                await this.fetchUmrahPackages();
                toast("Umrah package has been deleted successfully.", {
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

        async fetchUmrahHeaderImages(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/umrah-header-images");
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

        async saveUmrahHeaderImages(params) {
            this.isLoading = true;
            try {
                await apiService.post("/umrah-header-images", params);
                await this.fetchUmrahHeaderImages();
                toast("Header images saved successfully", { type: "success" });
            } catch (error) {
                //console.log(error);
                toast(error, { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async deleteUmrahHeaderImages(params) {
            this.isLoading = true;
            try {
                await apiService.delete("/umrah-header-images", {
                    params: params,
                });
                await this.fetchUmrahHeaderImages();
                toast("Umrah header image deleted successfully.", {
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
