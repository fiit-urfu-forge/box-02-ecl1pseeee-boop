# syntax=docker/dockerfile:1.6
FROM nginx:1.27-alpine

RUN apk add --no-cache curl \
    && rm /etc/nginx/conf.d/default.conf

COPY nginx.conf /etc/nginx/nginx.conf

# Nginx needs to read Laravel's public/ so that try_files can serve static assets.
# The laravel codebase is mounted in compose (ro).

HEALTHCHECK --interval=10s --timeout=3s --start-period=5s --retries=3 \
    CMD curl -fsS http://127.0.0.1/healthz || exit 1

EXPOSE 80
