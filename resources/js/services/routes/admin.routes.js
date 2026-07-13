export const adminRoutes = [
    {
        path: "/admin",
        component: () => import("@/layouts/AdminLayout.vue"),
        meta: {
            requiresAuth: true,
        },
        children: [
            {
                path: "dashboard",
                name: "Dashboard",
                component: () => import("@/pages/admin/Dashboard.vue"),
                meta: { requiredRole: 'admin' }
            },
            // add
             {
                path: "promotions",
                name: "Promotions",
                component: () => import("@/pages/admin/Promotions.vue"),
                meta: {
                    requiredPermissions: ["airlines_view"],
                },
            },
            {
                path: "segment-margins",
                name: "SegmentMargins",
                component: () => import("@/pages/admin/SegmentMargins.vue"),
                meta: {
                    requiredPermissions: ["airlines_view"],
                },
            },
             {
                path: "update-segment-margin/:id",
                name: "UpdateSegmentMargin",
                component: () => import("@/pages/admin/UpdateSegmentMargin.vue"),
                meta: {
                    requiredPermissions: ["airlines_view"],
                },
            },

            {
                path: "new-promotion",
                name: "NewPromotion",
                component: () => import("@/pages/admin/NewPromotion.vue"),
                meta: {
                    requiredPermissions: ["airlines_view"],
                },
            },
            {
                path: "update-promotion/:id",
                name: "UpdatePromotion",
                component: () => import("@/pages/admin/UpdatePromotion.vue"),
                meta: {
                    requiredPermissions: ["booking_settings_view"],
                },
            },

            {
                path: "new-segment-margin",
                name: "NewSegmentMargin",
                component: () => import("@/pages/admin/NewSegmentMargin.vue"),
                meta: {
                    requiredPermissions: ["airlines_view"],
                },
            },


            {
                path:"setting",
                name: "Setting",
                component: () => import("@/pages/admin/Setting.vue"),
                meta: { permission: 'manage-settings' }
            },
             {
                path: "/zoho/callback",
                name: "ZohoCallback",
                component: () => import("@/pages/admin/ZohoCallback.vue"),
            },
            
             {
                path: "/activity-log",
                name: "ActivityLog",
                component: () => import("@/pages/admin/ActivityLog.vue"),
                meta: { permission: 'view-activity-logs' }
            },

            {
                path: "customer-margin",
                name: "CustomerMargin",
                component: () => import("@/pages/admin/CustomerSettings.vue"),
                meta: { permission: 'manage-settings' }
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
                meta: { permission: 'manage-staff' }
            },
            {
                path: "staff",
                name: "Staff",
                component: () => import("@/pages/admin/Staff.vue"),
                meta: { permission: 'manage-staff' }
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
                path: "roles",
                name: "Roles",
                component: () => import("@/pages/admin/Roles.vue"),
                meta: { permission: 'manage-roles' }
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
                meta: { permission: 'manage-marketing' }
            },
            {
                path: "topup-request",
                name: "TopUpRequest",
                component: () => import("@/pages/admin/TopUpRequests.vue"),
                meta: { permission: 'manage-finance' }
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
                meta: { permission: 'view-bookings' }
            },
            {
                path: "offline-bookings",
                name: "OfflineBookings",
                component: () => import("@/pages/admin/OfflineBookings.vue"),
            },
            {
                path: "edit-offline-booking",
                name: "EditOfflineBookings",
                component: () => import("@/pages/admin/EditOfflineBooking.vue"),
            },
            {
                path: "new-offline-booking",
                name: "NewOfflineBookings",
                component: () => import("@/pages/admin/NewOfflineBooking.vue"),
            },
            {
                path: "offline-booking-details",
                name: "OfflineBookingDetails",
                component: () => import("@/pages/admin/OfflineBookingDetails.vue"),
            },
            {
                path: "void-bookings",
                name: "VoidBookings",
                component: () => import("@/pages/admin/VoidBookings.vue"),
                meta: { permission: 'manage-bookings' }
            },
            {
                path: "customers",
                name: "Customers",
                component: () => import("@/pages/admin/Customers.vue"),
                meta: { permission: 'manage-customers' }
            },
            {
                path: "customer-details",
                name: "CustomerDetails",
                component: () => import("@/pages/admin/CustomerDetails.vue"),
            },
            {
                path: "update-customer-details",
                name: "UpdateCustomer",
                component: () => import("@/pages/admin/UpdateCustomerData.vue"),
            },
            {
                path: "admin-customer-bookings",
                name: "AdminCustomerBookings",
                component: () => import("@/pages/admin/AdminCustomerBookings.vue"),
            },
            {
                path: "customer-booking-details",
                name: "CustomerBookingDetails",
                component: () => import("@/pages/admin/CustomerBookingDetails.vue"),
            },
            {
                path: "admin-customer-booking-layout",
                name: "AdminCustomerBookingsLayout",
                component: () => import("@/pages/AdminCustomerBookingsLayout.vue"),
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
                meta: { permission: 'manage-airlines' }
            },
            {
                path: "airport-markups",
                name: "AirportMarkups",
                component: () => import("@/pages/admin/AirportMargin.vue"),
                meta: { permission: 'manage-airports' }
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
                component: () => import("@/pages/admin/crm/airports/Airports.vue"),
                meta: { permission: 'manage-cms' }
            },
            {
                path: "airports/create",
                name: "NewAirport",
                component: () => import("@/pages/admin/crm/airports/NewAirport.vue"),
                meta: { permission: 'manage-cms' }
            },
            {
                path: "airports/edit/:id",
                name: "EditAirport",
                component: () => import("@/pages/admin/crm/airports/EditAirport.vue"),
                meta: { permission: 'manage-cms' }
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
            {
                path: "other-charges",
                name: "OtherCharges",
                component: () => import("@/pages/admin/OtherCharges.vue"),
            },
            {
                path: "new-charges",
                name: "NewCharges",
                component: () => import("@/pages/admin/newCharges.vue"),
            },
            {
                path: "new-bank",
                name: "NewBank",
                component: () => import("@/pages/admin/NewBank.vue"),
            },
            {
                path: "update-bank/:id",
                name: "UpdateBank",
                component: () => import("@/pages/admin/UpdateBank.vue"),
            },
            {
                path: "banks",
                name: "Banks",
                component: () => import("@/pages/admin/Banks.vue"),
            },
            {
                path: "currencies",
                name: "Currencies",
                component: () => import("@/pages/admin/Currencies.vue"),
            },
            {
                path: "import-pnr",
                name: "ImportPnr",
                component: () => import("@/pages/admin/ImportPnr.vue"),
            },
            {
                path: "add-booking",
                name: "AddBooking",
                component: () => import("@/pages/admin/AddBooking.vue"),
            },
            {
                path: "direct-bookings",
                name: "DirectBookings",
                component: () => import("@/pages/admin/DirectBookings.vue"),
            },
            {
                path: "direct-booking-details",
                name: "DirectBookingDetails",
                component: () => import("@/pages/admin/DirectBookingDetails.vue"),
            },
            {
                path: "booking-details-offline",
                name: "BookingDetailsOffline",
                component: () => import("@/pages/admin/BookingDetailsOffline.vue"),
            },
            {
                path: "bookings-details",
                name: "AdminBookingsDetails",
                component: () => import("@/pages/AdminBookingsDetails.vue"),
            },

            {
                path: "update-agent-data",
                name: "UpdateAdminAgentData",
                component: () => import("@/pages/admin/UpdateAdminAgentData.vue"),
            },
             {
                path: "admin-ledger",
                name: "AdminLedger",
                component: () => import("@/pages/admin/AdminLedger.vue"),
            },
             {
                path: "profit-loss-report",
                name: "ProfitLossReport",
                component: () => import("@/pages/admin/ProfitLossReport.vue"),
            },
            
             {
                path: "modify-requests",
                name: "ModifyRequests",
                component: () => import("@/pages/admin/ModifyRequests.vue"),
            },
            {
                path: "popular-routes",
                name: "PopularRoutes",
                component: () => import("@/pages/admin/crm/popularRoutes/PopularRoutes.vue"),
            },
            {
                path: "create/popular-routes",
                name: "NewPopularRoutes",
                component: () => import("@/pages/admin/crm/popularRoutes/NewPopularRoutes.vue"),
            },
            {
                path: "popular-routes/view/:id",
                name: "PopularRouteView",
                component: () => import("@/pages/admin/crm/popularRoutes/ViewPopularRoute.vue"),
            },
            {
                path: "popular-routes/edit/:id",
                name: "PopularRouteEdit",
                component: () => import("@/pages/admin/crm/popularRoutes/EditPopularRoute.vue"),
            },
            {
                path: "top-airlines",
                name: "TopAirlines",
                component: () => import("@/pages/admin/crm/topAirlines/TopAirlines.vue"),
            },
            {
                path: "top-airlines/create",
                name: "NewTopAirline",
                component: () => import("@/pages/admin/crm/topAirlines/NewTopAirline.vue"),
            },
            {
                path: "top-airlines/edit/:id",
                name: "EditTopAirline",
                component: () => import("@/pages/admin/crm/topAirlines/EditTopAirline.vue"),
            },
            {
                path: "blogs",
                name: "Blogs",
                component: () => import("@/pages/admin/crm/blogs/index.vue"),
            },
            {
                path: "blogs/create",
                name: "NewBlog",
                component: () => import("@/pages/admin/crm/blogs/newBlog.vue"),
            },
            {
                path: "blogs/update/:id",
                name: "UpdateBlog",
                component: () => import("@/pages/admin/crm/blogs/newBlog.vue"),
            },
            {
                path: "seo-settings",
                name: "SeoSettings",
                component: () => import("@/pages/admin/crm/SeoSettings.vue"),
                meta: { permission: 'manage-cms' },
            },
            {
                path: "reviews",
                name: "AdminReviews",
                component: () => import("@/pages/admin/crm/reviews/Reviews.vue"),
                meta: { permission: 'manage-cms' }
            },
            {
                path: "subcribers",
                name: "Subcribers",
                component: () => import("@/pages/admin/crm/Subcribers/Subcribers.vue"),
                meta: { permission: 'manage-cms' }
            },

            // hotDeals Routes

            {
                path: "hot-deals",
                name: "HotDeals",
                component: () => import("@/pages/admin/crm/HotDealsRoutes/IndexHotDealsRoutes.vue"),
                meta: { permission: 'manage-cms' }
            },
            {
                path: "hot-deals/create",
                name: "HotDealCreate",
                component: () => import("@/pages/admin/crm/HotDealsRoutes/CreateHotDealsRoutes.vue"),
                meta: { permission: 'manage-cms' }
            },
            {
                path: "hot-deals/:id/edit",
                name: "HotDealEdit",
                component: () => import("@/pages/admin/crm/HotDealsRoutes/EditHotDealsRoutes.vue"),
                meta: { permission: 'manage-cms' }
            },
            {
                path: "hot-deals/:id",
                name: "HotDealView",
                component: () => import("@/pages/admin/crm/HotDealsRoutes/ViewHotDealsRoutes.vue"),
                meta: { permission: 'manage-cms' }
            },
        ],
    },
];
