# Vanyolai Plugin / Subtree Integration Matrix

> Snapshot date: 2026-10-19.
>
> This file records integration topology. Detailed implementation status and roadmaps belong in each plugin repository's `DEVELOPMENT.md`.

## 1. Canonical consumer

```text
repository: vanyolai/dolibarr
branch:     23.0
production: /mnt/applications/data/production/dolibarr
```

Production is a clean deployment checkout of `origin/23.0`.

## 2. Integration matrix

| Module | Authoritative repository | Source branch/ref | Dolibarr prefix | Mode | Standalone status | Embedded status / note |
| --- | --- | --- | --- | --- | --- | --- |
| CompletionCertificate | `vanyolai/dolibarr-completioncertificate` | `main` | `htdocs/custom/completioncertificate` | **unsquashed** | 0.5.6 | Dolibarr snapshot is 0.5.5 as of this snapshot; 0.5.6 email auto-attachment/formmail integration still needs subtree update + acceptance |
| Email2Order | `vanyolai/dolibarr-email2order` | `main` | `htdocs/custom/email2order` | squashed | 0.1.0 | 0.1.0 embedded |
| FieldService | `vanyolai/dolibarr-fieldservice` | currently explicit tested commit; intended future source `main` | `htdocs/custom/fieldservice` | squashed | main 0.1.0-dev | Embedded source is `4c5c59aec3c3acd0dbc57b4a70e781cc92502ccc`, one commit ahead of standalone main and part of shipment-integration work; resolve this drift before the next update |
| WarrantyPeriod | `vanyolai/dolibarr-warrantyperiod` | `main` | `htdocs/custom/warrantyperiod` | squashed | 0.3.1 | 0.3.1 embedded |
| WarrantySvc | `vanyolai/dolibarr-warrantysvc` | **`dist/dolibarr-module`** | `htdocs/custom/warrantysvc` | squashed | publish branch 1.46.9 | 1.46.9 embedded; active feature branches are not production sources |
| BankSync | `vanyolai/dolibarr-banksync` | `main` | `htdocs/custom/banksync` | squashed | 0.5.1 | 0.5.1 embedded |
| NAV Online Invoice | `vanyolai/dolibarr-nav-online-invoice` | `main` | `htdocs/custom/navinvoice` | squashed | 0.9.0 | 0.9.0 embedded |
| Paperless | `vanyolai/dolibarr-paperless` | `main` | `htdocs/custom/paperless` | squashed | 0.2.0 | 0.2.0 embedded |
| CDAV | `Befox/cdav` | `master` | `htdocs/cdav` | squashed | external upstream | tracked subtree; no nested `.git` |

## 3. Exact subtree commands

Run integrations from a clean development/integration branch based on current `origin/23.0`, not from the live production checkout.

### CompletionCertificate — unsquashed

```bash
git subtree pull \
  --prefix=htdocs/custom/completioncertificate \
  https://github.com/vanyolai/dolibarr-completioncertificate.git \
  main \
  -m "Update CompletionCertificate subtree"
```

### Email2Order

```bash
git subtree pull \
  --prefix=htdocs/custom/email2order \
  https://github.com/vanyolai/dolibarr-email2order.git \
  main \
  --squash \
  -m "Update Email2Order subtree"
```

### WarrantyPeriod

```bash
git subtree pull \
  --prefix=htdocs/custom/warrantyperiod \
  https://github.com/vanyolai/dolibarr-warrantyperiod.git \
  main \
  --squash \
  -m "Update WarrantyPeriod subtree"
```

### WarrantySvc

Only pull the explicitly published module branch:

```bash
git subtree pull \
  --prefix=htdocs/custom/warrantysvc \
  https://github.com/vanyolai/dolibarr-warrantysvc.git \
  dist/dolibarr-module \
  --squash \
  -m "Update WarrantySvc subtree"
```

Do **not** substitute `main` or an active feature branch in this command unless the release process is deliberately being changed.

### BankSync

```bash
git subtree pull \
  --prefix=htdocs/custom/banksync \
  https://github.com/vanyolai/dolibarr-banksync.git \
  main \
  --squash \
  -m "Update BankSync subtree"
```

### NAV Online Invoice

```bash
git subtree pull \
  --prefix=htdocs/custom/navinvoice \
  https://github.com/vanyolai/dolibarr-nav-online-invoice.git \
  main \
  --squash \
  -m "Update NAV Online Invoice subtree"
```

### Paperless

```bash
git subtree pull \
  --prefix=htdocs/custom/paperless \
  https://github.com/vanyolai/dolibarr-paperless.git \
  main \
  --squash \
  -m "Update Paperless subtree"
```

### CDAV

