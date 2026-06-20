---
name: project-overview
description: "Tech stack, file structure conventions, and build process for CourtMaster admin"
metadata: 
  node_type: memory
  type: project
  originSessionId: 67232c9d-9bb5-4a29-9565-6e3fbfb286d0
---

## App
CourtMaster — multi-tenant badminton/sports court booking SaaS.
Live URL: https://getcourtmaster.com/admin
Demo tenant: demo-pickleball

## Stack
- **Laravel** (Blade views, controllers, routes)
- **Bootstrap 5** with custom SCSS → compiled via `npm run build`
- **Alpine.js** for interactivity (`x-data`, `x-show`, `x-transition`)
- **ApexCharts** for dashboard charts
- **Bootstrap Icons** (`bi-*`)
- **Spatie Media Library** for court photos

## Key paths
- Views: `resources/views/admin/`
- SCSS: `resources/scss/app.scss`
- Components: `resources/views/components/` (e.g. `x-page-header`, `x-stat-card`, `x-filter-bar`, `x-badge`, `x-empty-state`)
- Controllers: `app/Http/Controllers/Admin/`
- Routes: `routes/web.php`
- Built assets: `public/build/` (gitignored — never commit)

## Build
```
npm run build
```
Run after any SCSS change. Blade-only changes need no build.

## Route cache
If a new route is added and throws "Route not defined": `php artisan route:clear && php artisan cache:clear`

## Design reference
https://demo.tailadmin.com/ — used as visual inspiration throughout
