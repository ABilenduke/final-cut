#!/usr/bin/env bash
# Checks if any uncommitted changes (staged, unstaged, or untracked) touch
# config/structure files that CLAUDE.md documents. Used as a Claude Code Stop
# hook to remind about documentation updates.

set -euo pipefail

ROOT="$(git -C "$(dirname "$0")/.." rev-parse --show-toplevel 2>/dev/null)" || exit 0
cd "$ROOT"

# Patterns of files whose changes may make CLAUDE.md stale
# Use :(glob) pathspecs so patterns match files in subdirectories
WATCHED_PATTERNS=(
  ":(glob)**/package.json"
  ":(glob)**/composer.json"
  ":(glob)**/nuxt.config.ts"
  ":(glob)**/vitest.config.ts"
  ":(glob)**/playwright.config.ts"
  ":(glob)docker-compose*.yml"
  "Makefile"
  ":(glob)**/Dockerfile"
  ":(glob).github/workflows/*"
  ":(glob)**/.storybook/*"
  ":(glob)**/phpstan.neon"
  ":(glob)**/eslint.config.*"
)

# Collect all local changes: staged, unstaged, and untracked files
{
  git diff --name-only --cached -- "${WATCHED_PATTERNS[@]}" 2>/dev/null || true
  git diff --name-only -- "${WATCHED_PATTERNS[@]}" 2>/dev/null || true
  git ls-files --others --exclude-standard -- "${WATCHED_PATTERNS[@]}" 2>/dev/null || true
} | sort -u > /tmp/docs-staleness-check.$$

MATCHES=$(cat /tmp/docs-staleness-check.$$)
rm -f /tmp/docs-staleness-check.$$

if [ -n "$MATCHES" ]; then
  echo "CLAUDE.md files may need updating. The following config/structure files were modified:"
  echo "$MATCHES" | sed 's/^/  - /'
  echo ""
  echo "Run /update-docs to review and update all CLAUDE.md files."
fi
