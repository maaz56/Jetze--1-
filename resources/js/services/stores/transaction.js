import { defineStore } from "pinia";
import apiService from "@/config/axios";
import { toast } from "vue3-toastify";

export const useTransactionStore = defineStore("transaction", {
    state: () => ({
        transactions: [],
        isLoading: false,
        validationErrors: [],
    }),
    getters: {
        getTransactions(state) {
            return state.transactions;
        },
        getIsLoading(state) {
            return state.isLoading;
        },
        getValidationErrors(state) {
            return state.validationErrors;
        },
    },
    actions: {
        async fetchTransactions(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("transactions", {
                    params: params,
                });
                this.transactions = response.data;
            } catch (error) {
                toast("Something went wrong.", {
                    type: "error",
                });
                this.validationErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async saveTransaction(params) {
            this.isLoading = true;
            try {
                const response = await apiService.post("transactions", params);
                toast("Transaction saved successfully.", {
                    type: "success",
                });
            } catch (error) {
                toast("Something went wrong.", {
                    type: "error",
                });
                this.validationErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },

        async updateTransaction(params) {
            this.isLoading = true;
            try {
                const response = await apiService.put("transactions", params);
                toast("Transaction updated successfully.", {
                    type: "success",
                });
            } catch (error) {
                toast("Something went wrong.", {
                    type: "error",
                });
                this.validationErrors = error.response?.data?.errors || [];
            } finally {
                this.isLoading = false;
            }
        },
    },
});
