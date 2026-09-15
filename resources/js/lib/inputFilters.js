/**
 * Client-side filters so invalid characters never enter form fields.
 */

/** Letters (incl. accented / Ñ), spaces, apostrophe, hyphen, period — strip digits/symbols. */
export function personName(value) {
    return String(value ?? '').replace(/[^\p{L}\s'.-]/gu, '');
}

/** Digits only (for LRN). */
export function digitsOnly(value, maxLength = 20) {
    return String(value ?? '').replace(/\D/g, '').slice(0, maxLength);
}

/** Digits and common phone punctuation. */
export function phoneChars(value, maxLength = 20) {
    return String(value ?? '').replace(/[^\d\s+()-]/g, '').slice(0, maxLength);
}

/** Letters, numbers, hyphens (employee no.). */
export function employeeNo(value, maxLength = 50) {
    return String(value ?? '').replace(/[^A-Za-z0-9-]/g, '').slice(0, maxLength);
}
