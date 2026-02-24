const {setGlobalOptions} = require("firebase-functions/v2");
const {onRequest} = require("firebase-functions/v2/https");
const {onSchedule} = require("firebase-functions/v2/scheduler");
const logger = require("firebase-functions/logger");
const Anthropic = require("@anthropic-ai/sdk");
const {google} = require("googleapis");
const fs = require("fs");
const path = require("path");

setGlobalOptions({maxInstances: 10, region: "europe-west1"});

// --- CORS ---
const ALLOWED_ORIGINS = [
  "https://gonexo.site",
  "https://www.gonexo.site",
  "http://localhost:3000",
];

/**
 * Gestisce le intestazioni CORS.
 * Restituisce true se la richiesta è un preflight OPTIONS (già gestito).
 */
function handleCors(req, res) {
  const origin = req.headers.origin;
  if (ALLOWED_ORIGINS.includes(origin)) {
    res.set("Access-Control-Allow-Origin", origin);
  }
  res.set("Access-Control-Allow-Methods", "GET, POST, OPTIONS");
  res.set("Access-Control-Allow-Headers", "Content-Type");
  res.set("Access-Control-Max-Age", "3600");

  if (req.method === "OPTIONS") {
    res.status(204).send("");
    return true;
  }
  return false;
}

// --- System Prompt (caricato una sola volta per istanza) ---
let cachedSystemPrompt = null;

function getSystemPrompt() {
  if (!cachedSystemPrompt) {
    const promptPath = path.join(__dirname, "system-prompt.txt");
    cachedSystemPrompt = fs.readFileSync(promptPath, "utf8");
  }
  return cachedSystemPrompt;
}

// --- CHAT FUNCTION ---
exports.chat = onRequest(
    {
      memory: "512MiB",
      timeoutSeconds: 120,
      secrets: ["ANTHROPIC_API_KEY"],
    },
    async (req, res) => {
      if (handleCors(req, res)) return;

      // Health check per keepAlive
      if (req.method === "GET") {
        res.status(200).json({status: "ok"});
        return;
      }

      if (req.method !== "POST") {
        res.status(405).json({error: "Metodo non consentito"});
        return;
      }

      const {sessionId, message, history} = req.body;

      if (!message) {
        res.status(400).json({error: "Il campo 'message' è obbligatorio"});
        return;
      }

      try {
        const anthropic = new Anthropic({
          apiKey: process.env.ANTHROPIC_API_KEY,
        });

        // Costruisce l'array dei messaggi: history pregressa + nuovo messaggio
        const messages = [];
        if (Array.isArray(history)) {
          for (const msg of history) {
            messages.push({role: msg.role, content: msg.content});
          }
        }
        messages.push({role: "user", content: message});

        logger.info("Chat request", {
          sessionId,
          messageCount: messages.length,
          userMessage: message,
        });

        // SSE headers
        res.setHeader("Content-Type", "text/event-stream");
        res.setHeader("Cache-Control", "no-cache");
        res.setHeader("Connection", "keep-alive");

        // Streaming con Anthropic SDK
        const stream = anthropic.messages.stream({
          model: "claude-sonnet-4-20250514",
          max_tokens: 1500,
          system: getSystemPrompt(),
          messages,
        });

        stream.on("text", (text) => {
          const payload = JSON.stringify({type: "text", content: text});
          res.write(`data: ${payload}\n\n`);
        });

        stream.on("end", () => {
          res.write(`data: ${JSON.stringify({type: "done"})}\n\n`);
          res.end();
        });

        stream.on("error", (err) => {
          logger.error("Errore streaming Anthropic", err);
          const errMsg = "Si è verificato un errore. Riprova tra qualche istante.";
          const payload = JSON.stringify({type: "error", content: errMsg});
          res.write(`data: ${payload}\n\n`);
          res.end();
        });
      } catch (err) {
        logger.error("Errore nella funzione chat", err);

        // Se gli header SSE sono già stati inviati, chiudi lo stream
        if (res.headersSent) {
          const errMsg = "Si è verificato un errore. Riprova tra qualche istante.";
          const payload = JSON.stringify({type: "error", content: errMsg});
          res.write(`data: ${payload}\n\n`);
          res.end();
        } else {
          res.status(500).json({
            error: "Si è verificato un errore. Riprova tra qualche istante.",
          });
        }
      }
    },
);

