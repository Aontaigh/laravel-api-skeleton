# Performance Notes

Operational guidance for keeping list and show endpoints fast under load.
Implemented optimisations live in code and migrations; the sections below cover
trade-offs and future paths.

**Related code:** [UserFilterQuery](../app/Queries/Users/UserFilterQuery.php),
[LikePattern](../app/Support/LikePattern.php),
[UserQueryConstraints](../app/Queries/Users/UserQueryConstraints.php).

## Admin Cross-Team User Index (`users.list-all`)

**Issue:** When a viewer holds `users.list-all`,
[UserFilterQuery](../app/Queries/Users/UserFilterQuery.php) drops the `team_id`
constraint. Pagination caps rows per page, but Laravel's length-aware paginator
still runs a `COUNT(*)` over the full `users` table.

**Options when that becomes slow:**

| Approach | When to use |
| --- | --- |
| **Cursor pagination** (`cursorPaginate`) | Large tables, infinite-scroll UIs; avoids offset `COUNT` |
| **Require `filter[team_id]` for admins** | Ops tooling where cross-team views are rare |
| **Cached or approximate counts** | Dashboard totals where exact counts are not required |
| **Read replica + deferred counts** | High read volume; return rows first, count async |

For this starter, offset pagination is sufficient until `users` exceeds low
millions of rows or admin `COUNT` shows up in slow-query logs.

## `filter[search]` and B-Tree Indexes

**Issue:** [LikePattern::contains()](../app/Support/LikePattern.php) builds
`LIKE '%term%'` predicates. Leading wildcards cannot use a standard B-tree index,
so search scans every row in the current scope (team, guard, or token owner).

**Options at scale:**

| Approach | Notes |
| --- | --- |
| **MySQL `FULLTEXT`** on `name` (and `email` for users) | Good for word-based search; add a dedicated `filter[search_fulltext]` or swap implementation behind the same key when volume warrants |
| **PostgreSQL `tsvector` + GIN** | Same idea if you migrate off MySQL |
| **Dedicated search** (Meilisearch, OpenSearch, Typesense) | Best for fuzzy match, ranking, and multi-field search across resources |
| **Prefix-only search** (`term%`) | Can use B-tree indexes but changes UX — only match starts-with |

Wildcard **neutralisation** (via `LikePattern` + `ESCAPE`) is already enforced
so `%` and `_` in user input cannot become match-all probes — that is a
correctness and CPU guard, not an index strategy.
