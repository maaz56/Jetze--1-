// resources/js/api/apiService.js
import axios from "axios";

const apiService = axios.create({
  baseURL:
    import.meta.env.MODE === "production"
      ? import.meta.env.VITE_API_ROOT
      : import.meta.env.VITE_API_ROOT_LOCAL,
  headers: { Accept: "application/json" },
  withCredentials: true,
});

export default apiService;
