# Advisory Module Implementation Plan

## Overview
Complete implementation of Advisory module with Articles and Farmer Questions sub-modules for FAO FFS-MIS system.

---

## Phase 1: Backend Database & Models

### 1.1 Article Categories
**Model:** `ArticleCategory`
**Table:** `article_categories`
**Fields:**
- id (bigint, PK)
- name (string, required, unique)
- description (text, nullable)
- icon (string, nullable) - for UI display
- order (int, default 0) - for sorting
- status (enum: Active/Inactive, default Active)
- created_at, updated_at

### 1.2 Article Posts
**Model:** `ArticlePost`
**Table:** `article_posts`
**Fields:**
- id (bigint, PK)
- category_id (bigint, FK → article_categories)
- title (string, required)
- slug (string, unique, indexed)
- content (longtext, required)
- summary (text, nullable) - short description
- image (text, nullable) - main image URL
- language (string, nullable) - English, Swahili, Luganda, etc.
- tags (text, nullable) - comma-separated
- status (enum: Published/Draft/Archived, default Draft)
- featured (enum: Yes/No, default No)
- has_video (enum: Yes/No, default No)
- video_url (text, nullable)
- has_audio (enum: Yes/No, default No)
- audio_url (text, nullable)
- has_youtube_video (enum: Yes/No, default No)
- youtube_video_url (text, nullable)
- has_pdf (enum: Yes/No, default No)
- pdf_url (text, nullable)
- author_id (bigint, FK → users)
- published_at (timestamp, nullable)
- view_count (int, default 0)
- likes_count (int, default 0)
- created_at, updated_at
**Indexes:** category_id, author_id, status, featured, published_at, slug

### 1.3 Farmer Questions
**Model:** `FarmerQuestion`
**Table:** `farmer_questions`
**Fields:**
- id (bigint, PK)
- title (string, required)
- content (longtext, required)
- author_id (bigint, FK → users)
- author_name (string) - cached for performance
- author_location (string, nullable)
- has_image (enum: Yes/No, default No)
- image_url (text, nullable)
- has_audio (enum: Yes/No, default No)
- audio_url (text, nullable)
- status (enum: Open/Answered/Closed, default Open)
- view_count (int, default 0)
- likes_count (int, default 0)
- answers_count (int, default 0)
- created_at, updated_at
**Indexes:** author_id, status, created_at

### 1.4 Farmer Question Answers
**Model:** `FarmerQuestionAnswer`
**Table:** `farmer_question_answers`
**Fields:**
- id (bigint, PK)
- question_id (bigint, FK → farmer_questions)
- content (longtext, required)
- author_id (bigint, FK → users)
- author_name (string) - cached
- author_location (string, nullable)
- has_image (enum: Yes/No, default No)
- image_url (text, nullable)
- has_audio (enum: Yes/No, default No)
- audio_url (text, nullable)
- has_video (enum: Yes/No, default No)
- video_url (text, nullable)
- has_youtube_video (enum: Yes/No, default No)
- youtube_video_url (text, nullable)
- has_pdf (enum: Yes/No, default No)
- pdf_url (text, nullable)
- is_approved (enum: Yes/No, default No)
- is_accepted (enum: Yes/No, default No) - marked by question author
- status (enum: Published/Draft/Archived, default Published)
- likes_count (int, default 0)
- created_at, updated_at
**Indexes:** question_id, author_id, is_approved, is_accepted, created_at

---

## Phase 2: Laravel Admin Controllers

### 2.1 ArticleCategoryController
- Grid: name, description, order, status, articles count
- Form: name, description, icon, order, status
- Actions: activate/deactivate, reorder
- Filters: status
- Custom columns: articles count

### 2.2 ArticlePostController
- Grid: title, category, author, status, featured, views, likes, published_at
- Form: Full fields with media upload support
- Actions: publish, unpublish, feature, unfeature
- Filters: category, status, featured, author, language, date range
- Custom columns: preview, statistics
- Rich text editor for content
- Image upload with preview
- Tag input with suggestions

### 2.3 FarmerQuestionController
- Grid: title, author, status, views, likes, answers count, created_at
- Form: View-only (submitted by farmers)
- Actions: close question, mark as answered
- Filters: status, author, date range
- Custom columns: author details, answer count
- Inline answers display

### 2.4 FarmerQuestionAnswerController
- Grid: question, content preview, author, status, is_approved, is_accepted, likes
- Form: Approve/reject, edit if needed
- Actions: approve, reject, accept answer
- Filters: question, is_approved, is_accepted, author
- Custom columns: media indicators

---

## Phase 3: Backend Routes & Menu

### 3.1 Laravel Admin Routes
```php
$router->resource('article-categories', ArticleCategoryController::class);
$router->resource('article-posts', ArticlePostController::class);
$router->resource('farmer-questions', FarmerQuestionController::class);
$router->resource('farmer-question-answers', FarmerQuestionAnswerController::class);
```

