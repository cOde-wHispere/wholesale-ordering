# Phase 7 — Recovery Procedure

## Purpose

This procedure describes how to restore the Wholesale Ordering application
after a serious deployment, database, filesystem or security failure.

---

## 1. Stop and assess

Before restoration:

1. stop further changes;
2. identify the affected environment;
3. determine whether the problem is:
   - application code;
   - database;
   - uploaded files;
   - private documents;
   - configuration;
   - cache;
   - security compromise;
4. preserve relevant logs where possible.

Do not overwrite evidence unnecessarily during a security investigation.

---

## 2. Select a known-good backup

Choose a backup from before the failure.

Confirm:

- database backup is readable;
- plugin files are present;
- required WordPress files are present;
- private documents are present;
- backup date is known.

---

## 3. Restore database

Restore the selected WordPress database backup.

Verify:

- users;
- applications;
- wholesale statuses;
- product data;
- pricing metadata;
- WooCommerce orders;
- plugin options;
- audit data.

---

## 4. Restore application files

Restore the corresponding application/plugin files.

Do not mix files from unrelated application versions with an older database
unless the compatibility has been confirmed.

The `wholesale-ordering` plugin must be restored as a complete version.

---

## 5. Restore private documents

Restore the protected document storage.

After restoration verify:

- documents are not publicly exposed;
- direct unauthenticated requests are denied;
- owners can access their own documents;
- authorized administrators can access documents;
- unrelated customers cannot access them.

---

## 6. Clear caches

After restoration:

1. clear application/page caches;
2. clear object caches where applicable;
3. clear CDN caches where applicable;
4. regenerate/revalidate rewrite rules where required;
5. verify authenticated and unauthenticated responses separately.

This is especially important because wholesale pricing is role-sensitive.

---

## 7. Verify application startup

Confirm:

- PHP compatibility;
- WordPress loads;
- WooCommerce loads;
- Wholesale Ordering loads;
- migrations report the expected state;
- no fatal errors occur.

---

## 8. Verify security

Repeat the Phase 7 security matrix:

- Guest;
- Pending;
- Rejected;
- Approved;
- Suspended;
- Administrator.

Verify both:

- authorization;
- price leakage protection.

---

## 9. Verify documents

Perform direct-request tests against known document references.

Expected result:

- anonymous request → denied;
- wrong customer → denied;
- document owner → allowed;
- authorized administrator → allowed.

No public attachment URL may be treated as the authorization mechanism.

---

## 10. Verify audit logging

Confirm that audit records remain available after restoration.

Test at least one safe administrative event and verify that it is recorded.

Do not record passwords, authentication secrets, payment data or document
contents.

---

## 11. Staging restoration

Before restoring production where practical:

1. restore the backup into staging;
2. run the application;
3. execute the security checks;
4. verify pricing;
5. verify customer access;
6. verify documents;
7. verify orders;
8. verify the audit log.

Only proceed to production restoration after the staging restoration is
confirmed usable.

---

## 12. Post-recovery review

After recovery document:

- failure date/time;
- backup used;
- restoration date/time;
- affected components;
- actions taken;
- security findings;
- remaining issues;
- final verification result.

The recovery is complete only when the application is operational and no known
critical authorization or wholesale-price leakage issue remains open.