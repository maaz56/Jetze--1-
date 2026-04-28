import { useAuthStore } from "@/services/stores/auth";
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
        if (["admin"].includes(user.role)) {
            if (to.name !== "Dashboard") return next({ name: "Dashboard" });
        } else if (user.role === "agent") {
            if (isAuthenticated && isEmailVerified) {
                if (to.name !== "AgentDashboard") return next({ name: "AgentDashboard" });
            } else {
                if (to.name !== "AgentDashboard") return next({ name: "AgentDashboard" });
            }   
        } else if (user.role === "salesman") {
            if (to.name !== "SalesmanUsers") return next({ name: "SalesmanUsers" });
        }else if (user.role === "reservation") {
            if (to.name !== "ReservationUsers") return next({ name: "ReservationUsers" });
        } else {
            return next({ name: "Login" });
        }
    }

    if (to.meta.requiresAuth) {
        if (!isAuthenticated) {
            return next({ name: "Login" });
        } else {
            const requiredRole = to.meta.requiredRole;
            if (user.role !== requiredRole) {
                if (["admin", "reservation", "accounts"].includes(user.role)) {
                    if (to.name !== "Dashboard") return next({ name: "Dashboard" });
                } else if (user.role === "agent") {
                    if (to.name !== "AgentDashboard") return next({ name: "AgentDashboard" });
                } else if (user.role === "salesman") {
                    if (to.name !== "SalesmanUsers") return next({ name: "SalesmanUsers" });
                } else if (user.role === "reservation") {
                    if (to.name !== "ReservationUsers") return next({ name: "ReservationUsers" });
                } else {
                    if (to.name !== "Login") return next({ name: "Login" });
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
