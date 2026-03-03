---
name: wpgraphql-wordpress-agent-skills
description: Install and use WordPress/agent-skills for general WordPress patterns (plugin dev, PHPStan, WP-CLI, blocks). Use when the task needs WordPress expertise beyond this repo's skills.
---

# wpgraphql-wordpress-agent-skills (meta-skill)

Install and use the [WordPress/agent-skills](https://github.com/WordPress/agent-skills) repository so the agent (and developer) can use expert-level WordPress skills (plugin development, PHPStan, WP-CLI, blocks, etc.) alongside this repo’s project-specific skills.

## When to use this skill

- The task involves general WordPress patterns: plugin architecture, hooks, settings API, security, PHPStan for WordPress, WP-CLI, block development, REST API, etc.
- The user or agent has not yet installed WordPress agent-skills and needs clear steps to do so.

## What WordPress agent-skills provides

Portable skills for WordPress (blocks, themes, plugins, PHPStan, WP-CLI, performance, Playground, etc.). See the [Available Skills](https://github.com/WordPress/agent-skills#available-skills) table in their README. Examples relevant to WPGraphQL:

- **wp-plugin-development** — Plugin architecture, hooks, settings API, security
- **wp-phpstan** — PHPStan for WordPress (config, baselines, WP-specific typing)
- **wp-wpcli-and-ops** — WP-CLI commands, automation, multisite

## How to install (for the user or agent to run)

1. **Clone and build** (from a directory of your choice, e.g. home or temp):
   ```bash
   git clone https://github.com/WordPress/agent-skills.git
   cd agent-skills
   node shared/scripts/skillpack-build.mjs --clean
   ```

2. **Install into this repo** (so skills are available when working in wp-graphql):
   ```bash
   node shared/scripts/skillpack-install.mjs --dest=/path/to/wp-graphql --targets=claude,cursor
   ```
   Replace `/path/to/wp-graphql` with the absolute path to the WPGraphQL repo. This copies skills into `.cursor/skills/` and/or `.claude/skills/` inside that repo.

   **Or install globally** (available in all projects):
   ```bash
   node shared/scripts/skillpack-install.mjs --global
   ```
   For Cursor globally: `node shared/scripts/skillpack-install.mjs --targets=cursor-global`.

3. **Install only specific skills** (optional):
   ```bash
   node shared/scripts/skillpack-install.mjs --dest=/path/to/wp-graphql --targets=cursor --skills=wp-plugin-development,wp-phpstan,wp-wpcli-and-ops
   ```

## Do not duplicate

Do not copy the content of WordPress agent-skills into this repo. This meta-skill only guides installation and points to their repo. Their README and docs are the source of truth.

## After installation

Once installed (into this repo or globally), the agent or IDE will load those skills when relevant. Use **WPGraphQL .ai/skills** (wpgraphql-dev-cycle, wpgraphql-php, wpgraphql-worktree, wpgraphql-acf-e2e) for monorepo-specific procedures; use **WordPress agent-skills** for general WordPress patterns.
