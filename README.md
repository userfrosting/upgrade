# UserFrosting Database Updater v0.3.1.7

http://www.userfrosting.com

[![Join the chat at https://gitter.im/alexweissman/UserFrosting](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/alexweissman/UserFrosting?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

[![Click here to lend your support to: UserFrosting: A secure, modern user management system for PHP and make a donation at pledgie.com !](https://pledgie.com/campaigns/29583.png?skin_name=chrome)](https://pledgie.com/campaigns/29583)

## By [Mike Jacobs](http://netrilix.com)

This is an upgrade script to bring UserFrosting's database from version 0.3.0 to 0.3.1

## Running

1. Backup your database. There's no rollback for structure-altering statements, so always back up.
2. Pull in the new version of the UserFrosting repository and merge it with your changes.
3. Place upgrade.php in the userfrosting directory (the same directory that contains initialize.php).
4. Configure the option(s) listed below.
5. Run it from the command line:
	php upgrade.php

## Configuration Options
$usersToProcess - The count of users to convert to the database per query. Each user can have up to 4 statements that need to be written to the database.

## Changelog for 0.3.1.7
- Initial version
- Able to upgrade from 0.3.0 to 0.3.1.8 (latest at time of release)

## Coming Soon
- Able to upgrade from any version after 0.3.0 to latest (priority)
- Able to upgrade to any version after 0.3.0 (less important unless someone has a specific need)
