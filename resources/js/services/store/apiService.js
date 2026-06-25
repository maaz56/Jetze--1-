import apiClient from "@/config/axios";

export default {
    // auth
    login(params) {
        return apiClient.post("/login", params);
    },
    googleAuth() {
        return apiClient.get("/auth/google");
    },
    register(params) {
        return apiClient.post("/register", params);
    },

    getUser() {
        return apiClient.get("/user");
    },

    forgotPassword(params) {
        return apiClient.post("/forgot-password", params);
    },

    resetPassword(params) {
        return apiClient.post("/reset-password", params);
    },

    logout() {
        return apiClient.post("/logout");
    },

    // file uploader
    getUploadedFiles(params) {
        return apiClient.get("/uploads", {
            params: params,
        });
    },

    uploadFile(formData) {
        return apiClient.post("/uploads", formData, {
            headers: {
                "Content-Type": "multipart/form-data",
            },
        });
    },

    deleteUploadedFiles(params) {
        return apiClient.delete(`/uploads/${params.file_name}`);
    },

    // countries
    getCountries(params) {
        return apiClient.get("/countries", {
            params: params,
        });
    },

    getCities(params) {
        return apiClient.get("/cities", {
            params: params,
        });
    },

    getUsers(params) {
        return apiClient.get("/users", {
            params: params,
        });
    },

    getUsersSummary(params) {
        return apiClient.get("/users-summary", {
            params: params,
        });
    },

    saveUser(params) {
        return apiClient.post("/users", params);
    },

    saveAgentData(params) {
        return apiClient.post("/agents", params);
    },

    saveAdminAgentData(params) {
        return apiClient.post("/save-admin-agent", params);
    },

    sendPriceRequest(params) {
        return apiClient.post("/send-price-request", params);
    },
    updateBookingAmount(params) {
        return apiClient.post("update-booking-amount", params);
    },
    sendAddToCart(params) {
        return apiClient.post("/send-add-to-cart", params);
    },
    fetchAncillaries(params) {
        return apiClient.post("/ancillaries", 
             params
        );
    },
    patchAncillaries(params) {
        return apiClient.post("/patch-ancillaries", params);
    },

    initializeAbhiPay(params) {
        return apiClient.post("/initialize-abhipay", params);
    },
    checkPaymentStatus(params) {
        return apiClient.get("/check-payment-status", {
            params: params,
        });
    },
    updateAgentData(params) {
        return apiClient.post("/update-agents-data", params);
    },
    saveStaff(params) {
        return apiClient.post("/add-staff", params);
    },

    updateStaff(params) {
        return apiClient.post("/update-staff", params);
    },

    saveAgentCharges(params) {
        return apiClient.post("/save-agent-charges", params);
    },

    getAgentCharges(params) {
        return apiClient.get("/show-all-charges", {
            params: params,
        });
    },
    updateAgentChargeStatus(params) {
        return apiClient.post("/update-agent-charge-status", params);
    },

    // updateAgentData(params) {
    //     return apiClient.put("/update-agents-data", {
    //         params: params,
    //     });
    // },

    // getAgentData(params) {
    //     return apiClient.get("/agents", params);
    // },

    getAgentData(params) {
        return apiClient.get("/agentsData", {
            params: params, // Use the 'params' object to send query parameters
        });
    },
    saveAdminAgent(params) {
        return apiClient.post("/agents", params);
    },

    // getAgentData(params) {
    //     return apiClient.get("/agents", { params: params }); // Correctly send params as query parameters
    // },

    saveDepositData(params) {
        return apiClient.post("/deposit-data", params);
    },
    getDepositData(params) {
        return apiClient.get("/get-deposit-data", {
            params: params, // Use the 'params' object to send query parameters
        });
    },
    getTotalApprovedDeposits(params) {
        return apiClient.get("approved-deposits-total", {
            params: params, // Use the 'params' object to send query parameters
        });
    },
    updateDepositStatus(params) {
        return apiClient.put(`/update-deposit-status`, params);
    },
    saveAgentMargin(params) {
        return apiClient.post("/save-agent-margin", params);
    },

    getDepositDataWithAgents(params) {
        return apiClient.get("/get-agents-deposits", {
            params: params, // Use the 'params' object to send query parameters
        });
    },

    getDepositDetails(params) {
        return apiClient.get("/deposit-details", {
            params: params,
        });
    },
    deleteDepositData(params) {
        return apiClient.delete("/delete-deposit-data", {
            params: params,
        });
    },

    updateUserStatus(params) {
        return apiClient.put(`/update-status/`, params);
    },

    deleteUser(params) {
        return apiClient.delete("/users", {
            params: params,
        });
    },

    getAirports(params) {
        return apiClient.get("/airports", {
            params: params,
        });
    },

    getAirlines(params) {
        return apiClient.get("/airlines", {
            params: params,
        });
    },

    saveAirLine(params) {
        return apiClient.post("/airlines", params);
    },
    deleteAirline(params) {
        return apiClient.delete("/airlines", {
            params: params,
        });
    },
    updateAirline(params) {
        return apiClient.post("/updateAirlineMargin", params);
    },

    saveAirportMargins(params) {
        return apiClient.post("/save-airport-margins", params);
    },
    getAirportMargins(params) {
        return apiClient.get("/get-airport-margins", {
            params: params,
        });
    },
    getFlights(params) {
        return apiClient.get("/flights", {
            params: params,
        });
    },

    getFlight(params) {
        return apiClient.get(
            `/flight/${params.flight_id}/${params.supplier}/${params.isSooperFlight}`,
        );
    },
    getFlightProviders(params) {
        return apiClient.get("/flight-providers", { params });
    },
    sortFlights(params) {
        return apiClient.post("/sort-flights", params);
    },

    getBookings(params) {
        return apiClient.get("/get-bookings", {
            params: params,
        });
    },
    getCustomerBookings(params) {
        return apiClient.get("/get-customer-bookings", {
            params: params,
        });
    },
    getCustomerBooking(params) {
        return apiClient.get("/get-public-bookings", {
            params: params,
        });
    },
    updateCustomerType(params) {
        return apiClient.post("/update-customer-type", params);
    },
    getPnrDetails(params) {
        return apiClient.get("/get-pnr-details", {
            params: params,
        });
    },
    getPnrData(params) {
        return apiClient.get("get-pnr-data", {
            params: params,
        });
    },

    getAgentStatment(params) {
        return apiClient.get("agent-statment", {
            params: params,
        });
    },
    // cancelBooking(params) {
    //     return apiClient.post("/cancel-pnr", {
    //         params: params,
    //     });
    // },
    cancelBooking(params) {
        return apiClient.post("/cancel-pnr", params);
    },
    confirmBooking(params) {
        return apiClient.post("/confirm-pnr", params);
    },
    approveBooking(params) {
        return apiClient.post("/approve-booking", params);
    },
    voidBooking(params) {
        return apiClient.post("/void-booking", params);
    },
    voidRequest(params) {
        return apiClient.post("/void-request", params);
    },

    updateCardAllowance(params) {
        return apiClient.post("/update-card-allowance", params);
    },

    getActivityLogs(params) {
        return apiClient.get("/activity-logs", {
            params: params,
        });
    },
    deleteActivityLog(params) {
        return apiClient.delete(`/delete-activity-logs`, {
            params: params,
        });
    },

    getFlightBookingDetail(params) {
        return apiClient.get("get-flight-booking-details", {
            params: params,
        });
    },
    getCustomerFlightBookingDetail(params) {
        return apiClient.get("get-customer-flight-booking-details", {
            params: params,
        });
    },

    saveOfflineBooking(params) {
        return apiClient.post("offline-bookings", params);
    },
    getOfflineBookings(params) {
        return apiClient.get("get-offline-bookings", {
            params: params,
        });
    },
    getOfflineBookingDetails(params) {
        return apiClient.get("get-offline-booking-details", {
            params: params,
        });
    },
    deleteOfflineBooking(params) {
        return apiClient.delete("delete-offline-booking", {
            params: params,
        });
    },
    updateOfflineBooking(params) {
        return apiClient.post("update-offline-booking", params);
    },
    sendOfflineBookingEmail(params) {
        return apiClient.post("send-offline-booking-email", params);
    },

    saveBooking(params) {
        return apiClient.post("bookings", params);
    },
    saveBookingData(params) {
        return apiClient.post("/", params);
    },
    saveAdminBooking(params) {
        return apiClient.post("admin-booking", params);
    },
    getAdminBookings(params) {
        return apiClient.get("/get-admin-bookings", {
            params: params,
        });
    },
    getAdminBooking(params) {
        return apiClient.get("/get-admin-booking", {
            params: params,
        });
    },

    // transactions
    getTransaction(params) {
        return apiClient.get("transactions", {
            params: params,
        });
    },

    saveTransaction(params) {
        return apiClient.post("transactions", params);
    },

    updateTransaction(params) {
        return apiClient.put("transactions", params);
    },

    sendQuotation(params) {
        return apiClient.get("flight-quotation", {
            params: params,
        });
    },

    // visas
    getVisas(params) {
        return apiClient.get("/visas", {
            params: params,
        });
    },

    saveVisa(params) {
        return apiClient.post("/visas", params);
    },

    updateVisa(params) {
        return apiClient.put("/visas", params);
    },

    deleteVisa(params) {
        return apiClient.delete("/visas", {
            params: params,
        });
    },

    getBanks(params) {
        return apiClient.get("/banks", {
            params: params,
        });
    },

    saveCurrency(params) {
        return apiClient.post("/currencies", params);
    },
    deleteCurrency(params) {
        return apiClient.delete("/currencies", {params});
    },
    fetchCurrencies(params) {
        return apiClient.get("/currencies", {
            params: params,
        });
    },
    updateCurrency(params) {
        return apiClient.post("/update-currencies", params);
    },
    saveBank(params) {
        return apiClient.post("/banks", params);
    },

    updateBank(params) {
        return apiClient.put("/banks", params);
    },

    deleteBank(params) {
        return apiClient.delete("/banks", {
            params: params,
        });
    },

    getVisaHeaderImages() {
        return apiClient.get("/visa-header-images");
    },

    saveVisaHeaderImages(params) {
        return apiClient.post("/visa-header-images", params);
    },

    deleteVisaHeaderImages(params) {
        return apiClient.delete("/visa-header-images", {
            params: params,
        });
    },

    // holidays
    getHolidays(params) {
        return apiClient.get("/holidays", {
            params: params,
        });
    },

    saveHoliday(params) {
        return apiClient.post("/holidays", params);
    },

    updateHoliday(params) {
        return apiClient.put(`/holidays`, params);
    },

    deleteHolidays(params) {
        return apiClient.delete("/holidays", {
            params: params,
        });
    },

    getHolidayHeaderImages() {
        return apiClient.get("/holiday-header-images");
    },

    saveHolidayHeaderImages(params) {
        return apiClient.post("/holiday-header-images", params);
    },

    deleteHolidayHeaderImages(params) {
        return apiClient.delete("/holiday-header-images", {
            params: params,
        });
    },

    // umrah packages
    getUmrahPackages(params) {
        return apiClient.get("/umrah-packages", {
            params: params,
        });
    },

    saveUmrahPackage(params) {
        return apiClient.post("/umrah-packages", params);
    },

    updateUmrahPackage(params) {
        //console.log(params);
        return apiClient.patch("/umrah-packages", params);
    },

    deleteUmrahPackage(params) {
        return apiClient.delete("/umrah-packages", {
            params: params,
        });
    },

    getUmrahHeaderImages() {
        return apiClient.get("/umrah-header-images");
    },

    saveUmrahHeaderImages(params) {
        return apiClient.post("/umrah-header-images", params);
    },

    deleteUmrahHeaderImages(params) {
        return apiClient.delete("/umrah-header-images", {
            params: params,
        });
    },

    // Group tickets
    getGroupTickets(params) {
        return apiClient.get("/group-tickets", {
            params: params,
        });
    },

    saveGroupTicket(params) {
        return apiClient.post("/group-tickets", params);
    },

    updateGroupTicket(params) {
        return apiClient.put("/group-tickets", params);
    },

    deleteGroupTickets(params) {
        return apiClient.delete("/group-tickets", {
            params: params,
        });
    },

    saveProfileImage(params) {
        return apiClient.post("agent-profile", params);
    },

    savePromoImage(params) {
        return apiClient.post("save-promo-image", params);
    },
    updatePromoImage(params) {
        return apiClient.post("update-promo-image", params);
    },

    getPromoImages(params) {
        return apiClient.get("/get-promo-images", {
            params: params,
        });
    },

    deletePromoImage(params) {
        return apiClient.delete("/delete-promo-image", {
            params: params,
        });
    },
    updateBookingStatus(params) {
        return apiClient.post("/update-booking", params);
    },

    getBookingStatus(params) {
        return apiClient.get("/get-booking-status-settings", {
            params: params,
        });
    },

    getCustomerMargin(params) {
        return apiClient.get("/get-customer-margin-values", {
            params: params,
        });
    },
    saveCustomerMargin(params) {
        return apiClient.post("/save-customer-margin-values", params);
    },

    saveTraveller(params) {
        return apiClient.post("/save-travellers", params);
    },
    getTravellers(params) {
        return apiClient.get("/get-travellers", {
            params: params,
        });
    },
    deleteTraveller(params) {
        return apiClient.delete("/delete-travellers", {
            params: params,
        });
    },
    updateTraveller(params) {
        return apiClient.put("/update-travellers", {
            params: params,
        });
    },
    assignTicketNumber(params) {
        return apiClient.post("/assign-ticket-number", params);
    },

    getSafePayUrl(params) {
        return apiClient.get("/safepay-url", {
            params: params,
        });
    },


     getPromotions(params) {
        return apiClient.get("/promotions", { params });
    },
    getPromotion(id) {
        return apiClient.get(`/promotions/${id}`);
    },
    savePromotion(params) {
        return apiClient.post("/promotions", params);
    },
    updatePromotion(id, params) {
        return apiClient.put(`/promotions/${id}`, params);
    },
    deletePromotion(id) {
        return apiClient.delete(`/promotions/${id}`);
    },
    getPromotionProviders() {
        return apiClient.get("/promotions/providers");
    },

    getSegmentMargins(params) {
        return apiClient.get("/segment-margins", { params });
    },
    getSegmentMargin(id) {
        return apiClient.get(`/segment-margins/${id}`);
    },
    saveSegmentMargin(params) {
        return apiClient.post("/segment-margins", params);
    },
    updateSegmentMarginById(id, params) {
        return apiClient.put(`/segment-margins/${id}`, params);
    },
    deleteSegmentMargin(id) {
        return apiClient.delete(`/segment-margins/${id}`);
    },
    getSegmentMarginProviders() {
        return apiClient.get("/segment-margins/providers");
    },
    sendEmail(params) {
        return apiClient.post("/send-email", params);
    },
    submitContactMessage(params) {
        return apiClient.post("/contact-messages", params);
    },
    sendPaymentRequest(params) {
        return apiClient.post("/send-payment-request", params);
    },
    getNotifications(params) {
        return apiClient.get("/notifications", {
            params: params,
        });
    },
    readNotification(params) {
        return apiClient.post(`/notifications/read/${params.id}`);
    },
    deleteNotification(params) {
        return apiClient.delete(`/notifications/${params.id}`);
    },
    getAllNotifications() {
        return apiClient.get("/notifications");
    },
    clearAllNotifications() {
        return apiClient.delete("/notifications/clear-all");
    },

    saveKeys(params) {
        return apiClient.post("/save-keys", params);
    },
    fetchKeys() {
        return apiClient.get("/fetch-keys");
    },
    getZohoToken(params) {
        return apiClient.post("/zoho-token", { code: params });
    },
    createInvoice(params) {
        return apiClient.get("/create-invoice", {
            params: params,
        });
    },
    getProfitLossReport(params) {
        return apiClient.get("profit-loss-report", {
            params: params,
        });
    },

    updateCustomerData(params) {
        return apiClient.post("/update-customer-data", params);
    },
    getCustomers(params) {
        return apiClient.get("/customers", {
            params: params,
        });
    },
    getCustomerData(params) {
        return apiClient.get("/customer-data", {
            params: params,
        });
    },
    getCustomerSettings(params) {
        return apiClient.get("/customer-settings", {
            params: params,
        });
    },

    updateCustomerSettings(params) {
        return apiClient.post("/update-customer-settings", params);
    },

    saveRequest(params) {
        return apiClient.post("/save-request", params);
    },
    fetchRequests(params) {
        return apiClient.get("/fetch-requests", {
            params: params,
        });
    },
    fetchModifyRequestData(params) {
        return apiClient.get("/fetch-modify-request-data", {
            params: params,
        });
    },
    updateStatus(params) {
        return apiClient.post("/update-modify-request-status", params);
    },
   
    sendReply(params) {
        return apiClient.post("/send-reply", params);
    },

    savePopularRoute(params) {
        return apiClient.post("/save-popular-route", params);
    },
    getPopularRoutes(params) {
        return apiClient.get("/fetch-popular-routes", {
            params: params,
        });
    },
     deletePopularRoute(params) {
        return apiClient.delete(`/deletePopularRoute/${params.id}`);
    },
    

    saveBlog(params) {
        return apiClient.post("/save-blog", params);
    },
    fetchBlogs(params) {
        return apiClient.get("/fetch-blogs", {
            params: params,
        });
    },
    fetchBlog(id) {
        return apiClient.get(`/fetch-blog/${id}`);
    },
    deleteBlog(id) {
        return apiClient.delete(`/delete-blog/${id}`);
    },
    updateBlog(params) {
        return apiClient.post(`/update-blog`, params);
    }
    
};
