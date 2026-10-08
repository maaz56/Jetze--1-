import axios from "axios";
import { resolveApiBaseUrl } from "./apiBaseUrl";

export const resolveAtApiBaseUrl = () => resolveApiBaseUrl();

const atApiService = axios.create({
    baseURL: resolveAtApiBaseUrl(),
    headers: {
        Accept: "application/json",
    },
    withCredentials: false,
});

export default atApiService;
