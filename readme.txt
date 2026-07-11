=== PrayerPop ===
Contributors: osain
Tags: prayer, church, ministry, notifications
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.5.11
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

PrayerPop helps churches collect prayer requests from their website, review them in WordPress, and notify the right people.

== Description ==

[PrayerPop](https://prayerpop.eu/) is a simple prayer submission workflow for WordPress churches, ministries, and Christian organizations. It helps you collect prayer requests, review them, and send notifications from WordPress. With PrayerPop Pro, churches can also receive testimonies and run flexible [Prayer Campaigns](https://prayerpop.eu/prayer-campaigns/) with prayer slots for things like 24h prayer, camps, Sundays, events, or focused prayer seasons.

Instead of asking people to find an email address, fill out a long form, or send prayer requests through scattered messages, PrayerPop gives your website one clear place for prayer. Visitors can submit a request from any page, and your team can review, approve, archive, email, and manage everything inside WordPress.

[PrayerPop](https://prayerpop.eu/) is useful when you want to:

* Receive prayer requests through your church website
* Organize incoming submissions in one admin screen
* Review requests before your team acts on them
* Send prayer requests by email
* Get immediate, daily, or weekly notification summaries
* Connect visitors to a simple prayer submission workflow
* Customize the wording, colors, bubble style, and form behavior
* Reduce spam with honeypot, minimum time, rate limit, and cooldown protection
* Keep prayer request management inside WordPress

= What PrayerPop makes simpler =

Prayer requests often arrive through email, social messages, paper cards, and conversations with different team members. [PrayerPop](https://prayerpop.eu/) brings those requests into one clear workflow. Your website becomes the entry point, and WordPress becomes the place where your team can manage what happens next.

The free plugin focuses on the core prayer request workflow: collect prayer requests, review them, and send notifications.

= Core features =

**Prayer request collection**

* Floating [PrayerPop](https://prayerpop.eu/) bubble for frontend prayer request submissions
* Prayer request submission form

**Review and admin workflow**

* Admin submissions screen for review and moderation
* Approve, Decline, Archive, Trash, Restore, and Mark as Answered actions
* Bulk actions for email sending, approval, decline, answered status, editing, archiving, and trashing
* Basic filters in the admin submissions list

**Notifications and settings**

* Email notifications with immediate, daily, or weekly scheduling
* Send Test Email tool
* Required admin review for incoming requests
* Retention period cleanup controls
* Primary color, global font family, bubble position, bubble animation, and bubble icon settings
* Text customization with JSON export and import
* Built-in documentation

= Need a larger prayer workflow? =

[PrayerPop](https://prayerpop.eu/) Pro adds testimonies, public [prayer and testimony walls](https://prayerpop.eu/demo-wall/), engagement actions, sharing, [Prayer Campaigns](https://prayerpop.eu/prayer-campaigns/), Divi modules, custom popup extras, and optional AI assisted moderation.

[Prayer Campaigns](https://prayerpop.eu/prayer-campaigns/) let churches create focused prayer signups with time slots. You can use them for 24h prayer, church camps, Sunday services, outreach events, prayer weeks, or any situation where people need to sign up for a specific prayer time.

See the full [PrayerPop Features](https://prayerpop.eu/features/) page or the [submissions wall](https://prayerpop.eu/demo-wall/) for examples.

== Installation ==

= Minimum Requirements =

* WordPress 5.8 or greater
* PHP 7.2 or greater

= Automatic installation =

1. Log in to your WordPress dashboard.
2. Go to `Plugins -> Add New`.
3. Search for `PrayerPop`.
4. Click `Install Now`.
5. Click `Activate`.

= Manual installation =

1. Download the [PrayerPop](https://prayerpop.eu/) plugin zip file.
2. Go to `Plugins -> Add New -> Upload Plugin`.
3. Upload the zip file.
4. Click `Install Now`.
5. Click `Activate`.

= Setup =

1. Open `PrayerPop -> Settings`.
2. In General, confirm the [PrayerPop](https://prayerpop.eu/) bubble is enabled.
3. In Notifications, set the recipient email and notification schedule.
4. In Style, adjust the bubble color, icon, position, animation, and font.
5. In Text Customization, edit the visible form labels and messages if needed.
6. Visit the frontend of your site and click the [PrayerPop](https://prayerpop.eu/) bubble to test a prayer request.
7. Review incoming requests in `PrayerPop -> Submissions`.

== Screenshots ==

1. [PrayerPop](https://prayerpop.eu/) bubble on a church website.
2. Prayer request form opened from the frontend bubble.
3. Submissions list for reviewing incoming prayer requests.
4. Notification settings for immediate, daily, or weekly email updates.
5. Style settings for customizing the bubble and form.
6. Text customization settings for changing visitor-facing wording.

== Frequently Asked Questions ==

= Who is PrayerPop made for? =

[PrayerPop](https://prayerpop.eu/) is made for churches, ministries, and Christian organizations that want a simple way to receive and manage prayer requests through WordPress.

= How do visitors submit prayer requests? =

Visitors click the [PrayerPop](https://prayerpop.eu/) bubble and complete the prayer request form.

= Where do submitted requests go? =

Submitted requests appear in `PrayerPop -> Submissions` for admin review.

= What happens after a request is submitted? =

The request starts as `Pending Action` until an admin reviews it.

= Can PrayerPop email submitted requests? =

Yes. Notifications can be sent immediately, daily, or weekly to the configured recipient email address.

= Can I change the form wording? =

Yes. Open `PrayerPop -> Settings -> Text Customization`.

= Can I change the bubble appearance? =

Yes. Open `PrayerPop -> Settings -> Style`.

= Where can I contact support? =

For questions, bugs, [feature requests](https://prayerpop.eu/contact/), or [support](https://prayerpop.eu/contact/), use the [PrayerPop contact page](https://prayerpop.eu/contact/).

= Does the free plugin include public prayer walls, testimonies, or Prayer Campaigns? =

No. The free plugin focuses on collecting, reviewing, and emailing prayer requests. Public [prayer walls](https://prayerpop.eu/demo-wall/), testimonies, [Prayer Campaigns](https://prayerpop.eu/prayer-campaigns/), Divi modules, and optional AI assisted moderation are part of [PrayerPop](https://prayerpop.eu/) Pro.

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

== Changelog ==

= 1.5.11 =
* Added the bubble positioning feature from PrayerPop Pro to the free version for greater convenience and in response to user feedback.
* Added side selection and precise X/Y offset controls to the Style settings.

= 1.5.10 =
* Improved WordPress.org listing copy and release metadata.
* Clarified the free prayer request workflow, setup steps, FAQ, privacy notes, and upgrade path.

= 1.5.8 =
* Prepared plugin review fixes, asset loading cleanup, and security-warning reductions.

= 1.5.7 =
* Improved admin submission workflows, settings guidance, and plugin compatibility.

= 1.5.5 =
* Documentation corrected to match the current feature set.

= 1.5.1 =
* Prayer-request workflow updates in admin and frontend.
* Settings, documentation, and readme alignment.

= 1.0.0 =
* Initial release.
