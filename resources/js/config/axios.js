import axios from "axios";
import { useRoute } from "vue-router";

const apiService = axios.create({
    baseURL:
        import.meta.env.VITE_MODE === "production"
            ? import.meta.env.VITE_API_ROOT
            : import.meta.env.VITE_API_ROOT_LOCAL,
    headers: {
        Accept: "application/json",
       
    },
    withCredentials: true,
});

// Function to set CSRF token before each API call
const setCsrfToken = async () => {
    try {
        await apiService.get("/sanctum/csrf-cookie");
        // console.log("CSRF token set");
    } catch (error) {
        console.error("Failed to get CSRF token:", error);
    }
};

// Call setCsrfToken once when the app loads
//setCsrfToken();

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
