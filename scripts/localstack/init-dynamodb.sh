#!/usr/bin/env bash
set -euo pipefail

TABLE_NAME="${TABLE_NAME:-leilao-radar-lotes}"

awslocal dynamodb create-table \
  --table-name "${TABLE_NAME}" \
  --billing-mode PAY_PER_REQUEST \
  --attribute-definitions \
    AttributeName=lote_id,AttributeType=S \
    AttributeName=gsi_pk,AttributeType=S \
    AttributeName=desconto_pct,AttributeType=N \
    AttributeName=relevance_score,AttributeType=N \
  --key-schema AttributeName=lote_id,KeyType=HASH \
  --global-secondary-indexes \
    "IndexName=gsi_relevancia,KeySchema=[{AttributeName=gsi_pk,KeyType=HASH},{AttributeName=relevance_score,KeyType=RANGE}],Projection={ProjectionType=ALL}" \
    "IndexName=gsi_desconto,KeySchema=[{AttributeName=gsi_pk,KeyType=HASH},{AttributeName=desconto_pct,KeyType=RANGE}],Projection={ProjectionType=ALL}" \
  2>/dev/null || true

awslocal dynamodb update-time-to-live \
  --table-name "${TABLE_NAME}" \
  --time-to-live-specification "Enabled=true, AttributeName=ttl" \
  2>/dev/null || true

echo "DynamoDB table '${TABLE_NAME}' ready on LocalStack"
