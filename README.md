Gestionale Medicina del Lavoro (GMDL) è un gestionale SaaS multi-tenant pensato per studi di medicina del lavoro e strutture sanitarie che devono gestire in modo strutturato visite, documentazione e flussi operativi, con separazione totale tra i dati di ogni cliente.

L’architettura è basata su un control-plane (registry) e su tenant isolati:

Registry (control-plane): gestisce onboarding e governo della piattaforma (tenants, piani, sottoscrizioni, audit, configurazioni globali).

Tenant runtime: ogni cliente (es. “Studio Medico Rossi”) accede tramite dominio dedicato (subdomain) e lavora nel proprio contesto, con dati e permessi segregati.

Autenticazione JWT: login stateless e API-first.

RBAC + Domain Scope Hardening: separazione netta tra super admin (solo control-plane) e utenti tenant (solo dominio tenant), con blocchi espliciti anti-cross-domain.

Billing gate: middleware che blocca le funzionalità “business” se la sottoscrizione non è valida (trial/active/past_due, grace, ecc.).

Audit log: tracciamento eventi critici (login success/fail, chiamate admin), utile per compliance e troubleshooting.

Obiettivo: una base enterprise-grade, sicura e scalabile, su cui costruire tutte le funzionalità verticali del gestionale (workflow reali, documenti, protocolli, dashboard e ruoli).