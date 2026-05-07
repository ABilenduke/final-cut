# ── Base ─────────────────────────────────────
FROM denoland/deno:debian-2.7.11 AS base
WORKDIR /app
COPY package.json deno.json deno.lock ./
RUN deno install
COPY . .

# ── Development ──────────────────────────────
FROM base AS development

# Create a dev user matching the host UID/GID so bind-mounted files
# share the same ownership. Scoped to dev stage only — production
# uses the distroless image with no shell or user management.
ARG DEV_UID=1000
ARG DEV_GID=1000
ENV DEV_UID=${DEV_UID}
ENV DEV_GID=${DEV_GID}
RUN groupadd -g ${DEV_GID} devuser \
    && useradd -u ${DEV_UID} -g devuser -m devuser \
    && mkdir -p /home/devuser/.cache/deno \
    && chown -R devuser:devuser /home/devuser /app

ENV DENO_DIR=/home/devuser/.cache/deno

# Dev entrypoint: starts as root to fix ownership on the /app/.nuxt
# named volume (Docker creates volume roots as root:root), then drops
# to devuser via runuser before exec'ing the CMD.
COPY docker/dev-entrypoint.sh /usr/local/bin/dev-entrypoint.sh
RUN chmod +x /usr/local/bin/dev-entrypoint.sh

EXPOSE 3000
ENTRYPOINT ["dev-entrypoint.sh"]
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
