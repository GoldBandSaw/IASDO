require("dotenv").config();
const http = require("http");
const https = require("https");
const fs = require("fs");
const crypto = require("crypto");
const path = require("path");
const { URL } = require("url");
const { Pool, types } = require("pg");

// Par défaut, "pg" renvoie les colonnes BIGINT (OID 20) sous forme de chaînes
// pour éviter les pertes de précision sur de très grands nombres. Les
// identifiants de tâches (Date.now()) restent toujours dans la plage sûre
// des nombres JS, donc on les reconvertit ici pour que app.js (qui compare
// des id avec Number(...)) continue de fonctionner sans modification.
types.setTypeParser(20, value => parseInt(value, 10));

const PORT = process.env.PORT || 3000;
const ROOT = __dirname;
const MIME_TYPES = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8"
};

// Supabase impose une connexion chiffrée. On désactive la vérification stricte
// du certificat (courant avec le pooler Supabase) sauf en local.
const isLocalDb = /localhost|127\.0\.0\.1/.test(process.env.DATABASE_URL);
const pool = localMode ? null : new Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: isLocalDb ? false : { rejectUnauthorized: false }
});

async function initDatabase() {
    await pool.query(`CREATE TABLE IF NOT EXISTS tasks (
    id BIGINT PRIMARY KEY,
    title TEXT NOT NULL,
    course TEXT NOT NULL,
    due TEXT NOT NULL,
    time TEXT NOT NULL DEFAULT '',
    all_day BOOLEAN NOT NULL DEFAULT TRUE,
    priority TEXT NOT NULL,
    done BOOLEAN NOT NULL DEFAULT FALSE
  )`);
  await pool.query(`CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY CHECK (id = 1),
    first_name TEXT NOT NULL DEFAULT '',
    last_name TEXT NOT NULL DEFAULT '',
    dark_mode BOOLEAN NOT NULL DEFAULT FALSE,
    calendar_url TEXT NOT NULL DEFAULT ''
  )`);
  await pool.query(`CREATE TABLE IF NOT EXISTS users (
    username TEXT PRIMARY KEY,
    display_name TEXT NOT NULL,
    password_hash TEXT NOT NULL DEFAULT '',
    setup_token_hash TEXT NOT NULL DEFAULT '',
    setup_used BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
  )`);
  await pool.query(`CREATE TABLE IF NOT EXISTS sessions (
    id TEXT PRIMARY KEY,
    data TEXT NOT NULL,
    last_activity BIGINT NOT NULL
  )`);
  const initialPassword = process.env.INITIAL_PASSWORD || "CampusFlow2026!";
  const passwordHash = await hashPassword(initialPassword);
  for (const username of ["antonin", "lucas", "aymen", "youssef", "maelle", "jason", "nolann", "leon", "roman", "cedric"]) {
    await pool.query(
      `INSERT INTO users (username, display_name, password_hash, setup_used)
       VALUES ($1, $1, $2, TRUE)
       ON CONFLICT (username) DO UPDATE SET
         password_hash = CASE WHEN users.password_hash = '' THEN EXCLUDED.password_hash ELSE users.password_hash END,
         setup_used = CASE WHEN users.password_hash = '' THEN TRUE ELSE users.setup_used END`,
      [username, passwordHash]
    );
  }
}

function cookies(request) {
  return Object.fromEntries((request.headers.cookie || "").split(";").filter(Boolean).map(cookie => {
    const [name, ...value] = cookie.trim().split("=");
    return [name, decodeURIComponent(value.join("="))];
  }));
}

function hashPassword(password) {
  return new Promise((resolve, reject) => {
    crypto.randomBytes(16, (error, salt) => {
      if (error) return reject(error);
      crypto.scrypt(password, salt, 64, (scryptError, derivedKey) => {
        if (scryptError) return reject(scryptError);
        resolve(`scrypt$${salt.toString("hex")}$${derivedKey.toString("hex")}`);
      });
    });
  });
}

function verifyPassword(password, storedHash) {
  const [, saltHex, hashHex] = String(storedHash || "").split("$");
  if (!saltHex || !hashHex) return Promise.resolve(false);
  return new Promise((resolve, reject) => {
    crypto.scrypt(password, Buffer.from(saltHex, "hex"), 64, (error, derivedKey) => {
      if (error) return reject(error);
      const expected = Buffer.from(hashHex, "hex");
      resolve(expected.length === derivedKey.length && crypto.timingSafeEqual(expected, derivedKey));
    });
  });
}

async function currentUser(request) {
  const sessionId = cookies(request).campusflow_session;
  if (!sessionId) return null;
  const result = await pool.query(
    "SELECT data FROM sessions WHERE id = $1 AND last_activity >= $2",
    [sessionId, Math.floor(Date.now() / 1000) - 7 * 24 * 60 * 60]
  );
  if (!result.rows[0]) return null;
  const session = JSON.parse(result.rows[0].data);
  const user = await pool.query("SELECT username, display_name FROM users WHERE username = $1", [session.username]);
  return user.rows[0] || null;
}

