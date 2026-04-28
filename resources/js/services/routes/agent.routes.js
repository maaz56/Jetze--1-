export const agentRoutes = [
    {
        path: "/agent",
        component: () => import("@/layouts/AgentLayout.vue"),
        meta: {
            requiresAuth: true,
            requiredRole: "agent",
            requiredStatus: 1, // Only approved agents can access this route. You can add your own logic here.

        },

        children: [
            {
                path: "dashboard",
                name: "AgentDashboard",
                component: () => import("@/pages/agent/Dashboard.vue"),
            },
            {
                path: "flights",
                name: "Flights",
                component: () => import("@/pages/agent/Flights.vue"),
            },
            {
                path: "dashboard-flights",
                name: "DashboardFlights",
                component: () => import("@/pages/agent/DashboardFlights.vue"),
            },
            {
                path: "flight-checkout",
                name: "AgentFlightCheckout",
                component: () => import("@/pages/agent/FlightCheckout.vue"),
            },
            {
                path: "agent-flight-checkout",
                name: "AgentCheckout",
                component: () => import("@/pages/AgentCheckout.vue"),
            },
            {
                path: "agent-bookings",
                name: "AgentBookings",
                component: () => import("@/pages/agent/AgentBookings.vue"),
            },
            {
                path: "agent-booking-details",
                name: "AgentBookingDetails",
                component: () => import("@/pages/AgentBookingsDetails.vue"),
            },
            {
                path: "agent-booking-details-offline",
                name: "AgentBookingDetailsOffline",
                component: () => import("@/pages/agent/AgentBookingDetailsOffline.vue"),
            },
            {
                path: "deposits",
                name: "Deposits",
                component: () => import("@/pages/agent/Deposits.vue"),
            },
            {
                path: "settings",
                name: "Settings",
                component: () => import("@/pages/agent/Settings.vue"),
            },
            {
                path: "update-agent-data",
                name: "UpdateAgentData",
                component: () => import("@/pages/agent/UpdateAgentData.vue"),
            },
            {
                path: "agent-deposit",
                name: "AgentDeposit",
                component: () => import("@/pages/agent/AgentDepositDetails.vue"),
            },
            {
                path: "verify-message",
                name: "VerifyMessage",
                component: () => import("@/pages/agent/VerifyMessage.vue"),
            },
            {
                path: "agent-ledger",
                name: "AgentLedger",
                component: () => import("@/pages/agent/AgentLedger.vue"),
            },
            {
                path: "other-charges",
                name: "AgentOtherCharges",
                component: () => import("@/pages/agent/OtherCharges.vue"),
            },
            {
                path: "travellers",
                name: "Travellers",
                component: () => import("@/pages/agent/Travellers.vue"),
            },
            {
                path: "new-traveller",
                name: "NewTraveller",
                component: () => import("@/pages/agent/NewTraveller.vue"),
            },
            {
                path: "pnr-details",
                name: "PnrDetails",
                component: () => import("@/pages/agent/pnrDetails.vue"),
            },
             {
                path: "contact/us",
                name: "ContactUs",
                component: () => import("@/pages/agent/ContactUs.vue"),
            },
            {
                path: "agent-offline-bookings",
                name: "AgentOfflineBookings",
                component: () => import("@/pages/agent/AgentOfflineBookings.vue"),
            },
            {
                path: "agent-offline-bookings-details",
                name: "AgentOfflineBookingsDetails",
                component: () => import("@/pages/agent/OfflineBookingDetails.vue"),
            },
            {
                path: "agent-profit-loss-report",
                name: "AgentProfitLossReport",
                component: () => import("@/pages/agent/AgentProfitLossReport.vue"),
            },
            {
                path: "payment-view",
                name: "PaymentView",
                component: () => import("@/pages/PaymentView.vue"),
            },



        ],

    },
    {
        path: "/agent/add-agent-details",
        name: "AddAgentDetails",
        component: () => import("@/pages/profile/agent/AddAgentDetails.vue"),
        meta: {

            requiredRole: "agent", // No requiredStatus here
        },

    },
    {
        path: "/verify-message",
        name: "VerifyMessage",
        component: () => import("@/pages/agent/VerifyMessage.vue"),
        meta: {

            requiredRole: "agent", // No requiredStatus here
        },
    },

    {
        path: "/email-success-message",
        name: "EmailVerifySuccessMessage",
        component: () => import("@/pages/agent/EmailSuccessVerify.vue"),
        meta: {

            requiredRole: "agent", // No requiredStatus here
        },
    },
];
