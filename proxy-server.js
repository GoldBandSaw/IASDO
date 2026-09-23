require("dotenv").config();
const http = require("http");
const https = require("https");
const fs = require("fs");
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
const localMode = !process.env.DATABASE_URL;
const localSessions = new Set();
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
  if (localMode) return;
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
}

function cookies(request) {
  return Object.fromEntries((request.headers.cookie || "").split(";").filter(Boolean).map(cookie => {
    const [name, ...value] = cookie.trim().split("=");
    return [name, decodeURIComponent(value.join("="))];
  }));
}

function localUser(request) {
  return localSessions.has(cookies(request).campusflow_session)
    ? { username: "antonin", display_name: "antonin" }
    : null;
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

  if (localMode && pathname === "/api/auth/login" && request.method === "POST") {
    const body = await readBody(request);
    if (String(body.username || "").trim().toLowerCase() !== "antonin") {
      return json(response, 401, { error: "En mode local, seul le compte antonin est disponible." });
    }
    const sessionId = require("crypto").randomBytes(24).toString("hex");
    localSessions.add(sessionId);
    response.writeHead(200, {
      "Content-Type": "application/json; charset=utf-8",
      "Set-Cookie": `campusflow_session=${sessionId}; Path=/; HttpOnly; SameSite=Lax`,
      "Cache-Control": "no-store"
    });
    return response.end(JSON.stringify({ authenticated: true, user: { username: "antonin", display_name: "antonin" } }));
  }

  if (localMode && pathname === "/api/auth/me" && request.method === "GET") {
    const user = localUser(request);
    return user ? json(response, 200, { authenticated: true, user }) : json(response, 401, { authenticated: false });
  }

  if (localMode && pathname === "/api/auth/logout" && request.method === "POST") {
    localSessions.delete(cookies(request).campusflow_session);
    return json(response, 200, { ok: true });
  }

  if (localMode && pathname === "/api/state" && request.method === "GET") {
    if (!localUser(request)) return json(response, 401, { error: "Authentification requise." });
    return json(response, 200, { tasks: [], settings: null, courses: [], resources: [] });
  }

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

function serveFile(request, response, pathname) {
  if (localMode && pathname !== "/login.html" && pathname !== "/auth.js" && pathname !== "/auth.css" &&
      pathname !== "/modern.css" && (pathname === "/" || pathname.endsWith(".html")) && !localUser(request)) {
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
  serveFile(request, response, requestUrl.pathname);
});

initDatabase()
  .then(() => {
    server.listen(PORT, localMode ? "127.0.0.1" : "0.0.0.0", () => {
      console.log(localMode
        ? `Mode local actif : connecte-toi avec le bouton antonin sur http://localhost:${PORT}`
        : `CampusFlow est disponible sur http://localhost:${PORT}`);
    });
  })
  .catch(error => {
    console.error("Impossible d'initialiser la base de données Postgres.", error);
    process.exit(1);
  });
