# Vanyolai Dolibarr Development Guide

> Canonical engineering rules for the Vanyolai Dolibarr 23 environment.
>
> Last reviewed: 2026-10-19
>
> This document is intentionally suitable as the starting context for a new development session. Read it before changing Dolibarr core or any embedded custom module.

## 1. Purpose and sources of truth

The production application is based on the `vanyolai/dolibarr` fork of Dolibarr 23.

The canonical production branch is:

```text
repository: vanyolai/dolibarr
branch:     23.0
```

The production checkout is deployment-only:

```text
/mnt/applications/data/production/dolibarr
```

Normal production state must be clean:

```bash
git status -sb
# ## 23.0...origin/23.0
```

Production must not become the only place where a fix exists. There must be no long-lived untracked PHP files, anonymous local patches, nested repositories or manually copied plugin versions.

For a standalone custom module, the standalone module repository is the authoritative development source. The Dolibarr fork is the consumer of that source through a Git subtree. Core/backport work is authoritative in the Dolibarr fork itself.

The official upstream repository is `Dolibarr/dolibarr`. Upstream `develop` is not a substitute for our `23.0` production branch. Required upstream changes are backported deliberately.

## 2. Repository boundaries

There are three different concerns and they must not be mixed:

1. **Dolibarr core fork** — `vanyolai/dolibarr`.
2. **Standalone external-module repositories** — one repository per plugin/module.
3. **Consumer subtrees inside Dolibarr** — checked-in copies under `htdocs/custom/<module>` or another explicit prefix.

A module repository contains the detailed development history. The Dolibarr repository contains the integrated snapshot needed to deploy the complete application.

A subtree is not a second canonical source. Do not develop independently on both sides and later try to reconcile two histories.

Normal direction:

```text
standalone module repository
        |
        | tested commit / release branch
        v
git subtree pull
        |
        v
vanyolai/dolibarr:23.0 integration
        |
        v
production checkout
```

Emergency edits made in an integrated subtree must be exported back to the standalone repository before normal development continues. This is exceptional, not the default workflow.

Nested `.git` directories inside the Dolibarr working tree are prohibited. A module is either a subtree or an ordinary tracked directory owned by the same repository.

## 3. Branch roles

### Dolibarr fork

`23.0` is the canonical deployable branch.

Use focused topic branches:

```text
feature/<topic>
fix/<topic>
integration/<topic>
test/<topic>
```

Use `backup/*` only for short-lived safety snapshots. Delete obsolete backup branches after their content is proven redundant or preserved by an explicit audit branch.

An audit branch is a historical reference, not an integration source.

Do not merge a stale feature branch wholesale simply because it contains one useful commit. Rebase/rebuild/cherry-pick the smallest validated change onto the current `23.0` when appropriate.

### Module repositories

`main` should normally be the stable integration branch consumed by Dolibarr.

Exceptions must be documented explicitly. WarrantySvc currently has a dedicated publish branch; FieldService currently has an integration drift that must be resolved. See `docs/VANYOLAI_PLUGIN_INTEGRATION.md`.

Feature branches should be derived from the current documented development baseline and should not silently become the subtree source.

## 4. Production update policy

Configure fast-forward-only pull:

```bash
cd /mnt/applications/data/production/dolibarr
git config pull.ff only
```

Normal production update:

```bash
cd /mnt/applications/data/production/dolibarr
git status -sb
git fetch origin --prune
git switch 23.0
git pull --ff-only
```

A failed fast-forward is a safety signal. Investigate divergence instead of hiding it with an automatic merge or rebase.

Do not use these as routine deployment commands:

```bash
git pull --rebase
git reset --hard origin/23.0
git push --force
```

A hard reset is a recovery operation and requires a deliberate backup/audit point first.

## 5. Development worktrees

Do not perform substantial development in the production checkout.

A clean worktree can be created from the latest canonical branch:

```bash
cd /mnt/applications/data/production/dolibarr
git fetch origin --prune

git worktree add \
  -b integration/example \
  /mnt/applications/data/production/dolibarr-example \
  origin/23.0
```

After the work is integrated:

```bash
git worktree remove /mnt/applications/data/production/dolibarr-example
git branch -d integration/example
```

Never force-remove a worktree containing uncommitted work without first reviewing it.

