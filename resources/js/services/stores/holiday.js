import apiService from "@/config/axios";
import { defineStore } from "pinia";
import { toast } from "vue3-toastify";

export const useHolidayStore = defineStore("holiday", {
    state: () => ({
        holidays: [],
        holiday: null,
        headerImages: [],
        isLoading: false,
        apiErrors: [],
    }),
    getters: {
        getHolidays(state) {
            return state.holidays;
        },
        getHoliday: (state) => (id) => {
            return state.holidays.find((holiday) => holiday.id == id) || null;
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
        async fetchHolidays(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/holidays", {
                    params: params,
                });
                //console.log(JSON.stringify(response.data));
                this.holidays = response.data;
            } catch (error) {
                //console.log(error);
                toast("Something went wrong.", { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async saveHoliday(params) {
            this.isLoading = true;
            try {
                await apiService.post("/holidays", params);
                await this.fetchHolidays();
                toast("Holiday saved successfully", { type: "success" });
            } catch (error) {
                //console.log(error);
                toast(error, { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async updateHoliday(params) {
            this.isLoading = true;
            try {
                await apiService.put(`/holidays`, params);
                await this.fetchHolidays();
                toast("Holiday has been updated successfully.", {
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

        async deleteHoliday(params) {
            this.isLoading = true;
            try {
                await apiService.delete("/holidays", {
                    params: params,
                });
                await this.fetchHolidays();
                toast("Holiday has been deleted successfully.", {
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

        async fetchHolidayHeaderImages(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/holiday-header-images");
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

        async saveHolidayHeaderImages(params) {
            this.isLoading = true;
            try {
                await apiService.post("/holiday-header-images", params);
                await this.fetchHolidayHeaderImages();
                toast("Header images saved successfully", { type: "success" });
            } catch (error) {
                //console.log(error);
                toast(error, { type: "error" });
                this.apiErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async deleteHolidayHeaderImages(params) {
            this.isLoading = true;
            try {
                await apiService.delete("/holiday-header-images", {
                    params: params,
                });
                await this.fetchHolidayHeaderImages();
                toast("Holiday header image deleted successfully.", {
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
