# VerifyRadar

Daily collector for **Sodré Santoro** vehicle auction lots. Enriches each lot with **FIPE** prices and stores results in **DynamoDB** with TTL. Includes a **Streamlit** dashboard to rank the best deals (bid vs FIPE).

## Architecture

```
EventBridge (04:00 BRT) → Lambda Sodré collector
EventBridge (04:30 BRT) → Lambda Palácio collector
                              ↓
                    DynamoDB (TTL = auction end + 1 day)
                              ↓
GitHub Actions (05:00 BRT) → docs/data/lotes.json → Pages
                              ↓
                    Laravel app (`web/`) 05:30 BRT → e-mail digest
```

The collectors do **not** use Cursor or browser automation. Sodré reads the public Elasticsearch index; Palácio uses the site AJAX endpoints (`listar_lote` / `exibir_lote_m`).

The **product app** (cadastro, preferências, alertas) lives in `web/` — Laravel 13 + Livewire, marca **VerifyRadar** (grupo VerifyCar). Collectors Python/AWS remain unchanged. Pagamento fica fora do app (admin ativa a assinatura). WhatsApp Cloud API está preparado e desligado (`RADAR_WHATSAPP_ENABLED=false`).

## Project layout

```
leilao-radar/
  collector/          # Lambda handler, Sodré + DynamoDB clients
  shared/             # Pydantic models + FIPE client
  web/                # Laravel product: cadastro, preferências, alertas
  docs/               # GitHub Pages snapshot (catálogo estático)
  infra/template.yaml # AWS SAM stack
  scripts/            # LocalStack init helpers
  docker-compose.yml  # LocalStack for local DynamoDB
  Makefile
  requirements-dev.txt
```

## Requirements

- Python 3.12+
- Docker + Docker Compose (for local DynamoDB via LocalStack)
- AWS CLI + SAM CLI (for deploy)
- AWS credentials configured locally (only for production DynamoDB)

## Local setup

```bash
cd ~/Projects/leilao-radar
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements-dev.txt
cp .env.example .env
```

## Local DynamoDB (LocalStack)

Start LocalStack and create the table:

```bash
make local-up      # docker compose up + health check
make local-init    # creates leilao-radar-lotes + GSI + TTL
```

The `.env` file points boto3 to LocalStack:

```
DYNAMODB_ENDPOINT_URL=http://localhost:4566
AWS_ACCESS_KEY_ID=test
AWS_SECRET_ACCESS_KEY=test
```

Stop LocalStack:

```bash
make local-down
```

## Run collector locally

```bash
make local-collect
```

Or manually:

```bash
export PYTHONPATH=.
set -a && source .env && set +a
python -m collector.handler
```

This will:

1. Bootstrap Sodré Elasticsearch credentials from public HTML
2. Page through open vehicle lots (`auction_status=aberto`, `lot_status=andamento`)
3. Match each lot to FIPE (`exact`, `closest`, or `failed`)
4. Upsert into DynamoDB with TTL

## Run dashboard locally

```bash
make local-dashboard
```

Logs aparecem no **terminal** (conexão Dynamo, carga de lotes, filtros). No browser, o sidebar **Status** mostra tabela, endpoint, tempo de carga e botão **Recarregar dados**.

Para mais detalhe: `LOG_LEVEL=DEBUG make local-dashboard`

Or manually:

```bash
export PYTHONPATH=.
set -a && source .env && set +a
streamlit run dashboard/app.py
```

The dashboard shows:

- Ranking by discount vs FIPE
- Filters for FIPE match type and brand
- Estimated total cost (bid + 5% commission)
- Histogram and average discount by brand

## Deploy to AWS

Prereqs: AWS CLI + SAM CLI (`brew install awscli aws-sam-cli`), Docker running, and credentials configured (`aws configure` or SSO).

```bash
# 1) Build Lambda package (linux/arm64 via Docker)
make aws-build

# 2) Deploy stack leilao-radar in sa-east-1
make aws-deploy

# 3) Optional: run collectors now (instead of waiting for schedule)
make aws-invoke
make aws-invoke-palacio

# 4) Follow logs
make aws-logs
make aws-logs-palacio
```

Stack creates:

- DynamoDB table `leilao-radar-lotes` (on-demand, TTL, GSI `gsi_relevancia` + `gsi_desconto`)
- Lambda `leilao-radar-collector` (Sodré, 04:00 BRT)
- Lambda `leilao-radar-collector-palacio` (Palácio, 04:30 BRT)
- CloudWatch Logs with 14-day retention

