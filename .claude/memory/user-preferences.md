---
name: user-preferences
description: How the user likes things done — preferences and feedback collected across sessions
metadata: 
  node_type: memory
  type: user
  originSessionId: 67232c9d-9bb5-4a29-9565-6e3fbfb286d0
---

## Buttons
- Header action buttons: **never `btn-sm`** — always standard size
- Card footer primary action: `btn btn-primary flex-grow-1` (fills space)
- Secondary/icon actions: `btn btn-outline-secondary` icon-only, standard size
- The user explicitly said "Make the dashboard buttons standard button" — this applies globally

## Design reference
Always use https://demo.tailadmin.com/ as the visual reference when improving pages.

## Icons in card headers
User asked to remove icon badges from card section headers — plain title text only. Do not re-add icons to card headers.

## Modal gradient line
User asked to remove the tri-color gradient crown bar on modals. It has been removed from `app.scss`. Do not re-add it.

## Back button style
User wants back link and page title on the **same line** with a `|` divider, not stacked above the title.

## Mobile first
Always test/consider mobile layout. The user actively uses the app on mobile and will notice overflows, stacking issues, and spacing problems.

## Git workflow
User asks to "push to GitHub" at the end of a work session. Always commit source files only (never `public/build/`). Write descriptive commit messages.

**Why:** `public/build/` is in `.gitignore` — the server builds assets independently.
