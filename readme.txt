=== Unconfirmed  ===
Contributors: boonebgorges, cuny-academic-commons
Donate link: http://teleogistic.net/donate
Tags: multisite, network, activate, activation, email
Requires at least: 3.1
Tested up to: 5.4
Stable tag: 1.3.5

Allows WordPress admins to manage unactivated users, by activating them manually, deleting their pending registrations, or resending the activation email.

== Description ==

If you run a WordPress or BuddyPress installation, you probably know that some of the biggest administrative headaches come from the activation process. Activation emails may be caught by spam filters, deleted unwillingly, or simply not understood. Yet WordPress itself has no UI for viewing and managing unactivated members.

Unconfirmed creates a Dashboard panel under the Users menu (Network Admin > Users on Multisite) that shows a list of unactivated user registrations. For each registration, you have the option of resending the original activation email, or manually activating the user.

Note that the plugin works for the following configurations:
1. Multisite, with or without BuddyPress
2. Single site, with BuddyPress used for user registration

There is currently no support for single-site WP registration without BuddyPress.

== Installation ==

1. Install
1. Activate
1. Navigate to Network Admin > Users > Unconfirmed

== Changelog ==

= 1.3.5 =
* Fix compatibility with FacetWP

= 1.3.4 =
* Security hardening
* PHPCS improvements

= 1.3.3 =
* Internationalization improvements

= 1.3.2 =
* Internationalization improvements
* Coding standards fixes

= 1.3.1 =
* Fix bug that causes email resend to fail on BP 2.5+

= 1.3 =
* Use custom 'moderate_signups' cap instead of 'create_users' when adding Unconfirmed panel
* Add fine-grained filter for whether to use the Network Admin
* Fix ordering in Multisite

= 1.2.7 =
* Better loading of assets over SSL

= 1.2.6 =
* Removed PHP4 constructors from boones-* libraries, to avoid PHP notices
* Enable search

= 1.2.5 =
* Improved protection against XSS

= 1.2.4 =
* Improved sanitization
* Improved bootstrap for loading in various environments
* Removed some error warnings

= 1.2.3 =
* Allows searching
* Better support for WP 3.5+

= 1.2.2 =
* Fixes pagination count for non-MS installations

= 1.2.1 =
* Better support for WP 3.5

= 1.2 =
* Adds 'Delete' buttons to remove registrations
* Adds support for non-MS WordPress + BuddyPress

= 1.1 =
* Adds bulk resend/activate options
* Adds a Resent Count column, to keep track of how many times an activation email has been resent to a given user
* Refines the success/failure messages to contain better information
* Updates Boone's Pagination and Boone's Sortable Columns

= 1.0.3 =
* Removes Boone's Sortable Columns plugin header to ensure no conflicts during WP plugin activation

= 1.0.2 =
* Adds language file
* Fixes problem with email resending feedback related to BuddyPress

= 1.0.1 =
* Adds pagination styling

= 1.0 =
* Initial release
