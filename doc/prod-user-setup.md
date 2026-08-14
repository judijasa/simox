# Production User Setup (`<app_user>`)

Steps to create the app dedicated user `<app_user>` account on a fresh Debian host. Run all commands as root.

## 1. Create the account

```bash
useradd --create-home --shell /bin/bash --comment "Simox production user" <app_user>
passwd --lock <app_user>
```

`--create-home` is required: Nix installs in no-daemon mode into `~<app_user>`.  
`passwd --lock` disables password login; SSH key is the only entry point.

## 2. Authorize the <app_user> workstation's SSH public key

The framework `deploy` CLI SSHes into the server as both `root` and
`<app_user>`. Authorize the same key for both.

```bash
mkdir -p /home/<app_user>/.ssh
chmod 700 /home/<app_user>/.ssh
echo "ssh-ed25519 AAAA...your-key-here user@deployer" >> /home/<app_user>/.ssh/authorized_keys
chmod 600 /home/<app_user>/.ssh/authorized_keys
chown -R <app_user>:<app_user> /home/<app_user>/.ssh
```

---

## What the automated tooling handles

Everything else is provisioned by `deploy --init` (the framework `deploy`
CLI, which runs `bin/provision.sh` on the remote for one-time provisioning,
and `bin/deploy/post-nix.sh` on every deploy for `.env` + cron). No manual
steps needed for the items below.

| Resource | Created by | Final owner |
|---|---|---|
| `/srv/apps` | `deploy` root SSH block | `<app_user>` (set by `bin/provision.sh`) |
| `/srv/apps/simox` | `deploy` root SSH block | `<app_user>` |
| `/var/log/simox` | `deploy` root SSH block | `<app_user>` |
| `/nix` | `deploy` root SSH block (first deploy only) | `<app_user>` |
| `/var/lib/simox/mariadb` | `bin/provision.sh` | `<app_user>` |
| `/etc/cron.d/simo-orchestrator` | `bin/deploy/post-nix.sh` | `root` (644) |
| `/usr/local/simox/result` | `deploy` root SSH block | `root` (`<app_user>` read/exec only) |

## What `<app_user>` does NOT need

- `sudo` or any sudoers entry — privileged steps run via a separate `root` SSH session
- Password login
- Membership in any special group — MariaDB is initialized with `mariadb-install-db --user=<app_user>`, not via group membership
