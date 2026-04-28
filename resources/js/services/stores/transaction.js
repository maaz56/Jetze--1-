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
                //console.log(response.data);
                this.transactions = response.data;
            } catch (error) {
                //console.log(error);
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
                //console.log(response.data);
                toast("Transaction saved successfully.", {
                    type: "success",
                });
            } catch (error) {
                //console.log(error);
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
                //console.log(response.data);
                toast("Transaction updated successfully.", {
                    type: "success",
                });
            } catch (error) {
                //console.log(error);
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
