# ── Base ─────────────────────────────────────
FROM denoland/deno:debian-2.7.11 AS base
WORKDIR /app
COPY package.json deno.json deno.lock ./
RUN deno install
COPY . .

# ── Development ──────────────────────────────
FROM base AS development
EXPOSE 3000
CMD ["deno", "task", "dev"]

# ── Build ────────────────────────────────────
FROM base AS build
RUN deno task build

# ── Production ───────────────────────────────
FROM denoland/deno:distroless-2.7.11 AS production
WORKDIR /app
COPY --from=build /app/.output .output
EXPOSE 3000
CMD ["run", "--allow-net", "--allow-read", "--allow-env", ".output/server/index.mjs"]
