const {setGlobalOptions} = require("firebase-functions/v2");
const {onRequest} = require("firebase-functions/v2/https");
const {onSchedule} = require("firebase-functions/v2/scheduler");
const logger = require("firebase-functions/logger");
const Anthropic = require("@anthropic-ai/sdk");
const {google} = require("googleapis");
const fs = require("fs");
const path = require("path");

setGlobalOptions({maxInstances: 10, region: "europe-west1"});

// --- SHARED HELPERS ---

/**
 * Returns authenticated Google Sheets client.
 */
function getSheetsClient() {
  const raw = process.env.GOOGLE_SERVICE_ACCOUNT_JSON || "";
  const credentials = JSON.parse(raw);
  const auth = new google.auth.GoogleAuth({
    credentials,
    scopes: ["https://www.googleapis.com/auth/spreadsheets"],
  });
  return google.sheets({version: "v4", auth});
}

/**
 * Finds row number (1-indexed) by sessionId in column K.
 * Returns null if not found.
 */
async function findRowBySessionId(sheets, sheetId, sessionId) {
  const resp = await sheets.spreadsheets.values.get({
    spreadsheetId: sheetId,
    range: "K:K",
  });
  const rows = resp.data.values || [];
  for (let i = 0; i < rows.length; i++) {
    if (rows[i][0] === sessionId) {
      return i + 1; // 1-indexed
    }
  }
  return null;
}

/**
 * Formats conversation array as "Utente: ... | Spark: ..."
 */
