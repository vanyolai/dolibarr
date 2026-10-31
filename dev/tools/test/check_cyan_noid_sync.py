#!/usr/bin/env python3
"""Report Cyan NoID drift after integrating a Dolibarr 23 upstream update."""

import argparse
import difflib
from pathlib import Path
import sys


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument(
        "--root", type=Path, default=Path(__file__).resolve().parents[3],
        help="Dolibarr repository root (defaults to this checkout)",
    )
    args = parser.parse_args()
    directory = args.root / "htdocs/core/modules/propale/doc"
    try:
        upstream = (directory / "pdf_cyan.modules.php").read_text(encoding="utf-8")
        custom = (directory / "pdf_cyan_noid.modules.php").read_text(encoding="utf-8")
    except OSError as error:
        print(str(error), file=sys.stderr)
        return 2

    # Only these identity changes and the forced hidden reference are accepted.
    custom = custom.replace("pdf_cyan_noid", "pdf_cyan")
    custom = custom.replace("Cyan NoID", "Cyan")
    custom = custom.replace('$this->name = "cyan_noid";', '$this->name = "cyan";')
    hidden_reference = (
        "\t\t// This template intentionally never exposes the internal product/service reference.\n"
        "\t\t$hideref = 1;\n\n"
    )
    if custom.count(hidden_reference) != 1:
        print("FAIL: Cyan NoID must force the product reference to be hidden exactly once.", file=sys.stderr)
        return 1
    custom = custom.replace(hidden_reference, "", 1)

    if custom != upstream:
        print("FAIL: review the Cyan upstream changes before deploying Cyan NoID.", file=sys.stderr)
        sys.stdout.writelines(difflib.unified_diff(
            upstream.splitlines(keepends=True), custom.splitlines(keepends=True),
            fromfile="upstream Cyan", tofile="Cyan NoID (customizations removed)",
        ))
        return 1

    print("OK: Cyan NoID matches the integrated Cyan model, with product references hidden.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
