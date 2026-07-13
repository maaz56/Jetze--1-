import { useAuthStore } from "@/services/stores/auth";
import { getFirstAllowedAdminRoute, getUserHomeRoute, routePermissions } from "@/services/routes/authorizedRoute";
import { createRouter, createWebHistory } from "vue-router";
import routes from "../services/routes";

const router = createRouter({
    history: createWebHistory(),
    routes: routes,
});

router.beforeEach(async (to, from, next) => {
    const authStore = useAuthStore();
    // Fetch authenticated user if not already loaded
    if (!authStore.user && to.name !== "Login") {
        await authStore.fetchUser();
    }

    const isAuthenticated = authStore.isAuthenticated;
    const isEmailVerified = authStore.isEmailVerified;
    const user = authStore.user;

    const loginPages = ["Login", "Register", "AgentRegister"];
    if (isAuthenticated && loginPages.includes(to.name)) {
        return next(getUserHomeRoute(authStore));
    }

    if (to.meta.requiresAuth) {
        if (!isAuthenticated) {
            return next({ name: "Login" });
        } else {
            const requiredRole = to.meta.requiredRole;
            if (requiredRole && user.role !== requiredRole && user.role !== 'admin') {
                return next(getFirstAllowedAdminRoute(authStore));
            }

            const requiredPermissions = routePermissions(to.meta);
            if (requiredPermissions.length && !requiredPermissions.some((permission) => authStore.hasPermission(permission))) {
                if (user.role === 'admin') {
                    return next({ name: "Dashboard" });
                } else {
                    return next(getFirstAllowedAdminRoute(authStore));
                }
            }
        }
    }
    // const requiredStatus = to.meta.requiredStatus;
    // // If role matches, check for status
    // if (requiredStatus && user.is_approved !== requiredStatus) {
    //     //console.log("Required status: " + requiredStatus + " Aproved: " + user.is_approved);

    //     console.warn(`Access denied. User status (${user.is_approved}) does not match required status (${requiredStatus}).`);
    //     return next({ name: "AgentDashboard" }); // Redirect to Home if status doesn't match
    // }
    // //console.log("Proced Normal " );

    next();
});

export default router;
