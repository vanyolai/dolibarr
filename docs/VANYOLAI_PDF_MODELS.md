# Custom PDF models and upstream updates

Reviewed against Dolibarr `23.0` commit `6b461ab5bd5` on 2026-10-31.

## Cyan NoID proposal model

`htdocs/core/modules/propale/doc/pdf_cyan_noid.modules.php` is a copy of the
integrated core Cyan model. Its intentional differences are the model/class
identity, documentation name and forcing `$hideref = 1` in `write_file()`.
The product/service reference remains hidden even when the caller requests it.

The 2026-10-31 synchronization includes:

- upstream #38398: account for the rendered height of a long project title;
- upstream #42098: keep linked-object and shipping-address header shifts separate,
  so notes and the table clear the address frames;
- upstream #42260: restore price visibility after closing nested subtotal blocks;
- the current Cyan guard that excludes title/subtotal lines from the regular
  VAT-inclusive amount column.

After every upstream integration, run from the repository root:

```bash
python3 dev/tools/test/check_cyan_noid_sync.py
php -l htdocs/core/modules/propale/doc/pdf_cyan_noid.modules.php
```

The checker reads the two files in this checkout, performs no network requests
and writes nothing. Exit 0 means only the accepted customizations differ;
exit 1 means drift or a missing forced-hidden reference; exit 2 means a file
could not be read. It is a manual maintenance check, not a scheduled job or CI
workflow. It detects differences requiring review; it does not prove PDF layout.

Port relevant core changes on a topic branch, preserve the NoID differences,
and regenerate representative sandbox PDFs before production acceptance:

- an ordinary proposal with visible descriptions and hidden product references;
- a linked document, public note and shipping address;
- a long project title and a multipage product description;
- nested title/subtotal blocks followed by an ordinary priced line;
- VAT-inclusive columns enabled, checking normal lines and subtotal lines;
- draft watermark, header/footer, currency and language settings.

Already generated PDFs must be regenerated to use changed template code.

## Nautilus shipment models

The copies in WarrantyPeriod and WarrantySvc both extend core `pdf_espadon`.
They inherit its header and contact resolution; neither overrides `_pagehead()`.
Consequently upstream #42253 (missing origin reference/date) and #42326
(contacts of standalone shipments) reach both models through the updated core.
No duplicate backport is needed in these module files.

The two Nautilus implementations have different warranty responsibilities.
Do not replace one with the other. Any necessary module change belongs in its
canonical standalone repository before a subtree integration.

Sandbox checks should cover a shipment from an order and a standalone shipment
with its own SHIPPING contact, plus serial/LOT descriptions and warranty columns.

## Independent document models

CompletionCertificate and WarrantySvc's service/supplier-return PDFs are
independent models, not copied Cyan or Zenith implementations. The specific
upstream Cyan/Zenith patches must not be applied to them indiscriminately.

## Validation status

On 2026-10-31, the source synchronization check, PHP 8.3 syntax checks of
Cyan NoID and both Nautilus files, and `git diff --check` passed. The checker
was also verified to reject future upstream drift and a missing hidden-reference
override, and to report an unreadable source separately.
Representative database-backed PDF generation and visual acceptance have not
been performed in this development checkout and remain required for acceptance.
