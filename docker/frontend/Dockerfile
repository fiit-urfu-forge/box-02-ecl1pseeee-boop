# syntax=docker/dockerfile:1.6
# ---------------------------------------------------------------------------
# Next.js 14 — dev mode by default. The image is used by docker-compose with
# the host's frontend/ directory mounted in, so we optimise for fast iteration
# rather than baking a production artefact here. Prod uses a multi-stage
# build via `docker build --target=runner` when that target is added.
# ---------------------------------------------------------------------------
FROM node:20-alpine

RUN apk add --no-cache bash tini curl libc6-compat \
    && corepack enable

WORKDIR /app

ENV NEXT_TELEMETRY_DISABLED=1 \
    NODE_ENV=development \
    PORT=3000 \
    HOSTNAME=0.0.0.0

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 3000

ENTRYPOINT ["/sbin/tini", "--", "/usr/local/bin/docker-entrypoint.sh"]
CMD ["npm", "run", "dev"]
