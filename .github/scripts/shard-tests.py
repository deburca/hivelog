#!/usr/bin/env python3
"""Splits the module's Kernel and Unit tests into shards that CI runs in parallel (task 0218).

    shard-tests.py <module root> <i>/<n>      # prints shard i of n: test files, space separated
    shard-tests.py <module root> --check      # proves that no shard count from 1 to 8 loses a test

Tests are found the way CI found them before sharding: every `*/tests/src/Kernel` and
`*/tests/src/Unit` directory under the module root (the core's own and each submodule's), so a new
submodule's tests join a shard on their own (ADR-0098 section 7: a fixed list would silently skip
them). A file is a `*Test.php`; any other file that declares tests is an error, because PHPUnit's
default suffix would never run it.

Shards are balanced, not alphabetical: each file gets a cost (its test methods, doubled for a class
that runs every test in a separate process, nearly nothing for a unit test, which needs no Drupal),
then the files are dealt out largest first, each to the shard with the least so far. The result is
deterministic, so a failed shard can be re-run and a local run matches CI.
"""
import os
import re
import sys
from pathlib import Path

SKIP_DIRS = {".git", "vendor", "node_modules", "demo", "docs"}
KINDS = ("Kernel", "Unit")


def test_dirs(root: Path):
    for here, dirs, _ in os.walk(root):
        dirs[:] = sorted(d for d in dirs if d not in SKIP_DIRS)
        path = Path(here)
        if path.parts[-3:-1] == ("tests", "src") and path.name in KINDS:
            yield path


def cost(path: Path) -> float:
    text = path.read_text()
    methods = len(re.findall(r"public function test\w+", text))
    weight = max(methods, 1)
    if "RunTestsInSeparateProcesses" in text:
        weight *= 2
    if "/Unit/" in str(path):
        weight *= 0.05
    return weight


def all_tests(root: Path):
    files, stray = [], []
    for directory in test_dirs(root):
        for path in sorted(directory.rglob("*.php")):
            if path.name.endswith("Test.php"):
                files.append(path)
            elif re.search(r"public function test\w+", path.read_text()):
                stray.append(path)
    if stray:
        sys.exit("Tests in files PHPUnit will not run (name must end in Test.php): "
                 + ", ".join(str(p) for p in stray))
    return files


def shards(files, count):
    bins = [[0.0, i, []] for i in range(count)]
    for path in sorted(files, key=lambda p: (-cost(p), str(p))):
        lightest = min(bins, key=lambda b: (b[0], b[1]))
        lightest[0] += cost(path)
        lightest[2].append(path)
    return bins


def main(argv):
    if len(argv) != 3:
        sys.exit(__doc__)
    root = Path(argv[1])
    files = all_tests(root)
    if not files:
        sys.exit(f"No tests found under {root}")
    if argv[2] == "--check":
        for count in range(1, 9):
            dealt = [p for b in shards(files, count) for p in b[2]]
            assert sorted(dealt) == sorted(files), f"{count} shards lose or repeat a test file"
        print(f"ok: {len(files)} test files, none lost or repeated for 1 to 8 shards")
        return
    index, count = (int(x) for x in argv[2].split("/"))
    if not 1 <= index <= count:
        sys.exit(f"shard {argv[2]}: need 1 <= i <= n")
    print(" ".join(str(p) for p in sorted(shards(files, count)[index - 1][2])))


if __name__ == "__main__":
    main(sys.argv)
