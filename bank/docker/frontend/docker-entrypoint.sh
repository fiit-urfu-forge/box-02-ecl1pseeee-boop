#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# Boots the Next.js dev server. Installs dependencies on first run (or when
# the named `frontend_node_modules` volume is empty / missing `next`).
# ---------------------------------------------------------------------------
set -euo pipefail

cd /app

if [[ ! -f package.json ]]; then
  cat <<'MSG' >&2
[frontend] package.json not found in /app — running placeholder server.
           Run Stage 10 (npx create-next-app) to replace this.
MSG
  cat > /tmp/placeholder.cjs <<'JS'
const http = require('http');
const port = process.env.PORT || 3000;
const body = JSON.stringify({
  ok: true,
  service: 'frontend-placeholder',
  note: 'Next.js project not initialised yet.',
});
http.createServer((req, res) => {
  res.writeHead(200, { 'Content-Type': 'application/json' });
  res.end(body);
}).listen(port, '0.0.0.0', () => console.log(`[frontend] placeholder on :${port}`));
JS
  exec node /tmp/placeholder.cjs
fi

# Install deps when the named volume is empty (fresh container) OR when
# the `next` binary is missing for any other reason.
if [[ ! -x node_modules/.bin/next ]]; then
  echo "[frontend] node_modules incomplete — installing..."
  npm install --no-audit --no-fund
fi

exec "$@"
