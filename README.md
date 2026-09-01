# Wholesale Ordering for WooCommerce

## About the project

Wholesale Ordering is a WordPress/WooCommerce plugin for running a wholesale ordering workflow alongside the normal WooCommerce store.

The main goal is simple:

- normal visitors and customers see the Regular Price;
- approved wholesale customers can see and use the configured Wholesale Price;
- wholesale access is controlled by customer status and capability;
- administrators can review wholesale applications and manage wholesale access;
- pricing remains consistent through the catalogue, cart, checkout and orders;
- private application documents and wholesale information are protected;
- the project can be tested, backed up, restored and maintained without changing WordPress or WooCommerce core files.

The plugin is designed as an extension of WooCommerce rather than as a replacement for WooCommerce.

## V1 scope

V1 uses one Regular Price and one Wholesale Price.

The wholesale price is not a second discount calculation. It is a product-level wholesale price configured by the administrator.

The authoritative pricing rule is:

1. The customer must be authenticated.
2. The wholesale status must be `approved`.
3. The customer must have the approved-wholesale capability.
4. A valid Wholesale Price must exist.
5. If any condition fails, the Regular Price is used.

| Customer state | Price |
|---|---|
| Guest | Regular Price |
| Pending | Regular Price |
| Rejected | Regular Price |
| Approved | Wholesale Price when valid |
| Suspended | Regular Price / restricted behavior |
| Administrator | Administrative access; customer-facing pricing follows the applicable context |

V1 does **not** introduce multiple wholesale tiers, quantity-break pricing or customer-specific negotiated pricing. Those are later/optional features in the specification. fileciteturn190file6L1190-L1210

## Main customer workflow

`Register → Business Details → Pending Review → Admin Review → Approve/Reject → Wholesale Access`

An approved customer can later be suspended or reactivated. Suspension removes wholesale privileges on subsequent authoritative server requests. fileciteturn189file2L259-L270

## Project structure

```text
wholesale-ordering/
├── wholesale-ordering.php
├── composer.json
├── composer.lock
├── phpunit.xml.dist
├── wp-tests-config.php
├── readme.txt
│
├── assets/
│   ├── css/
│   │   └── frontend.css
│   └── js/
│
├── bin/
│   └── install-wp-tests.sh
│
├── src/
│   ├── Plugin.php
│   ├── Admin/
│   ├── Applications/
│   │   ├── ApplicationRepository.php
│   │   ├── ApplicationService.php
│   │   ├── ApplicationValidator.php
│   │   └── WholesaleApplication.php
│   ├── Audit/
│   │   └── AuditLog.php
│   ├── Auth/
│   │   └── Registration.php
│   ├── Cart/
│   │   ├── CartIntegration.php
│   │   ├── CartPricing.php
│   │   └── CartValidator.php
│   ├── Checkout/
│   │   ├── CheckoutIntegration.php
│   │   └── CheckoutValidator.php
│   ├── Customers/
│   │   └── WholesaleStatus.php
│   ├── Frontend/
│   │   └── Frontend.php
│   ├── Infrastructure/
│   │   ├── Config.php
│   │   ├── Environment.php
│   │   ├── Logger.php
│   │   ├── MigrationRunner.php
│   │   ├── Requirements.php
│   │   └── RoleManager.php
│   ├── Notifications/
│   ├── Orders/
│   │   ├── OrderIntegration.php
│   │   └── ReorderService.php
│   ├── Pricing/
│   │   ├── CustomerContext.php
│   │   ├── PricingService.php
│   │   └── WooCommercePricingIntegration.php
│   ├── Products/
│   │   └── ProductFields.php
│   ├── REST/
│   └── Security/
│       ├── DocumentSecurity.php
│       └── PricingLeakageProtection.php
│
├── templates/
|   |__home.php
|   
├── tests/
│   ├── bootstrap.php
│   ├── Unit/
│   ├── Integration/
│   ├── Phase4Test.php
│   ├── PricingServiceTest.php
│   ├── Phase6Test.php
│   ├── Phase6EResponsiveTest.php
│   ├── Phase7SecurityTest.php
│   └── Phase7DocumentSecurityTest.php
└── docs/
    ├── PHASE-6E-RESPONSIVE-QA.md
    ├── PHASE-7-SECURITY-REVIEW.md
    ├── PHASE-7-PERMISSION-MATRIX.md
    ├── PHASE-7-BACKUP-PROCEDURE.md
    └── PHASE-7-RECOVERY-PROCEDURE.md
```

## What each part does

### `src/Plugin.php`

