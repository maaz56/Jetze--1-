import test from "node:test";
import assert from "node:assert/strict";
import { updateCardFareReferences } from "../../resources/js/components/common/cardFareSelection.js";

test("selects a leg fare without changing the other leg", () => {
    const current = ["outbound", "return"];
    assert.deepEqual(updateCardFareReferences(current, 1, { ref_id: "return-flex" }), ["outbound", "return-flex"]);
    assert.deepEqual(current, ["outbound", "return"]);
});

test("selects both references of a provider-approved return combination", () => {
    assert.deepEqual(updateCardFareReferences(["old-out", "old-return"], 0, { fare_references: ["new-out", "new-return"] }), ["new-out", "new-return"]);
});

test("selects a shared Travelport fare across the legs", () => {
    assert.deepEqual(updateCardFareReferences(["first", "second"], 0, { ref_id: "shared" }, true), ["shared", "shared"]);
});
