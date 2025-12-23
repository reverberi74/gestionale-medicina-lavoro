# Gestionale Medicina del Lavoro — Infra (Docker Dev)

## Prerequisiti
- Docker Desktop avviato

## Setup (prima volta)
1) Crea env locale:
   cp infra/.env.example infra/.env

2) Avvia stack:
   docker compose -f infra/docker-compose.yml up -d

3) Verifica:
   docker compose -f infra/docker-compose.yml ps

> Nota: `gmdl-minio-init` è un init container: farà bootstrap e poi resterà "Exited (0)". È corretto.

## URL utili
- Mailpit UI: http://localhost:8026
  - SMTP: localhost:1026
- MinIO Console: http://localhost:9001
- MinIO API (S3): http://localhost:9000
- MySQL: 127.0.0.1:3307
- Redis: 127.0.0.1:6379

## Connessione HeidiSQL (MySQL Docker)
- Host: 127.0.0.1
- Port: 3307
- User: valore di MYSQL_APP_USER (infra/.env)
- Password: valore di MYSQL_APP_PASSWORD (infra/.env)
- DB: gmdl_registry / gmdl_tenant_demo

## Stop / Reset
- Stop:
  docker compose -f infra/docker-compose.yml down

- Stop + rimuovi volumi (RESET TOTALE DB/MinIO/Redis):
  docker compose -f infra/docker-compose.yml down -v
