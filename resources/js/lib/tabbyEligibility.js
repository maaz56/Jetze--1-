/**
 * Country calling codes whose mobile numbers may use Tabby (UAE, KSA).
 * Mirrors TabbyService::normalizePhone(); the backend still enforces it.
 */
const TABBY_PHONE_CODES = ["971", "966"];

/** Whether Tabby should be offered for this customer mobile number. */
export function isTabbyEligiblePhone(phone) {
    let digits = String(phone ?? "").replace(/\D+/g, "");
    if (digits.startsWith("00")) digits = digits.slice(2);

    return TABBY_PHONE_CODES.some(
        (code) => digits.startsWith(code) && digits.length - code.length >= 8,
    );
}
