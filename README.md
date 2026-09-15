# PrayerPop

> A WordPress plugin for church prayer requests, testimonies, and private visitor Chat.

**Version:** 1.7.0 · **License:** GPL-2.0-or-later · **WordPress:** 5.8+ · **PHP:** 7.2+

[Documentation](#documentation) · [Changelog](CHANGELOG.md) · [WordPress.org listing](https://wordpress.org/plugins/prayerpop/)

## Overview

PrayerPop gives a church one place to receive prayer requests, testimonies, and visitor messages. Visitors can submit from the website. Church staff review and manage the content inside WordPress.

Prayer requests and testimonies are held for review by default. Chat conversations stay in the church's WordPress database and use a private browser token so a visitor can return to their own conversation.

## Documentation

| Document | Includes |
| --- | --- |
| [Changelog](CHANGELOG.md) | Complete release history from 1.0.0 to the current version |
| [WordPress.org documentation](readme.txt) | Setup guide, FAQ, screenshots, privacy details, and support information |

PrayerPop Pro adds public walls, shortcodes, Divi modules, prayer campaigns, Chat attachments, and optional AI moderation. See the [PrayerPop features page](https://prayerpop.eu/features/) for the edition comparison.

## Features

| Area | Included |
| --- | --- |
| Prayer requests and testimonies | Website forms, anonymous submissions, administrator review, approval, decline, answered status, and archive workflow |
| Visitor Chat | Guided first message, private visitor conversation, WordPress inbox, staff replies, unread state, and configurable retention |
| Church setup | Guided setup for church details, appearance, welcome text, and notification recipient |
| Appearance and wording | Popup controls, text settings, email wording, and JSON export/import for supported text fields |
| Notifications | WordPress email alerts for new submissions and Chat messages |
| Privacy | WordPress personal-data export and erasure support for Chat records, retention controls, and privacy-policy guidance |
| Anti-spam | Local cooldown and rate-limit checks. PrayerPop does not require CAPTCHA or add analytics or advertising trackers. |

## Installation

### From WordPress.org

1. In WordPress, go to **Plugins → Add New**.
2. Search for **PrayerPop**.
3. Install and activate the plugin.
4. Open **PrayerPop → Settings** and complete the setup flow.

### From this repository

1. Download the repository as a ZIP file.
2. Extract the `prayerpop` folder into `wp-content/plugins/`.
3. Activate **PrayerPop** in WordPress.

Do not activate PrayerPop and PrayerPop Pro at the same time. They share the same data model and WordPress prevents both editions from loading together.

## Directory Structure

```text
prayerpop/
├── prayer-pop.php                 Plugin header, constants, activation hooks
├── core/
│   ├── class-prayer-pop.php        Main plugin bootstrap and asset registration
│   └── includes/classes/           Settings, submissions, Chat, email, and upgrades
├── assets/
│   ├── css/                       Front-end and admin styles
│   ├── js/                        Front-end and admin scripts
│   ├── images/                    Icons and default images
│   └── data/                      Built-in FAQ and reference data
├── templates/                     Visitor popup and form markup
├── src/Admin/                     WordPress admin list table
├── languages/                     Translation template and translations
├── readme.txt                     WordPress.org listing content
└── uninstall.php                  Removes plugin-owned settings and Chat tables
```

## Data Storage

PrayerPop uses standard WordPress storage where it fits and custom tables for Chat.

| Storage | Purpose |
| --- | --- |
| `wp_posts` (`prayer_request`) | Prayer requests and testimonies |
| `wp_postmeta` | Submission type, review status, anonymous marker, visibility, and answered details |
| `wp_prayerpop_chat_conversations` | Visitor identity, private token hash, conversation state, unread state, and timestamps |
| `wp_prayerpop_chat_messages` | Visitor and staff messages |
| `wp_options` | Plugin settings, wording, styles, and setup state |

`wp_` is the site's table prefix. WordPress may use a different prefix.

## REST API

PrayerPop has an internal REST API for the visitor Chat and the WordPress Chat inbox. It is not a versioned public integration API.

Base namespace: `/wp-json/prayerpop/v1/`

| Endpoint group | Use | Access |
| --- | --- | --- |
| `/chat/conversations`, `/chat/conversation` | Start and restore a visitor conversation | Visitor token is checked by the callback |
| `/chat/messages`, `/chat/read` | Read, send, and mark visitor messages as read | Visitor token is checked by the callback |
| `/chat/admin/conversations` | List, read, delete, reply to, and update conversations | WordPress `manage_options` capability |

These endpoints can change with the Chat user interface. Use WordPress hooks or the plugin user interface for integrations unless a stable API is documented in a future release.

## Privacy and External Services

PrayerPop stores its content in the site's WordPress database. It does not add external analytics or advertising trackers. It does use a secure, HTTP-only cookie to restore a visitor's Chat conversation and local protection data to slow repeated submissions.

Administrators can use **Tools → Export Personal Data** and **Tools → Erase Personal Data** for Chat records that match a visitor email address. Add the supplied privacy-policy guidance to the church's privacy policy where appropriate.

The retention period can archive and later remove old submissions. Chat conversations are permanently removed after the configured Chat retention period. Uninstall removes plugin settings and Chat tables unless PrayerPop Pro is still installed and uses the shared data. Prayer requests and testimonies remain WordPress posts until an administrator deletes them.

Email is sent through the site's configured WordPress mail service. The free plugin has no license validation or AI moderation service.

## Development

This repository contains the source used for the WordPress.org edition. Keep the plugin header and `readme.txt` version in sync for releases. Before packaging, run PHP syntax checks and test activation on a clean WordPress site with `WP_DEBUG` enabled.

## License

PrayerPop is licensed under [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
