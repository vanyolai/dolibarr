# Vanyolai Dolibarr Development Guide

> Canonical engineering rules for the Vanyolai Dolibarr 23 environment.
>
> Last reviewed: 2026-10-19
>
> This document is intended to be read at the start of every new Dolibarr development session. Repository state is authoritative; chat history is not.

## 1. Sources of truth

The deployable application lives in:

```text
repository: vanyolai/dolibarr
deploy branch: production
upstream-23 integration/reference branch: 23.0
production checkout: /mnt/applications/data/production/dolibarr
```

The standalone repository of each custom module is the authoritative development source for that module. The Dolibarr repository consumes tested module snapshots through Git subtrees.

The official upstream is `Dolibarr/dolibarr`. Upstream `develop` must never be treated as the base of the production installation merely because GitHub displays comparisons against it. Our production line follows Dolibarr 23.

## 2. Repository, tree and subtree boundaries

Three layers must remain distinct:

1. **Dolibarr core fork** — `vanyolai/dolibarr`.
2. **Standalone module repositories** — one canonical repository per custom module.
3. **Integrated subtree snapshots** — tracked module trees inside the Dolibarr fork.

Normal flow:

```text
standalone plugin repo
    -> feature branch
    -> tests/review
    -> canonical plugin branch
    -> git subtree pull into Dolibarr integration branch
    -> integrated/sandbox tests
    -> production
    -> live checkout fast-forward
```

A subtree is not an independent second development source. Do not modify the standalone repository and the integrated copy independently and later attempt to reconcile two histories.

Nested `.git` directories inside the Dolibarr tree are prohibited. A directory is either part of the Dolibarr repository or an integrated subtree.

Emergency subtree-first edits are exceptional. Export them back to the canonical module repository before continuing normal development.

## 3. Branch roles in vanyolai/dolibarr

### `production`

The current deployable integration branch. It contains Dolibarr 23 plus accepted local core changes and accepted plugin subtree snapshots.

Production deployment must follow this branch.

### `23.0`

The Dolibarr-23 synchronization/reference line used to track and reason about upstream 23.x. Do not confuse it with upstream `develop`, and do not compare production requirements against `develop` unless that is explicitly the task.

### Topic branches

Use focused branches:

```text
feature/<topic>
fix/<topic>
integration/<topic>
test/<topic>
docs/<topic>
```

Use `backup/*` only as temporary safety points. An `audit/*` branch is a historical reference, not a source to merge wholesale.

Never merge a stale branch just because it contains one useful change. Rebuild or cherry-pick the smallest validated delta onto the current intended base.

## 4. Production checkout policy

The live checkout is deployment-only.

Normal state:

```bash
cd /mnt/applications/data/production/dolibarr
git status -sb
```

It must be clean: no anonymous local patches, no untracked PHP files, no nested repositories.

Configure:

```bash
git config pull.ff only
```

Normal update:

```bash
cd /mnt/applications/data/production/dolibarr
git status -sb
git fetch origin --prune
git switch production
git pull --ff-only
```

A failed fast-forward is a safety signal. Investigate the divergence. Do not hide it with an automatic merge or rebase.

Do not routinely use:

```bash
git pull --rebase
git reset --hard origin/production
git push --force
```

A hard reset is a recovery operation and requires a deliberate backup/audit point.

## 5. Development worktrees

Substantial work should happen outside the production checkout.

Example:

```bash
cd /mnt/applications/data/production/dolibarr
git fetch origin --prune

git worktree add \
  -b integration/example \
  /mnt/applications/data/production/dolibarr-example \
  origin/production
```

After integration:

```bash
git worktree remove /mnt/applications/data/production/dolibarr-example
git branch -d integration/example
```

Never force-remove a worktree before reviewing uncommitted changes.

## 6. Git message convention: always avoid unnecessary editor prompts

Whenever Git supports an explicit commit message, use `-m`.

Merge:

```bash
git merge feature/example \
  -m "Merge feature/example"
```

Subtree pull:

```bash
git subtree pull \
  --prefix=htdocs/custom/example \
  https://github.com/vanyolai/dolibarr-example.git \
  main \
  --squash \
  -m "Update Example subtree"
```

This is a project convention: routine integration commands must not require repeatedly leaving `nano`.

## 7. Subtree rules

Before every subtree operation verify:

- canonical source repository;
- source branch/ref;
- Dolibarr prefix;
- historical squash/unsquash mode;
- intended source version/commit;
- test/acceptance status of the source;
- current consumer branch.

Never switch squash mode by guesswork.

### Squashed subtree

```bash
git subtree add \
  --prefix=htdocs/custom/example \
  https://github.com/vanyolai/dolibarr-example.git \
  main \
  --squash \
  -m "Add Example subtree"
```

Update:

```bash
git subtree pull \
  --prefix=htdocs/custom/example \
  https://github.com/vanyolai/dolibarr-example.git \
  main \
  --squash \
  -m "Update Example subtree"
```

