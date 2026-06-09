=== PrayerPop ===
Contributors: osain
Tags: prayer, church, ministry, notifications
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.5.6
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

PrayerPop helps churches collect prayer requests through a floating bubble, review them in WordPress admin, and receive email notifications.

== Description ==

PrayerPop includes a focused prayer-request workflow:

* Floating PrayerPop bubble for frontend prayer request submissions
* Prayer request submission form
* Admin submissions screen for review and moderation
* Approve, Decline, Archive, Trash, Restore, and Mark as Answered actions
* Bulk send via email, approve, decline, mark as answered, edit, archive, and trash actions
* Basic filters in the admin submissions list
* Email notifications with immediate, daily, or weekly scheduling
* Send Test Email
* Required admin review for incoming requests
* Retention period cleanup controls
* Primary color, global font family, bubble position, bubble animation, and bubble icon settings
* Text Customization with text export/import JSON
* Honeypot, minimum-submit-time, rate-limit, and cooldown protection
* Built-in documentation

== Quick Start ==

1. Install and activate the plugin.
2. Open `PrayerPop -> Settings`.
3. In General, confirm the PrayerPop bubble is enabled.
4. In Notifications, set the recipient email and notification schedule.
5. In Style, adjust the bubble color, icon, position, animation, and font.
6. In Text Customization, edit the visible form labels and messages if needed.
7. Visit the frontend of your site and click the PrayerPop bubble to test a prayer request.
8. Review incoming requests in `PrayerPop -> Submissions`.

== Frontend Usage ==

PrayerPop is bubble-first.

The plugin adds a floating bubble to the frontend when `Show PrayerPop Bubble` is enabled in General settings. Visitors click the bubble, submit a prayer request, and the request is saved for admin review.

== Admin Workflow ==

Submissions are stored as `prayer_request` custom post type entries.

Core statuses:

* `Pending Action`: waiting for admin decision.
* `Approved`: approved by an admin.
* `Answered`: approved prayer request marked as answered.
* `Declined`: rejected by an admin.
* `Archived`: kept for history, outside the active queue.
* `Trash`: moved to trash.

Available single-item actions include:

* Approve
* Decline
* Archive / Unarchive
* Move to Trash / Restore
* Mark as Answered / move answered prayer back to Approved

Available bulk actions include:

* Send selected submissions via email
* Approve selected
* Decline selected
* Mark selected prayer requests as answered
* Edit selected submissions
* Archive
* Trash

== Notifications ==

Notification settings support:

* Enable/disable email notifications
* Recipient email address
* Frequency: immediate, daily, weekly
* Time/day scheduling for daily/weekly notifications
* Send Test Email

Email template placeholders:

* `{type}`
* `{name}`
* `{message}`
* `{pending_count}`
* `{admin_url}`
* `{site_url}`
* `{site_name}`

== Text Customization ==

Use `PrayerPop -> Settings -> Text Customization` to edit frontend strings.

You can:

* Edit prayer request form labels and messages
* Export text fields as JSON
* Import JSON to restore or migrate text settings

== Privacy & Data Handling ==

= Stored data =

* Submission content is stored as WordPress posts (`prayer_request` post type).
* Metadata includes name/anonymous marker, submission type, public marker, moderation status, and answered-prayer note when used.
* Plugin settings are stored in WordPress options.

= Browser storage =

* Local anti-spam/cooldown tracking is used for submission protection.

= Retention cleanup =

* Retention period is configurable in General settings.
* Older approved or answered items can be archived first and cleaned later.

= Uninstall =

* Plugin options and scheduled cron hooks are removed on uninstall.
* Submission posts remain in WordPress after uninstall unless you delete them manually.

== Frequently Asked Questions ==

= How do visitors submit prayer requests? =

Visitors click the PrayerPop bubble and complete the prayer request form.

= Where do submitted requests go? =

Submitted requests appear in `PrayerPop -> Submissions` for admin review.

= What happens after a request is submitted? =

The request starts as `Pending Action` until an admin reviews it.

= Can I change the form wording? =

Yes. Open `PrayerPop -> Settings -> Text Customization`.

= Can I change the bubble appearance? =

Yes. Open `PrayerPop -> Settings -> Style`.

== Changelog ==

= 1.5.6 =
* Improved admin submission workflows, settings guidance, and plugin compatibility.

= 1.5.5 =
* Documentation corrected to match the current feature set.

= 1.5.1 =
* Prayer-request workflow updates in admin and frontend.
* Settings, documentation, and readme alignment.

= 1.0.0 =
* Initial release.