function setSessionCookie(response, request, sessionId) {
  const secure = request.headers["x-forwarded-proto"] === "https";
  response.setHeader("Set-Cookie", `campusflow_session=${sessionId}; Path=/; Max-Age=${7 * 24 * 60 * 60}; HttpOnly; SameSite=Lax${secure ? "; Secure" : ""}`);
}

function send(response, status, body, contentType = "text/plain; charset=utf-8") {
  response.writeHead(status, {
    "Content-Type": contentType,
    "Access-Control-Allow-Origin": "*",
    "Cache-Control": "no-store"
  });
  response.end(body);
}

function json(response, status, payload) {
  send(response, status, JSON.stringify(payload), "application/json; charset=utf-8");
}

function readBody(request) {
  return new Promise((resolve, reject) => {
    let body = "";
    request.on("data", chunk => { body += chunk; });
    request.on("end", () => {
      try { resolve(body ? JSON.parse(body) : {}); }
      catch (error) { reject(error); }
    });
    request.on("error", reject);
  });
}

async function getState(response) {
  try {
    const tasksResult = await pool.query(
      `SELECT id, title, course, due, time, all_day AS "allDay", priority, done
       FROM tasks ORDER BY due`
    );
    const settingsResult = await pool.query(
      `SELECT first_name AS "firstName", last_name AS "lastName",
              dark_mode AS "darkMode", calendar_url AS "calendarUrl"
       FROM settings WHERE id = 1`
    );
    json(response, 200, {
      tasks: tasksResult.rows,
      settings: settingsResult.rows[0] || null
    });
  } catch (error) {
    json(response, 500, { error: error.message });
  }
}

async function handleApi(request, response, requestUrl) {
  const pathname = requestUrl.pathname;

  if (pathname === "/api/auth/login" && request.method === "POST") {
    const body = await readBody(request);
    const username = String(body.username || "").trim().toLowerCase();
    const result = await pool.query("SELECT username, display_name, password_hash FROM users WHERE username = $1", [username]);
    if (!result.rows[0] || !(await verifyPassword(String(body.password || ""), result.rows[0].password_hash))) {
      return json(response, 401, { error: "Identifiant ou mot de passe incorrect." });
    }
    const sessionId = crypto.randomBytes(24).toString("hex");
    await pool.query("INSERT INTO sessions (id, data, last_activity) VALUES ($1, $2, $3)", [
      sessionId, JSON.stringify({ username }), Math.floor(Date.now() / 1000)
    ]);
    setSessionCookie(response, request, sessionId);
    return json(response, 200, { authenticated: true, user: { username, display_name: result.rows[0].display_name } });
  }

  if (pathname === "/api/auth/me" && request.method === "GET") {
    const user = await currentUser(request);
    return user ? json(response, 200, { authenticated: true, user }) : json(response, 401, { authenticated: false });
  }

  if (pathname === "/api/auth/logout" && request.method === "POST") {
    await pool.query("DELETE FROM sessions WHERE id = $1", [cookies(request).campusflow_session || ""]);
    return json(response, 200, { ok: true });
  }

  if (pathname === "/api/auth/password" && request.method === "PUT") {
    const user = await currentUser(request);
    if (!user) return json(response, 401, { error: "Authentification requise." });
    const body = await readBody(request);
    if (String(body.password || "").length < 10 || !/[A-Za-z]/.test(body.password) || !/\d/.test(body.password)) {
      return json(response, 422, { error: "Le mot de passe doit contenir au moins 10 caractères, une lettre et un chiffre." });
    }
    await pool.query("UPDATE users SET password_hash = $1 WHERE username = $2", [await hashPassword(body.password), user.username]);
    return json(response, 200, { ok: true });
  }

  const user = await currentUser(request);
  if (!user) return json(response, 401, { error: "Authentification requise." });

  if (pathname === "/api/state" && request.method === "GET") {
    return getState(response);
  }

  if (pathname === "/api/settings" && request.method === "PUT") {
    const body = await readBody(request);
    try {
      await pool.query(
        `INSERT INTO settings (id, first_name, last_name, dark_mode, calendar_url)
         VALUES (1, $1, $2, $3, $4)
         ON CONFLICT (id) DO UPDATE SET
           first_name = EXCLUDED.first_name,
           last_name = EXCLUDED.last_name,
           dark_mode = EXCLUDED.dark_mode,
           calendar_url = EXCLUDED.calendar_url`,
        [body.firstName || "", body.lastName || "", Boolean(body.darkMode), body.calendarUrl || ""]
      );
      return json(response, 200, { ok: true });
    } catch (error) {
      return json(response, 500, { error: error.message });
    }
  }

  if (pathname === "/api/tasks" && request.method === "POST") {
    const body = await readBody(request);
    try {
      await pool.query(
        `INSERT INTO tasks (id, title, course, due, time, all_day, priority, done)
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8)
         ON CONFLICT (id) DO UPDATE SET
           title = EXCLUDED.title,
           course = EXCLUDED.course,
           due = EXCLUDED.due,
           time = EXCLUDED.time,
           all_day = EXCLUDED.all_day,
           priority = EXCLUDED.priority,
           done = EXCLUDED.done`,
        [body.id, body.title, body.course, body.due, body.time || "", Boolean(body.allDay), body.priority, Boolean(body.done)]
      );
      return json(response, 201, { ok: true });
    } catch (error) {
      return json(response, 400, { error: error.message });
    }
  }

  const taskMatch = pathname.match(/^\/api\/tasks\/(\d+)$/);
  if (taskMatch && request.method === "PUT") {
    const body = await readBody(request);
    try {
      await pool.query(
        `UPDATE tasks SET title=$1, course=$2, due=$3, time=$4, all_day=$5, priority=$6, done=$7 WHERE id=$8`,
        [body.title, body.course, body.due, body.time || "", Boolean(body.allDay), body.priority, Boolean(body.done), taskMatch[1]]
      );
      return json(response, 200, { ok: true });
    } catch (error) {
      return json(response, 400, { error: error.message });
    }
  }

  if (taskMatch && request.method === "DELETE") {
    try {
      await pool.query("DELETE FROM tasks WHERE id=$1", [taskMatch[1]]);
      return json(response, 200, { ok: true });
    } catch (error) {
      return json(response, 500, { error: error.message });
    }
  }

  return json(response, 404, { error: "API introuvable." });
}

