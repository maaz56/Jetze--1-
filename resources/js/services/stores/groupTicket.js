import apiService from "@/config/axios";
import { defineStore } from "pinia";
import { toast } from "vue3-toastify";

export const useGroupTicketStore = defineStore("groupTicket", {
    state: () => ({
        flights: [],
        flight: {},
        isLoading: false,
        validationErrors: [],
    }),
    getters: {
        tickets: (state) => state.flights,
        ticket: (state) => (id) => {
            return state.flights.find((ticket) => ticket.id == id) || null;
        },
        isLoading: (state) => state.isLoading,
        validationErrors: (state) => state.validationErrors,
    },
    actions: {
        async fetchGroupTickets(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/group-tickets", {
                    params: params,
                });
                //console.log(JSON.stringify(response.data));
                this.flights = response.data.flights;
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
                    this.validationErrors = error.response.data.errors;
                }
            } finally {
                this.isLoading = false;
            }
        },

        async saveGroupTicket(params) {
            this.isLoading = true;
            try {
                const response = await apiService.post(
                    "/group-tickets",
                    params,
                );
                //console.log(JSON.stringify(response.data));
                await this.fetchGroupTickets();
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
                    this.validationErrors = error.response.data.errors;
                }
            } finally {
                this.isLoading = false;
            }
        },

        async updateGroupTicket(params) {
            this.isLoading = true;
            try {
                const response = await apiService.put("/group-tickets", params);
                //console.log(JSON.stringify(response.data));
                await this.fetchGroupTickets();
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
                    this.validationErrors = error.response.data.errors;
                }
            } finally {
                this.isLoading = false;
            }
        },

        async deleteGroupTicket(params) {
            this.isLoading = true;
            try {
                const response = await apiService.delete("/group-tickets", {
                    params: params,
                });
                //console.log(JSON.stringify(response.data));
                await this.fetchGroupTickets();
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
                    this.validationErrors = error.response.data.errors;
                }
            } finally {
                this.isLoading = false;
            }
        },
    },
});