The main bootstrap and wiring point. It checks requirements, waits for WooCommerce, runs migrations, and registers administration, products, pricing, cart, checkout, orders, frontend, account and security services. The project keeps business rules out of the bootstrap class. fileciteturn191file6L525-L534

### `src/Applications/`

Owns the wholesale application lifecycle. `WholesaleApplication` represents application state, `ApplicationRepository` handles persistence, `ApplicationValidator` validates submissions and commands, and `ApplicationService` owns application operations and state transitions.

The application state is separate from pricing eligibility. A pending application does not grant wholesale pricing. fileciteturn190file3L727-L827

### `src/Auth/`

`Registration.php` connects additional wholesale/business fields to WooCommerce registration. WordPress/WooCommerce remains responsible for account creation and password handling. V1 requires first name, last name, email, password, company/trading name, phone, billing address and consent. fileciteturn189file2L271-L285

### `src/Customers/`

`WholesaleStatus.php` defines the supported wholesale states: pending, approved, rejected and suspended. Status is deliberately separate from authentication. fileciteturn189file5L453-L466

### `src/Pricing/`

The most important business boundary. `CustomerContext` describes the current customer, `PricingService` makes the authoritative pricing decision, and `WooCommercePricingIntegration` applies that decision to WooCommerce surfaces. The same decision must remain correct across catalogue, cart, checkout and orders. fileciteturn189file2L240-L258

### `src/Products/`

`ProductFields.php` manages wholesale product metadata, including Regular Price, Wholesale Price, Wholesale Minimum Quantity, Wholesale Quantity Step and Wholesale Only. Variations must have independent pricing where variations are used. fileciteturn189file2L246-L252

### `src/Cart/` and `src/Checkout/`

These layers revalidate important rules on server requests rather than trusting browser values. Pricing, quantity, stock and checkout requirements remain tied to the authoritative WooCommerce/application rules. fileciteturn189file8L892-L900

### `src/Orders/`

Connects wholesale behavior to WooCommerce orders. Order records preserve the actual price charged, while reorder behavior uses current eligibility and current product prices rather than blindly copying historical prices. fileciteturn189file8L798-L804

### `src/Frontend/`

Provides the customer-facing catalogue layer around WooCommerce. Phase 6A added the missing search, category and availability filtering controls while leaving WooCommerce authoritative for product queries and pricing. fileciteturn189file4L372-L413

### `src/Account/`

Adds the customer Wholesale Status experience and customer-safe order document presentation while leaving normal My Account features to WooCommerce. The specification requires a customer-facing wholesale status page and available order documents/invoices. fileciteturn191file9L885-L903

### `src/Admin/`

Provides the shop-owner administration boundary for wholesale applications and operational management. Normal business operation should not require direct database manipulation. fileciteturn189file1L80-L157

### `src/Audit/`

`AuditLog.php` records high-value actions such as approval, rejection, suspension, reactivation, important permission changes and relevant price changes. The specification explicitly requires an activity/audit log for sensitive wholesale/customer changes. fileciteturn189file8L1342-L1351

### `src/Security/`

`PricingLeakageProtection.php` protects against wholesale-price exposure through supported public/application interfaces. `DocumentSecurity.php` protects private business documents, including upload validation and restricted access. The specification treats price leakage and document access as security requirements. fileciteturn189file2L243-L250 fileciteturn191file9L943-L951

### `src/Infrastructure/`

Shared application services: configuration, environment checks, logging, migration/version management, runtime requirements and role/capability installation. The project prefers native WordPress/WooCommerce entities and metadata unless custom storage is justified. fileciteturn189file8L910-L917

### `assets/`

Contains frontend CSS and JavaScript. Phase 6E responsive work is intentionally presentation-focused and does not replace pricing, product queries or wholesale eligibility. fileciteturn191file1L156-L171

### `tests/`

Contains unit, integration and phase-specific tests. Automated tests support the project, while browser QA and the final security matrix still require testing in the actual environment.

### `docs/`

Contains phase-specific QA, security, permission, backup and recovery documentation. The production acceptance criteria require security and backup/recovery procedures to be documented before launch. fileciteturn190file6L1150-L1167

## Security principles

Security is part of the design, not optional polish.

1. Never use login status alone to grant wholesale access.
2. Approved status and approved capability must both be present.
3. Unauthorized customers receive the Regular Price.
4. Wholesale prices must not be leaked through alternate interfaces.
5. Browser input is never trusted for final pricing or authorization.
6. Administrative write actions require appropriate authorization and CSRF protection.
7. Input is validated and sanitized; output is escaped.
8. Supporting documents are private business information.
9. Passwords, payment card numbers and CVV are not stored by this plugin.
10. Payment processing remains delegated to compliant gateway integrations.
11. Historical order prices must remain stable after later product-price changes. fileciteturn189file2L253-L270

