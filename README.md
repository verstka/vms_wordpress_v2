# Verstka Backend v2 — WordPress Plugin

Self-contained WordPress integration for the [Verstka](https://verstka.io) visual editor (API v2).

## Features

- Single-session visual editor for posts and pages
- Secure HMAC-SHA256 callbacks via WordPress REST API
- Automatic media and fonts storage in `wp-content/uploads`
- Frontend rendering with Verstka Viewer v2 (`viewer-latest.js`)
- No Composer, no external SDK — upload and activate

## Requirements

- WordPress 5.0+
- PHP 7.4+
- PHP extensions: `json`, `hash`, `zip`

## Installation

1. Copy the `vms_wordpress_v2` folder to `wp-content/plugins/` (or upload a ZIP via **Plugins → Add New**).
2. Activate **Verstka Backend v2**.
3. Open **Settings → Verstka Backend v2** and set your API Key and API Secret from Verstka.
4. Register the **Callback URL** shown on the settings page with your Verstka API key:
   - Default: `https://your-site.example/wp-json/verstka/v2/callback`

## Settings

| Setting | Description |
|---------|-------------|
| **Credentials** | API Key and API Secret from Verstka |
| **Additional Settings** | API URL, callback URL, viewer script, upload subdirectories, webhook basic auth |
| **Max Content Size (MB)** | Maximum ZIP download size for callbacks (stored in bytes internally). Default on first install: PHP `memory_limit` |
| **Dev Mode** | Toggles immediately via AJAX (no Save button) |
| **Verify Callback User Email** | When enabled, the editor user's email must match an existing site user with permission to edit posts. Default: off (HMAC-only) |

## Usage

1. Edit a post or page in WordPress.
2. Click **Edit in Verstka** (classic editor, block editor, or posts list).
3. Design content in the Verstka editor and save — content is stored automatically via callback.

## REST API

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/wp-json/verstka/v2/callback` | POST | Verstka webhook (material + fonts) |
| `/wp-json/verstka/v2/test` | GET | Diagnostics |

## Differences from v1 plugin

| | v1 | v2 |
|--|----|----|
| API | `api.r1.verstka.org/1/open` | `api.r2.verstka.org/integration/session/open` |
| Auth | MD5 salt | HMAC-SHA256 |
| Content | separate desktop/mobile HTML | `vms_html` + `vms_json` |
| Frontend | `api.js` | `viewer-latest.js` |

## License

MPL-2.0 — see [LICENSE](LICENSE).
