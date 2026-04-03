# WPGraphQL Persisted Query URLs — status

**Slug:** `wp-graphql-pqu` (install folder). **Version:** 0.1.0-beta.1 (experimental). This document is the living snapshot of what works, what is untested, and known limits. It will be expanded as the plugin matures.

## Summary

Early beta. Suitable for local experiments and review, not production.

## Implemented (code paths exist)

- Permalink cold/warm GET handling, POST registration with nonce (see [SPEC.md](./SPEC.md)).
- MySQL store (`DBStore`), purge adapters (null, VIP, HTTP), `graphql_purge` integration.
- WP-CLI `graphql-pqu register`.
- Database tables created/upgraded via `Schema::ensure_schema()` on each load.

## Limited testing / theory

- VIP and real edge purge behavior in customer environments.
- Alternative `StoreInterface` implementations (Redis/KV) beyond design notes in [INTEGRATIONS.md](./INTEGRATIONS.md).
- Load and cache benchmarks under [benchmark/README.md](../benchmark/README.md) are optional harnesses, not CI gates.

## Known limitations

- Persisted traffic is **GET**-oriented; mutations belong on POST `/graphql`.
- Smart Cache **query analyzer** must emit keys or the index is not written.
- Nonce registration flow is incompatible with clients that cannot send custom `extensions` (unless nonce is disabled via filter).
- **Apollo APQ** client links are not drop-in compatible; see [SPEC.md](./SPEC.md) (“How clients differ from typical Apollo APQ”).
