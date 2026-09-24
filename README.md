# Dolibarr Completion Certificate

Dolibarr 23 module for creating completion certificates from customer orders.

## MVP
- Adds **Create completion certificate** to validated customer orders.
- Copies order lines while allowing certified quantity per line.
- Stores an independent certificate with draft/validated status.
- Keeps source order and source order-line relations.
- Generates a printable PDF after validation.
- Supports Hungarian and English UI labels.

Module path: `htdocs/custom/completioncertificate`.

Current development branch: `feature/completion-certificate`.
