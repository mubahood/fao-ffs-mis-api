-- ========================================
-- Advisory Module Menu Addition
-- Date: 29 November 2025
-- Purpose: Add Advisory module to admin menu
-- ========================================

-- Add Advisory Module as main menu item (order 600)
INSERT INTO admin_menu (parent_id, `order`, title, icon, uri, created_at, updated_at) VALUES
(0, 600, 'Advisory', 'fa-lightbulb', NULL, NOW(), NOW());

-- Get the parent ID
SET @advisory_id = LAST_INSERT_ID();

-- Add sub-menu items
INSERT INTO admin_menu (parent_id, `order`, title, icon, uri, created_at, updated_at) VALUES
(@advisory_id, 601, 'Categories', 'fa-folder', 'advisory-categories', NOW(), NOW()),
(@advisory_id, 602, 'Articles', 'fa-newspaper', 'advisory-posts', NOW(), NOW()),
(@advisory_id, 603, 'Farmer Questions', 'fa-question-circle', 'farmer-questions', NOW(), NOW()),
(@advisory_id, 604, 'Answers', 'fa-comments', 'farmer-question-answers', NOW(), NOW());

-- ========================================
-- Verification Query
-- ========================================
-- Run this to verify the menu was added:
SELECT id, parent_id, `order`, title, icon, uri FROM admin_menu WHERE parent_id = @advisory_id OR id = @advisory_id;

-- ========================================
-- Menu Structure Added:
-- ========================================
-- └─ Advisory
--    ├─ Categories        → advisory-categories
--    ├─ Articles          → advisory-posts
--    ├─ Farmer Questions  → farmer-questions
--    └─ Answers           → farmer-question-answers
-- ========================================
