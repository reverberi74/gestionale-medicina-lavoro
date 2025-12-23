#!/bin/sh
set -eu

echo "[minio-init] waiting MinIO..."
until mc alias set local http://minio:9000 "$MINIO_ROOT_USER" "$MINIO_ROOT_PASSWORD" >/dev/null 2>&1; do
  sleep 1
done

echo "[minio-init] ensure bucket: $MINIO_BUCKET"
mc mb -p "local/$MINIO_BUCKET" >/dev/null 2>&1 || true

echo "[minio-init] enable versioning"
mc version enable "local/$MINIO_BUCKET" >/dev/null 2>&1 || true

echo "[minio-init] done."