## 6. Git commit and editor convention

Supply commit messages explicitly whenever the command supports it. This avoids opening `nano` or another editor during routine integration.

Examples:

```bash
git merge feature/example \
  -m "Merge feature/example"
```

and, critically for subtrees:

```bash
git subtree pull \
  --prefix=htdocs/custom/example \
  https://github.com/vanyolai/dolibarr-example.git \
  main \
  --squash \
  -m "Update Example subtree"
```

The `-m` convention is part of the normal workflow.

## 7. Subtree rules

Before updating a subtree, verify:

- authoritative repository;
- authoritative source branch;
- prefix in the Dolibarr tree;
- whether historical integration is squashed or unsquashed;
- exact source version expected;
- whether the source branch has passed its acceptance gate.

Never change squash mode because a command copied from another module looks convenient.

### Squashed subtree

Initial import:

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

Initial import/update omit `--squash` but still supply `-m`.

### Exporting an emergency subtree change

If a change genuinely had to be made inside Dolibarr first:

```bash
git subtree split \
  --prefix=htdocs/custom/example \
  -b example-export

git push example example-export:<target-branch>
```

Review the exported history before making that branch canonical.

## 8. Core changes versus external modules

Prefer an external module when the behavior can be implemented through Dolibarr's extension mechanisms.

Modify core only when at least one of these applies:

- the change is an upstream bug fix/backport;
- the required extension point does not exist;
- the functionality is naturally core behavior rather than module behavior;
- implementing it externally would require fragile replacement/copying of core pages.

Every core delta should be small, documented and easy to reevaluate during a future Dolibarr upgrade.

When backporting upstream work, record the upstream PR and commit in the commit/PR description.

Do not merge broad chunks of upstream `develop` into `23.0` to obtain one feature.

## 9. Dolibarr-native module structure

Use the native external-module layout. Typical structure:

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

Only create directories the module actually needs.

### Module descriptor

The descriptor belongs under:

```text
core/modules/modMyModule.class.php
```

Use it for:

- module ID and version;
- family and dependencies;
- permissions;
- menus and tabs;
- hooks, triggers, APIs and document models;
- constants/configuration;
- cron jobs;
- data directories.

Do not hide module registration logic in unrelated page controllers.

## 10. Hooks

Use supported hooks before patching core.

Typical class:

```text
class/actions_mymodule.class.php
```

Register only the contexts actually required by the module.

Hook code should coordinate with domain/service classes; it should not become a second copy of a core page controller.

Avoid replacing large blocks of native HTML if a small hook output or action is sufficient.

## 11. Triggers

Use Dolibarr triggers for lifecycle events that are naturally event-driven.

Typical path:

```text
core/triggers/interface_99_modMyModule_MyModuleTriggers.class.php
```

A trigger should:

- validate event context;
- call a domain/service method;
- return a correct Dolibarr status/error;
- participate correctly in the transaction owned by the calling object.

Do not open independent transactions inside a trigger without understanding the caller's transaction lifecycle.

Complex business logic belongs in reusable classes, not in the trigger file.

## 12. Object and domain APIs

Use native Dolibarr business objects whenever an API exists.

Prefer:

```php
$object->fetch($id);
$object->create($user);
$object->update($user);
$object->delete($user);
```

over direct writes to core business tables.

Use object status constants instead of magic integers.

Bad:

```php
if ($object->status === 1) {
    // ...
}
```

Preferred:

```php
if ($object->status === SomeObject::STATUS_VALIDATED) {
    // ...
}
```

Direct SQL against core tables is an exception that needs a documented reason, particularly when a Dolibarr object API exists.

## 13. Request input and validation

Do not read untrusted request data directly through `$_GET`, `$_POST` or `$_REQUEST` in normal module code.

Use Dolibarr helpers:

```php
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
```

Choose the narrowest appropriate filter.

Mutating actions should normally require POST and Dolibarr CSRF protections. Check permissions again in the action handler; hiding a button is not authorization.

Do not trust object/entity IDs sent by the browser without loading the object in the active entity and checking access.

## 14. Permissions

Define module rights in the module descriptor.

Distinguish read, create/write, delete, validate/close/post and other high-risk operations when their business impact differs.

