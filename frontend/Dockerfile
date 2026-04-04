# ── Base ─────────────────────────────────────
FROM node:22-alpine AS base
WORKDIR /app
COPY package.json ./
RUN npm install
COPY . .

# ── Development ──────────────────────────────
FROM base AS development
EXPOSE 3000
CMD ["npx", "nuxt", "dev", "--host", "0.0.0.0"]

# ── Build ────────────────────────────────────
FROM base AS build
RUN npx nuxt build

# ── Production ───────────────────────────────
FROM node:22-alpine AS production
WORKDIR /app
COPY --from=build /app/.output .output
EXPOSE 3000
CMD ["node", ".output/server/index.mjs"]
