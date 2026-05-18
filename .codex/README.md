# Project Cowork Setup

This directory is the project-local cowork/Codex manifest for Final Cut.

The environment mounts repo-local `.codex/` and `.agents/` as read-only tool directories, so project-owned configuration lives here instead. Global files such as `~/.codex/config.toml` and `~/.codex/AGENTS.md` are intentionally not modified.

Use:

- `config.toml` for project defaults and instruction file locations.
- `agents.toml` for project role routing.
- `skills.toml` for project-local skill registration.
- `skills/` for Final Cut-specific skills translated from `.claude/skills/`.

`AGENTS.md`, `frontend/AGENTS.md`, and `backend/AGENTS.md` are the canonical instruction files for agents that support repository-local guidance.
