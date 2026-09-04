.PHONY: local-up local-down local-init local-status local-collect local-collect-palacio \
	local-dashboard aws-build aws-deploy aws-invoke aws-invoke-palacio aws-logs aws-logs-palacio \
	aws-status export-docs web-setup web-install web-test web-sync web-alerts web-serve

local-up:
	docker compose up -d
	@echo "Waiting for LocalStack..."
	@until curl -sf http://localhost:4566/_localstack/health >/dev/null; do sleep 1; done
	@echo "LocalStack is ready at http://localhost:4566"

local-down:
	docker compose down

local-init:
	PYTHONPATH=. python scripts/init_dynamodb.py

local-status:
	curl -s http://localhost:4566/_localstack/health | python -m json.tool

local-collect:
	@echo "=== Leilão Radar — Collector Sodré ==="
	@set -a && [ -f .env ] && . ./.env; set +a; \
	echo "TABLE_NAME=$${TABLE_NAME:-leilao-radar-lotes}"; \
	echo "DYNAMODB_ENDPOINT_URL=$${DYNAMODB_ENDPOINT_URL:-AWS default}"; \
	echo "AWS_REGION=$${AWS_REGION:-sa-east-1}"; \
	echo "LOG_LEVEL=INFO"; \
	if curl -sf http://localhost:4566/_localstack/health >/dev/null 2>&1; then \
		echo "LocalStack: OK (http://localhost:4566)"; \
	else \
		echo "LocalStack: não detectado (ok se usa AWS real)"; \
	fi; \
	echo "Logs de cada lote aparecem abaixo."; \
	echo "----------------------------------------"
	set -a && [ -f .env ] && . ./.env; set +a; \
	LOG_LEVEL=INFO PYTHONPATH=. python -m collector.handler

local-collect-palacio:
	@echo "=== Leilão Radar — Collector Palácio ==="
	@set -a && [ -f .env ] && . ./.env; set +a; \
	echo "TABLE_NAME=$${TABLE_NAME:-leilao-radar-lotes}"; \
	echo "DYNAMODB_ENDPOINT_URL=$${DYNAMODB_ENDPOINT_URL:-AWS default}"; \
	echo "AWS_REGION=$${AWS_REGION:-sa-east-1}"; \
	echo "LOG_LEVEL=INFO"; \
	echo "Logs de cada lote aparecem abaixo."; \
	echo "----------------------------------------"
	set -a && [ -f .env ] && . ./.env; set +a; \
	LOG_LEVEL=INFO PYTHONPATH=. python -m collector.palacio_handler

local-dashboard:
	@echo "=== Leilão Radar — Dashboard ==="
	@set -a && [ -f .env ] && . ./.env; set +a; \
	echo "TABLE_NAME=$${TABLE_NAME:-leilao-radar-lotes}"; \
	echo "DYNAMODB_ENDPOINT_URL=$${DYNAMODB_ENDPOINT_URL:-AWS default}"; \
	echo "AWS_REGION=$${AWS_REGION:-sa-east-1}"; \
	echo "LOG_LEVEL=$${LOG_LEVEL:-INFO}"; \
	if curl -sf http://localhost:4566/_localstack/health >/dev/null 2>&1; then \
		echo "LocalStack: OK (http://localhost:4566)"; \
	else \
		echo "LocalStack: não detectado (ok se usa AWS real)"; \
	fi; \
	echo "Logs do app aparecem abaixo. URL: http://localhost:8501"; \
	echo "----------------------------------------"
	set -a && [ -f .env ] && . ./.env; set +a; \
	LOG_LEVEL=$${LOG_LEVEL:-INFO} PYTHONPATH=. streamlit run dashboard/app.py \
		--server.headless true \
		--logger.level=info

aws-build:
	cd infra && sam build --use-container

aws-deploy:
	cd infra && sam deploy

aws-invoke:
	aws lambda invoke \
		--region sa-east-1 \
		--function-name leilao-radar-collector \
		--cli-binary-format raw-in-base64-out \
		--payload '{}' \
		/tmp/leilao-radar-invoke.json
	@python -m json.tool /tmp/leilao-radar-invoke.json

aws-invoke-palacio:
	aws lambda invoke \
		--region sa-east-1 \
		--function-name leilao-radar-collector-palacio \
		--cli-binary-format raw-in-base64-out \
		--payload '{}' \
		/tmp/leilao-radar-invoke-palacio.json
	@python -m json.tool /tmp/leilao-radar-invoke-palacio.json

aws-logs:
	aws logs tail /aws/lambda/leilao-radar-collector --region sa-east-1 --follow

aws-logs-palacio:
	aws logs tail /aws/lambda/leilao-radar-collector-palacio --region sa-east-1 --follow

aws-status:
	@aws cloudformation describe-stacks \
		--region sa-east-1 \
		--stack-name leilao-radar \
		--query 'Stacks[0].{Status:StackStatus,Outputs:Outputs}' \
		--output table

export-docs:
	unset DYNAMODB_ENDPOINT_URL AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY AWS_SESSION_TOKEN; \
	PYTHONPATH=. python scripts/export_lotes_json.py

web-setup:
	cd web && composer install --no-interaction
	cd web && test -f .env || cp .env.example .env
	cd web && php artisan key:generate --force
	cd web && php artisan migrate --force
	cd web && npm install --ignore-scripts && npm run build
	cd web && php artisan radar:sync-lots || true

web-install:
	cd web && composer install && npm install && npm run build

web-test:
	cd web && php artisan test

web-sync:
	cd web && php artisan radar:sync-lots

web-alerts:
	cd web && php artisan radar:dispatch-alerts --skip-sync

web-serve:
	cd web && php artisan serve
