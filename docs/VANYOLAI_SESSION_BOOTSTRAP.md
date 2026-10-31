# Dolibarr Development Session Bootstrap

> Short handoff context for a new development session. Detailed rules: `docs/VANYOLAI_DEVELOPMENT_GUIDE.md`. Integration topology: `docs/VANYOLAI_PLUGIN_INTEGRATION.md`.

## Environment

```text
Dolibarr fork:       vanyolai/dolibarr
deploy branch:       production
Dolibarr-23 branch:  23.0
live checkout:       /mnt/applications/data/production/dolibarr
```

The live checkout is deployment-only. Do not develop directly there.

Normal deployment update:

```bash
cd /mnt/applications/data/production/dolibarr
git status -sb
git fetch origin --prune
git switch production
git pull --ff-only
```

Repository policy:

```bash
git config pull.ff only
```

## Latest integration checkpoint (2026-10-31)

- Official Dolibarr `23.0` baseline: `6b461ab5bd5`, integrated in production
  by `a1443b5bcd5`.
- Cyan NoID synchronization: source commit `510e5f4dbe` on
  `fix/custom-pdf-upstream-23`, included in the production integration carrying
  this checkpoint. Product references remain hidden.
- Both Nautilus variants inherit the relevant Espadon header/contact fixes;
  module subtrees and versions were not changed by this integration.
- Source/drift checks and PHP 8.3 syntax checks passed. Live deployment is
  authorized but unconfirmed; generated-PDF visual acceptance is pending.
- Details and rollout commands: `docs/VANYOLAI_PDF_MODELS.md`.
- After future upstream integrations run
  `python3 dev/tools/test/check_cyan_noid_sync.py`; this is a manual checker,
  not an automatic background task.

## Before changing anything

1. Inspect current GitHub branch/PR/CI state.
2. Read `docs/VANYOLAI_DEVELOPMENT_GUIDE.md`.
3. Read `docs/VANYOLAI_PLUGIN_INTEGRATION.md`.
4. For a plugin, read its own `DEVELOPMENT.md` and any project-status/design docs.
5. Treat Git/code/CI as newer than chat summaries.
6. Work on a feature/fix/integration branch or separate worktree, not the live checkout.

## Source-of-truth rule

Standalone module repository = canonical module development source.

Dolibarr subtree = deployable consumer snapshot.

Normal direction:

```text
plugin feature
 -> plugin canonical branch/publish branch
 -> git subtree pull
 -> integrated tests
 -> production
 -> live fast-forward
```

No nested `.git` repositories inside Dolibarr. No long-lived untracked production patches.

## Current subtree topology

| Module | Source | Prefix | Mode |
| --- | --- | --- | --- |
| CompletionCertificate | `vanyolai/dolibarr-completioncertificate:main` | `htdocs/custom/completioncertificate` | **unsquashed** |
| Email2Order | `vanyolai/dolibarr-email2order:main` | `htdocs/custom/email2order` | squashed |
| FieldService | `vanyolai/dolibarr-fieldservice` (verify accepted source before pull) | `htdocs/custom/fieldservice` | squashed |
| WarrantyPeriod | `vanyolai/dolibarr-warrantyperiod:main` | `htdocs/custom/warrantyperiod` | squashed |
| WarrantySvc | `vanyolai/dolibarr-warrantysvc:dist/dolibarr-module` | `htdocs/custom/warrantysvc` | squashed |
| BankSync | `vanyolai/dolibarr-banksync:main` | `htdocs/custom/banksync` | squashed |
| NAV Online Invoice | `vanyolai/dolibarr-nav-online-invoice:main` | `htdocs/custom/navinvoice` | squashed |
| Paperless | `vanyolai/dolibarr-paperless:main` | `htdocs/custom/paperless` | squashed |
| CDAV | `Befox/cdav:master` | `htdocs/cdav` | squashed |

## Git integration convention

Always provide an explicit message where supported so Git does not open `nano`:

```bash
git subtree pull \
  --prefix=... \
  <repo> \
  <branch> \
  --squash \
  -m "Update ... subtree"
```

CompletionCertificate is the exception: omit `--squash`.

## Dolibarr-native rules

Prefer native mechanisms before custom infrastructure:

- hooks instead of core patches;
- triggers for lifecycle events;
- native object/domain APIs instead of direct writes to core business tables;
- `GETPOST()` / `GETPOSTINT()` instead of raw request globals;
- `MAIN_DB_PREFIX`, DoliDB escaping/date helpers and entity scoping;
- native Form/UI helpers, tabs, card headers, linked objects and document blocks;
- native stock/shipment/LOT-SN APIs;
- native financial/payment objects;
- native email/formmail/substitution mechanisms;
- native document-model lifecycle.

Business logic belongs in reusable domain/service/provider classes, not page controllers.

## Languages

All user-facing text uses translation keys.

Maintain HU and EN together:

```text
langs/hu_HU/<module>.lang
langs/en_US/<module>.lang
```

Reuse a core key only if semantics match exactly. Avoid plugin keys that accidentally override generic core keys. Do not build Hungarian pluralization from English suffix rules.

## Evidence and acceptance

Do not equate PHP lint with feature verification.

Evidence levels:

```text
implemented
 -> syntax checked
 -> smoke/unit tested
 -> DB/integration tested
 -> authenticated UI tested
 -> representative sandbox accepted
 -> production deployed
```

A roadmap/status file must state which level is actually reached.

## Minimum check before integration

```bash
git status -sb
git diff --check
git diff --stat origin/production...HEAD
git log --oneline --decorate -10
```

Run PHP lint and the module-specific tests.

For subtree updates, verify the resulting diff is limited to the intended subtree unless a separately reviewed core change is intentional.

## Documentation rule

When a meaningful milestone changes:
- update the module's `DEVELOPMENT.md`;
- update its living status document if one exists;
- update the central integration matrix if branch/prefix/squash/version topology changed.

Do not let project continuity depend on chat history.
