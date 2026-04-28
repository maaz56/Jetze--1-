import apiService from "@/config/axios";
import router from "@/config/router";
import {
    handleError,
    handleResponse,
    handleValidationMessage,
} from "@/lib/apiResponseHandler";
import { defineStore } from "pinia";
import { toast } from "vue3-toastify";

export const useAuthStore = defineStore("auth", {
    state: () => ({
        user: null,
        isLoggedIn: false,
        isDialogOpen: false,
        isLoading: false,
        success: false,
        validationMessages: null,
    }),

    // getters: {
    //     isAuthenticated(state) {
    //         return !!state.user;
    //     },
    getters: {
        isAuthenticated: (state) => !!state.user,
        isEmailVerified: (state) => state.user?.email_verified ?? false,
    },

    actions: {
        async fetchUser() {
            this.isLoading = true;
            try {
                const response = await apiService.get("user");
                this.user = response.data;
                // handleResponse(response);
                this.success = response.status === 200 || false;
                
                // if (this.success) {
                //     if (response?.data?.data?.user.role == "admin") {
                //         router.push({ name: "Dashboard" });
                //     } else if (
                //         response?.data?.data?.user.role == "agent" &&
                //         response?.data?.data?.user.is_approved == "0" &&
                //         response?.data?.data?.user.is_formFilled == "0"
                //     ) {
                //         router.push({ name: "AddAgentDetails" });
                //     } else if (response?.data?.data?.user.role == "agent") {
                //         this.isLoggedIn = true;
                //         router.push({ name: "DashboardFlights" });
                //     } else if (
                //         response?.data?.data?.user.role == "salesman" &&
                //         response?.data?.data?.user.is_approved == "1"
                //     ) {
                //         router.push({ name: "SalesmanUsers" });
                //     } else if (
                //         response?.data?.data?.user.role == "reservation" &&
                //         response?.data?.data?.user.is_approved == "1"
                //     ) {
                //         router.push({ name: "ReservationUsers" });
                //     } else if (response?.data?.data?.user.role == "customer") {
                //         this.isLoggedIn = true;
                //         this.isDialogOpen = false;
                //         // router.push({ name: "CustomerProfile" });
                //     } else {
                //         router.push({ name: "Login" });
                //     }
                // }
            } catch (error) {
                this.success = false;
            } finally {
                this.isLoading = false;
            }
        },
        openDialog() {
            this.isDialogOpen = !this.isDialogOpen;
           
        },
        async requestLoginOtp(params) {
            this.isLoading = true;
            try {
                const response = await apiService.post(
                    "/login/request-otp",
                    params,
                );
                this.success = response?.status===200 || false;
                return response;
            } catch (error) {
                this.success = false;
                this.validationMessages = handleValidationMessage(error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },
        async googleLogin() {
            try {
                // const response = await apiService.get("/auth/google");
                // Redirect to Google OAuth URL
                window.location.href = "/auth/google";
            } catch (error) {
                handleError(error);
                throw error;
            }
        },

        async loginWithGoogleCallback() {
            this.isLoading = true;
            try {
                // Call backend API after redirect callback
                const response = await apiService.get("/auth/google/callback");

                this.success = response?.data?.success || false;
                this.user = response?.data?.data?.user;

                if (this.success) {
                    const role = this.user?.role;
                    const approved = this.user?.is_approved;
                    const filled = this.user?.is_formFilled;

                    if (role === "admin") {
                        router.push({ name: "Dashboard" });
                    } else if (
                        role === "agent" &&
                        approved == "0" &&
                        filled == "0"
                    ) {
                        router.push({ name: "AddAgentDetails" });
                    } else if (role === "agent") {
                        this.isLoggedIn = true;
                        router.push({ name: "DashboardFlights" });
                    } else if (role === "salesman" && approved == "1") {
                        router.push({ name: "SalesmanUsers" });
                    } else if (role === "reservation" && approved == "1") {
                        router.push({ name: "ReservationUsers" });
                    } else if (role === "customer") {
                        this.isLoggedIn = true;
                        this.isDialogOpen = false;
                        // router.push({ name: "CustomerProfile" });
                    } else {
                        router.push({ name: "Login" });
                    }
                }

                return response.data;
            } catch (error) {
                this.success = false;
                this.validationMessages = handleValidationMessage(error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async verifyLoginOtp(params) {
            this.isLoading = true;
            try {
                const response = await apiService.post(
                    "/login/verify-otp",
                    params,
                );
                localStorage.setItem("access_token", response.data.token.plainTextToken);
                
                this.fetchUser();
                
                return response;
            } catch (error) {
                this.success = false;
                this.validationMessages = handleValidationMessage(error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async register(params) {
            console.log(params);
            this.isLoading = true;
            try {
                const response = await apiService.post("/register", params);
                console.log(response);
                // handleResponse(response);
                this.success = response?.data?.success || false;

                if (this.success) {
                    //console.log(response);
                    const user = response?.data?.user;
                    if (response?.data?.user.role == "admin") {
                        router.push({ name: "Dashboard" });
                    } else if (
                        response?.data?.user.role == "agent" &&
                        response?.data?.user.is_approved == "0" &&
                        response?.data?.user.is_formFilled == "0"
                    ) {
                        router.push({ name: "AddAgentDetails" });
                    } else if (
                        response?.data?.user.role == "agent" &&
                        response?.data?.user.is_approved == "0"
                    ) {
                        router.push({ name: "DashboardFlights" });
                    } else if (
                        response?.data?.user.role == "salesman" &&
                        response?.data?.user.is_approved == "1"
                    ) {
                        router.push({ name: "SalesmanUsers" });
                    } else if (
                        response?.data?.user.role == "reservation" &&
                        response?.data?.user.is_approved == "1"
                    ) {
                        router.push({ name: "ReservationUsers" });
                    } else if (response?.data?.user.role == "customer") {
                        this.isLoggedIn = true;
                        this.isDialogOpen = false;
                        
                        router.push({ name: "CustomerProfile" });
                    } else {
                        router.push({ name: "Login" });
                    }
                    //  if (user?.role == "admin" ||user?.role == "accounts" || user?.role == "reservation"
                    // ) {
                    //     router.push({ name: "Dashboard" });
                    // } else if (user?.role == "agent" && user?.is_approved == "0" &&user.user?.is_formFilled == '0') {
                    //     router.push({ name: "AddAgentDetails" });
                    //     //router.push({ name: "DashboardFlights" });
                    // }
                    // else if (user?.role == "agent" && user?.is_approved == "1") {
                    //     router.push({ name: "DashboardFlights" });
                    // }
                    // else {
                    //     router.push({ name: "Home" });
                    //     // window.location.reload();

                    // }
                }
            } catch (error) {
                this.success = false;
                console.log(error.response.data.message);
                // toast(error.response.data.message.details);
                // handleError(error);
                this.validationMessages = handleValidationMessage(error);
            } finally {
                this.isLoading = false;
            }
        },

        async verifyEmail() {
            this.isLoading = true;
            try {
                const response = await apiService.post(
                    "/email/verification-notification",
                );
                handleResponse(response);
                this.success = response?.status === 200 || false;
                return response;
            } catch (error) {
                this.success = false;
                handleError(error);
                throw error;
            } finally {
                this.isLoading = false;
            }
        },

        async forgotPassword(params) {
            this.isLoading = true;
            try {
                const response = await apiService.post(
                    "forgot-password",
                    params,
                );
                handleResponse(response);
                this.success = response?.data?.success || false;
                if (this.success) {
                    router.push({ name: "Home" });
                }
            } catch (error) {
                this.success = false;
                handleError(error);
                this.validationMessages = handleValidationMessage(error);
            } finally {
                this.isLoading = false;
            }
        },

        async resetPassword(params) {
            this.isLoading = true;
            try {
                //console.log(params);

                const response = await apiService.post(
                    "/reset-password",
                    params,
                );
                //console.log(response);
                handleResponse(response);
                this.success = response?.data?.success || false;

                if (this.success) {
                    router.push({ name: "Home" });
                }
            } catch (error) {
                this.success = false;
                handleError(error);
                this.validationMessages = handleValidationMessage(error);
            } finally {
                this.isLoading = false;
            }
        },

        async logout() {
            this.isLoading = true;
            try {
                const response = await apiService.post("logout");
                
                localStorage.removeItem("access_token");
                this.user = null;
                this.success = true;
                this.isLoggedIn = false;
                // Redirect user to home page after successfully logged out
                if (this.success) {
                    router.push({ name: "Home" });
                    //window.location.href = "/";
                }
            } catch (error) {
                handleError(error);
                this.success = false;
            } finally {
                this.isLoading = false;
            }
        },
    },
});
