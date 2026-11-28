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

## Workflow: one branch per feature
- `main` contains only finished, working features (for example a whole module), never half-done pieces.
- One branch per feature (`feat/admin-ui`, later `feat/module-pages`). Several prompts or sessions may add commits to the same branch.
- While working, run only the fast local check (`ddev composer check:fast`). No CI wait, no PR. Pushing the branch is fine.
- A PR is opened only when the whole feature is done. Then the full `ddev composer check` runs locally once, CI runs once, and on green the PR is squash-merged: one clean commit per feature on `main`.
- A commit is made only after `ddev composer check:fast` passes. A failing check is investigated, never skipped or committed over, and a flaky test is fixed at its root cause (shared state, order dependence, time), never retried or skipped (W7).
- W8: The last step of every task is to leave the local dev environment ready to open in the browser (`ddev composer install`, `migrate`, `codeart:roles`, publish assets, `optimize:clear`) and say so in the report; the owner never runs setup commands by hand.
- Version tags only when a feature set is stable.
- Unless a task says "finish the feature", Claude does not open a PR, wait for CI or merge: it commits, pushes and reports.
- After merging to `main`, also wait for the push run on `main` (`gh run list --branch main --limit 1`, then `gh run watch`) and report its result. If it is red, fix it immediately before any other work. A rerun is allowed once for an external cause (e.g. a 429 from GitHub); a second failure is treated as deterministic.

## Commits
[Conventional Commits](https://www.conventionalcommits.org): `feat(core): add theme settings`, `fix(pages): ...`, `chore: ...`, `docs: ...`.
Small commits on the branch are fine.

## Before you push
```
ddev start
ddev composer setup   # first time
ddev composer check:fast  # while working: colors, Pint (check only), Larastan, tests of changed files (~1 min)
ddev composer check       # full run, once before opening a PR (also what CI runs)
```

## CI
GitHub Actions runs `composer check` on pull requests and on pushes to `main` only (not on every branch push; superseded runs of the same ref are cancelled), on PHP 8.4 (the minimum) and the latest stable PHP, against a MariaDB 12.3 service (tests need a `test` database; DDEV creates it on `ddev start`).
**Branch protection is not available on our GitHub plan, so CI is informational only**: nothing stops a red PR from being merged. Check that CI is green before merging.

## Rules
Code conventions, the color rule and the verification rule are in `CLAUDE.md`. Decisions are logged in `docs/DECISIONS.md`.
