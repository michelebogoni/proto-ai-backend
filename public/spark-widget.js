(function() {
  // --- Configuration ---
  var API_BASE = "https://europe-west1-proto-ai-8f205.cloudfunctions.net";
  var WELCOME_MSG = "Ciao! Sono Spark. Raccontami cosa vorresti realizzare e ti dico subito se \u00e8 fattibile e quanto potrebbe costare.";
  var LEAD_START = "|||LEAD_DATA|||";
  var LEAD_END = "|||END_LEAD|||";
  var SPARK_AVATAR = "https://proto-ai-8f205.web.app/spark-ai-72x72.png";
  var STORAGE_KEY = "spark_session";
  var SESSION_TTL_MS = 30 * 60 * 1000; // 30 minutes

  // --- Inject CSS ---
  var style = document.createElement("style");
  style.textContent = "\
  .proto-ai-widget {\
    width: 100%;\
    height: 100%;\
    min-height: 500px;\
    display: flex;\
    flex-direction: column;\
    background: #F8FAFC;\
    font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;\
    color: #111827;\
    border-radius: 20px;\
    overflow: hidden;\
    box-sizing: border-box;\
    border: 1px solid #E2E8F0;\
  }\
  .proto-ai-widget *, .proto-ai-widget *::before, .proto-ai-widget *::after {\
    box-sizing: border-box;\
  }\
  .proto-ai-messages {\
    flex: 1;\
    overflow-y: auto;\
    padding: 24px;\
    display: flex;\
    flex-direction: column;\
    gap: 16px;\
    scrollbar-width: none;\
  }\
  .proto-ai-messages::-webkit-scrollbar {\
    display: none;\
  }\
  .proto-ai-msg {\
    display: flex;\
    gap: 10px;\
    max-width: 85%;\
    animation: proto-ai-fade 0.3s ease;\
  }\
  @keyframes proto-ai-fade {\
    from { opacity: 0; transform: translateY(8px); }\
    to { opacity: 1; transform: translateY(0); }\
  }\
  .proto-ai-msg--user {\
    align-self: flex-end;\
    flex-direction: row-reverse;\
  }\
  .proto-ai-msg--assistant {\
    align-self: flex-start;\
  }\
  .proto-ai-avatar {\
    width: 36px;\
    height: 36px;\
    border-radius: 50%;\
    flex-shrink: 0;\
    margin-top: 2px;\
    overflow: hidden;\
    background: #F1F5F9;\
    display: flex;\
    align-items: center;\
    justify-content: center;\
  }\
  .proto-ai-avatar img {\
    width: 36px !important;\
    height: 36px !important;\
    max-width: 36px !important;\
    min-width: 36px !important;\
    object-fit: cover !important;\
    border-radius: 50% !important;\
    display: block !important;\
    margin: 0 !important;\
    padding: 0 !important;\
  }\
  .proto-ai-msg--user .proto-ai-avatar {\
    display: none;\
  }\
  @media (max-width: 768px) {\
    .proto-ai-avatar {\
      display: none !important;\
    }\
  }\
  .proto-ai-msg-content {\
    display: flex;\
    flex-direction: column;\
  }\
  .proto-ai-msg-label {\
    font-family: 'DM Mono', 'SF Mono', monospace;\
    font-size: 11px;\
    font-weight: 500;\
    letter-spacing: 1px;\
    margin-bottom: 4px;\
    color: #3B82F6;\
  }\
  .proto-ai-msg--user .proto-ai-msg-label {\
    display: none;\
  }\
  .proto-ai-bubble {\
    padding: 14px 18px;\
    border-radius: 16px;\
    line-height: 1.5;\
    font-size: 14px;\
    white-space: pre-wrap;\
    word-break: break-word;\
  }\
  .proto-ai-msg--assistant .proto-ai-bubble {\
    background: #FFFFFF;\
    border: 1px solid #E2E8F0;\
    color: #334155;\
    border-bottom-left-radius: 4px;\
  }\
  .proto-ai-msg--user .proto-ai-bubble {\
    background: #3B82F6;\
    color: #FFFFFF;\
    border-bottom-right-radius: 4px;\
  }\
  .proto-ai-input-bar {\
    display: flex;\
    gap: 10px;\
    padding: 16px 20px;\
    background: #FFFFFF;\
    border-top: 1px solid #E2E8F0;\
  }\
  .proto-ai-input {\
    flex: 1;\
    background: #FFFFFF;\
    border: 1.5px solid #E2E8F0;\
    border-radius: 12px;\
    padding: 14px 16px;\
    color: #0A0F1C;\
    font-size: 16px;\
    font-family: inherit;\
    outline: none;\
    resize: none;\
    min-height: 48px;\
    max-height: 120px;\
    line-height: 1.4;\
    transition: all 0.2s;\
  }\
  .proto-ai-input::placeholder {\
    color: #CBD5E1;\
  }\
  .proto-ai-input:focus {\
    border-color: #3B82F6;\
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);\
  }\
  .proto-ai-send {\
    width: 48px;\
    height: 48px;\
    border-radius: 12px;\
    background: #3B82F6;\
    border: none;\
    color: #FFFFFF;\
    font-size: 18px;\
    cursor: pointer;\
    display: flex;\
    align-items: center;\
    justify-content: center;\
    flex-shrink: 0;\
    transition: all 0.2s ease;\
    box-shadow: 0 1px 3px rgba(59, 130, 246, 0.3);\
  }\
  .proto-ai-send:hover:not(:disabled) {\
    background: #2563EB;\
    transform: translateY(-1px);\
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.35);\
  }\
  .proto-ai-send:disabled {\
    opacity: 0.4;\
    cursor: not-allowed;\
  }\
  .proto-ai-typing {\
    display: flex;\
    gap: 5px;\
    padding: 4px 0;\
  }\
  .proto-ai-typing span {\
    width: 8px;\
    height: 8px;\
    background: #94A3B8;\
    border-radius: 50%;\
    animation: proto-ai-bounce 1.2s infinite ease-in-out;\
  }\
  .proto-ai-typing span:nth-child(2) {\
    animation-delay: 0.2s;\
  }\
  .proto-ai-typing span:nth-child(3) {\
    animation-delay: 0.4s;\
  }\
  @keyframes proto-ai-bounce {\
    0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }\
    30% { transform: translateY(-6px); opacity: 1; }\
  }\
  #spark-fab {\
    transition: transform 0.5s ease !important;\
  }";
  document.head.appendChild(style);

  // --- localStorage helpers ---
  function saveSession(data) {
    try {
      data.updatedAt = Date.now();
      localStorage.setItem(STORAGE_KEY, JSON.stringify(data));
    } catch (e) { /* quota exceeded or private mode */ }
  }

  function loadSession() {
    try {
      var raw = localStorage.getItem(STORAGE_KEY);
      if (!raw) return null;
      return JSON.parse(raw);
    } catch (e) { return null; }
  }

  function clearSession() {
    try { localStorage.removeItem(STORAGE_KEY); } catch (e) { /* ignore */ }
  }

  function sendSummaryForSession(session) {
    if (!session || !session.history || session.history.length < 2) return;
    if (session.leadSent || session.summarySent) return;
    var payload = JSON.stringify({ conversazione: session.history });
    if (navigator.sendBeacon) {
      navigator.sendBeacon(
        API_BASE + "/summary",
        new Blob([payload], { type: "text/plain" })
      );
    }
  }

  // --- Check for expired session before initializing ---
  var savedSession = loadSession();
  if (savedSession && savedSession.updatedAt) {
    var age = Date.now() - savedSession.updatedAt;
    if (age > SESSION_TTL_MS) {
      // Session expired: send summary if needed, then clear
      sendSummaryForSession(savedSession);
      clearSession();
      savedSession = null;
    }
  }

  // --- Find container and inject HTML ---
  var container = document.getElementById("spark-widget-container");
  if (!container) {
    container = document.querySelector(".proto-ai-widget");
    if (container) {
      initWidget(
        container.querySelector(".proto-ai-messages") || document.getElementById("protoAiMessages"),
        container.querySelector(".proto-ai-input") || document.getElementById("protoAiInput"),
        container.querySelector(".proto-ai-send") || document.getElementById("protoAiSend")
      );
      return;
    }
    console.error("Spark Widget: container #spark-widget-container not found");
    return;
  }

  container.innerHTML = '\
    <div class="proto-ai-widget">\
      <div class="proto-ai-messages"></div>\
      <div class="proto-ai-input-bar">\
        <textarea class="proto-ai-input" placeholder="Scrivi un messaggio..." rows="1"></textarea>\
        <button class="proto-ai-send" aria-label="Invia">&#10148;</button>\
      </div>\
    </div>';

  var widget = container.querySelector(".proto-ai-widget");
  initWidget(
    widget.querySelector(".proto-ai-messages"),
    widget.querySelector(".proto-ai-input"),
    widget.querySelector(".proto-ai-send")
  );

  function initWidget(messagesEl, inputEl, sendBtn) {
    // --- Restore or create session ---
    var restoredSession = savedSession;
    var sessionId;
    var history;
    var leadSent;
    var isStreaming = false;

    if (restoredSession) {
      sessionId = restoredSession.sessionId;
      history = restoredSession.history || [];
      leadSent = restoredSession.leadSent || false;
    } else {
      sessionId = crypto.randomUUID ? crypto.randomUUID() : ("s-" + Math.random().toString(36).slice(2) + Date.now().toString(36));
      history = [];
      leadSent = false;
    }

    function persistState() {
      saveSession({
        sessionId: sessionId,
        history: history,
        leadSent: leadSent
      });
    }

    function addMessage(role, text) {
      var wrapper = document.createElement("div");
      wrapper.className = "proto-ai-msg proto-ai-msg--" + role;

      var avatar = document.createElement("div");
      avatar.className = "proto-ai-avatar";
      if (role === "assistant") {
        var img = document.createElement("img");
        img.src = SPARK_AVATAR;
        img.alt = "Spark";
        avatar.appendChild(img);
      }

      var content = document.createElement("div");
      content.className = "proto-ai-msg-content";

      var label = document.createElement("div");
      label.className = "proto-ai-msg-label";
      label.textContent = role === "assistant" ? "SPARK" : "";

      var bubble = document.createElement("div");
      bubble.className = "proto-ai-bubble";
      bubble.textContent = text;

      content.appendChild(label);
      content.appendChild(bubble);
      wrapper.appendChild(avatar);
      wrapper.appendChild(content);
      messagesEl.appendChild(wrapper);
      scrollToBottom();
      return bubble;
    }

    function scrollToBottom() {
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    inputEl.addEventListener("input", function() {
      inputEl.style.height = "auto";
      inputEl.style.height = Math.min(inputEl.scrollHeight, 120) + "px";
    });

    inputEl.addEventListener("keydown", function(e) {
      if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });

    sendBtn.addEventListener("click", function() {
      sendMessage();
    });

    function sendMessage() {
      var text = inputEl.value.trim();
      if (!text || isStreaming) return;

      addMessage("user", text);
      history.push({ role: "user", content: text });
      persistState();

      inputEl.value = "";
      inputEl.style.height = "auto";
      setStreaming(true);

      var assistantBubble = addMessage("assistant", "");
      assistantBubble.innerHTML = '<div class="proto-ai-typing"><span></span><span></span><span></span></div>';
      var typingRemoved = false;
      var fullResponse = "";

      fetch(API_BASE + "/chat", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ sessionId: sessionId, message: text, history: history.slice(0, -1) })
      }).then(function(response) {
        if (!response.ok) throw new Error("HTTP " + response.status);

        var reader = response.body.getReader();
        var decoder = new TextDecoder();
        var buffer = "";

        function read() {
          return reader.read().then(function(result) {
            if (result.done) {
              finishStream(fullResponse);
              return;
            }

            buffer += decoder.decode(result.value, { stream: true });
            var lines = buffer.split("\n");
            buffer = lines.pop() || "";

            for (var i = 0; i < lines.length; i++) {
              var line = lines[i].trim();
              if (!line.startsWith("data: ")) continue;

              try {
                var data = JSON.parse(line.slice(6));
                if (data.type === "text") {
                  if (!typingRemoved) {
                    assistantBubble.innerHTML = "";
                    typingRemoved = true;
                  }
                  fullResponse += data.content;
                  var displayText = fullResponse;
                  var markerIdx = displayText.indexOf(LEAD_START);
                  if (markerIdx !== -1) {
                    displayText = displayText.substring(0, markerIdx);
                  } else {
                    for (var k = Math.min(LEAD_START.length - 1, displayText.length); k > 0; k--) {
                      if (displayText.endsWith(LEAD_START.substring(0, k))) {
                        displayText = displayText.substring(0, displayText.length - k);
                        break;
                      }
                    }
                  }
                  assistantBubble.textContent = displayText.trimEnd();
                  scrollToBottom();
                } else if (data.type === "done") {
                  finishStream(fullResponse);
                  return;
                } else if (data.type === "error") {
                  assistantBubble.textContent = data.content || "Si \u00e8 verificato un errore.";
                  setStreaming(false);
                  return;
                }
              } catch (e) { /* skip malformed SSE */ }
            }

            return read();
          });
        }

        return read();
      }).catch(function() {
        assistantBubble.textContent = "Si \u00e8 verificato un errore di connessione. Riprova tra qualche istante.";
        setStreaming(false);
      });
    }

    function finishStream(responseText) {
      var cleanText = responseText;
      var startIdx = responseText.indexOf(LEAD_START);
      var endIdx = responseText.indexOf(LEAD_END);

      if (startIdx !== -1 && endIdx !== -1) {
        var jsonStr = responseText.substring(startIdx + LEAD_START.length, endIdx);
        cleanText = responseText.substring(0, startIdx).trimEnd();

        if (!leadSent) {
          try {
            var leadData = JSON.parse(jsonStr);
            if (leadData.telefono && leadData.descrizioneProgetto) {
              leadSent = true;
              persistState();
              fetch(API_BASE + "/lead", {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({
                  nome: leadData.nome || "",
                  email: leadData.email || "",
                  telefono: leadData.telefono,
                  nomeAzienda: leadData.nomeAzienda || "",
                  descrizioneProgetto: leadData.descrizioneProgetto,
                  preventivoIndicato: leadData.preventivoIndicato || "",
                  probabilitaChiusura: leadData.probabilitaChiusura || 0,
                  noteQualifica: leadData.noteQualifica || "",
                  conversazione: history.concat([{ role: "assistant", content: cleanText }])
                })
              }).then(function() {
                window.dataLayer = window.dataLayer || [];
                window.dataLayer.push({
                  event: "spark_lead",
                  lead_nome: leadData.nome || "",
                  lead_telefono: leadData.telefono || "",
                  lead_azienda: leadData.nomeAzienda || ""
                });
              }).catch(function() { /* silenzioso */ });
            }
          } catch (e) { /* JSON non valido, ignora */ }
        }
      }

      if (cleanText) {
        history.push({ role: "assistant", content: cleanText });
      }

      persistState();

      var bubbles = messagesEl.querySelectorAll(".proto-ai-msg--assistant .proto-ai-bubble");
      if (bubbles.length > 0) {
        bubbles[bubbles.length - 1].textContent = cleanText;
      }

      setStreaming(false);
    }

    function setStreaming(val) {
      isStreaming = val;
      sendBtn.disabled = val;
      inputEl.disabled = val;
      if (!val) inputEl.focus();
    }

    // --- Render: restore conversation or show welcome ---
    if (restoredSession && history.length > 0) {
      // Re-render all messages from history
      addMessage("assistant", WELCOME_MSG);
      for (var i = 0; i < history.length; i++) {
        addMessage(history[i].role, history[i].content);
      }
    } else {
      addMessage("assistant", WELCOME_MSG);
    }

    // --- Persist state on page leave (no summary) ---
    function onPageLeave() {
      persistState();
    }

    document.addEventListener("visibilitychange", function() {
      if (document.visibilityState === "hidden") onPageLeave();
    });

    window.addEventListener("beforeunload", onPageLeave);
  }

  // --- Hide FAB when #cta is visible ---
  function setupFabObserver() {
    var ctaEl = document.getElementById("cta");
    var fabEl = document.getElementById("spark-fab");

    if (!ctaEl || !fabEl) return;

    var tooltipEl = document.getElementById("spark-tooltip");
    var ctaIsVisible = false;

    var observer = new IntersectionObserver(function(entries) {
      ctaIsVisible = entries[0].isIntersecting;
      fabEl.style.transform = ctaIsVisible ? "translateX(120px)" : "translateX(0)";
      if (ctaIsVisible && tooltipEl) {
        tooltipEl.classList.remove("show");
      }
    }, { threshold: 0.1 });

    observer.observe(ctaEl);

    // Block tooltip from appearing while #cta is visible
    if (tooltipEl) {
      var tooltipWatcher = new MutationObserver(function() {
        if (ctaIsVisible && tooltipEl.classList.contains("show")) {
          tooltipEl.classList.remove("show");
        }
      });
      tooltipWatcher.observe(tooltipEl, { attributes: true, attributeFilter: ["class"] });
    }
  }

  // Wait for full DOM before looking for #cta and #spark-fab
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", setupFabObserver);
  } else {
    setupFabObserver();
  }
})();
