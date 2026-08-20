# "Leave a Review" modal audit — all hubs

Checked every hub's Review modal (Home tab → More → Leave a Review). Two things checked per hub:
1. Happy face 😊 → `openGoogleReview()` — should open a Google review link for THAT specific campground.
2. Sad face 😞 → `tel:` link — should call THAT campground's main phone number (matches the Contact page's Reservations number).

## Needs fixing

- **hubs/big-meadow-family-campground.html** (line 5754)
  - 😊 Google link points to "stone+mountain+park+campground" — wrong campground, leftover from template.
  - 😞 phone (865) 448-0625 — correct, matches Contact page.
  - **Fix:** replace Google review URL with Big Meadow Family Campground's actual Google Business review link.

- **hubs/san-war-rv-resort-hub.html** (line 5669 / 5337)
  - 😊 Google link points to "stone+mountain+park+campground" — wrong.
  - 😞 phone 770-413-5276 — wrong, that's Stone Mountain's number. Sanwar's real reservations number is 770-498-5710 (used elsewhere in this same file).
  - **Fix both.**

- **hubs/blue-ridge-river-resort.html** (line 6260)
  - 😊 Google link points to "stone+mountain+park+campground" — wrong.
  - 😞 phone 706-900-7682 — correct, matches Contact page.
  - **Fix:** Google review URL only.

- **hubs/sweetwater-valley-park.html** (line 5455 / 5166)
  - 😊 Google link points to "stone+mountain+park+campground" — wrong.
  - 😞 phone 770-413-5276 — wrong, that's Stone Mountain's number. Sweetwater's real main number is 470-701-0430 (used on Contact page).
  - **Fix both.**

- **hubs/stay-wilder-campground.html** (line 5329 / 4995)
  - 😊 Google link uses a "travel/search" URL for "stay wilder campground" — looks correct/specific, but worth a quick manual click-check since it's a long encoded URL that could be stale/expired.
  - 😞 phone 770-413-5276 — wrong, that's Stone Mountain's number. Stay Wilder's real main number is 678-447-6021 (used on Contact page).
  - **Fix:** phone number, at minimum.

- **hubs/talking-rock-motorcoach-resort.html** (line 5738 / 5407)
  - 😊 Google link is a raw "google.com/search" results URL with a session token (`sxsrf=...`) — these expire and will likely stop working; should be replaced with a stable Google Business/Maps review link.
  - 😞 phone 770-413-5276 — wrong, that's Stone Mountain's number. Talking Rock's real main number is 478-478-6686 (used on Contact page).
  - **Fix both.**

- **hubs/stone-mountain-park-hub.html** (line 5560 / 5229)
  - 😊 Google link says "stone+mountain+park+campground" — this hub is "Stone Mountain Park" (not "...Campground"), so the search term is close but not an exact match; worth confirming it lands on the right Google Business listing.
  - 😞 phone 770-413-5276 (SMP Police Non-Emergency #) — does NOT match this hub's own Contact page main line, 478-478-6686. Likely leftover/wrong number for a review flow.
  - **Fix:** phone number at minimum; double check Google review link target.

- **hubs/splash-rv-resort.html** (line 5791)
  - 😊 Google link is a broken/placeholder Google Maps URL — place name is literally `None` and the coordinates (15.20, 120.69) point to somewhere in the Philippines, nowhere near the resort.
  - 😞 phone 850-600-8500 — correct, matches Contact page.
  - **Fix:** Google review URL — completely broken, needs a real link.

## Confirmed OK (no action needed)

- hubs/carolina-camp-cedar.html — Google search term "carolina+camp+cedar" ✓, phone 864-878-6083 matches Contact ✓
- hubs/little-river-campground.html — Google Maps place link is specific to Little River Campground ✓, phone 865-738-3665 matches Contact ✓
- hubs/winding-waters-rv-resort-hub.html — Google travel/search for "winding waters rv resort" ✓, phone 770-415-1919 matches Contact ✓
- hubs/allatoona-landing-marine-resort.html — Google travel/search for "allatoona landing marine resort" ✓, phone 770-974-6089 matches Contact ✓
- hubs/talona-ridge-rv-resort.html — Google travel/search for "talona ridge rv resort" ✓, phone 706-636-2267 matches Contact ✓

## Summary — what to fix, in order of severity

1. **splash-rv-resort.html** — Google review link is completely broken (wrong country). Needs a real link ASAP.
2. **big-meadow-family-campground.html**, **san-war-rv-resort-hub.html**, **blue-ridge-river-resort.html**, **sweetwater-valley-park.html** — all still pointing 😊 to Stone Mountain's Google listing instead of their own.
3. **san-war-rv-resort-hub.html**, **sweetwater-valley-park.html**, **stay-wilder-campground.html**, **talking-rock-motorcoach-resort.html**, **stone-mountain-park-hub.html** — 😞 sad-face phone number is the wrong number (mostly the generic Stone Mountain Police non-emergency line, 770-413-5276) instead of that hub's own reservations/main line.
4. **talking-rock-motorcoach-resort.html** — Google review link uses a session-expiring search URL; should be swapped for a stable link regardless of the phone-number fix.

Need actual Google Business review links for: Big Meadow Family Campground, Sanwar RV Resort, Blue Ridge River Resort, Sweetwater Valley Park, Splash RV Resort (and ideally Talking Rock Motorcoach Resort, to replace its expiring link).
