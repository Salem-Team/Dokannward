#!/usr/bin/env python3
"""Stamp Dokan Ward release version into package.json and ROOTK tenant env."""

from __future__ import annotations

import json
import re
import sys
from pathlib import Path


def main() -> int:
    if len(sys.argv) != 3:
        print("Usage: stamp-release-version.py <app-root> <version>", file=sys.stderr)
        return 2

    remote = Path(sys.argv[1])
    ver = sys.argv[2]

    for rel in [".rootk/tenant.env", ".rootk/deployment.env"]:
        path = remote / rel
        if not path.is_file():
            continue
        text = path.read_text()
        for key in ("ROOTK_RELEASE_VERSION", "NEXT_PUBLIC_ROOTK_RELEASE_VERSION"):
            pat = rf"(?m)^{key}='[^']*'"
            if re.search(pat, text):
                text = re.sub(pat, f"{key}='{ver}'", text)
            else:
                text = text.rstrip() + f"\n{key}='{ver}'\n"
        path.write_text(text)
        print(f"stamped {path} -> {ver}")

    pkg = remote / "package.json"
    if pkg.is_file():
        data = json.loads(pkg.read_text())
        data["version"] = ver
        if data.get("name") in ("zibra", "dokannward", None):
            data["name"] = "dokannward"
        pkg.write_text(json.dumps(data, indent=2) + "\n")
        print(f"package.json version -> {ver}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
