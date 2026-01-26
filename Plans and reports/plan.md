## Goal
Implement requested UX/UI touches for the HP-React-Widgets funnel + checkout flow:
- TY page shows shipping address + “View order” link for logged-in users
- Shipping methods are sorted cheapest→most expensive and cheapest is auto-selected
- Authority quote icon no longer overlaps the vertical line on large screens
- Science section cards use more horizontal space; 2-card layout looks wider/shorter
- Infographics are full-width except on the largest breakpoint where margins apply

## Context
- Repo: `HP-React-Widgets`
- Targets:
  - React checkout app (`src/components/checkout-app/*`)
  - Funnel sections (`src/components/funnel/*`)
  - REST API (`includes/Rest/CheckoutApi.php`)
- Constraints:
  - Keep WooCommerce-compatible patterns
  - Avoid exposing order info without auth (pi_id or logged-in owner)

## Tasks
1. Checkout
  1.1 Sort shipping rates by actual total (ShipStation raw fields) using shared extraction util
  1.2 Default-select the cheapest rate after fetch
2. Thank-you
  2.1 Extend order summary REST payload to include shipping address
  2.2 Add “View order” My Account URL when logged in and owns order
  2.3 Render shipping address on thank-you page + conditional View Order link
3. Funnel UI tweaks
  3.1 Authority: shift quote icon right and add padding so it doesn’t overlap border
  3.2 Science: widen container; cap columns by card count; tune spacing/padding for 2 cards
  3.3 Infographics: only apply horizontal margins on the largest breakpoint
4. Delivery
  4.1 Bump plugin version
  4.2 Commit + push `dev`
  4.3 Verify on staging via browser checks (checkout shipping selection; thank-you content; desktop layout)
  4.4 Push `main` after user confirmation

## Acceptance criteria
- TY page displays shipping address for the order
- TY page shows “View this order in My Account” link only when logged in and order owner
- Shipping methods are shown sorted cheapest→most expensive and cheapest is selected by default
- Authority quote icon no longer overlaps the vertical line on large screens
- Science section cards are wider (use more horizontal space) and 2-card layout looks balanced
- Infographics are full-width on all but the largest breakpoint

