# Review tooling

`review.py` is a read-only evidence collector for the repository review.
It does not modify source files or the git index.

Run from the repository root:

```bash
python3 tools/review/review.py inventory
python3 tools/review/review.py dependencies
python3 tools/review/review.py public-surface
python3 tools/review/review.py rule-references
python3 tools/review/review.py duplication
python3 tools/review/review.py rule-matrix
python3 tools/review/review.py ui-inventory
python3 tools/review/review.py compliance
python3 tools/review/review.py report
python3 tools/review/review.py manifest
```

Artifacts are written to `var/review/` by default. They are generated evidence,
not production runtime data. The analyzers intentionally produce candidates:
architecture and domain findings require manual confirmation against code,
tests, and the current technical requirements. `manifest` records the current
HEAD, worktree status and SHA-256 hashes of generated artifacts.

Each artifact has `schemaVersion`, `analyzerVersion`, `command` and `records`.
Candidate records use `candidateId`, `status: candidate`, `type`, `path`,
`line`, `symbol`, `message`, `evidence` and `confidence`. Confirmed findings
are maintained separately in the review report and receive a stable
`findingId`; an automated match is not a finding by itself.
