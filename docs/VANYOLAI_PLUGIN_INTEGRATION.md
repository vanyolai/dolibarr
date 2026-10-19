# Vanyolai Plugin / Subtree Integration Matrix

> Snapshot date: 2026-10-19.
>
> This file records integration topology. Detailed feature status and roadmaps belong in each plugin repository's `DEVELOPMENT.md`.

## 1. Consumer topology

```text
repository:          vanyolai/dolibarr
deploy branch:       production
Dolibarr-23 branch:  23.0
production checkout: /mnt/applications/data/production/dolibarr
```

The standalone module repository is authoritative for module development. The Dolibarr repository is the deployable consumer.

## 2. Integration matrix

| Module | Authoritative repository | Source branch | Prefix | Mode | Embedded production version | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| CompletionCertificate | `vanyolai/dolibarr-completioncertificate` | `main` | `htdocs/custom/completioncertificate` | **unsquashed** | 0.5.6 | Auto-attach/formmail work is now present in production |
| Email2Order | `vanyolai/dolibarr-email2order` | `main` | `htdocs/custom/email2order` | squashed | 0.1.0 | Supplier-specific parser architecture |
| FieldService | `vanyolai/dolibarr-fieldservice` | intended `main`; verify active feature integration before each pull | `htdocs/custom/fieldservice` | squashed | 0.1.0-dev | Shipment/billing work is ahead of the old README baseline; source/consumer tree must be verified before next integration |
| WarrantyPeriod | `vanyolai/dolibarr-warrantyperiod` | `main` | `htdocs/custom/warrantyperiod` | squashed | 0.3.1 | Shipment-line warranty expiration + Nautilus model |
| WarrantySvc | `vanyolai/dolibarr-warrantysvc` | `dist/dolibarr-module` for published subtree | `htdocs/custom/warrantysvc` | squashed | 1.46.9 | Active feature branches are not production sources |
| BankSync | `vanyolai/dolibarr-banksync` | `main` | `htdocs/custom/banksync` | squashed | 0.5.1 | Native posting remains explicitly gated |
| NAV Online Invoice | `vanyolai/dolibarr-nav-online-invoice` | `main` | `htdocs/custom/navinvoice` | squashed | 0.9.0 | Experimental/manual draft import model |
| Paperless | `vanyolai/dolibarr-paperless` | `main` | `htdocs/custom/paperless` | squashed | 0.2.0 | PDF routing + invoice matching |
| CDAV | `Befox/cdav` | `master` | `htdocs/cdav` | squashed | external upstream | Tracked subtree, never nested `.git` |

## 3. Exact subtree update commands

Run from a clean integration worktree based on current `origin/production`, not from the live production checkout.

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

### FieldService

Before pulling, verify the accepted source branch/commit against the module repo's `DEVELOPMENT.md`.

Long-term intended command:

```bash
git subtree pull \
  --prefix=htdocs/custom/fieldservice \
  https://github.com/vanyolai/dolibarr-fieldservice.git \
  main \
  --squash \
  -m "Update FieldService subtree"
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

Only pull the publish branch unless the release model is deliberately changed:

```bash
git subtree pull \
  --prefix=htdocs/custom/warrantysvc \
  https://github.com/vanyolai/dolibarr-warrantysvc.git \
  dist/dolibarr-module \
  --squash \
  -m "Update WarrantySvc subtree"
```

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

## 4. Recommended module development flow

```text
module feature branch
 -> module tests
 -> module review/acceptance
 -> module main or documented publish branch
 -> Dolibarr integration branch/worktree based on production
 -> subtree pull with explicit -m
 -> integrated tests
 -> production
 -> live checkout git pull --ff-only
```

Never use the integrated subtree as a long-lived scratch source.

## 5. WarrantySvc special branch model

WarrantySvc deliberately has more layers than the other modules.

- `feature/supplier-rma-workflow`: supplier-RMA development baseline.
- `feature/rma-integrity-audit-concurrency`: current Phase-1 integrity/audit/concurrency work; draft PR #1; not a production subtree source.
- `dist/dolibarr-module`: publishable module-root branch consumed by Dolibarr.
- `main`: repository baseline, not automatically the production subtree source.

Publishing to `dist/dolibarr-module` is a separate release gate. Feature development must never update the publish branch as a side effect.

Read the repository's `docs/PROJECT_STATUS.md` for the authoritative active milestone.

## 6. FieldService integration rule

FieldService is still pre-stable and has active shipment/billing work.

Before every subtree update:

1. inspect current `main`, PRs and active feature branch;
2. establish which exact source tree passed acceptance;
3. merge/normalize that tree in the standalone repository first;
4. only then pull it into Dolibarr.

Do not let the Dolibarr consumer become the only place containing accepted FieldService code.

## 7. Core/backport work is not a plugin subtree

Current core-fork topics include branches such as:

- `feature/bulk-serial-receipt-input` — backport of upstream #39800 plus HU translations;
- `feature/order-agenda-prefill`;
- Fichinter billed-state work;
- shipment-delete stock-restore fix;
- Email Collector compatibility/fallback fixes.

These belong to `vanyolai/dolibarr`, not to standalone plugin repositories.

Backports must retain their upstream origin so they can be removed when the target Dolibarr release contains the feature natively.

## 8. Integration acceptance checklist

Before a module update reaches `production`:

- source repo `DEVELOPMENT.md` is current;
- exact source branch/ref is known;
- source tests passed at the claimed evidence level;
- prefix and squash mode are verified;
- integration starts from current `origin/production`;
- subtree command includes `-m`;
- resulting diff is limited to the intended prefix unless a separately reviewed core delta is deliberate;
- integrated PHP lint/tests pass;
- schema upgrade is tested when applicable;
- representative sandbox acceptance is completed for high-impact behavior;
- only then is `production` advanced;
- live checkout uses `git pull --ff-only`.

## 9. Updating this matrix

Update this file whenever:

- the deploy branch policy changes;
- a module's canonical source branch changes;
- subtree squash policy changes;
- a module is added/removed;
- an embedded version changes materially;
- a source/consumer drift is discovered or resolved;
- WarrantySvc publishing policy changes.

Feature-level status belongs in the module repository so this central file does not become a duplicate roadmap.
