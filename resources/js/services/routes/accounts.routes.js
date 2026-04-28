export const adminAccountsRoutes = [
    {
        path: "/admin",
        component: () => import("@/layouts/AdminLayout.vue"),
        meta: {
            requiresAuth: true,
            requiredRole: "accounts",
        },
        children: [
            {
                path: "dashboard",
                name: "Dashboard",
                component: () => import("@/pages/admin/Dashboard.vue"),
            },
            {
                path: "new-user",
                name: "NewUser",
                component: () => import("@/pages/admin/newUser.vue"),
            },
            {
                path: "update/user",
                name: "UpdateUser",
                component: () => import("@/pages/admin/updateUser.vue"),
            },
            {
                path: "users",
                name: "Users",
                component: () => import("@/pages/admin/Users.vue"),
            },
            {
                path: "staff",
                name: "Staff",
                component: () => import("@/pages/admin/Staff.vue"),
            },
            {
                path: "update-staff",
                name: "UpdateStaff",
                component: () => import("@/pages/admin/UpdateStaff.vue"),
            },
            {
                path: "new-staff",
                name: "NewStaff",
                component: () => import("@/pages/admin/NewStaff.vue"),
            },
            {
                path: "user-details",
                name: "UserDetails",
                component: () => import("@/pages/admin/UserDetails.vue"),
            },
            {
                path: "banners",
                name: "Banners",
                component: () => import("@/pages/admin/Banners.vue"),
            },
            {
                path: "topup-request",
                name: "TopUpRequest",
                component: () => import("@/pages/admin/TopUpRequests.vue"),
            },
            {
                path: "deposit-details",
                name: "DepositDetails",
                component: () => import("@/pages/admin/DepositDetails.vue"),
            },
            {
                path: "bookings",
                name: "Bookings",
                component: () => import("@/pages/admin/Bookings.vue"),
            },
            {
                path: "booking-details",
                name: "BookingDetails",
                component: () => import("@/pages/admin/BookingDetails.vue"),
            },
            {
                path: "airlines",
                name: "Airlines",
                component: () => import("@/pages/admin/Airlines.vue"),
            },
            {
                path: "new/airline",
                name: "NewAirline",
                component: () => import("@/pages/admin/newAirline.vue"),
            },
            {
                path: "update/airline",
                name: "UpdateAirline",
                component: () => import("@/pages/admin/updateAirline.vue"),
            },
            {
                path: "airports",
                name: "Airports",
                component: () => import("@/pages/admin/Airports.vue"),
            },
            {
                path: "new/airport",
                name: "NewAirport",
                component: () => import("@/pages/admin/newAirport.vue"),
            },
            {
                path: "update/airport",
                name: "UpdateAirport",
                component: () => import("@/pages/admin/updateAirport.vue"),
            },
            {
                path: "aircrafts",
                name: "Aircrafts",
                component: () => import("@/pages/admin/Aircrafts.vue"),
            },
            {
                path: "new/aircraft",
                name: "NewAircraft",
                component: () => import("@/pages/admin/newAircraft.vue"),
            },
            {
                path: "update/aircraft",
                name: "UpdateAircraft",
                component: () => import("@/pages/admin/updateAircraft.vue"),
            },
            {
                path: "suppliers",
                name: "Suppliers",
                component: () => import("@/pages/admin/Suppliers.vue"),
            },
            {
                path: "visas",
                name: "Visas",
                component: () => import("@/pages/admin/Visas.vue"),
            },
            {
                path: "new-visa",
                name: "NewVisa",
                component: () => import("@/pages/admin/NewVisa.vue"),
            },
            {
                path: "update-visa/",
                name: "UpdateVisa",
                component: () => import("@/pages/admin/UpdateVisa.vue"),
            },
            {
                path: "new/holiday",
                name: "NewHoliday",
                component: () => import("@/pages/admin/NewHoliday.vue"),
            },
            {
                path: "update/holiday",
                name: "UpdateHoliday",
                component: () => import("@/pages/admin/UpdateHoliday.vue"),
            },
            {
                path: "holidays",
                name: "Holidays",
                component: () => import("@/pages/admin/Holidays.vue"),
            },
            {
                path: "umrah-packages",
                name: "UmrahPackages",
                component: () => import("@/pages/admin/UmrahPackages.vue"),
            },
            {
                path: "new/umrah-package",
                name: "NewUmrahPackage",
                component: () => import("@/pages/admin/NewUmrahPackage.vue"),
            },
            {
                path: "update/umrah-package",
                name: "UpdateUmrahPackage",
                component: () => import("@/pages/admin/UpdateUmrahPackage.vue"),
            },
            {
                path: "group-tickets",
                name: "GroupTickets",
                component: () => import("@/pages/admin/GroupTickets.vue"),
            },
            {
                path: "new/group-ticket",
                name: "NewGroupTicket",
                component: () => import("@/pages/admin/NewGroupTicket.vue"),
            },
            {
                path: "update/group-ticket",
                name: "UpdateGroupTicket",
                component: () => import("@/pages/admin/UpdateGroupTicket.vue"),
            },
            {
                path: "agent-detail-bookings",
                name: "AgentDetailBookings",
                component: () => import("@/pages/admin/AgentDetailBookings.vue"),
            },
            {
                path: "agent-detail-deposits",
                name: "AgentDetailDeposits",
                component: () => import("@/pages/admin/AgentDetailDeposits.vue"),
            },

        ],
    },
];