## templates

Contains plugin-owned presentation templates.
It controls presentation while relying on the existing WooCommerce product and pricing
architecture for product data and authorized prices.

## Catalogue and pricing

The intended customer journey is:

`Shop → Category → Search → Product → Related Products → Cart → Checkout → Order`

The catalogue can search and filter products, but customer-facing code must not read or print the stored wholesale price directly. The specification requires only the price the current user is authorized to see. fileciteturn189file8L815-L830

## Development setup

Current project dependencies use PHP 8.3+, PHPUnit 9.6, WordPress PHPUnit 7.1 and PSR-4 autoloading. The Composer test script is `phpunit --configuration phpunit.xml.dist`. fileciteturn190file7L743-L770

From the plugin directory:

```bash
composer install
composer test
```

The plugin lives in:

```text
wp-content/plugins/wholesale-ordering/
```

It must run as a WordPress/WooCommerce extension and must not modify WordPress or WooCommerce core files. fileciteturn189file5L467-L475

## Testing approach

### Customer-state testing

The core authorization matrix is:

- Guest → Regular Price
- Pending → Regular Price
- Rejected → Regular Price
- Approved → authorized Wholesale Price when valid
- Suspended → Regular Price / restricted behavior
- Administrator → administrative access

This follows the specification's required six-state authorization test. fileciteturn190file6L1170-L1189

### Responsive testing

Phase 6E covers Chrome, Edge, Safari where applicable, mobile, tablet and desktop. The responsive layer must not change pricing behavior. fileciteturn191file2L247-L277

### Security testing

Before production acceptance, verify that unauthorized users cannot retrieve wholesale prices, private application data or private documents, and cannot perform administrative actions. Also test invalid/expired nonces and disallowed document uploads. fileciteturn190file6L1170-L1189

## Project phases

```text
Phase 0  Requirements and technical discovery
   ↓
Phase 1  Foundation
   ↓
Phase 2  Registration and wholesale application
   ↓
Phase 3  Wholesale product and pricing engine
   ↓
Phase 4  Cart, checkout and order workflow
   ↓
Phase 5  Admin management and operations
   ↓
Phase 6  Customer account and frontend experience
   ↓
Phase 7  Security, privacy and reliability
   ↓
Phase 8  Testing, acceptance and production deployment
```

This is the engineering order defined by the project specification. The pricing engine is treated as the highest-risk technical component because pricing errors can cause both financial errors and confidential-price disclosure. fileciteturn191file4L424-L447

### Current implementation history

Phase 6A completed the missing customer-facing catalogue discovery layer: search, categories and filtering. fileciteturn189file4L372-L413

Phase 6E completed responsive QA across the supported browser and viewport matrix. Its changes were deliberately limited to presentation and responsive hardening. fileciteturn191file1L145-L171

Phase 7 provides the dedicated security, privacy and reliability pass, including authorization, REST/AJAX security, document security, audit logging and backup/recovery procedures. fileciteturn191file9L921-L984

Phase 8 remains the final acceptance and production-deployment gate.

## Important V1 boundary

Do not add business rules simply because they may be useful later.

V1 is intentionally based on one Regular Price and one Wholesale Price with role/status-secured access. Multiple wholesale tiers, quantity-break pricing, customer-specific negotiated pricing, credit limits, payment terms, ERP integrations and similar advanced features are later/optional scope. fileciteturn190file6L1190-L1210

## Backup and recovery

Before production use, maintain backups of the database, uploaded media/private business documents, plugin/source code and relevant configuration. The Phase 7 backup and recovery documents define the project procedure.

A backup is only useful if restoration can be performed and verified.

## Production readiness

Before release, confirm:

- automated tests pass;
- the six-state authorization matrix passes;
- wholesale-price leakage tests pass;
- private document access tests pass;
- cart and checkout pricing are correct;
- historical order prices remain correct;
- responsive customer workflows pass;
- backup and restore have been tested;
- deployment and rollback procedures are documented.

The specification's MVP acceptance criteria also require private application data protection, security documentation and backup/deployment procedures before production launch. fileciteturn190file6L1150-L1167

## Design philosophy

**WooCommerce owns commerce.**

**Application services own wholesale business rules.**

**PricingService owns the pricing decision.**

**ApplicationService owns wholesale application state changes.**

**Security services protect sensitive boundaries.**

**The frontend presents information but does not become a second business-rule engine.**

Keeping these responsibilities separate makes the plugin easier to test, safer to change and less likely to create conflicting pricing or authorization behavior.

## License

GPL-2.0-or-later
