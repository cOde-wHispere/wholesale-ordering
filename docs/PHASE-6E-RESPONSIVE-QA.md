Phase 6E — Responsive QA

Purpose

Phase 6E validates the completed Phase 6 customer-facing experience across the
required browser and viewport classes without changing the Phase 3 pricing
authority or the WooCommerce catalogue engine.

The project specification requires responsive support for Mobile, Tablet and
Desktop and specifically calls for testing Chrome, Edge, Safari where
applicable, and mobile browsers.

Scope

Test these customer-facing workflows:

Shop/catalogue

Category navigation

Product search

Availability/category filtering

Product detail

Quantity controls

Cart

Checkout

My Account

Wholesale Status

Order history

Order documents/invoices where present

Authorized pricing visibility

Customer states

Where the environment contains the required test accounts, repeat pricing and
workflow checks for:

Guest

Registered/Pending

Registered/Rejected

Approved Wholesale

Suspended Wholesale

Administrator-only screens are not part of the customer responsive UI pass.

Required browser matrix

Browser

Desktop

Tablet

Mobile

Result

Chrome

☐

☐

☐



Edge

☐

☐

☐



Safari (where applicable)

☐

☐

☐



Mobile browser(s)

N/A

N/A

☐



Safari is tested where a supported Safari environment is available. On a
Windows development workstation, do not mark Safari as passed merely because
Chrome/Edge passed.

Viewport matrix

Use representative viewport classes rather than a single device screenshot.

Desktop

1280 × 720

1440 × 900

Check:

navigation remains usable;

catalogue toolbar stays within the content width;

search and filter controls do not collide;

product cards/listings retain usable spacing;

quantity controls remain usable;

cart/checkout tables remain readable.

Tablet

768 × 1024

1024 × 768

Check:

two-column filter layout where applicable;

category links wrap cleanly;

no clipped controls;

no accidental horizontal page scrolling;

touch targets remain usable;

product images and text remain readable.

Mobile

375 × 667

390 × 844

412 × 915

Check:

search input and button stack cleanly;

filter controls stack cleanly;

category links wrap without clipping;

buttons are reachable without zooming;

quantity controls remain usable;

tables use controlled horizontal scrolling where necessary;

no page-level horizontal overflow;

product detail content remains readable.

Functional smoke checks

For each browser/viewport class:

Catalogue

Shop loads without PHP/JS/CSS errors.

Search control is visible.

Category navigation is visible when categories exist.

Filter controls are visible.

Search returns the expected catalogue results.

Category selection returns the expected catalogue results.

Availability filtering works.

Reset returns to the unfiltered catalogue.

Product

Product title is readable.

Product image is not clipped.

Product price is correct for the current customer state.

Quantity control is usable.

Add-to-cart control is usable.

Related products remain usable where WooCommerce displays them.

Cart and checkout

Cart opens.

Quantity can be reviewed/changed.

Cart totals are readable.

Checkout fields do not overlap.

Checkout controls remain reachable.

No horizontal page overflow is introduced.

Account

My Account loads.

Account navigation is usable.

Wholesale Status is readable.

Orders are readable.

Order detail is readable.

Available documents/invoices remain reachable.

Pricing safety checks

Responsive QA must not become a pricing regression.

For every supported viewport:

Guest sees Regular Price only.

Pending customer sees Regular Price only.

Rejected customer sees Regular Price only.

Approved wholesale customer sees authorized Wholesale Price when configured.

Suspended customer does not receive wholesale pricing.

No wholesale value appears in visible unauthorized HTML.

No wholesale value is exposed merely because the viewport/browser changed.

The Phase 3 pricing service remains the authoritative pricing boundary. The
responsive layer must not calculate or substitute prices.

Visual defects to record

For every failure capture:

browser;

viewport;

customer state;

URL/page;

exact control or element;

expected behavior;

actual behavior;

screenshot;

console error if present;

network error if present.

Acceptance criteria

Phase 6E is accepted only when:

Core customer workflows work consistently across supported desktop and
mobile browsers.

Mobile, tablet and desktop layouts are usable.

No known critical layout defect blocks a core workflow.

No page-level horizontal overflow exists where it should not.

Search/filter controls remain usable at mobile widths.

Cart and checkout remain usable.

My Account and Wholesale Status remain usable.

Responsive changes do not alter authoritative pricing behavior.

Any browser-specific limitation is documented rather than silently treated
as passed.

Implementation boundary

Phase 6E does not introduce:

a second pricing engine;

new wholesale pricing rules;

customer-specific pricing;

quantity-break pricing;

a new product query engine;

custom checkout replacement;

a new REST/AJAX catalogue API.

The Phase 6A catalogue implementation remains the existing customer-facing
discovery layer. Phase 6E hardens its presentation and defines the browser and
viewport acceptance pass.