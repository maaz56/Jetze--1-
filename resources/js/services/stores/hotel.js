import { defineStore } from "pinia";
import apiService from "@/services/store/apiService";

export const useHotelStore = defineStore("hotel", {
    state: () => ({
        suggestions: [],
        hotels: [],
        searchSessionId: null,
        providerStatus: null,
        isLoadingSuggestions: false,
        isSearching: false,
        errorMessage: "",
        validationErrors: {},
    }),
    getters: {
        getSuggestions: (state) => state.suggestions,
        getHotels: (state) => state.hotels,
        getSearchSessionId: (state) => state.searchSessionId,
        getProviderStatus: (state) => state.providerStatus,
        getIsLoadingSuggestions: (state) => state.isLoadingSuggestions,
        getIsSearching: (state) => state.isSearching,
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

        resetSearch() {
            this.hotels = [];
            this.searchSessionId = null;
            this.providerStatus = null;
            this.errorMessage = "";
            this.validationErrors = {};
        },
    },
});
