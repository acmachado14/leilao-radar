from __future__ import annotations

import os
from datetime import datetime, timezone

import boto3
from botocore.exceptions import ClientError

from shared.models import LotRecord


def build_dynamodb_resource(region_name: str | None = None):
    region = region_name or os.environ.get("AWS_REGION", "sa-east-1")
    kwargs: dict = {"region_name": region}
    endpoint = os.environ.get("DYNAMODB_ENDPOINT_URL")
    if endpoint:
        kwargs["endpoint_url"] = endpoint
        kwargs["aws_access_key_id"] = os.environ.get("AWS_ACCESS_KEY_ID", "test")
        kwargs["aws_secret_access_key"] = os.environ.get("AWS_SECRET_ACCESS_KEY", "test")
    return boto3.resource("dynamodb", **kwargs)


def get_dynamodb_target() -> dict[str, str]:
    endpoint = os.environ.get("DYNAMODB_ENDPOINT_URL")
    return {
        "table_name": os.environ.get("TABLE_NAME", "leilao-radar-lotes"),
        "endpoint_url": endpoint or "AWS (default endpoint)",
        "region": os.environ.get("AWS_REGION", "sa-east-1"),
    }


class LotRepository:
    def __init__(self, table_name: str | None = None, region_name: str | None = None) -> None:
        self.table_name = table_name or os.environ.get("TABLE_NAME", "leilao-radar-lotes")
        self._table = build_dynamodb_resource(region_name).Table(self.table_name)

    def upsert_lot(self, lot: LotRecord) -> None:
        item = lot.to_dynamo_item()
        self._table.put_item(Item=item)

    def upsert_many(self, lots: list[LotRecord]) -> int:
        count = 0
        for lot in lots:
            self.upsert_lot(lot)
            count += 1
        return count

    def query_top_deals(self, limit: int = 100) -> list[dict]:
        for index_name in ("gsi_relevancia", "gsi_desconto"):
            try:
                response = self._table.query(
                    IndexName=index_name,
                    KeyConditionExpression="gsi_pk = :live",
                    ExpressionAttributeValues={":live": "LIVE"},
                    ScanIndexForward=False,
                    Limit=limit,
                )
                return response.get("Items", [])
            except ClientError as exc:
                code = exc.response["Error"]["Code"]
                if code in ("ResourceNotFoundException", "ValidationException"):
                    continue
                raise
        return []

    def scan_all(self, limit: int | None = None) -> list[dict]:
        items: list[dict] = []
        scan_kwargs: dict = {}
        while True:
            response = self._table.scan(**scan_kwargs)
            items.extend(response.get("Items", []))
            if limit and len(items) >= limit:
                return items[:limit]
            if "LastEvaluatedKey" not in response:
                break
            scan_kwargs["ExclusiveStartKey"] = response["LastEvaluatedKey"]
        return items

    @staticmethod
    def now_iso() -> str:
        return datetime.now(timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")
