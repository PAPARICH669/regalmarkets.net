// Country dial codes for the phone field. Malaysia first (default), then common
// markets, then the rest alphabetically by name.
export interface Country { name: string; iso: string; dial: string; flag: string; }

/** ISO-2 country code -> flag emoji (regional indicator pair). */
export function flagEmoji(iso?: string | null): string {
  if (!iso || iso.length !== 2) return "🌐";
  const cc = iso.toUpperCase();
  if (!/^[A-Z]{2}$/.test(cc)) return "🌐";
  return String.fromCodePoint(0x1f1e6 + (cc.charCodeAt(0) - 65), 0x1f1e6 + (cc.charCodeAt(1) - 65));
}

/**
 * Friendly hint for the expected NATIONAL-ID format by country (mirrors the
 * server-side KycIdValidator). Passport/licence are handled by the caller.
 */
export function idFormatHint(iso: string): string {
  switch ((iso || "").toUpperCase()) {
    case "MY": return "MyKad — 12 digits (e.g. 900101-01-5123)";
    case "SG": return "NRIC/FIN — letter + 7 digits + letter (e.g. S1234567D)";
    case "ID": return "NIK — 16 digits";
    case "TH": return "Thai ID — 13 digits";
    case "IN": return "Aadhaar — 12 digits";
    case "PK": return "CNIC — 13 digits";
    case "VN": return "CCCD — 9–12 digits";
    case "BD": return "NID — 10, 13, or 17 digits";
    case "PH": return "PhilSys / ID — 9–16 digits";
    default:   return "Enter your national ID number exactly as printed on the card";
  }
}

export const COUNTRIES: Country[] = [
  { name: "Malaysia", iso: "MY", dial: "+60", flag: "🇲🇾" },
  { name: "Singapore", iso: "SG", dial: "+65", flag: "🇸🇬" },
  { name: "Indonesia", iso: "ID", dial: "+62", flag: "🇮🇩" },
  { name: "Thailand", iso: "TH", dial: "+66", flag: "🇹🇭" },
  { name: "Philippines", iso: "PH", dial: "+63", flag: "🇵🇭" },
  { name: "Vietnam", iso: "VN", dial: "+84", flag: "🇻🇳" },
  { name: "Brunei", iso: "BN", dial: "+673", flag: "🇧🇳" },
  { name: "Cambodia", iso: "KH", dial: "+855", flag: "🇰🇭" },
  { name: "Myanmar", iso: "MM", dial: "+95", flag: "🇲🇲" },
  { name: "Laos", iso: "LA", dial: "+856", flag: "🇱🇦" },
  { name: "Australia", iso: "AU", dial: "+61", flag: "🇦🇺" },
  { name: "Bangladesh", iso: "BD", dial: "+880", flag: "🇧🇩" },
  { name: "Canada", iso: "CA", dial: "+1", flag: "🇨🇦" },
  { name: "China", iso: "CN", dial: "+86", flag: "🇨🇳" },
  { name: "France", iso: "FR", dial: "+33", flag: "🇫🇷" },
  { name: "Germany", iso: "DE", dial: "+49", flag: "🇩🇪" },
  { name: "Hong Kong", iso: "HK", dial: "+852", flag: "🇭🇰" },
  { name: "India", iso: "IN", dial: "+91", flag: "🇮🇳" },
  { name: "Japan", iso: "JP", dial: "+81", flag: "🇯🇵" },
  { name: "Nepal", iso: "NP", dial: "+977", flag: "🇳🇵" },
  { name: "New Zealand", iso: "NZ", dial: "+64", flag: "🇳🇿" },
  { name: "Pakistan", iso: "PK", dial: "+92", flag: "🇵🇰" },
  { name: "Saudi Arabia", iso: "SA", dial: "+966", flag: "🇸🇦" },
  { name: "South Korea", iso: "KR", dial: "+82", flag: "🇰🇷" },
  { name: "Sri Lanka", iso: "LK", dial: "+94", flag: "🇱🇰" },
  { name: "Taiwan", iso: "TW", dial: "+886", flag: "🇹🇼" },
  { name: "United Arab Emirates", iso: "AE", dial: "+971", flag: "🇦🇪" },
  { name: "United Kingdom", iso: "GB", dial: "+44", flag: "🇬🇧" },
  { name: "United States", iso: "US", dial: "+1", flag: "🇺🇸" },
];