Server-side permission checks are mandatory.

For actions that create accounting, stock or payment records, use a dedicated permission instead of assuming generic edit rights are sufficient.

## 15. Database rules

Use:

```php
MAIN_DB_PREFIX
```

instead of hard-coded `llx_` in PHP SQL.

Escape string data through the Dolibarr DB abstraction.

Use:

```php
$db->idate();
$db->jdate();
```

for DB date conversion.

Respect `entity` on module-owned business records. Every query that can cross entities must be reviewed explicitly.

Use transactions for a multi-step business operation that must be atomic:

```php
$db->begin();

try {
    // domain operations
    $db->commit();
} catch (Throwable $e) {
    $db->rollback();
    throw $e;
}
```

Understand nested DoliDB transaction behavior before wrapping native object calls.

Do not directly mutate Dolibarr core business tables where native domain APIs exist.

## 16. Schema and migration policy

Initial schema belongs in `sql/`.

Upgrade logic must be:

- additive where practical;
- idempotent;
- safe to run again;
- explicit about data migration;
- tested against an existing installation, not only a fresh DB.

Do not place destructive one-shot migration scripts in a directory that Dolibarr automatically scans on each module activation.

A module disable/enable cycle is not an acceptable substitute for understanding migration behavior.

Schema changes require:

- module version review/bump;
- migration/upgrade path;
- test of fresh install;
- test of upgrade/reactivation;
- preservation of existing business data.

## 17. Dates and time zones

Use Dolibarr helpers rather than ad-hoc PHP date formatting:

```php
dol_mktime();
dol_print_date();
$db->idate();
$db->jdate();
```

Distinguish business dates from technical timestamps.

If an audit log records both the time an event occurred and the time it was entered, preserve both concepts explicitly. Do not rewrite audit chronology when correcting a business-effective date.

## 18. Files, document paths and URLs

Use Dolibarr path helpers and constants:

```php
DOL_DOCUMENT_ROOT
dol_buildpath()
dol_mkdir()
dol_sanitizeFileName()
```

Avoid brittle `../../..` paths and hard-coded `/custom/mymodule/` URLs where Dolibarr provides a module-aware helper.

User-provided filenames must be sanitized.

For attachments and links, prefer Dolibarr's native document/link mechanisms so permissions and related-object behavior remain consistent.

## 19. UI conventions

Custom pages should look and behave like Dolibarr.

Reuse native helpers and classes, for example:

```php
$form->selectDate();
$form->editfieldkey();
$form->editfieldval();
setEventMessages();
img_picto();
dol_print_date();
```

Prefer standard card headers, tabs, list tables, status badges, linked-object rendering and document blocks.

Do not solve a layout issue by creating an isolated mini-framework or large custom CSS layer when native markup already supports the requirement.

Use Dolibarr confirmation dialogs and token/POST conventions for destructive actions.

## 20. Language keys and translations

All user-facing module text should normally use translation keys.

Bad:

```php
print 'Warranty expiration';
```

Preferred:

```php
print $langs->trans('WarrantyExpiration');
```

Standalone module language files normally live at:

```text
langs/en_US/mymodule.lang
langs/hu_HU/mymodule.lang
```

Load module languages through Dolibarr's language system.

### Key policy

Reuse an existing Dolibarr core key when it already expresses exactly the intended concept.

Module-specific keys should be semantically named and, where collision risk exists, module-prefixed.

Do not create generic keys such as `SetupSaved`, `CreateShipment` or `ReceptionValidated` in a module when they can override a core key with a different meaning.

A stable key describes a concept, not one exact sentence.

Do not build plurals by appending English suffixes. Hungarian and English grammar must remain independent.

Every new/changed key should be added to both `en_US` and `hu_HU` unless there is an explicit reason not to.

## 21. Email templates and substitutions

Use Dolibarr's email/template mechanisms.

Module-specific variables should normally be exposed through the substitution infrastructure, for example:

```text
core/substitutions/functions_mymodule.lib.php
```

Do not implement a parallel ad-hoc template engine with chained `str_replace()` calls when native substitutions can do the job.

Use the normal Dolibarr email form/workflow when practical. Generated business PDFs that are expected to accompany the message should be attached through the native formmail/document mechanism rather than through a second custom sender.

