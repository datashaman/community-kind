# Issue tracker: GitHub

Issues and PRDs for this repository live in GitHub Issues at `datashaman/community-kind`. Use the `gh` CLI for all operations.

## Conventions

- Create: `gh issue create --title "..." --body "..."`
- Read: `gh issue view <number> --comments`
- List: `gh issue list` with appropriate label and state filters
- Comment: `gh issue comment <number> --body "..."`
- Label: `gh issue edit <number> --add-label "..."` or `--remove-label "..."`
- Close: `gh issue close <number> --comment "..."`

Infer the repository from `git remote -v` when operating inside its clone.

## Publishing

When a skill says to publish to the issue tracker, create a GitHub issue.

## Fetching tickets

When a skill says to fetch a relevant ticket, run `gh issue view <number> --comments` and include its labels.
