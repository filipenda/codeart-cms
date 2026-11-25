# Contributing

## Branches
- `main` is always releasable. Never commit to it directly.
- Work on short-lived branches: `feat/...`, `fix/...`, `chore/...`.
- Open a pull request; it is **squash-merged**. The PR title must be a Conventional Commit.

## Commits
[Conventional Commits](https://www.conventionalcommits.org): `feat(core): add theme settings`, `fix(pages): ...`, `chore: ...`, `docs: ...`.
Small commits on the branch are fine.

## Before you push
```
ddev start
ddev composer setup   # first time
ddev composer check   # Pint, Larastan, Pest, color check
```

## CI
GitHub Actions runs `composer check` on PHP 8.2 and the latest PHP.
**Branch protection is not available on our GitHub plan, so CI is informational only**: nothing stops a red PR from being merged. Check that CI is green before merging.

## Rules
Code conventions, the color rule and the verification rule are in `CLAUDE.md`. Decisions are logged in `docs/DECISIONS.md`.