// --- LEAD FUNCTION ---
exports.lead = onRequest(
    {
      memory: "256MiB",
      timeoutSeconds: 30,
      secrets: ["GOOGLE_SERVICE_ACCOUNT_JSON", "GOOGLE_SHEET_ID"],
    },
    async (req, res) => {
      try {
        if (handleCors(req, res)) return;

        if (req.method !== "POST") {
          res.status(405).json({error: "Metodo non consentito"});
          return;
        }

        const {nome, email, telefono, nomeAzienda, descrizioneProgetto,
          preventivoIndicato, probabilitaChiusura, noteQualifica,
          conversazione} = req.body || {};

        if (!telefono) {
          res.status(400).json({error: "Il numero di telefono è obbligatorio"});
          return;
        }

        logger.info("Nuovo lead ricevuto", {
          telefono,
          nome: nome || "N/A",
          email: email || "N/A",
          nomeAzienda: nomeAzienda || "N/A",
          probabilitaChiusura: probabilitaChiusura || 0,
        });

        try {
          // Parsing credenziali service account
          const raw = process.env.GOOGLE_SERVICE_ACCOUNT_JSON || "";
          const credentials = JSON.parse(raw);

          const auth = new google.auth.GoogleAuth({
            credentials,
            scopes: ["https://www.googleapis.com/auth/spreadsheets"],
          });

          const sheets = google.sheets({version: "v4", auth});
          const sheetId = process.env.GOOGLE_SHEET_ID;

          // Formatta la conversazione come testo leggibile
          let conversazioneText = "";
          if (Array.isArray(conversazione)) {
            conversazioneText = conversazione
                .map((msg) => {
                  const ruolo =
                    msg.role === "user" ? "Utente" : "Spark";
                  return `${ruolo}: ${msg.content}`;
                })
                .join(" | ");
          } else if (typeof conversazione === "string") {
            conversazioneText = conversazione;
          }

          // Colore scoring
          let coloreScoring = "🔴";
          if (probabilitaChiusura >= 60) coloreScoring = "🟢";
          else if (probabilitaChiusura >= 30) coloreScoring = "🟠";

          const row = [
            new Date().toISOString(),
            noteQualifica || "",
            coloreScoring + " " + (probabilitaChiusura || 0) + "%",
            nome || "",
            telefono || "",
            email || "",
            nomeAzienda || "",
            preventivoIndicato || "",
            descrizioneProgetto || "",
            conversazioneText,
          ];

          await sheets.spreadsheets.values.append({
            spreadsheetId: sheetId,
            range: "A:J",
            valueInputOption: "USER_ENTERED",
            requestBody: {
              values: [row],
            },
          });

          logger.info("Lead salvato su Google Sheet", {nome, email});
        } catch (sheetErr) {
          logger.error("Errore salvataggio su Google Sheet", {
            message: sheetErr.message,
            stack: sheetErr.stack,
          });
        }

        // Restituisci sempre 200
        res.status(200).json({
          success: true,
          message: "Grazie! Il team ti contatterà entro 24 ore.",
        });
      } catch (outerErr) {
        logger.error("Errore critico nella funzione lead", {
          message: outerErr.message,
          stack: outerErr.stack,
        });
        res.status(200).json({
          success: true,
          message: "Grazie! Il team ti contatterà entro 24 ore.",
        });
      }
    },
);

// --- KEEP ALIVE FUNCTION ---
exports.keepAlive = onSchedule(
    {
      schedule: "*/5 * * * *",
      region: "europe-west1",
      timeoutSeconds: 30,
    },
    async () => {
      try {
        const chatUrl = process.env.CHAT_FUNCTION_URL;
        if (!chatUrl) {
          logger.warn("CHAT_FUNCTION_URL non configurata, skip keep-alive");
          return;
        }

        const response = await fetch(chatUrl, {method: "GET"});

        logger.info("Keep-alive eseguito", {status: response.status});
      } catch (err) {
        logger.warn("Keep-alive fallito", err);
      }
    },
);
