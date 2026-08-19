# AI Agent Skills

This project uses **agent skills** — portable instruction bundles that teach AI coding assistants (Claude Code, Cursor) how to work with WordPress the right way. Skills are Markdown files with YAML frontmatter that the agent loads automatically when a task matches their domain. They reduce outdated patterns, missed security checks, and generic non-WordPress code that AI models otherwise tend to produce by default.

## Installed skills

All skills come from [WordPress/agent-skills](https://github.com/WordPress/agent-skills), the official WordPress Foundation collection. They are committed to this repo under `.claude/skills/` and `.cursor/skills/` so no install step is needed after cloning — Claude Code and Cursor pick them up automatically.

| Skill | Why it's here |
|---|---|
| `wp-plugin-development` | Core plugin architecture, hooks, Settings API, security patterns. Applies to `whmcs_price.php`, `includes/settings.php`, and most of the codebase. |
| `wp-block-development` | Gutenberg block patterns, `block.json`, deprecations. Applies to `blocks/whmcs-price-product/` and `blocks/whmcs-price-domain/`. |
| `wp-rest-api` | REST route registration, permission callbacks, argument validation. Applies to `includes/rest-api.php` (the `/purge-cache` endpoint and the planned webhook endpoint in v2.10.0). |
| `wp-performance` | Caching layers, object cache, database queries, profiling. Directly relevant to the cache-architecture work in v2.10.0 (stale-while-revalidate, proactive warming, invalidation). |
| `wp-wpcli-and-ops` | WP-CLI command patterns and conventions. Applies to `includes/cli.php`. |
| `wp-plugin-directory-guidelines` | wp.org submission and update requirements. Applies to every release since the plugin is distributed via wp.org. |

Skills we deliberately did **not** install:

- `wp-block-themes`, `wp-interactivity-api`, `wpds` — not relevant to this plugin.
- `wp-phpstan` — worth adding when we introduce PHPStan static analysis to CI.
- `wp-playground` — worth adding when we adopt Playground for release testing.
- `wp-abilities-api` — worth revisiting if we modernise REST authentication in v3.x.

## How agents use skills

Both Claude Code and Cursor scan the `SKILL.md` frontmatter (`name` and `description`) at session start. When a task matches a skill's description, the agent reads that skill's full contents into its context before responding. You don't invoke skills manually — the trigger is automatic based on what you're working on.

To verify a skill is being loaded, ask the agent something specific to that skill's domain (e.g. "How should I register the REST route for the webhook endpoint?" for `wp-rest-api`) and check whether its response reflects skill-specific guidance versus generic training-data patterns.

## Directory layout

```
.claude/skills/
  wp-block-development/
  wp-performance/
  wp-plugin-development/
  wp-plugin-directory-guidelines/
  wp-rest-api/
  wp-wpcli-and-ops/
.cursor/skills/          # same six skills, mirrored
skills-lock.json          # source hashes for reproducibility
```

Both `.claude/skills/` and `.cursor/skills/` contain the same content. This is intentional — it lets each agent find its skills using its own conventional path without any config file pointing elsewhere. Total disk footprint is under 700 KB combined, so duplication is a fair trade for zero-config setup.

`skills-lock.json` is the equivalent of `package-lock.json` — it records the exact source hashes of each installed skill so updates are traceable.

## Reinstalling from scratch

If the checked-in skill directories are somehow lost, reinstall with:

```bash
npx skills add WordPress/agent-skills \
  --skill wp-plugin-development \
  --skill wp-block-development \
  --skill wp-rest-api \
  --skill wp-performance \
  --skill wp-wpcli-and-ops \
  --skill wp-plugin-directory-guidelines \
  -y
```

This installs into `.agents/skills/` (the CLI's universal directory). Copy that content into both `.claude/skills/` and `.cursor/skills/` and delete `.agents/` — that's the same layout we already have committed.

The `-y` flag is non-interactive and installs project-scoped, which is what we want. Do **not** pass `-g` — global installs live in your home directory and won't be shared with other contributors.

## Updating

The upstream repo is actively maintained by WordPress contributors. Check for updates periodically:

```bash
npx skills check          # list available updates
npx skills update -p      # update all project-scoped skills
```

Or update a single skill:

```bash
npx skills update wp-plugin-development
```

After running an update, mirror `.agents/skills/` into `.claude/skills/` and `.cursor/skills/` as above, then review the diff before committing — occasionally an upstream change conflicts with a project-specific convention. Skills updates belong in their own commit with a message like `chore(skills): update wp-performance to <sha>`.

## Removing a skill

```bash
npx skills remove wp-plugin-directory-guidelines
```

Remember to remove the same skill from `.claude/skills/` and `.cursor/skills/` too, since the CLI only touches `.agents/skills/`.

## Upstream

- Skills repository: <https://github.com/WordPress/agent-skills>
- CLI (the `npx skills` command): <https://github.com/vercel-labs/skills>
- AI authorship disclosure: skills were generated by GPT-5.2 Codex from official WordPress and Gutenberg documentation, then reviewed by WordPress contributors. Treat them as a high-quality starting point, not infallible authority — verify anything that conflicts with the plugin's existing conventions.