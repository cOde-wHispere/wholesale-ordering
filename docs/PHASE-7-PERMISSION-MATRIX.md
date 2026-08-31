# Phase 7 — Permission Matrix

## Purpose

This document defines the expected access boundary for the Wholesale Ordering
application.

The matrix separates:

- authentication;
- wholesale eligibility;
- customer ownership;
- administrative authority.

---

## Customer states

| Capability | Guest | Pending | Rejected | Approved | Suspended |
|---|---:|---:|---:|---:|---:|
| Browse products | Yes | Yes | Yes | Yes | Yes |
| Search products | Yes | Yes | Yes | Yes | Yes |
| Filter catalogue | Yes | Yes | Yes | Yes | Yes |
| See Regular Price | Yes | Yes | Yes | Yes* | Yes |
| Receive Wholesale Price | No | No | No | Yes | No |
| Add to cart | Yes | Yes | Yes | Yes | Yes |
| Checkout | Yes | Yes | Yes | Yes | Yes |
| View own account | No | Yes | Yes | Yes | Yes |
| View own Wholesale Status | No | Yes | Yes | Yes | Yes |
| View own order history | No | Yes | Yes | Yes | Yes |
| Download own authorized document | No | Yes | Yes | Yes | Yes |

`*` Approved customers receive the configured Wholesale Price only where the
product has a valid wholesale price and all wholesale eligibility conditions
are satisfied.

---

## Administrator

Administrator access is separate from wholesale pricing eligibility.

Administrative capability permits:

- viewing wholesale applications;
- reviewing customers;
- approving applications;
- rejecting applications;
- suspending wholesale access;
- reactivating wholesale access;
- managing products;
- managing configured wholesale pricing;
- viewing authorized operational information;
- processing supported refunds.

Administrator access must not be used as an implicit customer wholesale role.

---

## Document boundary

A document may be downloaded only when:

1. the request is authenticated where customer access is required;
2. the requester owns the document, or;
3. the requester has the required administrative capability;
4. the document reference resolves to a valid stored document;
5. the file remains inside the private document boundary.

Anonymous access is denied.

Direct public attachment URLs are not considered an acceptable security
boundary.

---

## Pricing boundary

Wholesale pricing requires:

1. authenticated customer;
2. approved wholesale status;
3. approved wholesale capability;
4. valid configured Wholesale Price.

Otherwise the system returns the Regular Price.

---

## Administrative actions

Administrative lifecycle actions must use the established application service
and its authorization boundary.

The expected reviewer capability is:

`manage_woocommerce`

The plugin-owned wholesale capability is:

`approved_wholesale_customer`

The wholesale capability is an eligibility signal, not an administrative
permission.

---

## Test requirement

Each state must be tested independently.

Do not assume that a successful test for one state proves the security of
another state.