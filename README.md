# Migration Sprinkle (UserFrosting 4.1)

Migrate to UserFrosting 4 from previous versions of UserFrosting, and other frameworks. 

## Usage

This Sprinkle sets up a fresh installation of UF4 with a pre-existing UF 3.1 database.  It will attempt to migrate your users, groups, event log, and custom permissions (`authorize_group`) to UF4 entities.

### Step 1

MAKE A COPY OF YOUR CURRENT DATABASE.  This tool does not drop any tables, but it is best to err on the cautious side.

### Step 2

Clone the UF4 repo and run `composer install`, as per the documentation.  Manually copy `app/sprinkles.example.json` to `app/sprinkles.json`.

### Step 3

Edit UserFrosting `app/sprinkles.json` and add the following to the `require` list : `"userfrosting/migrate": "~4.1.0"`. Also add `migrate` to the `base` list. For example:

```
{
    "require": {
        "userfrosting/migrate": "~4.1.0"
    },
    "base": [
        "core",
        "account",
        "admin",
        "migrate"
    ]
}
```

### Step 4 - Update Composer

Run `composer update` from the root project directory.

### Step 5

Run `php bakery upgrade` from the root project directory.  It will prompt you for the credentials for your database - use the credentials for the database you wish to upgrade.

### Step 6

Begin migrating your code over to UF 4.1.
