# Windows-only game servers (Wine/Proton) & Game licenses

## Architecture

Windows-only dedicated game servers (e.g. Farming Simulator 25) run on the
standard Linux infrastructure stack — there are **no Windows Wings nodes**:

```text
Proxmox KVM → Debian/Linux → Wings → Docker (Linux) → Wine/Proton → Game server
```

A game that requires Wine is declared in the game catalog with
`runtime = wine` (or `proton`) and a Docker image that ships Wine (e.g.
`ghcr.io/parkervcp/yolks:wine_latest`). The provisioning pipeline does not
treat Wine titles any differently: it still creates a KVM VM, installs Wings,
and starts the Egg using the selected Docker image. The `runtime` field is
metadata that drives the catalog UI and the chosen default image.

The actual game install/launch logic lives in the Egg (install script +
startup command), exactly like native Linux games.

## Game license pool

Commercially licensed titles (Farming Simulator 25, licensed by GIANTS, etc.)
receive their license during provisioning through a generic, encrypted pool:

- `game_license_pools` — one pool per title (`provider`, `license_variable`).
- `game_licenses` — individual keys, **stored encrypted at rest**.

Flow:

1. Admin adds licenses to a pool (admin UI only; keys are encrypted on write).
2. A catalog entry sets `requires_license = true` and references the pool's
   `license_variable` (e.g. `GAME_LICENSE`).
3. During provisioning, `ProvisioningService` allocates the first available
   license, decrypts it in memory, and injects it into the game server's
   environment under `license_variable`. Wings then passes it to the container.
4. On termination the license is released back into the pool.

Security guarantees:

- License keys are encrypted at rest and excluded from model serialization
  (`$hidden`).
- Keys are never exposed to the customer, the frontend, the provisioning
  timeline, or logs. The only plaintext existence is the brief in-memory
  hand-off to the server environment during provisioning.
- No licensing bypass is implemented. Licenses must be supplied legitimately
  by Host-On / GIANTS; an empty pool causes provisioning to fail with a clear
  "no licenses available" error.
