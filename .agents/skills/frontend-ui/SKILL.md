---
name: frontend-ui
description: >-
  Frontend HTML, CSS, and UI/UX guidelines for the Student Registration website.
  Use when designing or updating forms, student tables, navigation, responsive
  layouts, stylesheets (style.css), or client-side interactions.
---

# Frontend & UI Skill (Student Registration)

## Architecture & Scope
- **Stack:** Semantic HTML5, vanilla CSS (`style.css`), and lightweight progressive-enhancement JavaScript where helpful. No build step or heavy frameworks required.
- **Pages:** `index.php` (registration form), `view_students.php` (student directory table), `edit_student.php` (update form), and `index.html` (static reference/landing if used).

## Required Practices
1. **Centralized Styling:** Put shared styles in `style.css` rather than duplicating `<style>` blocks or inline `style="..."` attributes across PHP pages.
2. **Responsive & Mobile-Friendly Layout:**
   - Include `<meta name="viewport" content="width=device-width, initial-scale=1.0">` on all pages.
   - Ensure forms (`.container`) and data tables (`.table-container` with horizontal scroll wrapper on small screens) work cleanly on both desktop and mobile.
3. **Accessibility & Semantic HTML:**
   - Use proper `<label for="...">` associations, meaningful `<title>` tags, clear heading hierarchy, and accessible button/link contrast.
   - Provide clear `:focus` states for keyboard navigation on inputs, selects, buttons, and action links.
4. **Consistent Visual System:**
   - Keep a cohesive color palette, spacing, typography, and feedback states (`.success`, `.error`, `.edit-btn`, `.delete-btn`).
   - Preserve existing form field `name` attributes (`full_name`, `email`, `course`) unless updating the corresponding PHP handlers too.
5. **User Feedback & Safety:**
   - Keep clear confirmation prompts for destructive actions (e.g., deleting a student).
   - Provide clear navigation links between Registration (`index.php`) and View Students (`view_students.php`).
6. **Syncing to WSL:** After editing frontend files, remind the user to run `sync-to-wsl.ps1` so `/var/www/html` in WSL reflects the updates.
