import Link from "next/link";

/**
 * Brand logo. Renders the official Regal Markets artwork from /public/logo.png.
 * Save the supplied image as `frontend/public/logo.png` (ideally a transparent
 * or crown-only mark for the small/nav variants). `wordmark` keeps the text
 * lockup beside the mark for wide placements like the footer.
 */
export default function Logo({
  size = "md",
  wordmark = false,
  href = "/",
}: {
  size?: "sm" | "md" | "lg" | "xl";
  wordmark?: boolean;
  href?: string;
}) {
  const dim = { sm: "h-9", md: "h-11", lg: "h-16", xl: "h-28" }[size];
  const text = size === "xl" ? "text-3xl" : size === "lg" ? "text-2xl" : "text-lg";

  return (
    <Link href={href} className="inline-flex items-center gap-3">
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img src="/logo.png" alt="Regal Markets" className={`${dim} w-auto object-contain`} />
      {wordmark && (
        <span className={`font-bold tracking-tight ${text}`}>
          REGAL <span className="gold-text">MARKETS</span>
        </span>
      )}
    </Link>
  );
}
