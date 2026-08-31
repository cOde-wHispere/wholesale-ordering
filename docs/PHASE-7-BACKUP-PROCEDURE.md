# Phase 7 — Backup Procedure

## Purpose

The backup procedure protects the Wholesale Ordering application and its
business data before production deployment and before major maintenance.

Backups must cover both database data and files.

---

## 1. Database backup

The WordPress database must be backed up using the site's normal trusted
database backup mechanism.

The backup must include:

- WordPress users;
- user metadata;
- wholesale application metadata;
- wholesale status data;
- product metadata;
- WooCommerce orders;
- WooCommerce order items;
- plugin configuration/options;
- audit-log data.

A database backup must be verified as readable.

---

## 2. File backup

The file backup must include:

- WordPress files required by the site;
- the `wholesale-ordering` plugin;
- WooCommerce files/configuration where appropriate;
- theme files;
- required uploaded media;
- private wholesale document storage.

Private business documents must be included in the protected backup.

Do not expose private documents merely because they are included in a
backup process.

---

## 3. Backup timing

At minimum, create a verified backup:

- before a production deployment;
- before database/schema changes;
- before plugin upgrades;
- before large application changes;
- before restoring staging from production.

---

## 4. Backup verification

A backup is not considered complete merely because a backup command finished.

Verify:

1. the backup file exists;
2. the file can be read;
3. the database archive is not empty/corrupt;
4. required WordPress/plugin files are present;
5. private document files are present where applicable.

---

## 5. Staging verification

Where practical:

1. create a staging restoration from the backup;
2. restore the database;
3. restore required files;
4. verify WordPress loads;
5. verify WooCommerce loads;
6. verify the Wholesale Ordering plugin loads;
7. verify customer/application records;
8. verify product pricing;
9. verify private documents remain private.

---

## 6. Security of backups

Backups contain sensitive business information.

They must not be placed in a publicly accessible web directory.

Backup access must be restricted to authorized personnel.

---

## 7. Recovery point

Before production release, confirm:

- the latest database backup;
- the latest file backup;
- the backup location;
- the person responsible for restoration;
- the restoration procedure.

The recovery procedure is documented separately in
`PHASE-7-RECOVERY-PROCEDURE.md`.