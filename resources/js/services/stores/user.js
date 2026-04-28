import apiService from "@/config/axios";
import { handleResponse } from "@/lib/apiResponseHandler";
import { defineStore } from "pinia";
import { handleError } from "vue";
import { toast } from "vue3-toastify";

export const useUserStore = defineStore("user", {
    state: () => ({
        users: null,
        user: null,
        isLoading: false,
        success: false,
        validationErrors: null,
    }),

    getters: {
        getUsers(state) {
            return state.users;
        },
        getUser(state) {
            return state.user;
        },
        getIsLoading(state) {
            return state.isLoading;
        },
    },

    actions: {
        async fetchUsers(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/users", {
                    params: params,
                });
                this.users = response.data;

                // handleResponse(response);
                this.success = response?.data?.success || false;
            } catch (error) {
                handleError(error);
                this.success = false;
            } finally {
                this.isLoading = false;
            }
        },

        async fetchUser(params) {
            this.isLoading = true;
            try {
                //console.log(params);
                const response = await apiService.get(`/users/${params.id}`);
                this.user = response.data.data;

                // handleResponse(response);
                this.success = response?.data?.success || false;
            } catch (error) {
                handleError(error);
                this.success = false;
            } finally {
                this.isLoading = false;
            }
        },

        async saveUser(params) {
            this.isLoading = true;
            try {
                const response = await apiService.post("/users", params);
                console.log(response);
                await this.fetchUsers();

                handleResponse(response);
                this.success = response?.data?.success || false;
            } catch (error) {
                handleError(error);
                this.success = false;
            } finally {
                this.isLoading = false;
            }
        },

        async updateUserStatus(params) {
            this.isLoading = true;
            try {
                const response = await apiService.put(
                    `/users/${params.user_id}`,
                    params,
                );
                await this.fetchUsers();

                handleResponse(response);
                this.success = response?.data?.success || false;
            } catch (error) {
                handleError(error);
                this.success = false;
            } finally {
                this.isLoading = false;
            }
        },
        async updateUser(params) {
            //console.log(params);
            this.isLoading = true;
            try {
                const response = await apiService.put(
                    `/users/${params.user_id}`,
                    params,
                );
                await this.fetchUsers();

                handleResponse(response);
                this.success = response?.data?.success || false;
            } catch (error) {
                handleError(error);
                this.success = false;
            } finally {
                this.isLoading = false;
            }
        },

        async deleteUser(params) {
            this.isLoading = true;
            try {
                await apiService.delete(`/users/${params.id}`);
                await this.fetchUsers();

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
