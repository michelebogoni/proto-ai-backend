# Spark — Backend

Backend Firebase per Spark, il chatbot commerciale di [Nexo](https://gonexo.site).

## Architettura

Tre Cloud Functions Gen 2 (Cloud Run) in `functions/`:

- **chat** (HTTP POST + SSE) — Chatbot con streaming via Anthropic Claude
- **lead** (HTTP POST) — Salvataggio lead su Google Sheets
- **keepAlive** (Scheduled) — Ping ogni 5 minuti per evitare cold start

## Setup

### 1. Configurare i secrets su Firebase

```bash
# API key Anthropic
firebase functions:secrets:set ANTHROPIC_API_KEY

# Credenziali Google Service Account (JSON su una riga)
firebase functions:secrets:set GOOGLE_SERVICE_ACCOUNT_JSON

# ID del Google Sheet
firebase functions:secrets:set GOOGLE_SHEET_ID
```

### 2. Google Sheets — Preparazione

1. Crea un Google Sheet con le colonne: Data, Nome, Email, Telefono, Descrizione Progetto, Conversazione
2. Crea un Service Account nella Google Cloud Console
3. Abilita l'API Google Sheets nel progetto GCP
4. Condividi lo sheet con l'email del Service Account (permesso Editor)
5. Scarica il JSON delle credenziali e usalo per il secret `GOOGLE_SERVICE_ACCOUNT_JSON`

### 3. Test in locale

```bash
cd functions
npm install

# Avvia l'emulatore Firebase
firebase emulators:start --only functions
```

Per testare con i secrets in locale, crea un file `functions/.secret.local` con:
```
ANTHROPIC_API_KEY=sk-ant-...
GOOGLE_SERVICE_ACCOUNT_JSON={"type":"service_account",...}
GOOGLE_SHEET_ID=1aBcDeFg...
```

### 4. Deploy

```bash
firebase deploy --only functions
```

### 5. Test in produzione

```bash
# Health check
curl https://europe-west1-<PROJECT_ID>.cloudfunctions.net/chat

# Chat
curl -X POST https://europe-west1-<PROJECT_ID>.cloudfunctions.net/chat \
  -H "Content-Type: application/json" \
  -d '{"sessionId":"test-1","message":"Ciao","history":[]}'

# Lead
curl -X POST https://europe-west1-<PROJECT_ID>.cloudfunctions.net/lead \
  -H "Content-Type: application/json" \
  -d '{"nome":"Test","email":"test@test.com","telefono":"123","descrizioneProgetto":"Test","conversazione":[]}'
```
