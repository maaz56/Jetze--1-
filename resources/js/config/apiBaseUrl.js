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
        const currentHostname = window.location.hostname.toLowerCase();
        const apiHostname = url.hostname.toLowerCase();
        const isJetzeHost = /^(?:www\.)?jetze\.(?:ae|pk)$/.test(currentHostname);

        // Both public domains serve this Laravel application. Keeping its API
        // same-origin is essential: Laravel's session and XSRF cookies are
        // deliberately host-only, so a shared build must not send a jetze.pk
        // user to www.jetze.ae (or vice versa).
        if (isJetzeHost) {
            return new URL(`${url.pathname}${url.search}${url.hash}`, window.location.origin).toString();
        }

        if (
            window.location.protocol === "https:" &&
            url.protocol === "http:" &&
            apiHostname.replace(/^www\./, "") === currentHostname.replace(/^www\./, "")
        ) {
            url.protocol = "https:";
        }

        return url.toString();
    } catch {
        return configuredUrl;
    }
};
