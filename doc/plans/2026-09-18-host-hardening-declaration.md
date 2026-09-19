# Host-hardening declaration — Plan & Progress

Date: 2026-09-18
Repos: simox (this repo, declaration data); php_daas_framework
       (../php_daas_framework, upstream mechanism); simox_cnf
       (../simox_cnf, private values).

## Decision

simox owns the *data* for the firewall reconcile: a single host-hardening
declaration package in `srv/` that maps tags → firewall rules. The framework
owns the *mechanism* — `gen-firewall`, one CLI reading this declaration. sshd
hardening is not part of the declaration: it is three static directives
applied once per host as a manual pre-deployment step (documented in
`../simox_cnf`), not a reconcile reading this package.

See `../php_daas_framework/doc/plans/2026-09-18-gen-firewall.md` for the
mechanism and `../simox_cnf/doc/plans/2026-09-18-firewall-sshd-reconcile.md`
for the private values (ZeroTier range, `cloud` tag).

## Changes

### simox (this repo)

- [x] `srv/host-hardening-D0Q5MP2ZQJZWVHHB/default.php` — the declaration:
      - `$tagRules` — baseline (deny-in / allow-out; allow `22/tcp` from the
        ZeroTier range), `db:<name>` → allow `<port>/tcp` (port resolved from
        `reuter.ini [<name>] PORT`), `web` → 80/443, `cloud` → 9993/udp gated
        on the runtime cloud test;
      - ZeroTier-range reference (placeholder — the real /24 comes from
        `simox_cnf` private data) and the cloud test.
- [x] `etc/machines.ini.template` — header comment: `web`/`cloud` also drive
      the firewall; tags only add allow rules.
- [x] `doc/plans/2026-09-18-host-hardening-declaration.md` — this doc.

## Open items

- **ZeroTier range** — the actual /24 comes from `simox_cnf` private data; the
  declaration references it and never hardcodes the third octet.
- **Cloud-test placement** — the runtime metadata probe (AWS 169.254.169.254,
  short timeout) is arguably mechanism (framework) not data; the declaration
  supplies the endpoint/timeout, the framework supplies the fail-safe probe.
