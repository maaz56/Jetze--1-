export function updateCardFareReferences(current, index, fare, isTravelport = false) {
    if (fare?.fare_references?.length) return [...fare.fare_references];
    const references = [...current];
    if (isTravelport) references.fill(fare.ref_id);
    else references[index] = fare.ref_id;
    return references;
}
