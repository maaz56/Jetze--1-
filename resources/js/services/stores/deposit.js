import apiService from "@/config/axios";
import { handleResponse } from "@/lib/apiResponseHandler";
import { defineStore } from "pinia";
import { handleError } from "vue";

export const useDepositStore = defineStore("deposit", {
    state: () => ({
        deposits: null,
        isLoading: false,
        success: false,
        validationErrors: null,
    }),

    getters: {
        getDeposits(state) {
            return state.deposits;
        },
        getIsLoading(state) {
            return state.isLoading;
        },
    },

    actions: {
        async fetchDeposits(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/deposits", {
                    params: params,
                });
                this.deposits = response.data;

                // handleResponse(response);
                this.success = response?.data?.success || false;
            } catch (error) {
                handleError(error);
                this.success = false;
            } finally {
                this.isLoading = false;
            }
        },

        async saveDeposit(params) {
            this.isLoading = true;
            try {
                const response = await apiService.post("/deposits", params);
                await this.fetchDeposits();

                handleResponse(response);
                this.success = response?.data?.success || false;
            } catch (error) {
                handleError(error);
                this.success = false;
            } finally {
                this.isLoading = false;
            }
        },
    },
});