### 3.2 Menu Structure (Seeding)
```
Advisory
├── Article Categories
├── Article Posts
├── Farmer Questions
└── Question Answers
```

---

## Phase 4: API Implementation

### 4.1 Article Endpoints
```
GET    /api/articles/categories              - List all active categories
GET    /api/articles/posts                   - List published articles (paginated)
GET    /api/articles/posts/{id}              - Get article details (increments view)
GET    /api/articles/posts/featured          - Get featured articles
GET    /api/articles/posts/category/{id}     - Get articles by category
POST   /api/articles/posts/{id}/like         - Like/unlike article
GET    /api/articles/posts/search            - Search articles by title/tags
```

### 4.2 Farmer Question Endpoints
```
GET    /api/farmer-questions                 - List questions (paginated)
POST   /api/farmer-questions                 - Create new question (auth)
GET    /api/farmer-questions/{id}            - Get question with answers
POST   /api/farmer-questions/{id}/like       - Like question
POST   /api/farmer-questions/{id}/close      - Close own question (auth)
```

### 4.3 Farmer Answer Endpoints
```
POST   /api/farmer-questions/{id}/answers    - Post answer (auth)
POST   /api/farmer-answers/{id}/like         - Like answer
POST   /api/farmer-answers/{id}/accept       - Accept answer (question author)
```

### 4.4 API Resources
- ArticleCategoryResource
- ArticlePostResource
- ArticlePostDetailResource
- FarmerQuestionResource
- FarmerQuestionDetailResource
- FarmerQuestionAnswerResource

---

## Phase 5: Mobile App Implementation

### 5.1 Models
```
lib/models/
├── ArticleCategory.dart
├── ArticlePost.dart
├── FarmerQuestion.dart
└── FarmerQuestionAnswer.dart
```

### 5.2 Screens
```
lib/screens/advisory/
├── AdvisoryMainScreen.dart          - Main hub (Articles/Questions)
├── ArticleCategoriesScreen.dart     - List categories
├── ArticlePostsScreen.dart          - List articles by category
├── ArticlePostDetailScreen.dart     - View article with media
├── FarmerQuestionsScreen.dart       - List questions
├── FarmerQuestionDetailScreen.dart  - View question + answers
├── PostQuestionScreen.dart          - Create new question
└── PostAnswerScreen.dart            - Create new answer
```

### 5.3 Services
```
lib/services/
├── AdvisoryService.dart             - API calls
└── AdvisoryOfflineService.dart      - SQLite offline storage
```

### 5.4 Navigation
- Add to More menu → Advisory
- Advisory Main → Articles or Questions
- Breadcrumb navigation

---

## Phase 6: Offline Capabilities

### 6.1 Offline Storage (SQLite)
- Cache article categories
- Cache article posts (last 50)
- Queue farmer questions for upload
- Queue farmer answers for upload
- Sync when online

### 6.2 Sync Strategy
- Download: Pull articles on app open
- Upload: Push questions/answers when created
- Background sync: Every 30 minutes
- Manual sync button

---

## Phase 7: Testing & Sample Data

### 7.1 Sample Data
- 5 article categories
- 20 article posts (various statuses)
- 10 farmer questions
- 30 farmer answers

### 7.2 Test Cases
- CRUD operations from admin
- API endpoint responses
- Mobile app navigation
- Offline queue functionality
- Media upload/display
- Search functionality
- Like/view count updates

---

## Implementation Checklist

### Backend
- [ ] Create migrations (4 tables)
- [ ] Create models with relationships
- [ ] Create Laravel Admin controllers
- [ ] Add routes to admin
- [ ] Create menu seeder
- [ ] Seed sample data

### API
- [ ] Create API controllers
- [ ] Create API resources
- [ ] Add API routes
- [ ] Add authentication middleware
- [ ] Test all endpoints

### Mobile
- [ ] Create Dart models
- [ ] Create screens
- [ ] Create services
- [ ] Add offline support
- [ ] Add to navigation
- [ ] Test end-to-end

---

## Timeline Estimate
- Backend: 2-3 hours
- API: 1-2 hours
- Mobile: 3-4 hours
- Testing: 1 hour
**Total: 7-10 hours**

---

## Success Criteria
✅ Admins can fully manage articles from web portal
✅ Farmers can view articles on mobile
✅ Farmers can post questions on mobile
✅ Farmers can answer questions on mobile
✅ Offline functionality works seamlessly
✅ All media types supported
✅ Search works efficiently
✅ UI is consistent with existing app
✅ Zero bugs in production

---

**Status:** 📋 Planning Complete - Ready for Implementation
**Next Step:** Phase 1 - Create Database Migrations
