import test from "node:test";
import assert from "node:assert/strict";
import { aggregateFareBreakdown, fareBreakdownFields } from "../../resources/js/components/common/aggregateFareBreakdown.js";

const passenger = (type, count, amount, taxAvailable = true) => ({
    traveler_type: type,
    total_passenger: count,
    tax_breakdown_available: taxAvailable,
    display_money: Object.fromEntries(fareBreakdownFields.map((field) => [field, { amount, currency: "AED" }])),
});
const resolve = (row, field) => row.display_money[field];

test("combines each passenger type across legs without adding passenger counts", () => {
    const rows = aggregateFareBreakdown([
        { passenger_fares: [passenger("ADT", 2, 100), passenger("CHD", 1, 40)] },
        { passenger_fares: [passenger("ADT", 2, 50), passenger("CHD", 1, 20)] },
    ], resolve);
    assert.equal(rows[0].total_passenger, 2);
    assert.equal(rows[0].values.total_price.amount, 150);
    assert.equal(rows[1].values.base_price.amount, 60);
});

test("does not present incomplete or unreliable values as combined totals", () => {
    const rows = aggregateFareBreakdown([
        { passenger_fares: [passenger("ADT", 1, 100, false), passenger("CHD", 1, 20)] },
        { passenger_fares: [passenger("ADT", 1, 50)] },
    ], resolve);
    assert.equal(rows[0].values.taxes.available, false);
    assert.equal(rows[0].values.base_price.available, true);
    assert.equal(rows[1].values.total_price.available, false);
});

test("does not add amounts in different currencies", () => {
    const other = passenger("ADT", 1, 50);
    other.display_money.base_price.currency = "USD";
    const [row] = aggregateFareBreakdown([
        { passenger_fares: [passenger("ADT", 1, 100)] },
        { passenger_fares: [other] },
    ], resolve);
    assert.equal(row.values.base_price.available, false);
});
