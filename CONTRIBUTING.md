# Contributing

## Release rules
- `main` is the **next release**, NOT production. Nothing deploys from `main`.
- Releases are **Git tags**. Pre-releases (`x.y.z-rc.N`) are installed only on a test site. Stable tags (`x.y.z`) are what clients install.
- Client apps require `^x.y` of stable releases only.
- **Hotfix**: branch from the released tag, fix, tag a new patch release, then bring the fix back to `main`.
- **Maintenance branches** (`1.x`) exist only when an old line must be kept alive after `main` moved on.
- **Client app repositories are different**: PR -> `staging` -> `main` = production.
- Conventional Commits; PRs are squash-merged.

## Branches
- `main` is always releasable (a candidate for the next tag). Never commit to it directly.
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
GitHub Actions runs `composer check` on PHP 8.4 (the minimum) and the latest stable PHP, against a MariaDB 12.3 service (tests need a `test` database; DDEV creates it on `ddev start`).
**Branch protection is not available on our GitHub plan, so CI is informational only**: nothing stops a red PR from being merged. Check that CI is green before merging.

## Rules
Code conventions, the color rule and the verification rule are in `CLAUDE.md`. Decisions are logged in `docs/DECISIONS.md`.
