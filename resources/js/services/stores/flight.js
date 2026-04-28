import apiService from "@/config/axios";
import { defineStore } from "pinia";
import { toast } from "vue3-toastify";

export const useFlightStore = defineStore("flight", {
    state: () => ({
        flights: null,
        sooperFlights: null,
        sooperFlight: null,
        sortedSooperFlights: null,
        cheapestFlightsByAirline: null,
        flight: null,
        bookings: null,
        availableAirlines: null,
        isFlightLoading: false,
        isLoading: false,
        validationErrors: [],
    }),
    getters: {
        getFlights: (state) => state.flights,
        getSooperFlights: (state) => state.sooperFlights,
        getCheapestFlightsByAirline: (state) => state.cheapestFlightsByAirline,
        getSortedSooperFlights: (state) => state.sortedSooperFlights,
        getIsFlightLoading: (state) => state.isFlightLoading,
        getFlight: (state) => state.flight,
        getAvailableAirlines: (state) => state.availableAirlines,
        getBookings: (state) => state.bookings,
        getIsLoading: (state) => state.isLoading,
        getValidationErrors: (state) => state.validationErrors,
    },
    actions: {
        async fetchFlights(params) {
            this.isFlightLoading = true;
            try {
                // Prepare parameters, ensuring trips array is properly formatted for multi-city
                const requestParams = { ...params };
                if (params.flightType === "multi-city" && params.trips) {
                    // Ensure trips is an array of objects
                    requestParams.trips = Array.isArray(params.trips)
                        ? params.trips.map((trip) => ({
                              origin: trip.origin,
                              destination: trip.destination,
                              date: trip.date,
                          }))
                        : JSON.parse(params.trips);
                }

                const response = await apiService.get("/flights", {
                    params: requestParams,
                });

                // console.log(response);

                // Log response for debugging
                //console.log(JSON.stringify(response.data));

                // Persist search parameters in localStorage
                const previousSearch = {
                    ...requestParams,
                    timestamp: Date.now(),
                };
                localStorage.setItem(
                    "previous_search",
                    JSON.stringify(previousSearch),
                );
                // Update state with response data
                this.flights = response.data.flights;
                this.cheapestFlightsByAirline =
                    response.data.cheapest_flights_by_airline;
                this.availableAirlines = response.data.available_airlines;
                this.sooperFlights = response.data.sooper_flights;
                this.validationErrors = [];
            } catch (error) {
                console.error("Error fetching flights:", error);
                toast(
                    "Failed to fetch flights. Please check your input and try again.",
                    {
                        type: "error",
                    },
                );
                if (
                    error.response &&
                    error.response.data &&
                    error.response.data.errors
                ) {
                    //console.log(error.response.data.errors);
                    this.validationErrors = error.response.data.errors;
                } else {
                    this.validationErrors = [
                        { message: "An unexpected error occurred." },
                    ];
                }
            } finally {
                this.isFlightLoading = false;
            }
        },
        async sortFlights(params) {
            this.isLoading = true;
            try {
                const response = await apiService.post("/sort-flights", params);
                this.flights = response.data.flights;
                this.cheapestFlightsByAirline =
                    response.data.cheapest_flights_by_airline;
                // this.availableAirlines = response.data.available_airlines;
                this.sortedSooperFlights = response.data.sooper_flights;
                this.validationErrors = [];
            } catch (error) {
                console.error("Error sorting flights:", error);
                toast("Failed to sort flights.", {
                    type: "error",
                });
                if (
                    error.response &&
                    error.response.data &&
                    error.response.data.errors
                ) {
                    console.log(error.response.data.errors);
                    this.validationErrors = error.response.data.errors;
                } else {
                    this.validationErrors = [
                        { message: "Failed to sort flights." },
                    ];
                }
            } finally {
                this.isLoading = false;
            }
        },

        async fetchFlight(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get(
                    `/flight/${params.flight_id}/${params.supplier}`,
                );
                //console.log(JSON.stringify(response.data));
                this.flight = response.data;
                this.validationErrors = [];
            } catch (error) {
                console.error("Error fetching flight:", error);
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
                } else {
                    this.validationErrors = [
                        { message: "Failed to fetch flight details." },
                    ];
                }
            } finally {
                this.isLoading = false;
            }
        },

        async fetchBookings(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("/bookings", {
                    params: params,
                });
                //console.log(JSON.stringify(response.data));
                this.bookings = response.data;
                this.validationErrors = [];
            } catch (error) {
                console.error("Error fetching bookings:", error);
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
                } else {
                    this.validationErrors = [
                        { message: "Failed to fetch bookings." },
                    ];
                }
            } finally {
                this.isLoading = false;
            }
        },

        async saveBooking(params) {
            this.isLoading = true;
            try {
                const response = await apiService.post("bookings", params);
                //console.log(JSON.stringify(response.data));
                toast(response.data.message, {
                    type: response.data.type,
                });
                this.validationErrors = [];
            } catch (error) {
                console.error("Error saving booking:", error);
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
                } else {
                    this.validationErrors = [
                        { message: "Failed to save booking." },
                    ];
                }
            } finally {
                this.isLoading = false;
            }
        },

        async sendQuotation(params) {
            this.isLoading = true;
            try {
                const response = await apiService.get("flight-quotation", {
                    params: params,
                });
                //console.log(response.data);
                this.validationErrors = [];
                return response.data;
            } catch (error) {
                console.error("Error sending quotation:", error);
                toast("Failed to send quotation.", {
                    type: "error",
                });
                if (
                    error.response &&
                    error.response.data &&
                    error.response.data.errors
                ) {
                    //console.log(error.response.data.errors);
                    this.validationErrors = error.response.data.errors;
                } else {
                    this.validationErrors = [
                        { message: "Failed to send quotation." },
                    ];
                }
            } finally {
                this.isLoading = false;
            }
        },
    },
});
