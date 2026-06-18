# WP Smart 404

**Stop losing visitors to broken links.**

WP Smart 404 automatically logs every 404 error on your WordPress site, then uses Claude AI to find the closest matching page — so you can fix dead links in seconds instead of hours.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?style=flat-square&logo=wordpress)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777BB4?style=flat-square&logo=php)
![Claude AI](https://img.shields.io/badge/Claude-Haiku-D97706?style=flat-square)
![License](https://img.shields.io/badge/License-GPLv2-green?style=flat-square)

---

## What It Does

Every 404 on your site is logged automatically. From the admin dashboard you can:

- See all broken URLs ranked by hit count
- Click **🔍 Find Match** — Claude AI reads the broken slug and all your pages/posts, then picks the best redirect target with a confidence score (high / medium / low) and a reason
- Click **↪ Save Redirect** — the 301 redirect is live instantly, no `.htaccess` changes needed
- Click **✨ Auto-Match All** — batch-process every unmatched 404 in one go

---

## Architecture

```
Visitor hits broken URL
        │
        ▼
template_redirect (priority 1)
        │  check redirect map (wp_options)
        │  → if match: wp_redirect 301 + exit
        │  → if not: continue to 404
        │
        ▼
template_redirect (priority 5)
        │  is_404() → log to DB
        │  INSERT or UPDATE hits + last_seen
        │
        ▼
Admin Dashboard
        │  WP_List_Table — sorted by hits
        │  Find Match → Claude API
        │  Save Redirect → update wp_options + DB flag
        │
        ▼
WS404_Redirects (wp_options: ws404_redirect_map)
        │  { "/broken-url": "https://site.com/correct-page" }
        │  Applied on next visit — pure PHP, zero .htaccess
```

---

## File Structure

```
wp-smart-404/
├── wp-smart-404.php              # Bootstrap
├── includes/
│   ├── class-database.php        # DB table: wp_smart404_logs
│   ├── class-logger.php          # Catches 404s via template_redirect
│   ├── class-matcher.php         # Claude API — finds best matching page
│   ├── class-redirects.php       # Stores/applies 301 redirects
│   └── class-admin.php           # Admin UI + AJAX handlers
├── assets/
│   ├── admin.css
│   └── admin.js
└── README.md
```

---

## Database Schema

Table: `wp_smart404_logs`

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Auto-increment |
| url | varchar(500) | The broken URL path |
| referrer | varchar(500) | Where the visitor came from |
| hits | int | Total times this 404 was hit |
| last_seen | datetime | Most recent hit timestamp |
| suggested_url | varchar(500) | Claude's suggested redirect target |
| suggested_title | varchar(255) | Page title of suggestion |
| confidence | varchar(20) | high / medium / low |
| redirect_saved | tinyint | 1 = redirect is live |
| created_at | datetime | First logged timestamp |

---

## How AI Matching Works

1. Plugin builds a list of all published posts and pages (title + URL, up to 100)
2. Sends the broken URL + full page list to Claude Haiku
3. Claude returns JSON: `{ url, title, confidence, reason }`
4. Confidence is `high` (clear typo/slug match), `medium` (topic match), or `low` (no good match)
5. Result saved to DB — you review and choose to save or skip

**Example:**

Broken URL: `/servics/web-design`

Claude returns:
```json
{
  "url": "https://yoursite.com/services/web-design",
  "title": "Web Design Services",
  "confidence": "high",
  "reason": "The broken URL is a typo of the /services/web-design slug — missing the 'e' in services."
}
```

---

## Installation

1. Upload `wp-smart-404` folder to `/wp-content/plugins/`
2. Activate the plugin
3. Go to **Smart 404 → Settings**
4. Enter your Claude API key from [console.anthropic.com](https://console.anthropic.com)
5. Visit any non-existent URL on your site to test the logger

---

## Redirect Storage

Redirects are stored as a WordPress option (`ws404_redirect_map`) — a simple array:

```php
[
  '/old-url'    => 'https://site.com/new-page',
  '/broken-one' => 'https://site.com/correct-page',
]
```

Applied via `template_redirect` at priority 1 — before WordPress even serves the 404 template. No `.htaccess` editing, no server config changes.

---

## Part of the 30-Day WordPress AI Plugin Series

**Day 3** of building one open-source WordPress + AI plugin every day.

- Day 1: [WP RAG FAQ](https://github.com/sahibbilal/wp-rag-faq)
- Day 2: [WP Content Repurposer](https://github.com/sahibbilal/wp-content-repurposer)
- Day 3: **WP Smart 404** ← you are here

Follow along: [bilalmahmood.dev](https://bilalmahmood.dev) · [LinkedIn](https://linkedin.com/in/bilalmahmood)

---

## License

GPLv2 or later
