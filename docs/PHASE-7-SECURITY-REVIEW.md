# Phase 7 — Security Review

## Purpose

Phase 7 is the dedicated security, privacy and reliability pass for the
Wholesale Ordering application.

Security is a release requirement. It is not optional polish.

The review covers:

- authorization;
- REST/AJAX boundaries;
- wholesale-price leakage;
- document uploads and downloads;
- sensitive information;
- audit logging;
- cache behaviour;
- backup and recovery.

---

## 1. Authorization

The application has six security states that must be tested:

1. Guest
2. Pending customer
3. Rejected customer
4. Approved wholesale customer
5. Suspended customer
6. Administrator

### Expected pricing result

| State | Wholesale access | Expected product price |
|---|---|---|
| Guest | No | Regular Price |
| Pending | No | Regular Price |
| Rejected | No | Regular Price |
| Approved | Yes | Authorized Wholesale Price |
| Suspended | No | Regular Price |
| Administrator | Administrative access | Admin behaviour; never treated as wholesale customer automatically |

Approved wholesale access requires the application's approved status and
the plugin-owned wholesale capability.

The pricing service remains the authoritative pricing boundary.

---

## 2. REST and AJAX security

Every custom endpoint must be reviewed for:

- authentication;
- capability checks;
- nonce/CSRF protection;
- validation;
- sanitization;
- authorization;
- safe error responses.

The plugin must not expose wholesale prices through an endpoint merely because
the endpoint is technically accessible.

---

## 3. Wholesale price leakage

Check the following surfaces:

- Shop
- Category pages
- Search results
- Product pages
- Related products
- Product listings
- REST responses
- AJAX responses
- JavaScript payloads
- Structured data
- Product feeds
- Search indexes
- Cached fragments
- Browser/network responses

Unauthorized states must not receive stored wholesale prices.

The existing `PricingLeakageProtection` layer remains a secondary protection
layer. It does not replace `PricingService`.

---

## 4. Secure documents

Supporting documents are private business records.

Requirements:

- allowed MIME types are restricted;
- maximum file size is enforced;
- executable files are rejected;
- files are not intentionally published as normal public media;
- predictable public URLs must not be used;
- access is checked before download;
- the document owner may access their own document;
- authorized administrators may access documents;
- unrelated customers may not access documents;
- anonymous requests must not retrieve documents;
- optional malware scanning is supported where available.

The application stores an opaque document reference rather than exposing a
public attachment URL as the document's security boundary.

---

## 5. Sensitive information

The plugin must never store:

- plaintext passwords;
- payment card numbers;
- CVV values;
- authentication secrets.

Password management remains the responsibility of WordPress/WooCommerce.

Payment processing remains delegated to compliant payment gateways.

---

## 6. Audit log

High-value events are recorded by `AuditLog`.

Events include:

- wholesale approval;
- wholesale rejection;
- wholesale suspension;
- wholesale reactivation;
- product/variation price changes;
- refunds;
- role changes;
- permission-related changes.

Audit records intentionally avoid storing:

- passwords;
- authentication tokens;
- payment information;
- document contents;
- internal review-note contents;
- full wholesale price values.

---

## 7. Cache security

Role-sensitive pages and data must not be served from an inappropriate shared
cache.

Review:

- My Account;
- Cart;
- Checkout;
- authenticated requests;
- role-sensitive product pricing;
- wholesale-sensitive fragments.

A cached response must never allow a wholesale price generated for one
customer state to appear for another customer state.

---

## 8. Security test matrix

The final manual security pass must test all six states.

### Authorization matrix

| Test | Guest | Pending | Rejected | Approved | Suspended | Admin |
|---|---:|---:|---:|---:|---:|---:|
| View catalogue | PASS | PASS | PASS | PASS | PASS | PASS |
| Regular pricing | PASS | PASS | PASS | — | PASS | — |
| Wholesale pricing | BLOCK | BLOCK | BLOCK | PASS | BLOCK | BLOCK* |
| My Account | BLOCK | PASS | PASS | PASS | PASS | — |
| Wholesale Status | BLOCK | PASS | PASS | PASS | PASS | — |
| Customer document | BLOCK | OWNER ONLY | OWNER ONLY | OWNER ONLY | OWNER ONLY | AUTHORIZED |
| Admin application controls | BLOCK | BLOCK | BLOCK | BLOCK | BLOCK | PASS |

`*` Administrator access must not be interpreted as automatic wholesale
eligibility.

---

## 9. Acceptance status

Phase 7 is complete only when:

- no critical authorization vulnerability remains open;
- no known wholesale-price leakage remains open;
- private documents cannot be fetched anonymously;
- unrelated customers cannot fetch another customer's documents;
- audit events are recorded;
- backup procedure is documented;
- recovery procedure is documented;
- the six-state matrix has been executed;
- failures are documented and corrected before release.

Automated tests support this review but do not replace manual browser,
network and authorization testing.