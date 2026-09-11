-- simox service accounts — the single source of truth for bin/gen-service-users.
--
-- DO NOT apply this file directly. The reconcile (bin/gen-service-users) reads
-- the @directives below, expands {{dbname}}/{{host}}/{{privilege}}, and applies
-- the result to each database; it also drops stale accounts (legacy
-- admin/reader, accounts on the wrong database, and hosts no longer in the pin
-- set). The DDL lines in each block document the statements the reconcile
-- generates and are ignored by it.
--
-- Directive syntax (a leading `--` and any indentation are stripped; only
-- lines that then begin with `@` are read):
--   `@account <name>`           starts an account block
--   `@hosts <source>[,<src>]`   host-pin source(s): `member` (every IP value
--                               in etc/team.ini) and/or `worker`, `web`
--                               (etc/machines.ini [prod] tags)
--   `@db <name> <privilege>`    one per database the account is created on;
--                               the GRANT privilege for that database (e.g.
--                               SELECT, ALL PRIVILEGES)
--
-- Account policy (doc/plans/2026-09-10-simox-service-account-and-read-replica.md):
--   simox   writer — ALL on simo0, SELECT on simo1 — team members ∪ worker hosts
--   public  reader — SELECT on simo1 only — web hosts (never on simo0)
-- The `replication` transport account is NOT here: it is created on the
-- primary by the replica bootstrap (see doc/system/replica-bootstrap.md).

-- @account simox
-- @hosts member, worker
-- @db simo0 ALL PRIVILEGES
-- @db simo1 SELECT
CREATE USER IF NOT EXISTS 'simox'@'{{host}}' IDENTIFIED BY '';
GRANT {{privilege}} ON `{{dbname}}`.* TO 'simox'@'{{host}}';

-- @account public
-- @hosts web
-- @db simo1 SELECT
CREATE USER IF NOT EXISTS 'public'@'{{host}}' IDENTIFIED BY '';
GRANT {{privilege}} ON `{{dbname}}`.* TO 'public'@'{{host}}';
