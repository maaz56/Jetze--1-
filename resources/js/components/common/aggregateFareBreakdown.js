export const fareBreakdownFields = ["base_price", "taxes", "fees", "service_charges", "surchage", "discount", "total_price"];

// Passenger amounts are already provided for each type by the supplier.
export function aggregateFareBreakdown(fares, resolveMoney) {
    const rows = new Map();
    for (const fare of fares) {
        for (const passenger of fare?.passenger_fares ?? []) {
            const type = passenger.traveler_type || "Unknown";
            if (!rows.has(type)) rows.set(type, { traveler_type: type, total_passenger: passenger.total_passenger, values: {}, seen: 0 });
            const row = rows.get(type);
            row.seen++;
            if (row.total_passenger !== passenger.total_passenger) row.total_passenger = null;
            for (const field of fareBreakdownFields) {
                const value = row.values[field] ??= { amount: 0, currency: undefined, available: true };
                const money = resolveMoney(passenger, field);
                if ((field === "taxes" && passenger.tax_breakdown_available === false) || !money || !Number.isFinite(Number(money.amount)) || money.amount === null || money.amount === "") {
                    value.available = false;
                    continue;
                }
                const currency = money.currency || null;
                if (value.currency !== undefined && value.currency !== currency) value.available = false;
                value.currency = currency;
                value.amount += Number(money.amount);
            }
        }
    }
    for (const row of rows.values()) {
        if (row.seen !== fares.length) {
            for (const value of Object.values(row.values)) value.available = false;
        }
    }
    return [...rows.values()];
}
