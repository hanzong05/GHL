# hub-core changelog

Shared CSS/JS extracted from the 17 guest-hub HTML files (`hubs/*.html`), hosted on
Cloudflare so browsers cache it across page loads instead of re-downloading the same
boilerplate on every hub visit. See `C:\Users\Admin\.claude\plans\imperative-forging-wirth.md`
for the full extraction plan.

## v1 — Phase 0 (pilot)

**Filename note:** the deployment target only allows extensionless filenames, so
the file is `hub-corev1` (no `.js`), not `hub-core.v1.js`. It must be served with
`Content-Type: application/javascript` (or `text/javascript`) by whatever
Cloudflare setup hosts it — the filename alone won't tell the browser it's JS.
Referenced from each hub as `<script src="…/hub-corev1" defer></script>`.

`hub-corev1`:
- Extracted the map pinch-zoom/pan script. Confirmed byte-identical logic across all
  17 hubs (one hub, `allatoona-landing-marine-resort.html`, differed only in
  indentation). Guarded with an early return if `#mapContainer`/`#mapImg` aren't
  present, and moved its init from an immediately-invoked inline IIFE to a
  `DOMContentLoaded`-gated call, since it's now loaded from `<head>` via
  `<script defer>` instead of inline right after the map markup.
- Extracted `trackParkhubAccess` (per-hub + global visit analytics). Unchanged
  logic — already depended only on `window.db` and `window.HUB_ID`, both set by
  each hub's own Firebase module script before the `load` event fires.

No CSS or Firebase/app-core JS extracted yet — that's Phase 1 (CSS) and Phase 2
(JS core reconciliation), tracked separately per the plan.

Source hub used as the reference for extraction: `hubs/blue-water-rv-resort.html`.

**Rollout status: all 17 hubs converted.** Piloted on `carolina-camp-cedar.html`
first and verified (map zoom/pan and visit analytics both confirmed working via
the external script). Rolled out to the remaining 16 hubs via a script rather
than manual edits — hit one bug along the way: 15 of those 16 files use CRLF
line endings (vs. LF in the pilot file), and the first version of the rollout
script used a literal `\n` in its insertion regex, which silently failed to
match `\r\n` and left those 15 hubs with the old inline scripts *removed* but
the new `<script src>` reference *not yet added* — a real regression window.
Caught immediately via a verification pass (grepping every hub for the old
inline code, the shared-script reference, and the `#mapContainer` markup) and
fixed with a CRLF-tolerant regex (`\r?\n`) before anything was left in that
broken state for a live hub. Final verification: all 17 files reference
`hub-corev1.js`, none retain the old inline map/analytics code, and `<script>`
tag counts are balanced in every file (no HTML corruption from the edits).
