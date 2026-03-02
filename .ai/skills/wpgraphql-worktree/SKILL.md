# wpgraphql-worktree

Running multiple wp-env instances (e.g. for different git worktrees or agents) by using different ports.

## When to use this skill

- Setting up a second (or third) git worktree and you need its own wp-env so it does not conflict with another.
- An agent or developer needs to run wp-env in a worktree while another wp-env is already running on default ports (8888/8889).

## How it works

wp-env supports custom ports via environment variables or `.wp-env.override.json`. Each worktree can use a different port pair so multiple wp-env instances can run at once.

## Option A: Environment variables

From the worktree root:

```bash
WP_ENV_PORT=8890 WP_ENV_TESTS_PORT=8891 npm run wp-env start
```

Convention example: main worktree → 8888/8889; worktree 2 → 8890/8891; worktree 3 → 8892/8893.

## Option B: Override file

In the worktree root, create `.wp-env.override.json` (this file is gitignored):

```json
{
  "port": 8890,
  "testsPort": 8891
}
```

Then run `npm run wp-env start` as usual; wp-env will merge the override.

## Smoke test with custom port

When the dev site is on a non-default port, point the smoke test at it:

```bash
./bin/smoke-test.sh --endpoint http://localhost:8890/graphql
```

If the repo’s smoke script supports `WP_ENV_PORT`, you can run `WP_ENV_PORT=8890 ./bin/smoke-test.sh` and it may use that port by default.

## Notes

- Do not change `.wp-env.json` in the repo; use per-worktree overrides or env vars.
- Test site URL with custom tests port: http://localhost:8891 (if testsPort is 8891).
