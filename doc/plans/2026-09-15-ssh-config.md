# SSH config generation — Plan & Progress

Date: 2026-09-15
Repos: simox (this repo, consumer data); simox_cnf (../simox_cnf, private
       config); php_daas_framework (../php_daas_framework, upstream
       mechanism).

## Decision

Replace the hand-edited `~/.ssh/config.d/simox.conf` with a generated file,
driven by a new framework `bin/gen-ssh-config` CLI that reads a consumer's
`hosts` name→IP mapping and writes `~/.ssh/config.d/<app>.conf`. Dev machines
ssh to prod servers **as root** with **one project key**
(`~/.ssh/simox-sshkey`); there is no member-machine ssh, and no hostname
resolution layer beyond what ssh's `HostName` already provides.

Every generated `Host` is prefixed `simox-` for hard isolation: other
consumers using the same `~/.ssh/config.d/*.conf` drop-in — possibly against
the same prod server — cannot collide on alias names even when the underlying
`HostName` (IP) is shared. `reuter.ini` `SERVER` keeps direct IPs; prod is
untouched; the existing `hosts` → `/etc/hosts` merge stays alongside. The
`.zerotier` hostname-resolution plan is dropped.

Out of scope: prod→prod ssh (this generator is client-side, dev-only),
team-member ssh, and any DNS/ZeroNSD resolution layer.

## Config model

`hosts` (private, `ip hostname`, prod servers only) is the single source. Each
entry becomes:

```
# ~/.ssh/config.d/simox.conf (generated, idempotent)
Host simox-<name>
  HostName <ip>
  User root
  IdentityFile ~/.ssh/simox-sshkey
  IdentitiesOnly yes
```

- user: `root` (fixed for this consumer)
- key: `~/.ssh/simox-sshkey` (convention; its public half must be authorized
  in root's `authorized_keys` on each prod host)
- prefix: `simox-` (hard isolation)

## Changes

### php_daas_framework (../php_daas_framework)

- [ ] `bin/gen-ssh-config` + data contract — tracked in
      `php_daas_framework/doc/plans/2026-09-15-ssh-config.md`.

### simox (this repo)

- [x] `Makefile` — `_dev-ssh-config` target: run the framework
      `gen-ssh-config` CLI (Composer-delivered, `vendor/bin/gen-ssh-config`)
      after `_dev-update-hosts` in `dev-init`.
- [x] `etc/hosts.template` — header comment: entries are prod server names
      (no member machines); feed both the `/etc/hosts` merge and the
      generated ssh config.
- [x] `README.md` — dev ssh section: generated `~/.ssh/config.d/simox.conf`,
      `ssh simox-<name>` as root, project-key convention.
- [x] `doc/system/private-config.md` — `hosts` stays "no (dev-only)" but now
      also drives the ssh config.
- [x] `doc/plans/2026-09-15-ssh-config.md` — this doc.

### simox_cnf (../simox_cnf)

- [ ] `hosts` — real name→IP entries (prod servers only; drop the `john-*`
      member examples). Tracked in
      `simox_cnf/doc/plans/2026-09-15-ssh-config.md`.
- [ ] `README.md` — `hosts` wording (ssh source, prod-only). Tracked in
      `simox_cnf/doc/plans/2026-09-15-ssh-config.md`.
- [ ] `doc/system/zerotier-setup.md` — new "SSH config (generated)" section.
      Tracked in `simox_cnf/doc/plans/2026-09-15-ssh-config.md`.

## Open items

- **prod→prod** — **no** for now; it needs prod-host-side ssh setup (root keys
  between prod hosts), outside this dev-side generator.
- **server naming** — `hosts` names today double as DB names (`simo0`/`simo1`);
  revisit if a server ever hosts several databases and needs a distinct server
  name.
- **key deployment** — `~/.ssh/simox-sshkey` does not exist yet; it must be
  generated and installed in root's `authorized_keys` on each prod host (ties
  into the existing deploy/`prod-user-setup` key step).
- **CLI contract** — the exact flags/source resolution (`hosts` path, app name,
  user/key overrides) is decided in the framework plan doc.
