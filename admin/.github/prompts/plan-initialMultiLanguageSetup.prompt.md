# Initial Multi-language Setup & Minimal Frontend for KORFDYA

## Overview

Set up a Laravel 12.4 app with dual-language (English/Arabic) capability, JSON-based translations, RTL/LTR switching, and a Livewire/Blade + Tailwind minimal frontend (landing page & navigation), and premium branding. Laravel Livewire, Blade templates, and Tailwind CSS throughout. Includes an example Product entity using the translation approach.

## Implementation Plan

### 1. Laravel Multi-language Foundation

- Configure localization: EN and AR
- Add middleware for language switching
- Create language switch (controller/route + basic frontend)
- Store translation strings as JSON in configs and as JSON fields in Product table (migration example)

### 2. RTL/LTR Foundation

- Add RTL/LTR CSS classes based on active locale
- Set Livewire and Blade templates to respect direction

### 3. Product Model Example

- Add products table migration with JSON translation fields (e.g. name, description)
- Implement Product model, CRUD routes, and controller (backend only)

### 4. Blade Minimal Frontend

- Scaffold Blade with Vite integration
- Create landing page, navigation (mobile-first, thumb-friendly)
- Add language switcher with live switching (EN↔AR), updates CSS direction
- UI uses design specs: brand colors, Playfair/Inter/Noto fonts, luxury whitespace

### 5. Laravel Blade Integration

- Use Blade for server-rendered layout, pass initial locale & direction to Livewire
- Ensure accessibility (WCAG 2.1 AA focus)

### 6. Documentation

- Document all config in /docs/tech-spec.md for multilingual, structure, and RTL
- Annotate areas for future: Payment, shipping, admin, gallery, etc.

## Todos

- Each major bullet above as a specific actionable todo, blocking until the prior is finished.
- All changes must reference /docs/tech-spec.md and /docs/design-spec.md and reflect premium, RTL/LTR, and bilingual standards.

### To-dos

- [ ] Set up Laravel 12.4 localization config for EN/AR, routes, middleware, config files, and controller methods.
- [ ] Create products table migration with JSON fields for name/description (EN/AR). Document in tech-spec.md.
- [ ] Implement language switching route/controller and persist selection on frontend/backend.
- [ ] Add RTL/LTR CSS classes. Ensure Blade and Livewire respect direction by locale. Apply in navigation and layout.
- [ ] Scaffold Blade (Vite), set up minimal app shell, integrate with Laravel.
- [ ] Add basic Product model and backend routes/controller with CRUD. Use JSON translation fields.
- [ ] Create landing page and mobile-first navigation in Livewire, using brand design and fonts, supporting EN/AR + RTL/LTR.
- [ ] Build frontend language switcher (Blade) with live updates (direction, locale, persistence).
- [ ] Integrate Blade layout with Livewire+ pass initial lang/dir context. Ensure ARIA/accessibility compliance.
- [ ] Annotate work in /docs/tech-spec.md and /docs/design-spec.md. Mark areas for future (payment, shipping, admin, gallery).