Each lot stores `fonte` (`sodre` | `palacio`). Palácio IDs are namespaced as `palacio:{id}` to avoid PK collisions.
## GitHub Pages (static dashboard)

Public dashboard at GitHub Pages — no Streamlit server. Data is exported from DynamoDB to `docs/data/lotes.json` and deployed by GitHub Actions.

### Setup (once)

1. Push this repo to GitHub.
2. In the repo: **Settings → Pages → Build and deployment → Source: GitHub Actions**.
3. In **Settings → Secrets and variables → Actions**, add:
   - `AWS_ACCESS_KEY_ID`
   - `AWS_SECRET_ACCESS_KEY`
4. Run the workflow **Export and GitHub Pages** manually (Actions tab) or wait for the daily schedule (05:00 BRT).

### GitHub Pages snapshot

The JSON snapshot still deploys to `https://acmachado14.github.io/leilao-radar/data/lotes.json`. The product app lives on Oracle at **https://radar.verifycar.com.br**.

In Hostinger DNS for `verifycar.com.br`, `radar` must be **only** an A record to `168.75.108.171`. Do **not** keep a CNAME to `acmachado14.github.io` on the same name — CNAME + A together makes browsers keep hitting GitHub Pages.

Local export (uses `aws configure` credentials):

```bash
make export-docs
```

The default GitHub URL is `https://acmachado14.github.io/leilao-radar/`; production uses the custom domain above.

## Product app (Laravel)

Cadastro, preferências de alerta e digest diário por e-mail. Marca VerifyRadar, visual slate/emerald (grupo VerifyCar).

```bash
make web-setup
make web-serve          # http://localhost:8000
make web-sync           # importa docs/data/lotes.json (ou RADAR_LOTS_URL)
make web-alerts         # enfileira e-mails para assinantes trial/active
make web-test
```

- Trial de 7 dias no cadastro. Depois disso, ative em `/admin/assinantes` (e-mails em `APP_ADMIN_EMAILS`).
- Cron: `radar:sync-lots` 05:20 BRT e `radar:dispatch-alerts --skip-sync` 05:30 BRT (`web/routes/console.php`).
- WhatsApp: campo + opt-in no cadastro; envio só com `RADAR_WHATSAPP_ENABLED=true` e credenciais Meta Cloud API.

### Production (Oracle, mesmo host da VerifyCar)

GitHub Actions (`.github/workflows/deploy-web.yml`) faz rsync de `web/` para `/home/ubuntu/leilao-radar`, build Docker ARM64 na máquina e recarrega o nginx do host (`127.0.0.1:2103`). Secrets: `SSH_PRIVATE_KEY`, `SERVER_HOST`.

O `.env` de produção fica só no servidor (não vai no git). Snapshot de lotes continua no GitHub Pages (`RADAR_LOTS_URL` = `https://acmachado14.github.io/leilao-radar/data/lotes.json`). TLS (`radar.verifycar.com.br`) depois do DNS `radar` em `verifycar.com.br` apontar para o IP da Oracle.

From the `infra/` directory you can also run:

```bash
cd infra
sam build --use-container
sam deploy
```

## DynamoDB schema

| Field | Description |
|-------|-------------|
| `lote_id` | Primary key (Sodré lot id) |
| `lance_atual` | Current bid |
| `fipe_preco` | FIPE reference price |
| `desconto_pct` | `1 - lance_atual / fipe_preco` |
| `fipe_match` | `exact`, `closest`, or `failed` |
| `ttl` | Unix timestamp: lot end + 1 day |
| `gsi_pk` | Always `LIVE` for active ranking |

## FIPE matching

1. Primary: [tabelafipe.info](https://tabelafipe.info/api-tabela-fipe) `/match` endpoint
2. Fallback: [Parallelum FIPE API](https://parallelum.com.br/fipe/api/v1)
3. If no exact version match, stores `fipe_match=closest` with the nearest model/year

## Notes

- Sodré has no official public REST API for lots; this project uses the same Elasticsearch index as the website.
- `www.sodresantoro.com.br` may return 403 from some datacenter IPs; the collector uses `leilao.sodresantoro.com.br` bootstrap + Elastic Cloud instead.
- Elasticsearch API keys are **not** stored in git or SSM; they are refreshed on every run from the public HTML.
- Photos and non-vehicle categories are out of scope for v1.

## License

MIT
