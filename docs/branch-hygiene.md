# Branch Hygiene

Keep the skeleton's branches, pull requests, and release tags tidy. This repo
uses `main` as its only trunk. There is no `develop`, release branch, or
environment branch.

For the release procedure, see [releasing.md](releasing.md). For the merge gate,
see [testing.md](testing.md).

## Branch Roles

| Branch                            | Role                                           | Cleanup                                                                  |
| --------------------------------- | ---------------------------------------------- | ------------------------------------------------------------------------ |
| `main`                            | Only trunk; release commits and tags land here | Never delete or force-push                                               |
| `feat/*`, `fix/*`, `docs/*`, etc. | Short-lived work branches                      | Delete after merge or when the pull request is closed                    |
| `dependabot/*`                    | Dependabot updates                             | Do not push; use Dependabot comment commands                             |
| Tags such as `vX.Y.Z`             | Released commits                               | Never move or delete a published release tag without a rollback decision |

Do not create a `release/vX.Y.Z` branch. The release commit is pushed to `main`,
CI is green on that commit, and the tag points at it.

## After a Merge

1. Update local `main`:

    ```bash
    git switch main
    git pull --ff-only origin main
    git fetch --prune
    ```

2. Delete the merged local branch:

    ```bash
    git branch -d <branch>
    ```

    A branch merged with a squash commit is not an ancestor of `main`, so
    `git branch -d` may refuse. Confirm the pull request is merged, then use
    `git branch -D <branch>`.

3. If the remote branch still exists after merge, delete it only when no pull
   request uses it:

    ```bash
    git push origin --delete <branch>
    ```

4. After a release, do not create or reset a release branch. Confirm the tag
   points at the CI-green commit described in [releasing.md](releasing.md).

Never delete a branch that still has an open pull request. Closing a pull
request does not delete its branch automatically unless the repository setting
does so.

## Update Open Pull Requests

Bring normal open pull requests up to date with `main`:

```bash
gh pr list --state open --json number,headRefName,baseRefName --jq '.[]'
```

| Pull Request Head | Update From | How                                                               |
| ----------------- | ----------- | ----------------------------------------------------------------- |
| `dependabot/*`    | `main`      | Do not push. Comment `@dependabot rebase` on the pull request     |
| Everything else   | `main`      | Use GitHub's **Update branch**, or `gh pr update-branch <number>` |

Do not resolve conflicts in someone else's pull request. Abort the update and
leave the branch unchanged when a conflict appears. Do not update a pull
request from a fork when you do not have permission to push to the fork.

## Dependabot Branches: Do Not Push

Dependabot manages its own branches. Adding a commit to a `dependabot/*` branch
stops Dependabot from rebasing that pull request automatically, unless the commit
carries a Dependabot skip token.

- Behind `main` with no conflicts: comment `@dependabot rebase`
- Conflicts: comment `@dependabot rebase`
- Stale: comment `@dependabot recreate`
- Superseded by a newer pull request for the same dependency: close the older
  pull request after confirming the newer one is open
- Deliberately deferred: use the command for the pull request shape below
- Genuinely obsolete: close the pull request

For a standard pull request, comment `@dependabot ignore this dependency`. For a
grouped pull request, use the dependency-specific form
`@dependabot ignore DEPENDENCY_NAME`. Both commands close the pull request and
stop Dependabot creating further pull requests for that dependency until the
pull request is reopened or the dependency is upgraded manually.

For a grouped pull request, clear a stored ignore condition with
`@dependabot unignore DEPENDENCY_NAME`. Use
`@dependabot show DEPENDENCY_NAME ignore conditions` to inspect the stored
conditions first. See [Dependabot pull request comment commands](https://docs.github.com/en/code-security/reference/supply-chain-security/dependabot-pull-request-comment-commands).

When deferring an update, record a review date in the pull request so the
suppression is revisited. If the suppression must be visible to contributors,
add an `ignore` rule with a reason comment to `.github/dependabot.yml`. That
configuration is optional; comment-based ignores are stored by Dependabot rather
than in the repository. When the deferral ends, clear both suppression paths:
delete the matching `ignore` rule from `.github/dependabot.yml` if one was added,
and reopen the closed pull request, or use
`@dependabot unignore DEPENDENCY_NAME` on a grouped update, to clear the
`@dependabot ignore` command condition. Each action clears only its own
condition; deleting the rule does not clear the command condition, and reopening
or unignoring does not remove the rule.

Never close a pull request only to silence an update. Closing stops that pull
request, not the schedule. Only an ignore condition stops the update.

Official references: [Managing pull requests for dependency updates](https://docs.github.com/en/code-security/how-tos/secure-your-supply-chain/manage-your-dependency-security/manage-dependabot-prs),
[Dependabot pull request comment commands](https://docs.github.com/en/code-security/reference/supply-chain-security/dependabot-pull-request-comment-commands),
and [Dependabot options reference](https://docs.github.com/en/code-security/reference/supply-chain-security/dependabot-options-reference).

## Release Tags

- Tag only a commit whose **All Quality Gates** run is green
- The tag is `vX.Y.Z` and must match the `composer.json` version without the `v`
- Do not create a release branch, a second tag, or a manual changelog entry
- If a release tag is wrong, fix it through the documented release procedure;
  never move a tag silently

## Safety Rules

- Never force-push `main`
- Never create a release branch
- Never push commits to a `dependabot/*` branch
- Never delete a branch with an open pull request
- Never move a published tag without an explicit rollback decision
- Require the **All Quality Gates** check before merging to `main`

## References

- [Releasing](releasing.md)
- [Testing and quality gates](testing.md)
- [Dependabot pull request comment commands](https://docs.github.com/en/code-security/reference/supply-chain-security/dependabot-pull-request-comment-commands)
