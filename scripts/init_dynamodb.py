#!/usr/bin/env python3
"""Create the leilao-radar DynamoDB table on LocalStack (or any DynamoDB endpoint)."""

from __future__ import annotations

import os
import sys

import boto3
from botocore.exceptions import ClientError


def main() -> int:
    table_name = os.environ.get("TABLE_NAME", "leilao-radar-lotes")
    region = os.environ.get("AWS_REGION", "sa-east-1")
    endpoint = os.environ.get("DYNAMODB_ENDPOINT_URL", "http://localhost:4566")

    client = boto3.client(
        "dynamodb",
        region_name=region,
        endpoint_url=endpoint,
        aws_access_key_id=os.environ.get("AWS_ACCESS_KEY_ID", "test"),
        aws_secret_access_key=os.environ.get("AWS_SECRET_ACCESS_KEY", "test"),
    )

    try:
        client.create_table(
            TableName=table_name,
            BillingMode="PAY_PER_REQUEST",
            AttributeDefinitions=[
                {"AttributeName": "lote_id", "AttributeType": "S"},
                {"AttributeName": "gsi_pk", "AttributeType": "S"},
                {"AttributeName": "desconto_pct", "AttributeType": "N"},
                {"AttributeName": "relevance_score", "AttributeType": "N"},
            ],
            KeySchema=[{"AttributeName": "lote_id", "KeyType": "HASH"}],
            GlobalSecondaryIndexes=[
                {
                    "IndexName": "gsi_relevancia",
                    "KeySchema": [
                        {"AttributeName": "gsi_pk", "KeyType": "HASH"},
                        {"AttributeName": "relevance_score", "KeyType": "RANGE"},
                    ],
                    "Projection": {"ProjectionType": "ALL"},
                },
                {
                    "IndexName": "gsi_desconto",
                    "KeySchema": [
                        {"AttributeName": "gsi_pk", "KeyType": "HASH"},
                        {"AttributeName": "desconto_pct", "KeyType": "RANGE"},
                    ],
                    "Projection": {"ProjectionType": "ALL"},
                },
            ],
        )
        print(f"Created table '{table_name}' at {endpoint}")
    except ClientError as exc:
        if exc.response["Error"]["Code"] != "ResourceInUseException":
            raise
        print(f"Table '{table_name}' already exists at {endpoint}")

    try:
        client.update_time_to_live(
            TableName=table_name,
            TimeToLiveSpecification={"Enabled": True, "AttributeName": "ttl"},
        )
        print("TTL enabled on attribute 'ttl'")
    except ClientError as exc:
        if exc.response["Error"]["Code"] not in {"ValidationException", "ResourceInUseException"}:
            raise
        print("TTL already configured")

    return 0


if __name__ == "__main__":
    sys.exit(main())
