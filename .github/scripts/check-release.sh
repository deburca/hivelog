#!/usr/bin/env bash
# Checks a published release without re-running the test suite (task 0217).
#
#   check-release.sh <tag> <commit sha> <owner/repo>
#
# 1. Every .info.yml (core and each submodule) carries the tag as its version. A release that
#    forgets one of the six is the mistake this catches.
# 2. The CI run for that commit, made when it was pushed to main, has not failed. The release
#    event fires after the release is published, so this cannot stop it; it makes a red main
#    commit show on the release too. A run still in progress or never started is a warning,
#    not a failure: the release is usually created while the push run is still going.
set -euo pipefail

tag="${1:?tag}"
sha="${2:?commit sha}"
repo="${3:?owner/repo}"
failed=0

for file in hivelog.info.yml modules/*/*.info.yml; do
  version=$(sed -n "s/^version: *'\{0,1\}\([^' ]*\)'\{0,1\} *$/\1/p" "$file")
  if [ "$version" = "$tag" ]; then
    echo "ok: $file is $version"
  else
    echo "::error file=$file::version is '${version:-none}' but the tag is '$tag'"
    failed=1
  fi
done

runs=$(gh run list --repo "$repo" --workflow ci.yml --commit "$sha" --event push \
  --json status,conclusion --limit 20)
successes=$(jq '[.[] | select(.conclusion == "success")] | length' <<<"$runs")
failures=$(jq '[.[] | select(.conclusion == "failure")] | length' <<<"$runs")
pending=$(jq '[.[] | select(.status != "completed")] | length' <<<"$runs")

if [ "$failures" -gt 0 ] && [ "$successes" -eq 0 ]; then
  echo "::error::CI failed for commit $sha on its push to main"
  failed=1
elif [ "$successes" -gt 0 ]; then
  echo "ok: CI passed for commit $sha"
elif [ "$pending" -gt 0 ]; then
  echo "::warning::CI for commit $sha is still running; check it finishes green"
else
  echo "::warning::no CI run found for commit $sha (a docs-only commit is skipped by design)"
fi

exit "$failed"
