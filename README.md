# UserFrosting Database Updater v0.3.1.11.0

http://www.userfrosting.com

[![Join the chat at https://gitter.im/alexweissman/UserFrosting](https://badges.gitter.im/Join%20Chat.svg)](https://gitter.im/alexweissman/UserFrosting?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)

[![Click here to lend your support to: UserFrosting: A secure, modern user management system for PHP and make a donation at pledgie.com !](https://pledgie.com/campaigns/29583.png?skin_name=chrome)](https://pledgie.com/campaigns/29583)

## By [Mike Jacobs](http://netrilix.com)

### WARNING: This only upgrades the database, not your code base!

This is an upgrade script to bring UserFrosting's database from version 0.3.0 to Latest

## Strategies For Upgrading Your Code

### Option A:
#### Merge the latest version of UserFrosting into your own code using git
##### (This is better if you're familiar with git and comfortable handling merge conflicts)
  1. Ensure all changes are committed or stashed before you begin
  2. Add the public repository as a remote on your repository:
    - `git remote add upstream git@github.com/userfrosting/UserFrosting.git`
  3. Check out your primary branch (the one we'll merge into). We use `master` in this example:
    - `git checkout master`
  4. Duplicate your current branch so you have one specifically for merging:
    - `git checkout -b merge-upstream`
  5. Fetch the latest branch list from the public remote:
    - `git fetch upstream`
  6. Merge the public remote's master into your `merge-upstream` branch:
    - `git merge upstream/master`
  7. Handle any merge conflicts that arise:
    - Visit [here](https://help.github.com/articles/resolving-a-merge-conflict-from-the-command-line/) for a quick rundown on handling merge conflicts.
  8. Run `composer update` in the `/userfrosting` directory

### Option B:
#### Download the latest version of the code and manually merge your changes in
##### (This is better if you're unfamiliar with git, or if your code replaces a lot of core UserFrosting code)
  1. Download the latest copy of the code from [UserFrosting.com](http://www.userfrosting.com/)
  2. Extract the zip into your new UserFrosting directory
  3. Manually merge your code into the new UserFrosting code
  4. Run `composer update` in the `/userfrosting` directory

## Running the Database Upgrade

1. Backup your database. There's no rollback for structure-altering statements, so always back up
2. Merge the new UserFrosting code into your code (summarized above)
3. Place `upgrade.php` in the userfrosting directory (the same directory that contains `initialize.php`)
4. Configure the option(s) listed below, found at the top of `upgrade.php`
5. Run it from the command line: `php upgrade.php`

## Configuration Options
$usersToProcess - The count of users to convert to the database per query. Each user can have up to 4 statements that need to be written to the database.

## Changelog

#### 0.3.1.11.0
- Added better instructions to `README.md`
- Added warning to program about only upgrading database, not code base
- No database changes from UF 0.3.1.7 to UF 0.3.1.11
- Finalized version numbering: The first four digits refer to the latest version of UF tested and supported by the current database version (in this case, 0.3.1.11). The last number is for my own versioning within the Upgrade tool (in this case, .0).

#### 0.3.1.7
- Initial version
- Able to upgrade from UF 0.3.0 to UF 0.3.1.7

## Coming Soon
- Able to upgrade from any version after 0.3.0 to latest (priority)
- Able to upgrade to any version after 0.3.0 (less important unless someone has a specific need)
