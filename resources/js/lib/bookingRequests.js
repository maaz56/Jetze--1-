import moment from "moment";

// Shared display helpers for the customer and admin void / refund request pages.

export const REQUEST_STATUS_STYLES = {
    pending: { badge: "bg-amber-100 text-amber-800", dot: "bg-amber-500" },
    processing: { badge: "bg-blue-100 text-blue-800", dot: "bg-blue-500" },
    approved: { badge: "bg-emerald-100 text-emerald-800", dot: "bg-emerald-500" },
    rejected: { badge: "bg-rose-100 text-rose-800", dot: "bg-rose-500" },
    failed: { badge: "bg-red-100 text-red-800", dot: "bg-red-500" },
};

const DEFAULT_STYLE = { badge: "bg-gray-100 text-gray-800", dot: "bg-gray-400" };

export const requestStatusStyle = (status) => REQUEST_STATUS_STYLES[status] || DEFAULT_STYLE;

/** "partially_voided" -> "partially voided" */
export const statusLabel = (status) => String(status || "").replaceAll("_", " ");

/** Passenger statuses that block another void / refund request. */
export const SETTLED_PASSENGER_STATUSES = ["voided", "refunded"];

export const formatMoney = (value, currency) => {
    const amount = new Intl.NumberFormat("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));

    return currency ? `${currency} ${amount}` : amount;
};

export const formatRequestDate = (value) => (value ? moment(value).format("DD MMM YYYY, hh:mm A") : "N/A");

export const bookingRef = (booking, fallbackId) =>
    booking?.itinerary_ref || booking?.pnr || (fallbackId ? `#${fallbackId}` : "N/A");

export const paxName = (pax) => [pax?.title, pax?.first_name, pax?.last_name].filter(Boolean).join(" ");
