export const customerRoutes = [
    {
        path: "/customer",
        component: () => import("@/layouts/CustomerLayout.vue"),
        meta: {
            requiresAuth: true,
            requiredRole: "customer",
        },
        children: [

            // {
            //     path: "customer-profile",
            //     name: "CustomerProfile",
            //     component: () => import("@/pages/CustomerProfile.vue"),
            // },
            {
                path: "customer-wallet",
                name: "CustomerWallet",
                component: () => import("@/pages/CustomerWallet.vue"),
            },
            {
                path: "customer-bookings",
                name: "CustomerBookings",
                component: () => import("@/pages/CustomersBookings.vue"),
            },
            
          
        ],
    },
];
