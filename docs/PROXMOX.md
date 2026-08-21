# Proxmox VE Integration

The Host-On.Games Panel talks to Proxmox VE exclusively through its REST API
(`/api2/json`). It never shells out to `qm` or any other local binary.

## Provider configuration

Each `infrastructure_providers` record holds:

- `type` — `proxmox` (or `fake` for demo).
- `api_url` — base URL, e.g. `https://pve.example.com:8006`.
- `auth_user` — Proxmox API token user in `user@realm!tokenid` form.
- `auth_token` — the token secret, **encrypted at rest** via the framework
  encrypter and never exposed to the frontend or logs.
- `tls_verify` — TLS verification is **enabled by default**. Disabling it is
  only intended for development/mock systems.
- `tls_fingerprint` — optional certificate fingerprint for pinned environments.
- `timeout`, `location_id`, `enabled`, `maintenance_mode`.

## Authentication

Requests use the standard Proxmox token header:

```text
Authorization: PVEAPIToken=user@realm!tokenid=secret
```

Proxmox API tokens should be scoped to the minimum required privileges. The
root password is never used or stored.

## Supported operations

`ProxmoxInfrastructureProvider` implements the full
`InfrastructureProviderInterface`:

- `testConnection` — `GET /nodes`
- `getNodes` — `GET /nodes`
- `getNextVmId` — `GET /cluster/nextid`
- `createInstance` — `POST /nodes/{node}/qemu/{vmid}/clone` (QEMU/KVM), followed
  by `PUT .../config` (name/cpu/memory/net), `PUT .../resize` (disk) and
  Cloud-Init injection.
- `start/stop/reboot` — `POST .../status/{start,shutdown,reboot}`
- `deleteInstance` — `DELETE /nodes/{node}/qemu/{vmid}`
- `resizeInstance` — config + resize
- `getInstanceStatus` — `GET .../status/current`
- `getMetrics` — `GET /nodes/{node}/status`
- `waitForTask` — polls `GET /nodes/{node}/tasks/{upid}/status`
- snapshots — `POST/GET/DELETE .../snapshot` and rollback

## VM creation

VMs are cloned from Cloud-Init enabled templates. The admin defines templates
via `infrastructure_templates` (template VMID, storage pool, bridge,
Cloud-Init + Wings bootstrap flags). Cloning supports full or linked clones.

Cloud-Init is used to set the instance hostname, network config (via
`ipconfig0`) and initial user. The one-time bootstrap token is **not** a
permanent credential baked into the template.

## Bootstrap

The template is expected to contain a small bootstrap unit that:

1. Reads the bootstrap token (delivered via Cloud-Init meta/user data).
2. Calls `POST {panel}/api/hoston/bootstrap` with the token.
3. Receives the Wings node configuration (uuid, token, YAML config).
4. Installs/configures Docker + Wings, then starts Wings.

The Panel-side endpoint (`BootstrapController`) validates the token once,
returns the node configuration, and resumes the provisioning job.

## Security notes

- Tokens are encrypted at rest and never logged.
- TLS verification is on by default.
- Errors returned from Proxmox are sanitized and never include secrets.
- Provisioning timeouts are bounded and produce actionable messages
  (e.g. the Proxmox task UPID, node and VMID).

## Safety guarantees (what the panel does and does not touch)

This matters when connecting the panel to a **production** Proxmox server
that already hosts other VMs:

**Read-only actions** (safe on any cluster):

- Adding a cluster = a database record only. No API calls are made.
- "Test" = `GET /nodes`.
- "Sync Hosts" = `GET /nodes` (plus a DB upsert of host metadata).
- There is no background sync/cron: nothing happens automatically.

**Write actions** only run as part of an explicit provisioning order (or the
admin lifecycle operations), and they operate **exclusively** on the VMID
that the panel itself received from Proxmox (`GET /cluster/nextid`) for that
order:

- `POST /nodes/{node}/qemu/{template}/clone` with that `newid`.
- `PUT .../qemu/{vmid}/config`, `PUT .../qemu/{vmid}/resize` — only that VMID.
- `POST .../qemu/{vmid}/status/start|shutdown|reboot` — only that VMID.
- `DELETE .../qemu/{vmid}` — only during explicit termination, only the VMID
  stored on our `compute_instances` record.

The panel never enumerates the cluster's VMs to pick or delete anything, never
deletes by name, and never touches a VMID it did not create itself. If a
provisioning step fails, the created VM is **not** deleted automatically —
it is kept for manual review.

### Recommended production setup

1. Create a **restricted API token** in Proxmox (Datacenter → Permissions →
   API Tokens) with the minimum privileges needed:
   `Sys.Audit`, `VM.Clone`, `VM.Allocate`, `VM.Config.*`, `VM.PowerMgmt`,
   `VM.Audit`, `Datastore.Allocate` — scoped to the nodes/storage you want
   the panel to use. Without `VM.Allocate`/`VM.Clone` the panel can read and
   sync hosts but cannot create anything.
2. Add the cluster but leave it **disabled** (or use the read-only token) and
   run "Test" + "Sync Hosts" — these are read-only.
3. Enable the cluster only when you are ready for provisioning. A disabled
   cluster is excluded from placement and cannot receive orders.
