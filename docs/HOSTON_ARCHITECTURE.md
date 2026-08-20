# Host-On.Games Panel — Architecture

The Host-On.Games Panel is a fork of the Pterodactyl Panel that turns it into a
commercial game hosting platform where every customer order results in an
automatically provisioned, isolated Proxmox VM running a Pterodactyl Wings node
and the purchased game.

This document describes the Host-On-specific architecture and how it integrates
with (rather than replaces) the upstream Pterodactyl core.

## High level

```text
Customer UI ──► Host-On.Games Panel
                     │
        ┌────────────┴────────────────────┐
        │  Game Infrastructure Orchestrator│
        │  (provisioning state machine)    │
        │  (placement engine)              │
        │  (retry / rollback / audit)      │
        └────────────┬──────────────────┬───┘
                     │                  │
             InfrastructureProvider   Pterodactyl Core
                     │                  │
             Proxmox VE / Demo       Wings (inside VM)
                     │                  │
                Customer VM            Game
```

## Infrastructure abstraction

The single most important rule: business logic depends on
`Pterodactyl\Contracts\Infrastructure\InfrastructureProviderInterface`, never on
a concrete provider.

```text
InfrastructureProviderInterface
├── createInstance / deleteInstance
├── start / stop / reboot
├── resize / snapshot / rollback
├── getInstanceStatus / getMetrics / getNodes
├── waitForTask
└── testConnection

Implementations:
├── ProxmoxInfrastructureProvider  (REST API, no shelling out to `qm`)
├── FakeInfrastructureProvider     (demo/preview only)
└── (future: OpenStack, VMware, ...)
```

The `InfrastructureProviderManager` is the single seam that resolves a provider
from an `InfrastructureProvider` model (credentials are decrypted on demand and
never leave the backend).

## Core entities

| Table | Purpose |
| --- | --- |
| `infrastructure_clusters` | A Proxmox cluster carrying its own connection config (API URL, token, TLS) + enabled/location. |
| `infrastructure_hosts` | A compute node (hypervisor) with capacity + utilization. |
| `infrastructure_templates` | Cloud-Init enabled VM templates (VMID, storage, bridge). |
| `infrastructure_networks` / `infrastructure_ip_pools` | Networks and public IP ranges. |
| `infrastructure_ip_allocations` | Individual public IPs within a pool (available/allocated/reserved), assigned to a VM during provisioning and passed to Proxmox via Cloud-Init. |
| `compute_instances` | A customer's Proxmox VM (links to its Wings node). |
| `game_services` | The customer-facing service (links customer → VM → server). |
| `resource_profiles` | Products ("Minecraft Performance": CPU/RAM/disk split). |
| `game_catalog_entries` | Customer-friendly games mapped to Nests/Eggs. |
| `provisioning_jobs` / `provisioning_steps` | The state machine + timeline. |
| `bootstrap_tokens` | One-time, expiring tokens for VM bootstrap. |
| `infrastructure_audit_logs` | Audit trail of infrastructure operations. |

## Key relationships

```text
GameService
  ├── ComputeInstance (Proxmox VM)
  │     ├── ComputeInstanceNetwork (management + public interfaces)
  │     └── wings_node_id ──► Node (type = "managed")
  └── Server (Pterodactyl game server)
        └── node_id ──► same Node
```

A compute instance can host **multiple** game services (future "Game Cloud"):
the current provisioning rule is one order → one VM → one server, but the
data model does not prevent `1 VM → 1 Wings node → N game servers`.

`Node.type` distinguishes `static` (traditional manually provisioned
VPS/server) from `managed` (automatically created via the infrastructure
provider). Existing static nodes keep working unchanged.

## Clusters and hosts

The top-level unit an administrator manages is the **Proxmox cluster**:

```text
Proxmox Clusters (each carries its own API URL / token / TLS / location)
   └── Hypervisor Hosts (auto-synchronized from Proxmox)
         └── Compute Instance / Customer VM
               └── Managed Wings Node
                     └── Game Server(s)
```

- `infrastructure_clusters` — arbitrary number of Proxmox clusters, each with
  its own connection configuration (name, API URL, token ID/secret, TLS,
  location, enabled). The internal `InfrastructureProviderInterface` code
  abstraction still exists to allow other virtualization platforms later, but
  it is not a separate data/UI level.
- `infrastructure_hosts` — the physical hypervisors. Physical facts (name, CPU,
  RAM, disk, online status) are synchronized from Proxmox via `HostSyncService`
  (REST API, never `qm`). Host-On settings (`enabled`, `maintenance_mode`,
  `placement_weight`, `reserved_memory`/`reserved_disk`,
  `allowed_product_classes`, `tags`) are managed locally and never overwritten
  by sync.

## Networking

`compute_instance_networks` models each interface of a customer VM: a private
**management** interface for Wings traffic and a **public** interface carrying
the dedicated game IP (allocated from an IP pool), including `ip`, `ipv6`,
`vlan`, `network`, `gateway`, `bridge` and the `ip_allocation` reference.
`compute_instances.management_ip`/`game_ip` remain as primary convenience fields.

## Provisioning state machine

`ProvisioningService` runs the ordered steps in `ProvisioningJob::STEPS`.
Each step is recorded in `provisioning_steps` so the UI can render a live
timeline and failed jobs can be resumed from the correct point (external IDs
such as `vmid`, `wings_node_id` and `server_id` are persisted as soon as they
are created).

Steps: selecting_host → creating_vm → configuring_vm → starting_vm →
waiting_for_vm → configuring_network → registering_node → bootstrapping →
installing_wings → creating_allocations → creating_game_server →
installing_game → starting_game → verifying.

- `ProcessProvisioningJob` is the queued job that drives the machine.
- The run loop is idempotent and resumable (guarded per-step).
- In **demo mode** the full flow completes synchronously using the fake provider
  and real Pterodactyl records (node, allocation, server).
- In **real mode** the flow pauses after bootstrapping and resumes when the VM
  calls back to `POST /api/hoston/bootstrap` with its one-time token.

## Placement engine

`PlacementEngine` scores eligible `InfrastructureHost`s using free/allocated
memory, storage headroom, CPU utilization and load, then applies Host-On
settings: `placement_weight` scales the score, `reserved_*` reduce free
capacity, and `allowed_product_classes`/online/maintenance state can exclude a
host. Hosts in maintenance mode or with insufficient capacity are excluded.

## API surface

- `POST /api/application/hoston/services` — external (billing) provisioning API
  (`/api/application` is root-admin authenticated).
- `GET|POST /api/client/hoston/...` — customer catalog + ordering.
- `POST /api/hoston/bootstrap` — VM bootstrap callback (one-time token auth).

## See also

- `docs/PROXMOX.md` — Proxmox VE provider details.
- `docs/PROVISIONING.md` — provisioning flow, retry and rollback.
- `docs/UPSTREAM_MERGING.md` — how Host-On integration points map to upstream.
