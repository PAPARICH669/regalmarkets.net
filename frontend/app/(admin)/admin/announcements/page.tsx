"use client";

import { useEffect, useState } from "react";
import { Trash2 } from "lucide-react";
import api, { apiError } from "@/lib/api";
import { shortDate } from "@/lib/format";

interface Announcement { id: number; title: string; body: string; is_active: boolean; published_at: string; }

export default function AdminAnnouncements() {
  const [items, setItems] = useState<Announcement[]>([]);
  const [form, setForm] = useState({ title: "", body: "" });
  const [error, setError] = useState("");

  const load = () => api.get("/admin/announcements").then((r) => setItems(r.data.data));
  useEffect(() => { load(); }, []);

  async function create(e: React.FormEvent) {
    e.preventDefault(); setError("");
    try { await api.post("/admin/announcements", { ...form, is_active: true }); setForm({ title: "", body: "" }); load(); }
    catch (err) { setError(apiError(err)); }
  }
  async function remove(id: number) {
    try { await api.delete(`/admin/announcements/${id}`); load(); } catch (err) { setError(apiError(err)); }
  }

  return (
    <div className="space-y-5">
      <h1 className="text-2xl font-bold">Announcements</h1>
      {error && <div className="text-sm text-red-400 bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3">{error}</div>}
      <form onSubmit={create} className="glass p-6 space-y-3">
        <input className="input-field" placeholder="Title" value={form.title} onChange={(e) => setForm({ ...form, title: e.target.value })} required />
        <textarea className="input-field min-h-24" placeholder="Message" value={form.body} onChange={(e) => setForm({ ...form, body: e.target.value })} required />
        <button className="btn-gold px-6 py-2.5">Publish</button>
      </form>
      <div className="space-y-3">
        {items.map((a) => (
          <div key={a.id} className="glass p-4 flex items-start justify-between gap-4">
            <div>
              <h3 className="font-semibold">{a.title}</h3>
              <p className="text-sm text-muted mt-1 whitespace-pre-line">{a.body}</p>
              <p className="text-xs text-muted mt-2">{shortDate(a.published_at)}</p>
            </div>
            <button onClick={() => remove(a.id)} className="btn-ghost p-2 text-red-400"><Trash2 size={16} /></button>
          </div>
        ))}
      </div>
    </div>
  );
}
