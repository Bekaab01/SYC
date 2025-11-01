# Admin Panel Enhancement: Remove Carrier Management & Enhance Association Management

## A. Remove Carrier Management
- [ ] Remove carrier tab from sidebar navigation in index.php
- [ ] Remove carrier tab content and related HTML from index.php
- [ ] Remove carrier POST handlers (approve_carrier, reject_carrier) from index.php
- [ ] Deprecate carrier-details.php (move to deprecated folder or remove)

## B. Enhance Association Management
- [ ] Create association_audit table SQL schema
- [ ] Create admin/associations_pending.php - List page with pagination for pending associations
- [ ] Create admin/association_view.php - Detailed view page with documents and approve/reject
- [ ] Create admin/association_action.php - POST handler for approve/reject with validation and audit logging
- [ ] Update index.php association tab - Replace inline actions with links to new pages
- [ ] Implement email notifications for approval/rejection decisions

## C. Security & Authorization
- [ ] Add admin session checks to all new association management pages
- [ ] Implement audit logging for all admin actions on associations
- [ ] Add CSRF protection to action forms

## D. Minor UX & Safety
- [ ] Secure document serving - ensure proper access controls for association documents
- [ ] Add pagination and search to associations_pending.php

## E. Testing & Validation
- [ ] Execute SQL to create association_audit table
- [ ] Test new association management pages
- [ ] Test email notification system
- [ ] Verify admin authentication on all new pages
- [ ] Test document access security
- [ ] Create acceptance test checklist
