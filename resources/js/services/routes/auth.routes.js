export const authRoutes = [
    {
        path: "/auth",
        component: () => import("@/layouts/AuthLayout.vue"),
        children: [
            // {
            //     path: "login",
            //     name: "Login",
            //     component: () => import("@/pages/auth/Login.vue"),
            // },
            {
                path: "register",
                name: "Register",
                component: () => import("@/pages/auth/Register.vue"),
            },
            {
                path: "agent/register",
                name: "AgentRegister",
                component: () => import("@/pages/auth/AgentRegister.vue"),
            },
            {
                path: "verify-email",
                name: "VerifyEmail",
                component: () => import("@/pages/auth/VerifyEmail.vue"),
            },
            {
                path: "forgot-password",
                name: "ForgotPassword",
                component: () => import("@/pages/auth/ForgotPassword.vue"),
            },
            {
                path: "password-reset/:token",
                name: "ResetPassword",
                component: () => import("@/pages/auth/ResetPassword.vue"),
            },
        ],
    },
];