### Unsquashed subtree

Use the same commands without `--squash`, still with `-m`.

### Emergency export from consumer back to plugin

```bash
git subtree split \
  --prefix=htdocs/custom/example \
  -b example-export

git push example example-export:<target-branch>
```

Review the split history before making it canonical.

## 8. Core patch or external module?

Prefer an external module when hooks, triggers, tabs, APIs, substitutions, document models or public Dolibarr classes can provide the feature.

Core modification is justified only when:

- it is a deliberate upstream backport;
- the required extension point does not exist;
- the behavior is genuinely core behavior;
- an external implementation would require fragile replacement/copying of core pages.

Every core delta should be small, traceable and removable during a future Dolibarr upgrade.

For an upstream backport, record the upstream PR and commit. Never merge broad `develop` history into the Dolibarr-23 production line to obtain one feature.

## 9. Native module structure

Typical module root:

```text
htdocs/custom/mymodule/
├── admin/
├── ajax/
├── api/
├── class/
├── core/
│   ├── modules/
│   ├── substitutions/
│   └── triggers/
├── langs/
│   ├── en_US/
│   └── hu_HU/
├── lib/
├── sql/
├── tests/
└── README.md
```

Create only directories actually needed.

The module descriptor belongs under:

```text
core/modules/modMyModule.class.php
```

It owns module ID/version, dependencies, permissions, menus/tabs, hooks, triggers, APIs, document models, constants, cron jobs and module registration.

## 10. Hooks

Prefer hooks over core patches.

Typical implementation:

```text
class/actions_mymodule.class.php
```

Register only required contexts. Hook code should coordinate with reusable domain/service classes and should not become a copied version of a core page controller.

Avoid replacing large native HTML fragments when a focused hook output/action is sufficient.

## 11. Triggers

Typical location:

```text
core/triggers/interface_99_modMyModule_MyModuleTriggers.class.php
```

Triggers should:

- validate event context;
- call reusable domain logic;
- return proper Dolibarr success/error values;
- respect the transaction lifecycle of the calling core object.

Do not start independent transactions from a trigger without understanding whether Dolibarr already owns one.

## 12. Object APIs and business logic

Use native Dolibarr objects whenever an API exists:

```php
$object->fetch($id);
$object->create($user);
$object->update($user);
$object->delete($user);
```

Use class constants for statuses instead of magic integers.

Direct SQL against Dolibarr core business tables is exceptional and requires a documented reason.

Business rules belong in object/service/provider classes, not in page controllers, hooks or triggers.

## 13. Request handling and authorization

Do not normally consume raw `$_GET`, `$_POST` or `$_REQUEST`.

Use:

```php
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
```

Choose the narrowest filter.

Mutations should normally require POST and Dolibarr CSRF protection. Re-check permissions server-side. Hiding a button is never authorization.

Never trust entity/object IDs received from the browser without loading and validating the object in the current entity.

## 14. Permissions

Define rights in the descriptor. Separate read, edit/create, delete and high-impact operations such as validate/post/stock/accounting when appropriate.

Actions that create payments, bank lines, stock movements or other irreversible business effects should use explicit permissions.

## 15. Database rules

Use `MAIN_DB_PREFIX`, never hard-code `llx_` in PHP SQL.

Use the Dolibarr DB abstraction for escaping and dates:

```php
$db->escape();
$db->idate();
$db->jdate();
```

Respect `entity` everywhere the module owns entity-scoped data.

Multi-step atomic operations use transactions, while respecting nested DoliDB transaction semantics around native object APIs.

Do not directly mutate core stock/accounting/payment tables when a native business object exists.

## 16. Schema and migrations

Schema and upgrade logic must be:

- idempotent;
- additive where practical;
- safe on existing installations;
- explicit about data migration;
- tested both on fresh installation and upgrade/reactivation.

A module disable/enable cycle is not a substitute for migration design.

Schema changes require a version review/bump and preservation tests for existing data.

## 17. Dates and audit chronology

Prefer:

```php
dol_mktime();
dol_print_date();
$db->idate();
$db->jdate();
```

Distinguish business-effective dates from technical recorded timestamps. Correcting an effective date must not falsify event chronology.

## 18. Files, paths and URLs

Prefer:

```php
DOL_DOCUMENT_ROOT
dol_buildpath()
dol_mkdir()
dol_sanitizeFileName()
```

Avoid brittle relative path ladders and hard-coded module URLs when Dolibarr provides helpers.

Use native document/link mechanisms for attachments and related documents whenever practical.

## 19. Native UI conventions

Custom UI should behave like Dolibarr.

Reuse core form/UI helpers, for example:

```php
$form->selectDate();
$form->editfieldkey();
$form->editfieldval();
setEventMessages();
img_picto();
dol_print_date();
```

Prefer native cards, tabs, list tables, status badges, linked-object blocks, document blocks and confirmation flows.

