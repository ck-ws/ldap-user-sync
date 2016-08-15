# LDAP User Sync for WordPress

This is a WordPress plugin to synchronize user accounts from a LDAP directory to your WordPress user database.

License: [BSD 2-Clause](LICENSE)

Inspired by [ricardozanini/ldap-users-sync](https://github.com/ricardozanini/ldap-users-sync), which syncs AD accounts to WP. Differences are:
* Works with generic LDAP servers, not only Active Directory
* Allows to customize all mappings for the user table (first name, last name, nice name, login name, email, url, and display name)
* does NOT sync additional attributes to user meta

## Key features
* custom value to LDAP attribute mapping (with fallback option, if first LDAP attribute is not set)
* custom filter for selecting user accounts
* scheduling is possible (using WP-Cron)

## Installation
### GitHub Updater
This plugin is compatible with [GitHub Updater](https://github.com/afragen/github-updater).

Install it by copying the GitHub URL and pasting it under Settings -> GitHub Updater -> Install plugin.

### Manual
1. Download the latest ZIP using "Clone or download" -> Downloap ZIP
2. Unzip it into `wp-content/plugins/ldap-user-sync`

### Git
Clone into `wp-content/plugins/ldap-user-sync`:

````
git clone https://github.com/ck-ws/ldap-user-sync wp-content/plugins/ldap-user-sync
````

and enable it in the Plugins section of WP Admin.

