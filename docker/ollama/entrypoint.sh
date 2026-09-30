#!/bin/sh
# Starts Ollama and prepares the shop model. Idempotent: the base model is
# pulled only when missing, and the shop model is recreated from the
# Modelfile at every start (existing layers are reused). If the pull or the
# create fails the server keeps running: the backend reports the assistant
# as unavailable and the rest of the shop is not affected.

MODELFILE=/opt/ollama/Modelfile
# Must match config('assistant.model') of the backend.
SHOP_MODEL="${ASSISTANT_MODEL:-casa-del-silicio}"

ollama serve &
SERVER_PID=$!

trap 'kill -TERM "$SERVER_PID" 2>/dev/null; wait "$SERVER_PID"; exit 0' TERM INT

echo "[ollama] Attendo che il server risponda"
until ollama list >/dev/null 2>&1; do
    if ! kill -0 "$SERVER_PID" 2>/dev/null; then
        echo "[ollama] Il server non è partito"
        exit 1
    fi
    sleep 1
done

BASE_MODEL=$(awk 'toupper($1) == "FROM" { print $2; exit }' "$MODELFILE")

if [ -z "$BASE_MODEL" ]; then
    echo "[ollama] Nessuna riga FROM in $MODELFILE: modello del negozio non creato"
elif ollama show "$BASE_MODEL" >/dev/null 2>&1; then
    echo "[ollama] Modello base $BASE_MODEL già presente"
    BASE_READY=1
else
    echo "[ollama] Scarico il modello base $BASE_MODEL (al primo avvio richiede qualche minuto)"
    # In background, so the TERM trap runs at once during a long download.
    ollama pull "$BASE_MODEL" &
    if wait $!; then
        BASE_READY=1
    else
        echo "[ollama] Download di $BASE_MODEL non riuscito: l'assistente resterà non disponibile"
    fi
fi

if [ "${BASE_READY:-0}" = 1 ]; then
    echo "[ollama] Creo il modello $SHOP_MODEL dal Modelfile"
    if ! ollama create "$SHOP_MODEL" -f "$MODELFILE"; then
        echo "[ollama] Creazione di $SHOP_MODEL non riuscita: l'assistente resterà non disponibile"
    fi
fi

echo "[ollama] Pronto"
wait "$SERVER_PID"
