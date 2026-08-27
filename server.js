/**
 * Custom production entry point for shared-hosting "Node.js Selector" /
 * Passenger-style panels (used by home.pl and most Polish hosts) — those
 * panels run a single JS file directly and expect it to start an HTTP
 * server on the port they assign via process.env.PORT. Plain `next start`
 * can't be invoked that way, hence this small wrapper.
 *
 * Not needed for Vercel or a VPS with PM2 — there, `npm run build && npm
 * start` (which runs `next start`) is enough. See DEPLOY.md.
 */
const { createServer } = require("http");
const next = require("next");

const port = Number(process.env.PORT) || 3000;
const hostname = process.env.HOST || "0.0.0.0";
const dev = process.env.NODE_ENV !== "production";

const app = next({ dev, hostname, port });
const handle = app.getRequestHandler();

app
  .prepare()
  .then(() => {
    createServer((req, res) => {
      handle(req, res);
    }).listen(port, hostname, () => {
      console.log(`> INNOVA ready on http://${hostname}:${port} (env: ${dev ? "dev" : "production"})`);
    });
  })
  .catch((err) => {
    console.error("Failed to start server:", err);
    process.exit(1);
  });