```bash
git subtree pull \
  --prefix=htdocs/cdav \
  https://github.com/Befox/cdav.git \
  master \
  --squash \
  -m "Update CDAV subtree"
```

### FieldService

Do not blindly run a new pull until the open shipment-integration work is reconciled with `main`.

Current embedded source:

```text
4c5c59aec3c3acd0dbc57b4a70e781cc92502ccc
```

Desired long-term command after the standalone branch is canonical again:

```bash
git subtree pull \
  --prefix=htdocs/custom/fieldservice \
  https://github.com/vanyolai/dolibarr-fieldservice.git \
  main \
  --squash \
  -m "Update FieldService subtree"
```

## 4. Module development direction

Normal module development:

```text
feature branch in standalone module
 -> tests/review
 -> main (or documented publish branch)
 -> subtree integration branch in vanyolai/dolibarr
 -> Dolibarr/sandbox acceptance
 -> canonical 23.0
 -> production fast-forward
```

Do not use the Dolibarr subtree as a convenient scratch directory and later leave the standalone source behind.

## 5. WarrantySvc branch model

WarrantySvc is intentionally different from the simple `main -> subtree` model.

Current roles:

- `main`: older repository baseline; not the production subtree source.
- `feature/warranty-letter-v2`: warranty-letter development line.
- `feature/supplier-rma-workflow`: supplier RMA integration/development line.
- `feature/rma-integrity-audit-concurrency`: Phase 1 integrity/audit/concurrency PR branch; draft PR #1; not production-ready.
- `feature/dolibarr-23-migration`: Dolibarr 23 migration work.
- `dist/dolibarr-module`: publishable module-root branch consumed by the Dolibarr subtree; currently 1.46.9.

Feature work must not update `dist/dolibarr-module` as a side effect. Publishing is a separate release gate.

## 6. FieldService branch model

Current standalone `main` contains material usage and LOT/SN allocation.

Draft PR #4 (`feature/shipment-integration`) adds shipment integration and the billing queue.

The Dolibarr subtree currently contains source commit `4c5c59aec3c3acd0dbc57b4a70e781cc92502ccc`, which is one commit ahead of current standalone `main`.

This is a known integration drift. The next FieldService integration milestone is:

1. validate PR #4 behavior;
2. merge/normalize the accepted source into standalone `main`;
3. verify the tree matches the Dolibarr consumer;
4. resume normal `main -> subtree` updates.

## 7. CompletionCertificate drift

Standalone `main` is 0.5.6 and includes:

- automatic attachment of the generated completion-certificate PDF to the Dolibarr email form;
- `formmail` hook enablement required by that behavior.

The current Dolibarr `23.0` snapshot is still 0.5.5.

Do not call 0.5.6 production-deployed until it is pulled, tested and accepted in the Dolibarr consumer.

## 8. Current core/backport branches

These are core-fork work, not standalone plugin subtrees.

### Bulk serial receipt input

```text
branch: feature/bulk-serial-receipt-input
PR:     #6
purpose: backport upstream Dolibarr #39800 to 23.0, with Hungarian translations
```

Keep it isolated until accepted/merged. Record the upstream origin so it can be dropped when a later Dolibarr version contains it natively.

### Order Agenda prefill

```text
branch: feature/order-agenda-prefill
head:   cb48531dada972404919474d9de6cd6d9ca17b1f
```

The work keeps Agenda duration behavior on the native Product API. Treat as an independent core feature until explicitly integrated.

### Fichinter billed state

```text
branch: test/fichinter-billed-field-23
head:   06757a3043cf2b310732895024aed5a65b1f0179
```

This is a focused Dolibarr 23 backport/test line for an independent intervention billed classification. Do not mix it with FieldService billing state merely because both concern invoicing.

## 9. Integration acceptance checklist

Before a subtree update reaches `23.0`:

- source repo status document is current;
- intended source branch/ref is explicit;
- source tree passes its test gate;
- prefix and squash mode are verified;
- integration branch starts from latest `origin/23.0`;
- subtree command includes `-m`;
- resulting diff is limited to the expected prefix unless a separately reviewed core change is intentional;
- PHP lint / module tests pass in the integrated Dolibarr tree;
- schema migration is tested if applicable;
- representative sandbox checks are performed for high-impact behavior;
- only then is canonical `23.0` advanced;
- production uses `git pull --ff-only`.

## 10. Updating this matrix

Change this file whenever:

- a module changes its canonical source branch;
- squash policy changes deliberately;
- a module is added or removed;
- an embedded version changes materially;
- a known branch drift is resolved/introduced;
- the WarrantySvc publishing model changes.

Detailed feature milestones must remain in the plugin's own repository so the central matrix does not become a stale duplicate roadmap.
