const customerRoles = ["user", "customer", "agent"];

const adminLandingRoutes = [
    { permission: "view-bookings", route: { name: "AdminCustomerBookings" } },
    { permission: "manage-bookings", route: { name: "VoidBookings" } },
    { permission: "manage-cms", route: { name: "PopularRoutes" } },
    { permission: "manage-finance", route: { name: "TopUpRequest" } },
    { permission: "manage-settings", route: { name: "Setting" } },
    { permission: "view-ledger", route: { name: "AdminLedger" } },
    { permission: "manage-marketing", route: { name: "Banners" } },
    { permission: "manage-airlines", route: { name: "Airlines" } },
    { permission: "airlines_view", route: { name: "Promotions" } },
    { permission: "manage-airports", route: { name: "AirportMarkups" } },
    { permission: "manage-staff", route: { name: "Staff" } },
    { permission: "manage-roles", route: { name: "Roles" } },
    { permission: "manage-customers", route: { name: "Customers" } },
    { permission: "view-activity-logs", route: { name: "ActivityLog" } },
];

export function routePermissions(meta = {}) {
    return [
        ...(Array.isArray(meta.permission) ? meta.permission : [meta.permission]),
        ...(Array.isArray(meta.requiredPermissions) ? meta.requiredPermissions : [meta.requiredPermissions]),
    ].filter(Boolean);
}

export function getFirstAllowedAdminRoute(authStore) {
    if (authStore.user?.role === "admin") {
        return { name: "Dashboard" };
    }

    return adminLandingRoutes.find(({ permission }) => authStore.hasPermission(permission))?.route
        || { name: "AdminCustomerBookings" };
}

export function getUserHomeRoute(authStore) {
    const user = authStore.user;

    if (!user) {
        return { name: "Login" };
    }

    if (user.role === "agent") {
        return user.is_formFilled == "0"
            ? { name: "AddAgentDetails" }
            : { name: "DashboardFlights" };
    }

    if (customerRoles.includes(user.role)) {
        return;
    }

    return getFirstAllowedAdminRoute(authStore);
}
