# Firebase Realtime Database rules — version history

Firebase Realtime Database security rules live in the Firebase Console (Realtime Database →
Rules), **not** in this repo — publishing here does nothing to the live database. This folder is
a manually-kept history so past versions aren't lost, and a bad publish can be rolled back by
copy-pasting an older file back into the Console.

**After every time you Publish new rules in the Console, save a copy of what you published here**
(new dated filename, short description of what changed), so this folder stays a real history
instead of going stale.

## Versions

- `2026-09-02-hub_intake-only.json` — baseline before the `users` write lockdown. Adds `hub_intake`
  (the original Guest Hub Content Form's Firebase node) as public-write / admin-read. `users` write
  is still `auth != null` (any signed-in account) at this point.
- `2026-09-03-users-write-locked-to-admins.json` — current. Adds `hub_intake_v2` and
  `hub_intake_drafts` (the rebuilt 4-step onboarding form's nodes, same pattern as `hub_intake`).
  Tightens `users` write from "any signed-in account" to "only accounts whose own `role` is
  already `super` or `super_manager`" — closes the hole where any authenticated identity could
  write its own `role` into `/users` and grant itself admin access. Matches the admin panel's own
  UI gate (`adminpanel.html:5958`), which already restricts user management to those two roles.

## To roll back

Open Firebase Console → Realtime Database → Rules → select all → delete → paste the older file's
contents → confirm no red error indicator → Publish.