function formatTranscript(conversazione) {
  if (!Array.isArray(conversazione)) return "";
  return conversazione
      .map((msg) => {
        const ruolo = msg.role === "user" ? "Utente" : "Spark";
        const testo = (msg.content || "").replace(/\n+/g, " ").trim();
        return `${ruolo}: ${testo}`;
      })
      .join(" | ");
}

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
          model: "claude-sonnet-4-6",
          max_tokens: 1500,
          system: getSystemPrompt(),
          messages,
        });

        let fullResponse = "";

        stream.on("text", (text) => {
          fullResponse += text;
          const payload = JSON.stringify({type: "text", content: text});
          res.write(`data: ${payload}\n\n`);
        });

        stream.on("end", () => {
          // Log della risposta (senza LEAD_DATA)
          let logText = fullResponse;
          const leadIdx = logText.indexOf("|||LEAD_DATA|||");
          if (leadIdx !== -1) {
            logText = logText.substring(0, leadIdx).trimEnd();
          }
          logger.info("Chat response", {
            sessionId,
            assistantMessage: logText,
          });

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

        const {sessionId, nome, email, telefono, nomeAzienda,
          descrizioneProgetto, preventivoIndicato, probabilitaChiusura,
          noteQualifica, conversazione} = req.body || {};

        if (!telefono) {
          res.status(400).json({error: "Il numero di telefono è obbligatorio"});
          return;
        }

        logger.info("Nuovo lead ricevuto", {
          sessionId: sessionId || "N/A",
          telefono,
          nome: nome || "N/A",
          email: email || "N/A",
          nomeAzienda: nomeAzienda || "N/A",
          probabilitaChiusura: probabilitaChiusura || 0,
        });

        try {
          const sheets = getSheetsClient();
          const sheetId = process.env.GOOGLE_SHEET_ID;

          // Formatta la conversazione come testo leggibile
          let conversazioneText = "";
          if (Array.isArray(conversazione)) {
            conversazioneText = formatTranscript(conversazione);
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
            sessionId || "",
          ];

          // Upsert: cerca riga esistente per sessionId
          let rowNum = null;
          if (sessionId) {
            rowNum = await findRowBySessionId(sheets, sheetId, sessionId);
          }

          if (rowNum) {
            // Aggiorna riga esistente (A:K)
            await sheets.spreadsheets.values.update({
              spreadsheetId: sheetId,
              range: `A${rowNum}:K${rowNum}`,
              valueInputOption: "USER_ENTERED",
              requestBody: {values: [row]},
            });
            logger.info("Lead aggiornato su riga esistente", {
              rowNum, sessionId,
            });
          } else {
            await sheets.spreadsheets.values.append({
              spreadsheetId: sheetId,
              range: "A:K",
              valueInputOption: "USER_ENTERED",
              requestBody: {values: [row]},
            });
            logger.info("Lead salvato su nuova riga", {nome, email});
          }
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

// --- SUMMARY FUNCTION (conversazioni senza lead) ---
exports.summary = onRequest(
    {
      memory: "512MiB",
      timeoutSeconds: 60,
      secrets: [
        "ANTHROPIC_API_KEY",
        "GOOGLE_SERVICE_ACCOUNT_JSON",
        "GOOGLE_SHEET_ID",
      ],
    },
    async (req, res) => {
      try {
        if (handleCors(req, res)) return;

        if (req.method !== "POST") {
          res.status(405).json({error: "Metodo non consentito"});
          return;
        }

        // sendBeacon invia text/plain per evitare preflight CORS
        let body = req.body;
        if (typeof body === "string") {
          try {
            body = JSON.parse(body);
          } catch (e) {
            res.status(400).json({error: "Corpo non valido"});
            return;
          }
        }

        const {sessionId, conversazione} = body || {};
        if (!Array.isArray(conversazione) || conversazione.length < 2) {
          res.status(200).json({success: true});
          return;
        }

        logger.info("Richiesta summary conversazione", {
          sessionId: sessionId || "N/A",
          messageCount: conversazione.length,
        });

        // Formatta conversazione per Claude
        const conversazionePerAnalisi = conversazione
            .map((m) => {
              const ruolo = m.role === "user" ? "Utente" : "Spark";
              return `${ruolo}: ${m.content}`;
            })
            .join("\n");

        // Chiama Claude Haiku per generare il riassunto strutturato
        const anthropic = new Anthropic({
          apiKey: process.env.ANTHROPIC_API_KEY,
        });

        const summaryResponse = await anthropic.messages.create({
          model: "claude-haiku-4-5-20251001",
          max_tokens: 500,
          messages: [{
            role: "user",
            content: `Analizza questa conversazione tra un utente e Spark \
(chatbot di vendita per Nexo, azienda di sviluppo software su misura). \
L'utente NON ha lasciato i dati di contatto.

Rispondi SOLO con un JSON valido (nessun altro testo, nessun markdown) \
con questi campi:
- "argomento": cosa ha chiesto o voleva l'utente (1-2 frasi concise)
- "dubbi": dubbi, perplessità o obiezioni espresse dall'utente \
(1 frase, oppure "Nessuno emerso")
- "reazionePreventivo": "nessuna" se non si è arrivati al preventivo, \
"positiva" se ha reagito bene al prezzo, "resistenza" se ha mostrato \
dubbi o obiezioni sul prezzo
- "resistenzaContatto": "non richiesto" se non si è arrivati alla \
fase contatto, "rifiutato" se ha rifiutato esplicitamente, "evitato" \
se ha ignorato la richiesta
- "noteGenerali": breve analisi di come è andata e perché non si è \
convertita in lead (1-2 frasi)

Conversazione:
${conversazionePerAnalisi}`,
          }],
        });

        let summary;
        try {
          let rawText = summaryResponse.content[0].text.trim();
          // Strip markdown code blocks if present
          rawText = rawText
              .replace(/^```(?:json)?\s*/i, "")
              .replace(/\s*```\s*$/, "");
          summary = JSON.parse(rawText);
        } catch (e) {
          logger.warn("Summary JSON non valido, uso testo grezzo", {
            text: summaryResponse.content[0].text,
          });
          summary = {
            argomento: "Non analizzabile",
            dubbi: "",
            reazionePreventivo: "nessuna",
            resistenzaContatto: "non richiesto",
            noteGenerali: summaryResponse.content[0].text
                .substring(0, 200),
          };
        }

        // Formatta transcript per il foglio
        const transcriptText = formatTranscript(conversazione);

        // Componi nota qualifica con tutti i dettagli dell'analisi
        const noteQualifica = [
          `Reazione preventivo: ${summary.reazionePreventivo || "nessuna"}`,
          `Resistenza contatto: ` +
            `${summary.resistenzaContatto || "non richiesto"}`,
          `Dubbi: ${summary.dubbi || "Nessuno emerso"}`,
          summary.noteGenerali || "",
        ].filter(Boolean).join(" — ");

        // Salva su Google Sheets con upsert
        const sheets = getSheetsClient();
        const sheetId = process.env.GOOGLE_SHEET_ID;

        let rowNum = null;
        if (sessionId) {
          rowNum = await findRowBySessionId(sheets, sheetId, sessionId);
        }

        if (rowNum) {
          // Aggiorna solo B, C, I, J (non tocca D-H per non
          // sovrascrivere dati lead)
          await sheets.spreadsheets.values.batchUpdate({
            spreadsheetId: sheetId,
            requestBody: {
              valueInputOption: "USER_ENTERED",
              data: [
                {range: `B${rowNum}`, values: [[noteQualifica]]},
                {range: `C${rowNum}`, values: [["⚪ No lead"]]},
                {range: `I${rowNum}`, values: [[summary.argomento || ""]]},
                {range: `J${rowNum}`, values: [[transcriptText]]},
              ],
            },
          });
          logger.info("Summary aggiornato su riga esistente", {
            rowNum, sessionId,
          });
        } else {
          const row = [
            new Date().toISOString(), // A: Data
            noteQualifica, // B: Note Qualifica
            "⚪ No lead", // C: Colore Scoring
            "", // D: Nome
            "", // E: Telefono
            "", // F: Email
            "", // G: Nome Azienda
            "", // H: Preventivo Indicato
            summary.argomento || "", // I: Descrizione Progetto
            transcriptText, // J: Conversazione
            sessionId || "", // K: SessionId
          ];
          await sheets.spreadsheets.values.append({
            spreadsheetId: sheetId,
            range: "A:K",
            valueInputOption: "USER_ENTERED",
            requestBody: {values: [row]},
          });
        }

        logger.info("Summary conversazione salvato su Google Sheet");
        res.status(200).json({success: true});
      } catch (err) {
        logger.error("Errore nella funzione summary", {
          message: err.message,
          stack: err.stack,
        });
        res.status(200).json({success: true});
      }
    },
);

// --- TRACK FUNCTION (salva ogni conversazione su page leave) ---
exports.track = onRequest(
    {
      memory: "256MiB",
      timeoutSeconds: 30,
      secrets: [
        "ANTHROPIC_API_KEY",
        "GOOGLE_SERVICE_ACCOUNT_JSON",
        "GOOGLE_SHEET_ID",
      ],
    },
    async (req, res) => {
      try {
        if (handleCors(req, res)) return;

        if (req.method !== "POST") {
          res.status(200).end();
          return;
        }

        // sendBeacon invia text/plain
        let body = req.body;
        if (typeof body === "string") {
          try {
            body = JSON.parse(body);
          } catch (e) {
            res.status(200).end();
            return;
          }
        }

        const {sessionId, history, leadSent} = body || {};

        // Ignora se nessun messaggio utente
        if (!sessionId || !Array.isArray(history)) {
          res.status(200).end();
          return;
        }
        const hasUserMsg = history.some((m) => m.role === "user");
        if (!hasUserMsg) {
          res.status(200).end();
          return;
        }

        const sheets = getSheetsClient();
        const sheetId = process.env.GOOGLE_SHEET_ID;
        const conversazioneText = formatTranscript(history);
        const rowNum = await findRowBySessionId(sheets, sheetId, sessionId);

        if (rowNum) {
          // Aggiorna solo timestamp e conversazione
          await sheets.spreadsheets.values.batchUpdate({
            spreadsheetId: sheetId,
            requestBody: {
              valueInputOption: "USER_ENTERED",
              data: [
                {range: `A${rowNum}`, values: [[new Date().toISOString()]]},
                {range: `J${rowNum}`, values: [[conversazioneText]]},
              ],
            },
          });
        } else {
          // Crea nuova riga
          const label = leadSent ? "🟡 Lead inviato" : "⚪ No lead";
          const row = [
            new Date().toISOString(), // A: Data
            "", // B: Note Qualifica
            label, // C: Colore Scoring
            "", // D: Nome
            "", // E: Telefono
            "", // F: Email
            "", // G: Nome Azienda
            "", // H: Preventivo Indicato
            "", // I: Descrizione Progetto
            conversazioneText, // J: Conversazione
            sessionId, // K: SessionId
          ];
          await sheets.spreadsheets.values.append({
            spreadsheetId: sheetId,
            range: "A:K",
            valueInputOption: "USER_ENTERED",
            requestBody: {values: [row]},
          });
        }

        // Rispondi subito (sendBeacon è fire-and-forget)
        res.status(200).end();

        // Analisi AI asincrona (dopo aver risposto)
        // Skip se lead già inviato (verrà analizzato da /lead)
        if (!leadSent && history.length >= 2) {
          try {
            const anthropic = new Anthropic({
              apiKey: process.env.ANTHROPIC_API_KEY,
            });

            const conversazionePerAnalisi = history
                .map((m) => {
                  const ruolo = m.role === "user" ? "Utente" : "Spark";
                  return `${ruolo}: ${m.content}`;
                })
                .join("\n");

            const aiResp = await anthropic.messages.create({
              model: "claude-haiku-4-5-20251001",
              max_tokens: 300,
              messages: [{
                role: "user",
                content: `Analizza questa conversazione tra un utente e Spark \
(chatbot di vendita per Nexo, azienda di sviluppo software). \
Rispondi SOLO con un JSON valido con questi campi:
- "argomento": cosa ha chiesto l'utente (1 frase concisa)
- "note": breve analisi di come è andata (1-2 frasi)

Conversazione:
${conversazionePerAnalisi}`,
              }],
            });

            let rawText = aiResp.content[0].text.trim();
            rawText = rawText
                .replace(/^```(?:json)?\s*/i, "")
                .replace(/\s*```\s*$/, "");
            const analysis = JSON.parse(rawText);

            // Trova la riga corrente (potrebbe essere cambiata)
            const updRow =
              await findRowBySessionId(sheets, sheetId, sessionId);
            if (updRow) {
              await sheets.spreadsheets.values.batchUpdate({
                spreadsheetId: sheetId,
                requestBody: {
                  valueInputOption: "USER_ENTERED",
                  data: [
                    {range: `B${updRow}`,
                      values: [[analysis.note || ""]]},
                    {range: `I${updRow}`,
                      values: [[analysis.argomento || ""]]},
                  ],
                },
              });
            }
            logger.info("Track: analisi AI completata", {sessionId});
          } catch (aiErr) {
            logger.warn("Track: analisi AI fallita", {
              message: aiErr.message,
            });
          }
        }

        logger.info("Track salvato", {sessionId, msgCount: history.length});
      } catch (err) {
        logger.error("Errore nella funzione track", {
          message: err.message,
          stack: err.stack,
        });
        res.status(200).end();
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
