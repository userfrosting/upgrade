<?php

/**
 * Author: Netrilix
 * Date: 10/31/2015
 * Time: 11:24 AM
 * Version: 0.3.1.7
 *
 * Instructions:
 *
 * 1. Backup your database. There's no rollback for structure-altering statements, so always back up.
 * 2. Pull in the new version of the UserFrosting repository and merge it with your changes.
 * 3. Place this file in the userfrosting directory (the same directory that contains initialize.php).
 * 4. Configure the option(s) below.
 * 5. Run it from the command line ("php upgrade.php").
 *
 */

/*
 User-configurable options:
*/

$usersToProcess = 30; # of users to process at a time (each user will have up to 4 SQL statements).

/**
 *
 * Synopsis:
 *
 * This is the first version of the database upgrade software. I'm calling it 0.3.1.7, since that is the earliest
 * version it's compatible with (but 0.3.1.8 uses the same database structure). My plan is to grow this into something
 * that can upgrade from any version to later version, but I had to get the first version out the door because people
 * have been asking for it.
 *
 * @todo Move version detection routine to its own function (or set of functions).
 * @todo Function that decides which parts of the upgrade to run (eg. 0.3.0 to 0.3.1.8 or just 0.3.1.5 to 0.3.1.8)
 * @todo Consider configurable path to initialize.php instead of requiring this script to be in the same directory.
 */

use \Illuminate\Database\Capsule\Manager as Capsule;
use UserFrosting\User;
use UserFrosting\SiteSettings;

if (!defined('STDIN'))
{
    die('This program must be run from the command line.');
}

require_once('initialize.php');

$settings = SiteSettings::all()->first();

$versionTargetDatabase = '0.3.1.8'; // This is the current max version we can upgrade to.
$versionCurrentDatabase = $settings['version'];
$versionCurrentCode = $setting_values['userfrosting']['version'];

echo PHP_EOL . PHP_EOL . 'Current database version: ' . $versionCurrentDatabase . PHP_EOL;
echo 'Current code version: ' . $versionCurrentCode . PHP_EOL;
echo 'Target database version: 0.3.1.8' . PHP_EOL;

echo PHP_EOL . 'Is your database backed up and are you ready to proceed? [Y/n]: ';
$answer = trim(fgets(STDIN));

if ($versionCurrentDatabase != '0.3.0') {
    die(PHP_EOL . 'Version mismatch. Expected to upgrade from 0.3.0 but found version ' . $versionCurrentDatabase);
}
else if (in_array($versionCurrentCode, array('0.3.1', '0.3.1.1', '0.3.1.2', '0.3.1.3', '0.3.1.4', '0.3.1.5', '0.3.1.6')))
{
    die (PHP_EOL . 'Version mismatch. The current version of the conversion software cannot convert to code bases ' .
        'before 0.3.1.7 because of minor database differences. If you have very specific reasons for not upgrading ' .
        'straight to 0.3.1.8+, please contact us in the Gitter chat to discuss your options.');
}

if (!in_array(strtolower($answer), array('yes', 'y')))
{
    die(PHP_EOL . PHP_EOL . 'When you are ready to proceed, re-run the program and enter "Y".' . PHP_EOL .
        'Note: This program must be run from the command line.');
}

$connection = Capsule::connection();

echo PHP_EOL . PHP_EOL;

/**
 * 0.3.1 - Create user_event table
 */

