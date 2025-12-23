#!/usr/bin/env bash
set -euo pipefail

echo "[mysql-init] ensure registry & tenant db + grants..."

mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${MYSQL_REGISTRY_DB}\`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

CREATE DATABASE IF NOT EXISTS \`${MYSQL_TENANT_DEMO_DB}\`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;

GRANT ALL PRIVILEGES ON \`${MYSQL_TENANT_DEMO_DB}\`.* TO '${MYSQL_APP_USER}'@'%';
FLUSH PRIVILEGES;
EOSQL

echo "[mysql-init] done."
