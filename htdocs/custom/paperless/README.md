# Paperless-ngx integration for Dolibarr 23

This external Dolibarr module routes PDF uploads from standard linked-document pages to Paperless-ngx and stores a Dolibarr external link instead of keeping a second local PDF copy.

## Workflow

1. A user opens a Dolibarr object's **Documents / Linked files** tab and uploads one or more PDF files.
2. The `globalcard` `doActions` hook intercepts PDF-only submissions before `core/actions_linkedfiles.inc.php` stores them locally.
3. Each PDF is uploaded server-side to Paperless-ngx using `POST /api/documents/post_document/` with token authentication and API version 10.
4. Paperless returns an asynchronous consumption task UUID.
5. Dolibarr creates a normal `Link` object for the current Dolibarr object. The URL initially points to `paperless/open.php?task=...`.
6. When the link is opened, the resolver queries `/api/tasks/?task_id=...`. Once Paperless reports `SUCCESS` and a `related_document`, the resolver redirects to the Paperless document detail page and rewrites the Dolibarr link to use the stable Paperless document ID for future clicks.

The API token remains on the Dolibarr server and is never embedded in the linked URL.

## Behaviour

- PDF-only upload: archived in Paperless-ngx; Dolibarr stores a link.
- Non-PDF upload: unchanged Dolibarr core behaviour; file is stored locally.
- Mixed PDF + non-PDF upload in one submission: unchanged Dolibarr core behaviour for the whole batch, with a warning. Upload PDFs separately if they should be archived in Paperless.
- Deleting the Dolibarr link does **not** delete the Paperless document.
- Paperless metadata classification (correspondent, document type, tags, OCR, workflows) is left to Paperless.

## Installation

The module lives under `htdocs/custom/paperless`. Enable **Paperless-ngx integration** in Dolibarr's Modules/Application setup page, then open its configuration page.

Configure:

- **Paperless API base URL**: URL reachable from the Dolibarr PHP container/server, without `/api` suffix.
- **Paperless browser URL**: optional browser-facing URL if it differs from the server-side API URL.
- **Paperless API token**: token for a Paperless user allowed to create and read documents/tasks.
- **Archive PDF uploads in Paperless**: enables/disables interception.
- HTTP timeout and resolver wait time.

## Paperless notes

Paperless document ingestion is asynchronous. A successful upload only means that consumption has started; the final document ID is obtained from the task endpoint.

Paperless normally accepts duplicate documents. If `PAPERLESS_CONSUMER_DELETE_DUPLICATES=true` is enabled, a duplicate can end as a failed consumption task without a reliable `related_document`; in that configuration the resolver will show the Paperless task error instead of guessing an existing document ID from human-readable error text.

## Scope

This is intentionally a minimal first integration. Possible follow-ups include:

- map Dolibarr object types to Paperless document types/tags;
- add a Dolibarr backlink as a Paperless URL custom field;
- configurable title patterns using object reference/customer/supplier;
- explicit "Send to Paperless" action for already existing local Dolibarr files;
- optional synchronization of deletions or metadata.
