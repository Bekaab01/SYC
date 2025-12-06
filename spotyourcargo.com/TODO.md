# Truck Management Implementation Tasks

## 1. Database Schema Changes
- Add "deleted_at" DATETIME NULL column to "association_trucks" table for soft deleting trucks.
- Verify and add foreign keys on "association_id" and "driver_id" if missing.
- Add indexes for foreign key columns if needed.

## 2. Backend updates in association-dashboard.php
- Modify all SELECT queries on association_trucks to exclude rows where deleted_at IS NOT NULL.
- Add/update CRUD operations for trucks including soft delete by setting deleted_at timestamp.
- Maintain driver assignments and their status updates appropriately.
- Use database transactions where needed for consistency.
- Add proper error handling and notifications on CRUD and assignment operations.

## 3. Frontend UI updates in association-dashboard.php
- Extend Trucks UI tab to include:
  - Edit truck modal/forms.
  - Soft delete (archive) truck functionality with confirmation.
- Ensure soft deleted trucks do not appear in listings.
- Use existing modals for truck registration and driver assignment with necessary improvements.

## 4. Testing and Validation
- Test all CRUD operations on trucks including soft delete.
- Verify driver assignment transitions status correctly.
- Confirm UI accurately reflects current database state excluding soft deleted trucks.
- Verify notifications creation on key actions.

---

The implementation will be done incrementally with testing after each major step.
