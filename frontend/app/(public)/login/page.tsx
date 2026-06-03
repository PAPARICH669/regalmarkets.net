import { redirect } from "next/navigation";

// The landing page ("/") is now the single login screen. Keep /login working
// by redirecting any old links/bookmarks straight to it.
export default function LoginRedirect() {
  redirect("/");
}