echo '(0.3.1) Creating ' . \UserFrosting\Database::getSchemaTable('user_event')->name . ': ';
$connection->statement("CREATE TABLE IF NOT EXISTS `" . \UserFrosting\Database::getSchemaTable('user_event')->name . "` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT(11) NOT NULL,
            `event_type` VARCHAR(255) NOT NULL COMMENT 'An identifier used to track the type of event.',
            `occurred_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `description` TEXT NOT NULL,
            PRIMARY KEY (`id`)
          ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;")
or die('Failed.' . PHP_EOL . PHP_EOL . 'Exiting.');
echo 'Done.' . PHP_EOL;

/**
 * 0.3.1 - Create remember_me table
 */

echo '(0.3.1) Creating ' . $app->remember_me_table['tableName'] . ': ';
$connection->statement("CREATE TABLE IF NOT EXISTS `" . $app->remember_me_table['tableName'] . "` (
			`user_id` INT(11) NOT NULL,
			`token` VARCHAR(40) NOT NULL,
			`persistent_token` VARCHAR(40) NOT NULL,
			`expires` DATETIME NOT NULL
			) ENGINE=InnoDB DEFAULT CHARSET=utf8;")
or die('Failed.' . PHP_EOL . PHP_EOL . 'Exiting.');
echo 'Done.' . PHP_EOL;

/**
 * 0.3.1 - Copy all user events to the new user_event table
 */

$users = User::all();
echo '(0.3.1) Moving user events to ' . \UserFrosting\Database::getSchemaTable('user_event')->name . ': ';
User::chunk($usersToProcess, function ($users) use ($connection) {
    $inserts = array();
    foreach ($users as $user) {
        $user_id = $user['id'];
        $username = $user['user_name'];

        $sign_up = $user['sign_up_stamp'];
        $sign_in = $user['last_sign_in_stamp'];
        $pass_ac = $user['last_activation_request'];
        $pass_lo = $user['lost_password_timestamp'];

        $sign_up_text = 'User ' . $username . ' successfully registered on ' . $sign_up;
        $sign_in_text = 'User ' . $username . ' signed in at ' . $sign_in;
        $pass_ac_text = 'User ' . $username . ' requested verification on ' . $pass_ac;
        $pass_lo_text = 'User ' . $username . ' requested a password reset on ' . $pass_lo;

        if ($sign_up !== null) {
            $inserts[] = [
                'user_id' => $user_id,
                'event_type' => 'sign_up',
                'occurred_at' => $sign_up,
                'description' => $sign_up_text
            ];
        }
        if ($sign_in !== null) {
            $inserts[] = [
                'user_id' => $user_id,
                'event_type' => 'sign_in',
                'occurred_at' => $sign_in,
                'description' => $sign_in_text
            ];
        }

        if ($pass_ac !== null) {
            $inserts[] = [
                'user_id' => $user_id,
                'event_type' => 'verification_request',
                'occurred_at' => $pass_ac,
                'description' => $pass_ac_text
            ];
        }

        if ($pass_lo !== null) {
            $inserts[] = [
                'user_id' => $user_id,
                'event_type' => 'lost_password_request',
                'occurred_at' => $pass_lo,
                'description' => $pass_lo_text
            ];
        }
    }
    Capsule::table(\UserFrosting\Database::getSchemaTable('user_event')->name)->insert($inserts)
    or die('Failed.' . PHP_EOL . PHP_EOL . 'Exiting.');
});
echo 'Done.' . PHP_EOL;

/**
 * 0.3.1 - Adding columns to the user table
 */

echo '(0.3.1) Adding new columns to ' . \UserFrosting\Database::getSchemaTable('user')->name . ': ';
$connection->statement("ALTER TABLE `" . \UserFrosting\Database::getSchemaTable('user')->name . "`
						ADD `created_at` TIMESTAMP NULL DEFAULT NULL,
                        ADD `updated_at` TIMESTAMP NULL DEFAULT NULL; ")
or die('Failed.' . PHP_EOL . PHP_EOL . 'Exiting.');
echo 'Done.' . PHP_EOL;

/**
 * 0.3.1 - Copying data to new columns in the user table
 */

echo '(0.3.1) Copying data to the new columns in ' . \UserFrosting\Database::getSchemaTable('user')->name . ': ';
$connection->statement("UPDATE `" . \UserFrosting\Database::getSchemaTable('user')->name . "`
                        SET `created_at` = `sign_up_stamp`, `updated_at` = `sign_up_stamp`;")
or die('Failed.' . PHP_EOL . PHP_EOL . 'Exiting.');
echo 'Done.' . PHP_EOL;

/**
 * 0.3.1 - Removing and changing existing columns in the user table.
 */

echo '(0.3.1) Making structure changes to ' . \UserFrosting\Database::getSchemaTable('user')->name . ': ';
$connection->statement("ALTER TABLE `" . \UserFrosting\Database::getSchemaTable('user')->name . "`
						DROP `last_activation_request`, DROP `lost_password_timestamp`,
						DROP `sign_up_stamp`, DROP `last_sign_in_stamp`,
                        CHANGE `activation_token` `secret_token` VARCHAR(32) NOT NULL
                            COMMENT 'The current one-time use token for various user activities confirmed via email.',
                        CHANGE `active` `flag_verified` TINYINT(1) NOT NULL DEFAULT '1'
                            COMMENT 'Set to ''1'' if the user has verified their account via email, ''0'' otherwise.',
                        CHANGE `enabled` `flag_enabled` TINYINT(1) NOT NULL DEFAULT '1'
                            COMMENT 'Set to ''1'' if the user''s account is currently enabled, ''0'' otherwise.  Disabled accounts cannot be logged in to, but they retain all of their data and settings.',
                        CHANGE `lost_password_request` `flag_password_reset` TINYINT(1) NOT NULL DEFAULT '0'
                            COMMENT 'Set to ''1'' if the user has an outstanding password reset request, ''0'' otherwise.',
                        CHANGE `locale` `locale` VARCHAR(10) NOT NULL DEFAULT 'en_US'
                            COMMENT 'The language and locale to use for this user.',
                        CHANGE `primary_group_id` `primary_group_id` TINYINT(1) NOT NULL DEFAULT '1'
                            COMMENT 'The id of this user''s primary group.'; ")
or die('Failed.' . PHP_EOL . PHP_EOL . 'Exiting.');
echo 'Done.' . PHP_EOL;

/**
 * 0.3.1 - Changing existing columns in the group table.
 */

echo '(0.3.1) Making structure changes to ' . \UserFrosting\Database::getSchemaTable('group')->name . ': ';
$connection->statement("ALTER TABLE `" . \UserFrosting\Database::getSchemaTable('group')->name . "`
                        CHANGE `landing_page` `landing_page` VARCHAR(200) NOT NULL DEFAULT 'dashboard'
                            COMMENT 'The page to take primary members to when they first log in.'; ")
or die('Failed.' . PHP_EOL . PHP_EOL . 'Exiting.');
echo 'Done.' . PHP_EOL;

/**
 * 0.3.1 - Updating groups to use dashboard instead of the old accounts page.
 */

echo '(0.3.1) Updating groups to use new dashboard in ' . \UserFrosting\Database::getSchemaTable('group')->name . ': ';
$connection->statement("UPDATE `" . \UserFrosting\Database::getSchemaTable('group')->name . "`
                        SET `landing_page` = 'dashboard' WHERE `landing_page` = 'account'; ")
or die('Failed.' . PHP_EOL . PHP_EOL . 'Exiting.');
echo 'Done.' . PHP_EOL;

/**
 * 0.3.1.5 - Add default value for secret_token.
 */

echo '(0.3.1.5) Creating default value for secret_token: ';

$connection->statement("ALTER TABLE `" . \UserFrosting\Database::getSchemaTable('user')->name . "`
                        CHANGE `secret_token` `secret_token` varchar(32) NOT NULL DEFAULT ''
                            COMMENT 'The current one-time use token for various user activities confirmed via email.'")
or die('Failed.' . PHP_EOL . PHP_EOL . 'Exiting.');
echo 'Done.'.PHP_EOL;

/**
 * 0.3.1.7 - Change from "default_theme" to "guest_theme".
 */

echo '(0.3.1.7) Updating "default_theme" to "guest_theme": ';

if (isset($settings['default_theme']))
{
    $settings['guest_theme'] = $settings['default_theme'];
    $settings->save();
    $connection->statement('DELETE FROM `' . \UserFrosting\Database::getSchemaTable('configuration')->name
        . '` WHERE `name` = \'default_theme\'')
    or die('Failed.' . PHP_EOL . PHP_EOL . 'Exiting.');

}

echo 'Done.' . PHP_EOL;

/**
 * Latest - Update the version number in the database.
 */
$settings = SiteSettings::all()->first();
echo PHP_EOL . '(' . $versionTargetDatabase . ') Updating database version: ';
$settings['version'] = $versionTargetDatabase;
$settings->save()
    or die('Failed.' . PHP_EOL . PHP_EOL . 'Exiting.');
echo 'Done.' . PHP_EOL;

echo PHP_EOL . 'Conversion complete!' . PHP_EOL;
