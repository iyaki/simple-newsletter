#!/bin/bash
# Lefthook v2+ passes commit message file via LEFTHOOK_COMMIT_MSG_FILE env var
commit_msg=$(cat "$LEFTHOOK_COMMIT_MSG_FILE")
if ! echo "$commit_msg" | grep -qE "^(feat|fix|docs|style|refactor|test|chore)(\([^)]+\))?:"; then
  echo "Commit message must follow Conventional Commits format"
  echo "Example: feat(auth): add double-opt-in flow"
  exit 1
fi
