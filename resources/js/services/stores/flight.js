import apiService from "@/config/axios";
import { resolveApiBaseUrl } from "@/config/apiBaseUrl";
import { defineStore } from "pinia";
import { toast } from "vue3-toastify";

const flightMergeKey = (flight) => [
    flight?.provider?.identifier,
    flight?.provider?.TUI,
    flight?.provider?.sector,
    flight?.provider?.travel_date,
    flight?.leg?.flights?.map((leg) => leg?.flight_number || leg?.flight_index).join("~"),
    flight?.leg?.flights?.map((leg) => leg?.segments?.map((segment) => [
        segment?.operating_carrier?.iata,
        segment?.flight_number,
        segment?.from?.iata,
        segment?.to?.iata,
        segment?.departure_at,
        segment?.arrival_at,
    ].join("|")).join("~")).join("~~"),
].join("::");

const fareMergeKey = (fare) => [
    fare?.index,
    fare?.return_identifier,
    fare?.name,
    fare?.rbd_code,
    fare?.fare_basis_code,
    fare?.provider_booking_money?.amount ?? fare?.base_price,
    fare?.provider_gross_money?.amount ?? fare?.total_price,
].join("::");

const mergeFlightCollections = (existingFlights = [], incomingFlights = []) => {
    const merged = new Map();

    [...(existingFlights || []), ...(incomingFlights || [])].forEach((flight) => {
        const key = flightMergeKey(flight);
        if (!merged.has(key)) {
            merged.set(key, typeof structuredClone !== "undefined" ? structuredClone(flight) : JSON.parse(JSON.stringify(flight)));
            return;
        }

        const current = merged.get(key);
        const next = typeof structuredClone !== "undefined" ? structuredClone(flight) : JSON.parse(JSON.stringify(flight));
        const currentLegs = current?.leg?.flights || [];
        const nextLegs = next?.leg?.flights || [];

        currentLegs.forEach((currentLeg, legIndex) => {
            if (!nextLegs[legIndex]) {
                nextLegs[legIndex] = currentLeg;
                return;
            }

            const fares = new Map();
            [...(currentLeg.fares || []), ...(nextLegs[legIndex].fares || [])].forEach((fare) => {
                fares.set(fareMergeKey(fare), fare);
            });
            nextLegs[legIndex].fares = Array.from(fares.values());
        });

        next.quote_search_token = flight?.quote_search_token || current.quote_search_token;
        next.leg = next.leg || {};
        next.leg.flights = nextLegs;
        merged.set(key, next);
    });

    return Array.from(merged.values());
};

const streamUrlForParams = (params) => {
    const apiBase = new URL(resolveApiBaseUrl(), window.location.origin);
    const basePath = apiBase.pathname.endsWith("/") ? apiBase.pathname : `${apiBase.pathname}/`;
    const url = new URL(`${basePath}flight-search/stream`, apiBase.origin);

    Object.entries(params || {}).forEach(([key, value]) => {
        if (value === undefined || value === null || value === "") return;
        url.searchParams.set(key, Array.isArray(value) || typeof value === "object" ? JSON.stringify(value) : value);
    });

    return url.toString();
};

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
            const requestParams = this.normalizedSearchParams(params);

            try {
                const previousSearch = {
                    ...requestParams,
                    timestamp: Date.now(),
                };
                localStorage.setItem("previous_search", JSON.stringify(previousSearch));

                if (requestParams.airline === "AT" && typeof EventSource !== "undefined") {
                    await this.streamFlights(requestParams);
                    this.validationErrors = [];
                    return;
                }

                await this.fetchFlightsHttp(requestParams);
                this.validationErrors = [];
            } catch (error) {
                console.error("Error fetching flights:", error);
                toast("Failed to fetch flights. Please check your input and try again.", {
                    type: "error",
                });
                if (error.response?.data?.errors) {
                    this.validationErrors = error.response.data.errors;
                } else {
                    this.validationErrors = [{ message: "An unexpected error occurred." }];
                }
            } finally {
                this.isFlightLoading = false;
            }
        },
        normalizedSearchParams(params) {
            const requestParams = { ...params };
            if (params.flightType === "multi-city" && params.trips) {
                requestParams.trips = Array.isArray(params.trips)
                    ? params.trips.map((trip) => ({
                          origin: trip.origin,
                          destination: trip.destination,
                          date: trip.date,
                      }))
                    : JSON.parse(params.trips);
            }

            return requestParams;
        },
        async fetchFlightsHttp(requestParams) {
            const response = await apiService.get("/flights", {
                params: requestParams,
            });

            this.flights = response.data.flights;
            this.cheapestFlightsByAirline = response.data.cheapest_flights_by_airline;
            this.availableAirlines = response.data.available_airlines;
            this.sooperFlights = mergeFlightCollections(this.sooperFlights || [], response.data.sooper_flights || []);
        },
        streamFlights(requestParams) {
            return new Promise((resolve, reject) => {
                let receivedChunk = false;
                const source = new EventSource(streamUrlForParams(requestParams), { withCredentials: true });

                source.addEventListener("chunk", (event) => {
                    receivedChunk = true;
                    const data = JSON.parse(event.data);
                    this.availableAirlines = data.available_airlines || [];
                    this.sooperFlights = mergeFlightCollections(this.sooperFlights || [], data.sooper_flights || []);
                });

                source.addEventListener("complete", (event) => {
                    const data = JSON.parse(event.data);
                    this.availableAirlines = data.available_airlines || [];
                    this.sooperFlights = mergeFlightCollections(this.sooperFlights || [], data.sooper_flights || []);
                    source.close();
                    resolve(data);
                });

                source.addEventListener("stream-error", async (event) => {
                    source.close();
                    if (receivedChunk) {
                        resolve(event);
                        return;
                    }

                    try {
                        await this.fetchFlightsHttp(requestParams);
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                });

                source.onerror = async (event) => {
                    source.close();
                    if (receivedChunk) {
                        resolve(event);
                        return;
                    }

                    try {
                        await this.fetchFlightsHttp(requestParams);
                        resolve();
                    } catch (error) {
                        reject(error);
                    }
                };
            });
        },
        resetFlightResults() {
            this.flights = null;
            this.sooperFlights = null;
            this.sortedSooperFlights = null;
            this.cheapestFlightsByAirline = null;
            this.availableAirlines = null;
            this.validationErrors = [];
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
