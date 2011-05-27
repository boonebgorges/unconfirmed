=== Unconfirmed  ===
Contributors: boonebgorges, cuny-academic-commons
Donate link: http://teleogistic.net/donate
Tags: multisite, network, activate, activation, email
Requires at least: WordPress 3.1 Multisite
Tested up to: WordPress 3.2 beta
Stable tag: 1.1

Allows admins on a WordPress Multisite network to manage unactivated users, by either activating them manually or resending the activation email.

== Description ==

If you run a WordPress Multisite installation, you probably know that some of the biggest administrative headaches come from the activation process. Activation emails may be caught by spam filters, deleted unwillingly, or simply not understood. Yet WordPress itself has no UI for viewing and managing unactivated members.

Unconfirmed creates a Dashboard panel under Network Admin > Users that shows a list of unactivated user registrations. For each registration, you have the option of resending the original activation email, or manually activating the user.

Please note that this plugin currently only works with WordPress Multisite, aka Network Mode.

== Installation ==

1. Install
1. Activate
1. Navigate to Network Admin > Users > Unconfirmed 

== Changelog ==

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