Do not create a private mini-framework or broad custom CSS layer to solve problems that native markup already solves.

## 20. Language keys and translations

All user-facing module text should normally use language keys.

Bad:

```php
print 'Warranty expiration';
```

Preferred:

```php
print $langs->trans('WarrantyExpiration');
```

Module language files:

```text
langs/en_US/mymodule.lang
langs/hu_HU/mymodule.lang
```

Reuse a core language key only when it means exactly the same thing. Module-specific keys should be semantic and collision-resistant, preferably module-prefixed when generic naming could collide.

Never define a plugin key that silently overrides a core key with different semantics.

Do not construct plurals with English suffix rules. Hungarian and English grammar must be independent.

Every new/changed user-facing key should be represented in both `en_US` and `hu_HU` unless there is an explicit reason not to.

## 21. Email templates and substitutions

Use Dolibarr's native email composition and substitution mechanisms.

Module substitutions normally belong under:

```text
core/substitutions/functions_mymodule.lib.php
```

Avoid ad-hoc template engines and long `str_replace()` chains where the native substitution system applies.

Generated business PDFs that belong with an email should be attached through the normal formmail/document workflow.

## 22. Document/PDF models

Use normal Dolibarr model structure and document-generation lifecycle.

External model discovery should use the supported `module_parts['models']` mechanism.

When deriving a custom model from a core model, document the upstream base and keep the delta narrow.

PDF acceptance should cover:

- pagination;
- header/footer;
- watermark/status;
- localization;
- amount/currency rendering;
- line wrapping;
- notes/images;
- regeneration behavior;
- layer ordering.

## 23. Linked objects

Use native related-object facilities and correct Dolibarr element names.

Do not create a second relationship table if `element_element`, a native FK or the normal link API represents the relation.

A relation that only looks correct in custom HTML but is invisible to native related-object rendering is incomplete.

## 24. Stock, shipment and LOT/SN rules

Products, warehouses, lots, serial numbers and stock movements remain core Dolibarr concepts.

Do not directly write stock movement tables.

A service vehicle should normally be modeled as a normal Dolibarr warehouse, not a parallel inventory subsystem.

Use native shipment and batch/serial structures. Respect Dolibarr configuration governing when shipment stock movement occurs.

Physical movements must be traceable and retry-safe/idempotent.

## 25. Accounting and payment rules

Financial posting is a separate gate from import/matching.

Preferred flow:

```text
acquire/import
 -> normalize
 -> classify/match
 -> human review
 -> preview
 -> explicit confirmation
 -> native Dolibarr object/API
 -> audit link
```

Never silently validate or post an invoice/payment merely because parsing or matching succeeded.

## 26. Tests and evidence levels

Do not call syntax lint functional testing.

Evidence ladder:

1. `php -l` / syntax checks;
2. `git diff --check`;
3. deterministic unit/smoke tests;
4. DB-backed integration tests;
5. authenticated HTTP/UI tests;
6. representative sandbox acceptance;
7. production deployment.

Documentation must distinguish **implemented**, **syntax checked**, **integration tested**, **user accepted** and **production deployed**.

## 27. Minimum pre-merge checks

For core/integration work:

```bash
git status -sb
git diff --check
git diff --stat origin/production...HEAD
git log --oneline --decorate -10
```

Lint changed PHP files and run the module's documented test suite.

For a subtree update:

```bash
git show --stat --oneline HEAD
git status -sb
```

Verify that unexpected files outside the subtree prefix did not change.

## 28. Pull request expectations

A meaningful PR should state:

- purpose/scope;
- source branch and authoritative repository;
- schema impact;
- backward compatibility;
- security/permission impact;
- test evidence;
- acceptance still outstanding;
- upstream reference for backports;
- rollout/rollback notes.

Do not label code production-ready solely because it exists.

Force-push of the deploy branch is prohibited.

## 29. Documentation is part of development

Every standalone module should maintain a `DEVELOPMENT.md` containing:

- repository/subtree role;
- branch policy;
- supported baseline;
- current implementation state;
- active work;
- known risks/drift;
- next milestones in dependency order;
- acceptance gates;
- exact subtree integration command using `-m`.

Update it in the same change set when a meaningful milestone changes.

## 30. New-session bootstrap

At the start of a new development conversation:

1. inspect actual GitHub branch/PR state;
2. read this guide;
3. read `docs/VANYOLAI_PLUGIN_INTEGRATION.md`;
4. read the target module's `DEVELOPMENT.md`, README and focused status/design docs;
5. treat the standalone plugin repository as authoritative unless the integration matrix documents an explicit exception;
6. do not develop in production;
7. prefer native Dolibarr hooks/classes/helpers/UI/translations;
8. keep plugin source, Dolibarr integration and deployment as separate gates;
9. update repository documentation when the milestone changes;
10. never assume an old chat summary is newer than Git.

The objective is not merely working code. The objective is Dolibarr-native, reviewable, testable, reproducible and upgradeable code.
