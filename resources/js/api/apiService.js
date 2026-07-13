// resources/js/api/apiService.js
import axios from "axios";
import { resolveApiBaseUrl } from "@/config/apiBaseUrl";

const apiService = axios.create({
  baseURL: resolveApiBaseUrl(),
  headers: { Accept: "application/json" },
  withCredentials: true,
});

export default apiService;