## 22. Document/PDF models

Put document models in the normal Dolibarr model structure.

Use the standard document-generation lifecycle and document block. Preserve the native object's output directory rules.

For external models, register model paths via `module_parts['models']` or the supported Dolibarr mechanism.

If a model is based on a core model, keep the customization narrow and document the upstream base so future upgrades can be diffed intelligently.

PDF acceptance must include pagination, headers/footers, status watermarks, localization, amounts/currency, line wrapping and regenerated-document behavior.

## 23. Linked objects

Use Dolibarr's native related-object facilities and correct element names.

Do not invent a second relationship table when `element_element` or a proper FK/link API models the relation.

Element names must match the actual object's `$element` semantics. A visually working custom link that native related-object rendering cannot understand is not a complete integration.

## 24. Stock, shipments and serial/lot handling

Use native stock, shipment and batch/serial facilities.

Do not write directly to stock movement tables.

Warehouse, LOT/SN and movement timing must obey existing Dolibarr configuration unless the module explicitly owns a separate business rule.

Stock operations must be idempotent or protected against duplicate retries.

Every physical movement needs traceability to its business object.

## 25. Accounting and payments

Posting financial effects is higher risk than matching or previewing.

Prefer a staged workflow:

```text
import/acquire
 -> normalize
 -> match/classify
 -> preview
 -> explicit user confirmation
 -> native Dolibarr business object
 -> audit link
```

Do not silently validate/post merely because a source document can be parsed.

Use native payment/invoice/accounting objects and preserve audit history.

## 26. Testing levels

Do not treat PHP syntax success as functional verification.

Use progressively stronger evidence:

1. `php -l` / static syntax checks;
2. `git diff --check`;
3. deterministic unit/smoke tests;
4. database-backed integration tests;
5. authenticated HTTP/UI workflow tests where relevant;
6. representative sandbox acceptance;
7. production deployment only after the prior gate is explicitly accepted.

A status document must distinguish:

- implemented;
- syntax checked;
- integration tested;
- sandbox/user accepted;
- production deployed.

## 27. Minimum pre-merge checks

For Dolibarr work:

```bash
git status -sb
git diff --check
git diff --stat origin/23.0...HEAD
git log --oneline --decorate -10
```

Lint changed PHP files.

For subtree updates:

```bash
git show --stat --oneline HEAD
git status -sb
```

For a module, run its documented smoke/integration suite and update its `DEVELOPMENT.md` status/roadmap when the milestone changes.

## 28. Pull requests and release gates

A PR should explain:

- purpose and scope;
- authoritative source/branch;
- schema impact;
- backward compatibility;
- security/permission impact;
- test evidence;
- sandbox acceptance still required;
- upstream reference for backports;
- rollout/rollback considerations.

Do not mark production-ready because code exists.

Canonical `23.0` must never be force-pushed.

A direct fast-forward integration is only acceptable after verifying:

```bash
git merge-base --is-ancestor origin/23.0 HEAD
```

and after the branch has passed the intended review/test gate.

## 29. Documentation is part of the change

Each standalone module should keep a `DEVELOPMENT.md` that records:

- repository and subtree role;
- supported Dolibarr/PHP baseline;
- branch policy;
- current implementation state;
- active PRs/feature branches;
- known integration drift;
- next milestones in dependency order;
- acceptance gates;
- exact subtree update command.

Update that file when a meaningful milestone changes. Do not depend on chat history as the only project record.

## 30. New-session bootstrap

When starting a new coding conversation/session:

1. Read this guide.
2. Read `docs/VANYOLAI_PLUGIN_INTEGRATION.md`.
3. For a plugin, read that plugin repository's `DEVELOPMENT.md` and current README/CHANGELOG.
4. Inspect the actual branch/HEAD/PR state before making claims.
5. Treat the standalone module repo as authoritative unless the integration matrix documents an exception.
6. Do not modify production directly.
7. Prefer Dolibarr-native hooks, objects, helpers, translations and UI.
8. Keep source, integration and deployment as separate steps.
9. Update repository documentation when a milestone changes.
10. Never assume an old chat summary is newer than Git.

The objective is not merely working code. The objective is code that is Dolibarr-native, reviewable, testable, upgradeable and reproducible from Git.
