export const clientRoutes = [
    {
        path: "/",
        component: () => import("@/layouts/DefaultLayout.vue"),
        children: [
            {
                path: "/",
                name: "Home",
                component: () => import("@/pages/Home.vue"),
            },
            // {
            //     path: "/home",
            //     name: "Home",
            //     component: () => import("@/pages/Login.vue"),
            // },
            // {
            //     path: "/login",
            //     name: "Login",
            //     component: () => import("@/pages/Login.vue"),
            // },
           
            // Rendered by Laravel Blade (resources/views/pages/about-us.blade.php).
            // {
            //     path: "about/us",
            //     name: "AboutUs",
            //     component: () => import("@/pages/AboutUs.vue"),
            // },
            {
                path: "how-to-use-abhi-pay-bank-transfer",
                name: "HowToPay",
                component: () => import("@/pages/HowToPay.vue"),
            },
            // Rendered by Laravel Blade (resources/views/pages/contact-us.blade.php).
            // {
            //     path: "contact/us",
            //     name: "ClientContactUs",
            //     component: () => import("@/pages/ContactUs.vue"),
            // },
            {
                path: "our/services",
                name: "OurServices",
                component: () => import("@/pages/OurServices.vue"),
            },
            {
                path: "flight/search",
                name: "FlightSearch",
                component: () => import("@/pages/FlightSearch.vue"),
            },
            {
                path: "search/booking",
                name: "SearchBooking",
                component: () => import("@/pages/SearchBooking.vue"),
            },
            {
                path: "flight/details",
                name: "FlightDetails",
                component: () => import("@/pages/FlightDetails.vue"),
            },
            {
                path: "hotel/search",
                name: "HotelSearch",
                component: () => import("@/pages/HotelSearch.vue"),
            },
            {
                path: "hotel/details",
                name: "HotelDetails",
                component: () => import("@/pages/HotelDetails.vue"),
            },
            {
                path: "flight/checkout",
                name: "FlightCheckout",
                component: () => import("@/pages/FlightCheckout.vue"),
            },
            {
                path: "ancillaries/view",
                name: "AncillariesView",
                component: () => import("@/pages/AncillariesView.vue"),
            },
            {
                path: "hotel/checkout",
                name: "HotelCheckout",
                component: () => import("@/pages/HotelCheckout.vue"),
            },
            {
                path: "visa",
                name: "Visa",
                component: () => import("@/pages/Visas.vue"),
            },
            {
                path: "visa-details",
                name: "VisaDetails",

                component: () => import("@/pages/VisaDetails.vue"),
                props: true,
            },
            {
                path: "holidays",
                name: "HolidayPackages",
                component: () => import("@/pages/Holidays.vue"),
            },
            {
                path: "holiday-details",
                name: "HolidayDetails",
                component: () => import("@/pages/HolidayDetails.vue"),
            },
            {
                path: "umrah-package-details",
                name: "UmrahPackageDetails",

                component: () => import("@/pages/UmrahPackageDetails.vue"),
                props: true,
            },
            {
                path: "umra-packages",
                name: "UmraPackages",
                component: () => import("@/pages/UmraPackages.vue"),
            },
            {
                path: "travel-insurance",
                name: "TravelInsurance",
                component: () => import("@/pages/TravelInsurance.vue"),
            },
            {
                path: "group-tickets-main",
                name: "GroupTicketsMain",
                component: () => import("@/pages/GroupTickets.vue"),
            },
            {
                path: "add-agent-details",
                name: "AddAgentDetails",
                component: () => import("@/pages/profile/agent/AddAgentDetails.vue"),
            },
            {
                path: "flights-checkout",
                name: "Checkout",
                component: () => import("@/pages/Checkout.vue"),
            },
            {
                path: "privacy-policy",
                name: "PrivacyPolicy",
                component: () => import("@/pages/PrivaycyPolicy.vue"),
            },
            {
                path: "terms-condition",
                name: "TermsCondition",
                component: () => import("@/pages/Terms&Condition.vue"),
            },
            {
                path: "customer-bookings-details",
                name: "BookingsDetails",
                component: () => import("@/pages/CustomerBookingsDetails.vue"),
            },
            {
                path: "customer-profile",
                name: "CustomerProfile",
                component: () => import("@/pages/CustomerProfile.vue"),
            },
            {
                path: "disable-sms-verification",
                name: "DisableSmsVerification",
                component: () => import("@/pages/DisableSmsVerification.vue"),
                meta: {
                    requiresAuth: true,
                },
            },
            {
                path: "customer-payment-view",
                name: "CustomerPaymentView",
                component: () => import("@/pages/CustomerPaymentView.vue"),
            },
            // Blog pages are rendered by Laravel Blade.
            // {
            //     path: "/blog",
            //     name: "index",
            //     component: () => import("@/pages/blogs/index.vue"),
            // },
            // {
            //     path: "/blog/:id/:slug",
            //     name: "Blog",
            //     component: () => import("@/pages/blogs/show.vue"),
            //     props: true,
            // },
            // {
            //     path: "/blog/:slug",
            //     name: "BlogBySlug",
            //     component: () => import("@/pages/blogs/show.vue"),
            //     props: true,
            // },
            {
                path: "popular-routes/:id",
                name: "PopularRouteDetails",
                component: () => import("@/pages/PopularRouteDetails.vue"),
            }

        ],
    },
];
