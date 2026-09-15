# Changelog

All notable changes to PrayerPop are documented here. This file mirrors the release history in `readme.txt`.

## 1.7.0

- Added a guided first-time setup flow for church details, appearance, welcome text, notification recipients, and final checks.
- Refined the visitor Chat interface with shared headers, consistent message lists, improved popup spacing, and a unified message input.
- Added a three-step Chat start flow for visitor name, optional reply email, and the first message.
- Improved the Chat screen controls, form titles, and responsive behavior.
- Added Email wording settings so administrators can translate supported email text without editing email HTML, layout, or links.
- Added separate Email wording JSON export and import. Simplified Language & Text import so selecting a file starts the import.
- Updated the PrayerPop plugin icon assets and tested compatibility with WordPress 7.1.

## 1.6.6

- Made the Send One More action an outlined secondary button in the popup and standalone form, using the site primary colour.
- Refined popup sender spacing.

## 1.6.5

- Moved the Chat inbox save notice so it does not push the page heading down.

## 1.6.4

- Simplified the visitor Chat opening screen and removed unused space below its onboarding fields.
- Kept the Classic Popup welcome card visibly overlapping its background at different content lengths.
- Kept third-party plugin notices out of PrayerPop admin screens while leaving WordPress and PrayerPop notices visible.
- Updated translations and third-party notice documentation.

## 1.6.3

- Added testimony submissions to the free popup. Churches can enable prayer requests, testimonies, or both, with dedicated wording, placeholders, confirmation messages, and saved drafts for each form.
- Made the popup open directly to its only enabled form and added an optional welcome-header setting for Simple and Classic Popup layouts.
- Made Chat polling show a temporary connection warning and retry progressively without losing a visitor's draft.
- Improved the Classic Popup welcome-card layout so the header image and colour treatment remain consistent at different text lengths.

## 1.6.2

- Added Chat retention settings and WordPress personal-data export and erasure support for Chat records.
- Made the visitor bubble open the prayer request form when Chat is turned off.
- Made Chat storage and prayer-request notifications more reliable when WordPress cannot complete the first attempt.
- Improved submission and bulk-action handling so failed updates are not shown as successful.
- Hardened rate limiting for sites behind a trusted reverse proxy.
- Fixed the notification recovery schedule being cleared when PrayerPop is deactivated or uninstalled.

## 1.6.1

- Added an editable Chat opening message in the Chat inbox.
- Added team profile images to team replies and an unread reply count on the closed PrayerPop launcher.
- Improved Chat transitions, message grouping, refresh behaviour, and scroll preservation for returning visitors.
- Improved Chat message styling so team replies use a consistent profile, message bubble, sender name, and timestamp treatment.
- Improved Chat and Language & Text settings with clearer controls, search, and expandable text groups.
- Fixed shared Chat, text, design, and notification settings being overwritten when switching between PrayerPop Free and Pro.
- Fixed uninstall handling so shared Chat data is preserved while the other PrayerPop edition remains installed.

## 1.6.0

- Added a simple PrayerPop Chat with one classic visitor layout and a shared WordPress inbox.
- Added secure visitor sessions, optional reply email collection, admin replies, unread indicators, and close/reopen/delete controls.
- Added email notifications for new visitor messages and team replies.
- Improved scheduled prayer-request notifications so daily and weekly delivery survives reactivation and follows the WordPress timezone.
- Avoided loading frontend bubble and form assets when the bubble is disabled.
- Reorganised Free settings into clear task-based tabs, with global settings search and searchable, collapsible Language & Text groups.

## 1.5.12

- Fixed the Style settings icon switcher so selected Dashicons and Tabler icons preview and save correctly.
- Corrected bubble icon alignment for fixed Circle and Square layouts.

## 1.5.11

- Added the bubble positioning feature from PrayerPop Pro to the free version.
- Added side selection and precise X/Y offset controls to the Style settings.

## 1.5.10

- Improved WordPress.org listing copy and release metadata.
- Clarified the free prayer request workflow, setup steps, FAQ, privacy notes, and upgrade path.

## 1.5.8

- Prepared plugin review fixes, asset loading cleanup, and security-warning reductions.

## 1.5.7

- Improved admin submission workflows, settings guidance, and plugin compatibility.

## 1.5.5

- Corrected documentation to match the current feature set.

## 1.5.1

- Updated prayer-request workflow, settings, documentation, and readme alignment.

## 1.0.0

- Initial release.
