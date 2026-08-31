# Leilão Radar

Daily collector for **Sodré Santoro** vehicle auction lots. Enriches each lot with **FIPE** prices and stores results in **DynamoDB** with TTL. Includes a **Streamlit** dashboard to rank the best deals (bid vs FIPE).

## Architecture

```
EventBridge (04:00 BRT) → Lambda collector
                              ↓
                    Sodré Elasticsearch (via public Nuxt bootstrap)
                              ↓
                    tabelafipe.info (+ Parallelum fallback)
                              ↓
                    DynamoDB (TTL = auction end + 1 day)

Streamlit dashboard → DynamoDB (ranking by desconto_pct)
```

The collector does **not** use Cursor or browser automation. It reads the same Elasticsearch index the Sodré website uses. Credentials (`elasticURL`, `elasticApiKey`) are extracted from the public HTML at `https://leilao.sodresantoro.com.br/` on each run.

## Project layout

```
leilao-radar/
  collector/          # Lambda handler, Sodré + DynamoDB clients
  shared/             # Pydantic models + FIPE client
  dashboard/          # Streamlit app
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

# 3) Optional: run collector now (instead of waiting for 04:00 BRT)
make aws-invoke

# 4) Follow logs
make aws-logs
```

Stack creates:

- DynamoDB table `leilao-radar-lotes` (on-demand, TTL, GSI `gsi_relevancia` + `gsi_desconto`)
- Lambda `leilao-radar-collector` (15 min timeout, arm64, 512 MB)
- EventBridge schedule: `cron(0 7 * * ? *)` → 04:00 America/Sao_Paulo
- CloudWatch Logs with 14-day retention

Point the local dashboard at real AWS by removing `DYNAMODB_ENDPOINT_URL` from `.env` (and using real AWS credentials).

## GitHub Pages (static dashboard)

Public dashboard at GitHub Pages — no Streamlit server. Data is exported from DynamoDB to `docs/data/lotes.json` and deployed by GitHub Actions.

### Setup (once)

1. Push this repo to GitHub.
2. In the repo: **Settings → Pages → Build and deployment → Source: GitHub Actions**.
3. In **Settings → Secrets and variables → Actions**, add:
   - `AWS_ACCESS_KEY_ID`
   - `AWS_SECRET_ACCESS_KEY`
4. Run the workflow **Export and GitHub Pages** manually (Actions tab) or wait for the daily schedule (05:00 BRT).

Local export (uses `aws configure` credentials):

```bash
make export-docs
```

The site URL will be `https://<username>.github.io/leilao-radar/` (or your custom domain).

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
