import { defineStore } from "pinia";
import apiService from "@/services/store/apiService";

export const useHotelStore = defineStore("hotel", {
    state: () => ({
        suggestions: [],
        hotels: [],
        searchSessionId: null,
        providerStatus: null,
        prebook: null,
        booking: null,
        bookings: [],
        bookingMeta: null,
        isLoadingSuggestions: false,
        isSearching: false,
        isPrebooking: false,
        isBooking: false,
        isLoadingBookingDetails: false,
        isCancelling: false,
        errorMessage: "",
        validationErrors: {},
    }),
    getters: {
        getSuggestions: (state) => state.suggestions,
        getHotels: (state) => state.hotels,
        getSearchSessionId: (state) => state.searchSessionId,
        getProviderStatus: (state) => state.providerStatus,
        getPrebook: (state) => state.prebook,
        getBooking: (state) => state.booking,
        getBookings: (state) => state.bookings,
        getBookingMeta: (state) => state.bookingMeta,
        getIsLoadingSuggestions: (state) => state.isLoadingSuggestions,
        getIsSearching: (state) => state.isSearching,
        getIsPrebooking: (state) => state.isPrebooking,
        getIsBooking: (state) => state.isBooking,
        getIsLoadingBookingDetails: (state) => state.isLoadingBookingDetails,
        getIsCancelling: (state) => state.isCancelling,
        getErrorMessage: (state) => state.errorMessage,
    },
    actions: {
        async fetchSuggestions(query) {
            this.isLoadingSuggestions = true;

            try {
                const response = await apiService.getHotelSuggestions({ q: query });
                this.suggestions = response.data.data || [];
                return this.suggestions;
            } catch (error) {
                this.suggestions = [];
                throw error;
            } finally {
                this.isLoadingSuggestions = false;
            }
        },

        async searchHotels(params) {
            this.isSearching = true;
            this.errorMessage = "";
            this.providerStatus = null;
            this.hotels = [];
            this.searchSessionId = null;
            this.validationErrors = {};

            try {
                const response = await apiService.searchHotels(params);
                this.hotels = response.data.data?.hotels || [];
                this.searchSessionId = response.data.data?.search_session_id || null;
                this.providerStatus = response.data.provider_status || null;
                this.errorMessage = this.hotels.length ? "" : response.data.message || "No hotels available.";

                return response.data;
            } catch (error) {
                this.errorMessage = error.response?.data?.message || "Hotel search failed. Please try again.";
                this.providerStatus = error.response?.data?.provider_status || null;
                this.validationErrors = error.response?.data?.errors || {};
                throw error;
            } finally {
                this.isSearching = false;
            }
        },

        async prebookHotel(params) {
            this.isPrebooking = true;
            this.resetCheckout();

            try {
                const response = await apiService.prebookHotel(params);
                this.prebook = response.data.data || null;
                return response.data;
            } catch (error) {
                this.errorMessage = error.response?.data?.message || "Unable to confirm this room. Please try again.";
                throw error;
            } finally {
                this.isPrebooking = false;
            }
        },

        async fetchPrebook(prebookId) {
            this.resetCheckout();
            const response = await apiService.getHotelPrebook(prebookId);
            this.prebook = response.data.data || null;
            return response.data;
        },

        async bookHotel(params) {
            this.isBooking = true;
            this.errorMessage = "";

            try {
                const response = await apiService.bookHotel(params);
                this.booking = response.data.data || null;
                return response.data;
            } catch (error) {
                this.errorMessage = error.response?.data?.message || "Unable to complete this hotel booking.";
                throw error;
            } finally {
                this.isBooking = false;
            }
        },

        async fetchHotelBookings(params = {}) {
            const response = await apiService.getHotelBookings(params);
            this.bookings = response.data.data || [];
            this.bookingMeta = response.data.meta || null;
            return response.data;
        },

        async fetchHotelBooking(bookingId) {
            this.isLoadingBookingDetails = true;

            try {
                const response = await apiService.getHotelBooking(bookingId);
                this.booking = response.data.data || null;
                return response.data;
            } finally {
                this.isLoadingBookingDetails = false;
            }
        },

        async refreshHotelBookingDetails(bookingId) {
            this.isLoadingBookingDetails = true;

            try {
                const response = await apiService.refreshHotelBookingDetails(bookingId);
                this.booking = response.data.data || null;
                return response.data;
            } finally {
                this.isLoadingBookingDetails = false;
            }
        },

        async cancelHotelBooking(bookingId) {
            this.isCancelling = true;
            this.errorMessage = "";

            try {
                const response = await apiService.cancelHotelBooking(bookingId);
                this.booking = response.data.data || null;
                return response.data;
            } catch (error) {
                this.errorMessage = error.response?.data?.message || "Unable to cancel this hotel booking.";
                throw error;
            } finally {
                this.isCancelling = false;
            }
        },

        resetCheckout() {
            this.prebook = null;
            this.booking = null;
            this.errorMessage = "";
        },

        resetSearch() {
            this.hotels = [];
            this.searchSessionId = null;
            this.providerStatus = null;
            this.prebook = null;
            this.booking = null;
            this.bookings = [];
            this.bookingMeta = null;
            this.errorMessage = "";
            this.validationErrors = {};
        },
    },
});
