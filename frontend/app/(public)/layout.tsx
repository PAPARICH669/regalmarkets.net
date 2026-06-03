export default function PublicLayout({ children }: { children: React.ReactNode }) {
  return (
    <div className="min-h-screen flex flex-col">
      <main className="flex-1 flex items-center justify-center py-12">{children}</main>
      <footer className="py-5 text-center text-xs text-muted">
        © {new Date().getFullYear()} Regal Markets. All rights reserved.
      </footer>
    </div>
  );
}
