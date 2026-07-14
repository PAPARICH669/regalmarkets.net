"use client";

import { useEffect, useState } from "react";
import { X, ZoomIn } from "lucide-react";
import api from "@/lib/api";
import { flagEmoji } from "@/lib/countries";

export interface KycMember {
  id: number; username: string; name?: string;
  kyc_country?: string | null; id_type?: string; id_number?: string;
  kyc_document_path?: string | null; kyc_selfie_path?: string | null; kyc_status?: string;
}

/**
 * Modal that shows a member's KYC ID document and selfie SIDE BY SIDE so staff
 * can face-match in one glance, with Verify / Reject right there. Images are
 * fetched as authenticated blobs from `${basePath}/{id}/kyc-document|kyc-selfie`
 * (basePath = "/staff/members" or "/admin/members").
 */
export default function KycReviewModal({
  member, basePath, onClose, onVerify, onReject,
}: {
  member: KycMember; basePath: string;
  onClose: () => void; onVerify: (id: number) => void; onReject: (id: number) => void;
}) {
  const [doc, setDoc] = useState<{ url: string; pdf: boolean } | null>(null);
  const [selfie, setSelfie] = useState<string | null>(null);
  const [err, setErr] = useState("");

  useEffect(() => {
    const urls: string[] = [];
    async function grab(kind: "kyc-document" | "kyc-selfie") {
      const res = await api.get(`${basePath}/${member.id}/${kind}`, { responseType: "blob" });
      const url = URL.createObjectURL(res.data);
      urls.push(url);
      return { url, type: (res.data as Blob).type || "" };
    }
    (async () => {
      try {
        if (member.kyc_document_path) { const d = await grab("kyc-document"); setDoc({ url: d.url, pdf: /pdf/i.test(d.type) }); }
        if (member.kyc_selfie_path) { const s = await grab("kyc-selfie"); setSelfie(s.url); }
      } catch { setErr("Failed to load images."); }
    })();
    return () => { urls.forEach((u) => URL.revokeObjectURL(u)); };
  }, [member.id, basePath, member.kyc_document_path, member.kyc_selfie_path]);

  const verified = member.kyc_status === "verified";
  const open = (url: string | null) => url && window.open(url, "_blank");

  return (
    <div className="fixed inset-0 z-50 bg-black/70 flex items-center justify-center p-4" onClick={onClose}>
      <div className="w-full max-w-2xl rounded-2xl overflow-hidden border border-[var(--line)] bg-[var(--surface)] shadow-2xl"
        onClick={(e) => e.stopPropagation()}>
        {/* Header */}
        <div className="flex items-center gap-3 px-4 py-3 border-b border-[var(--line)]">
          <div className="flex-1 min-w-0">
            <div className="font-semibold truncate">{member.name || member.username}</div>
            <div className="text-xs text-muted truncate">
              @{member.username}
              {member.kyc_country ? ` · ${flagEmoji(member.kyc_country)} ${member.kyc_country}` : ""}
              {member.id_type ? ` · ${member.id_type.toUpperCase()} ${member.id_number || ""}` : ""}
            </div>
          </div>
          <button onClick={onClose} aria-label="Close" className="btn-ghost p-2"><X size={16} /></button>
        </div>

        {/* Images side by side */}
        <div className="p-4">
          <p className="text-xs text-muted text-center mb-3">Compare the face on the ID with the selfie — confirm it is the same person.</p>
          {err && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-3 py-2 mb-3">{err}</div>}
          <div className="grid grid-cols-2 gap-3">
            <div>
              <div className="text-xs font-medium text-muted mb-1.5">ID document</div>
              <div className="aspect-square bg-black/30 border border-[var(--line)] rounded-lg overflow-hidden flex items-center justify-center">
                {doc ? (doc.pdf
                  ? <button onClick={() => open(doc.url)} className="btn-ghost px-3 py-2 text-xs">Open PDF</button>
                  : <img src={doc.url} alt="ID" className="w-full h-full object-contain cursor-zoom-in" onClick={() => open(doc.url)} />)
                  : <span className="text-xs text-muted">{member.kyc_document_path ? "Loading…" : "No document"}</span>}
              </div>
            </div>
            <div>
              <div className="text-xs font-medium text-muted mb-1.5">Selfie</div>
              <div className="aspect-square bg-black/30 border border-[var(--line)] rounded-lg overflow-hidden flex items-center justify-center">
                {selfie
                  ? <img src={selfie} alt="Selfie" className="w-full h-full object-contain cursor-zoom-in" onClick={() => open(selfie)} />
                  : <span className="text-xs text-muted">{member.kyc_selfie_path ? "Loading…" : "No selfie"}</span>}
              </div>
            </div>
          </div>
          <div className="text-[11px] text-muted text-center mt-2 flex items-center justify-center gap-1"><ZoomIn size={12} /> Click an image to open full size</div>
        </div>

        {/* Actions */}
        {!verified && (
          <div className="flex gap-3 px-4 py-3 border-t border-[var(--line)]">
            <button onClick={() => onVerify(member.id)} className="btn-gold flex-1 py-2.5 text-sm">✓ Verify (face matches)</button>
            <button onClick={() => onReject(member.id)} className="btn-ghost flex-1 py-2.5 text-sm text-red-400">Reject</button>
          </div>
        )}
      </div>
    </div>
  );
}
