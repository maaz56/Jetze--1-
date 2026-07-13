const apiRoot = () => {
    const isProduction =
        import.meta.env.VITE_MODE === "production" ||
        import.meta.env.MODE === "production";

    if (isProduction && import.meta.env.VITE_API_ROOT) {
        return import.meta.env.VITE_API_ROOT;
    }

    return (
        import.meta.env.VITE_API_ROOT_LOCAL ||
        import.meta.env.VITE_API_ROOT ||
        "/api/"
    );
};

export const resolveApiBaseUrl = () => {
    const configuredUrl = apiRoot();

    if (typeof window === "undefined") {
        return configuredUrl;
    }

    try {
        const url = new URL(configuredUrl, window.location.origin);
        const currentHostname = window.location.hostname.replace(/^www\./, "");
        const apiHostname = url.hostname.replace(/^www\./, "");

        if (
            window.location.protocol === "https:" &&
            url.protocol === "http:" &&
            apiHostname === currentHostname
        ) {
            url.protocol = "https:";
        }

        return url.toString();
    } catch {
        return configuredUrl;
    }
};
