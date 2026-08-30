#!/usr/bin/env bash

set -euo pipefail

base_commit="${1:-}"
range="HEAD"

if [[ -n "$base_commit" ]] &&
    [[ "$base_commit" != "0000000000000000000000000000000000000000" ]] &&
    git cat-file -e "${base_commit}^{commit}" 2>/dev/null; then
    range="${base_commit}..HEAD"
fi

failed=0

while IFS= read -r commit; do
    if ! git show -s --format=%B "$commit" | grep -Eq '^Signed-off-by: .+ <[^>]+>$'; then
        printf 'Commit %s is missing a valid DCO sign-off.\n' "$commit" >&2
        failed=1
    fi
done < <(git rev-list --no-merges "$range")

if [[ "$failed" -ne 0 ]]; then
    exit 1
fi

printf 'All commits in %s have DCO sign-off.\n' "$range"
