import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Lint is run separately; don't fail production builds on the eslint-flat-config
  // module-resolution quirk in eslint-config-next under ESLint 9.
  eslint: { ignoreDuringBuilds: true },
};

export default nextConfig;
