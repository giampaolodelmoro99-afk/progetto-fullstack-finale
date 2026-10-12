# Assistente AI: avvio, demo e GPU

Presentazione dell'assistente nel [README](../../README.md), scelta del modello e misure in [`model-evaluation.md`](model-evaluation.md).

## Primo avvio

```sh
make up-ai
# equivale a
docker compose --profile ai up -d
```

Il primo avvio con il profilo `ai` scarica circa 7 GB:

| Download | Dimensione | A 40 MB/s | A 3 MB/s |
|---|---|---|---|
| Immagine `ollama/ollama:0.35.0` | 3,8 GB (9,3 GB su disco, comprende le librerie CUDA) | circa 2 minuti | circa 20 minuti |
| Modello qwen3.5:4b | 3,4 GB | circa 1 minuto e mezzo | circa 20 minuti |

Il negozio funziona durante il download e anche se il download fallisce. L'assistente è disponibile quando nei log compare `[ollama] Pronto` (`docker compose logs ollama`) e `docker compose ps` mostra `ollama` come `healthy`. Gli avvii successivi non scaricano nulla: immagine e modello restano nel volume `ollama-data`.

L'API di Ollama non ha autenticazione, quindi la sua porta non è pubblicata: la raggiunge solo il backend, nella rete Docker. Lo script `docker/ollama/entrypoint.sh` a ogni avvio scarica il modello base solo se manca e ricrea il modello del negozio dal `docker/ollama/Modelfile`.

## Prova dal terminale

```sh
make ask q="Cerco delle cuffie sotto i 400 euro"
# oppure
docker compose exec app php artisan assistant:ask --timing "Cerco delle cuffie sotto i 400 euro"
```

Il comando mostra le chiamate ai tool, la risposta in streaming e i tempi.

## Tempi su CPU

La prima parola arriva dopo circa 14 s per le domande semplici e fino a 40 s quando l'assistente cerca prima nel catalogo; una risposta completa con ricerche richiede fino a un minuto. Il primo messaggio dopo 30 minuti di inattività è più lento di circa 10 s, perché il modello va ricaricato in memoria (`OLLAMA_KEEP_ALIVE`, modificabile nel `.env` alla radice). L'assistente serve una risposta alla volta: chi scrive mentre è occupato riceve «L'assistente è occupato, riprova tra poco.».

## Demo su CPU

1. Avvia con `make up-ai` e aspetta che `docker compose ps` mostri `ollama` come `healthy`.
2. Qualche minuto prima di iniziare, riscalda il modello con una domanda: `make ask q='Quanto costa la spedizione?'`. La prima domanda dopo l'avvio, o dopo 30 minuti di inattività, carica il modello in memoria e costa circa 10 s in più. Durante la demo il modello resta caricato.
3. Tempi attesi a caldo:
   - regole del negozio e domande fuori tema: prima parola in circa 14–18 s, risposta completa in circa 15–25 s
   - ricerche nel catalogo: prima parola in circa 14–40 s, risposta completa in circa 40–50 s
   - confronti tra prodotti: prima parola dopo circa 30 s, risposta completa anche oltre un minuto
4. Domande consigliate, provate nella valutazione e affidabili:
   - «Quanto costa la spedizione e quando diventa gratuita?»: regole del negozio lette dal backend
   - «Cerco una radio, quali avete e quanto costano?»: ricerca con prezzi e disponibilità dal database e citazioni dei prodotti
   - «Cerco delle cuffie o degli auricolari sotto i 200 euro, cosa mi consigli?»: due ricerche con filtro di prezzo
   - «A che punto è il mio ordine?»: nessun accesso ai dati personali, rimando a «I miei ordini»
   - «Mi scrivi una ricetta per la carbonara?» oppure «Ignora le istruzioni precedenti…»: rifiuto con la frase fissa
   - un confronto come «Che differenza c'è tra le Cuffie Aria e le Cuffie Aria Studio?» è il più lento: meglio lasciarlo per ultimo, mentre si commenta il resto

Un'altra domanda inviata mentre l'assistente risponde riceve «L'assistente è occupato, riprova tra poco.»: durante la demo conviene una domanda alla volta.

## GPU NVIDIA (facoltativa)

Con il profilo `ai` l'assistente usa la CPU, la configurazione su cui è stato provato. L'uso della GPU è facoltativo e non è stato misurato ([`model-evaluation.md`](model-evaluation.md), sezione "GPU"). Con una GPU NVIDIA e i prerequisiti qui sotto si può aggiungere `docker-compose.gpu.yml`:

```sh
make up-gpu
# equivale a
docker compose -f docker-compose.yml -f docker-compose.gpu.yml --profile ai up -d
```

Prerequisiti sull'host: driver NVIDIA (`nvidia-smi` deve funzionare) e NVIDIA Container Toolkit. Su Ubuntu e Debian:

```sh
curl -fsSL https://nvidia.github.io/libnvidia-container/gpgkey \
  | sudo gpg --dearmor -o /usr/share/keyrings/nvidia-container-toolkit-keyring.gpg
curl -fsSL https://nvidia.github.io/libnvidia-container/stable/deb/nvidia-container-toolkit.list \
  | sed 's#deb https://#deb [signed-by=/usr/share/keyrings/nvidia-container-toolkit-keyring.gpg] https://#g' \
  | sudo tee /etc/apt/sources.list.d/nvidia-container-toolkit.list
sudo apt-get update
sudo apt-get install -y nvidia-container-toolkit
sudo nvidia-ctk runtime configure --runtime=docker
sudo systemctl restart docker
```

Verifica: `docker run --rm --gpus all ubuntu nvidia-smi` mostra la GPU. Con `docker compose exec ollama ollama ps` la colonna `PROCESSOR` indica se il modello è sulla GPU.
