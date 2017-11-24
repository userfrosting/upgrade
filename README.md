# Migration Sprinkle (UserFrosting 4.1)

Migrate to UserFrosting 4 from previous versions of UserFrosting, and other frameworks. 

# Installation

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

### Update Composer

- Run `composer update` from the root project directory.

### Run migration

- Run `php bakery bake` from the root project directory.
