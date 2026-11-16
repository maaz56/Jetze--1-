export function updateCardFareReferences(current, index, fare) {
    if (fare?.fare_references?.length) return [...fare.fare_references];
    const references = [...current];
    references[index] = fare.ref_id;
    return references;
}
