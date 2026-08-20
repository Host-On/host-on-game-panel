# Provisioning

This document describes the asynchronous provisioning workflow, retry and
rollback behaviour.

## States

High-level job statuses: `pending`, `running`, `completed`, `failed`,
`waiting_for_bootstrap`, `rolled_back`.

Step timeline (ordered):

```text
selecting_host → creating_vm → configuring_vm → starting_vm → waiting_for_vm
→ configuring_network → registering_node → bootstrapping → installing_wings
→ creating_allocations → creating_game_server → installing_game
→ starting_game → verifying
```

Every step writes a `provisioning_steps` record with its status, a human
message and (where applicable) the external ID (Proxmox task UPID, node ID,
server ID).

## Resumability

The state machine is idempotent and resumable. External IDs are persisted as
soon as they are created:

- VMID and compute instance are recorded during `creating_vm`.
- The Wings node ID during `registering_node`.
- The server ID during `creating_game_server`.

If a later step fails (or the worker crashes), retrying resumes from the first
incomplete step rather than duplicating resources. Step guards check the
recorded external IDs to determine what has already completed.

## Failure handling

On failure the job is marked `failed`, the error is stored (actionable and
sanitized — e.g. `Proxmox task UPID:... timed out after 180s while cloning VM
18242 on pve-game04`) and the step is marked `failed`. Resources are **not**
deleted automatically on a transient error.

## Retry

Administrators (or the API) can re-queue a failed job. The job transitions back
to `pending` and `ProcessProvisioningJob` continues from the correct step.

## Rollback

For an unrecoverable failure, resources are cleaned in reverse order:

```text
game server → node → VM → IP allocation
```

Resources whose ownership/state is ambiguous are never deleted automatically;
they are flagged for manual review.

## Demo mode

When the selected provider is `fake`, the entire flow (including Wings
installation and Egg install) is simulated but still creates real Pterodactyl
records so the resulting server is fully usable in the customer UI. Demo mode
is an explicit development/preview configuration and is not a substitute for
the real Proxmox provider.
