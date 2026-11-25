/** "£41,700" or "£41,700.50" (same as the server's PayCheck::money). */
export function money(amount: number): string {
    return '£' + amount.toLocaleString('en-GB', { minimumFractionDigits: amount % 1 === 0 ? 0 : 2, maximumFractionDigits: 2 });
}

/** "£38,700" or "38700" typed in a form → 38700, or null if it is not a number yet. */
export function parseMoney(value: string): number | null {
    const clean = value.replace(/[£,\s]/g, '');
    return clean !== '' && !Number.isNaN(Number(clean)) ? Number(clean) : null;
}

/** The live warning when a sponsored worker's salary is below the minimum for their visa (mirrors PayCheck::warning). */
export function payWarning(salary: number | null, minimum: number): string | null {
    if (salary === null || salary >= minimum) return null;
    return `${money(salary)} a year is below the minimum salary of ${money(minimum)} for this worker's visa. Paying a sponsored worker less than the minimum can lead to your sponsor licence being suspended. Check with an immigration adviser, and report any pay cut on the Sponsor Management System.`;
}
