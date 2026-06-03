# Regal Markets — Frontend (Next.js 15, App Router)

- Next.js **15** + React 19 + Tailwind CSS **v4** (CSS-first config in `app/globals.css`).
- Talks to the Laravel API via `lib/api.ts` (axios, Bearer token from the zustand store in `lib/auth.ts`).
- Set `NEXT_PUBLIC_API_URL` in `.env.local` (defaults to `http://localhost:8000/api`).
- Theme: dark luxury, gold accent. Tokens live in `app/globals.css` under `@theme`.
- Route groups: `(public)` landing/auth, `(dashboard)` member area, `(admin)` admin panel.