function proxyCalendar(response, target) {
  let parsed;
  try {
    parsed = new URL(target);
    if (parsed.protocol !== "https:") throw new Error("Le calendrier doit utiliser HTTPS.");
  } catch (error) {
    send(response, 400, error.message);
    return;
  }

  const request = https.get(parsed, { headers: { "User-Agent": "CampusFlow/1.0" } }, upstream => {
    if (upstream.statusCode < 200 || upstream.statusCode >= 300) {
      upstream.resume();
      send(response, 502, `Le serveur du calendrier a répondu ${upstream.statusCode}.`);
      return;
    }
    response.writeHead(200, {
      "Content-Type": "text/calendar; charset=utf-8",
      "Access-Control-Allow-Origin": "*",
      "Cache-Control": "no-store"
    });
    upstream.pipe(response);
  });
  request.setTimeout(15000, () => request.destroy(new Error("Délai de récupération dépassé.")));
  request.on("error", error => {
    if (!response.headersSent) send(response, 502, `Impossible de récupérer l'emploi du temps : ${error.message}`);
    else response.destroy(error);
  });
}

async function serveFile(request, response, pathname) {
  if (pathname !== "/login.html" && pathname !== "/auth.js" && pathname !== "/auth.css" &&
      pathname !== "/modern.css" && (pathname === "/" || pathname.endsWith(".html")) && !(await currentUser(request))) {
    response.writeHead(302, { Location: "/login.html" });
    return response.end();
  }
  const requested = pathname === "/" ? "/index.html" : pathname;
  const filePath = path.resolve(ROOT, `.${requested}`);
  if (!filePath.startsWith(ROOT) || !fs.existsSync(filePath) || !fs.statSync(filePath).isFile()) {
    send(response, 404, "Page introuvable.");
    return;
  }
  const extension = path.extname(filePath).toLowerCase();
  response.writeHead(200, { "Content-Type": MIME_TYPES[extension] || "application/octet-stream", "Cache-Control": "no-store" });
  fs.createReadStream(filePath).pipe(response);
}

const server = http.createServer((request, response) => {
  const requestUrl = new URL(request.url, `http://localhost:${PORT}`);
  if (requestUrl.pathname.startsWith("/api/") && requestUrl.pathname !== "/api/calendar") {
    handleApi(request, response, requestUrl).catch(error => json(response, 400, { error: error.message }));
    return;
  }
  if (requestUrl.pathname === "/api/calendar") {
    const target = requestUrl.searchParams.get("url");
    if (!target) return send(response, 400, "Paramètre url manquant.");
    return proxyCalendar(response, target);
  }
  serveFile(request, response, requestUrl.pathname).catch(error => send(response, 500, error.message));
});

initDatabase()
  .then(() => {
    server.listen(PORT, "0.0.0.0", () => {
      console.log(`CampusFlow est disponible sur http://localhost:${PORT}`);
    });
  })
  .catch(error => {
    console.error("Impossible d'initialiser la base de données Postgres.", error);
    process.exit(1);
  });
