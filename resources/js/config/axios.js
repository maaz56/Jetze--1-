import axios from "axios";
import { useRoute } from "vue-router";
import { resolveApiBaseUrl } from "./apiBaseUrl";

const apiService = axios.create({
    baseURL: resolveApiBaseUrl(),
    timeout: 300000,
    headers: {
        Accept: "application/json",
       
    },
    withCredentials: true,
});

let csrfCookieRequest;

// Establish one fresh, same-origin Sanctum session per page load before an
// authentication mutation. Axios then mirrors XSRF-TOKEN into X-XSRF-TOKEN.
export const ensureCsrfCookie = () => {
    if (!csrfCookieRequest) {
        csrfCookieRequest = apiService.get("/sanctum/csrf-cookie").catch((error) => {
            csrfCookieRequest = undefined;
            throw error;
        });
    }

    return csrfCookieRequest;
};

// Request interceptor (optional extra CSRF check)
apiService.interceptors.request.use((config) => {
    const token = localStorage.getItem('access_token');

    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Response interceptor
apiService.interceptors.response.use(
    (response) => {
        return response;
    },
    async (error) => {
        const route = useRoute();
        if (error.response && error.response.status === 401 && route.name !== "Home") {
            // Call the logout function from auth.js
            window.location.href = "/";
        }
        return Promise.reject(error);
    }
);

export default apiService;
