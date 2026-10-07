#!/usr/bin/env python3
"""Refreshes .github/scripts/test-times.json, the measured seconds per test file that
shard-tests.py balances the CI shards with (task 0218).

    # download every shard's junit artifact from a run on main, then:
    gh run download <run id> --pattern 'junit-*' --dir /tmp/junit
    .github/scripts/update-test-times.py /tmp/junit/*/junit.xml

Each shard's PHPUnit step writes junit.xml (kept as a CI artifact for 14 days). A file is keyed by
its path inside the module (`tests/src/Kernel/HiveTest.php`, `modules/nanoprobe/tests/...`) and holds
the summed time of its tests. A file not in a run keeps its old value; new files fall back to the
estimate in shard-tests.py. Re-run it now and then (say after adding a large test class), because the
shards drift out of balance as the tests change. The split is always complete whatever the numbers
say; only the balance suffers.
"""
import json
import re
import sys
import xml.etree.ElementTree as ET
from collections import defaultdict
from pathlib import Path

OUT = Path(__file__).resolve().parent / "test-times.json"
MODULE_ROOT = re.compile(r"/modules/(?:contrib/)?hivelog/(.*)$")


def main(paths):
    if not paths:
        sys.exit(__doc__)
    seconds = defaultdict(float)
    for xml in paths:
        for case in ET.parse(xml).getroot().iter("testcase"):
            match = MODULE_ROOT.search(case.get("file", ""))
            if match:
                seconds[match.group(1)] += float(case.get("time", 0))
    if not seconds:
        sys.exit("No timings found: are these PHPUnit --log-junit files from the shard jobs?")
    times = json.loads(OUT.read_text()) if OUT.exists() else {}
    times.update({path: round(value, 1) for path, value in seconds.items()})
    OUT.write_text(json.dumps(dict(sorted(times.items())), indent=1) + "\n")
    total = sum(seconds.values())
    print(f"{len(seconds)} files, {total / 60:.1f} minutes of tests, written to {OUT.name}")
    for path, value in sorted(seconds.items(), key=lambda kv: -kv[1])[:8]:
        print(f"  {value:6.1f}s  {path}")


if __name__ == "__main__":
    main(sys.argv[1:])
