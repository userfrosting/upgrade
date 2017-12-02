<?php

    /**
     * Create separate connection to database for legacy migration.  Note that this is still the same database,
     * we just create a separate connection to deal with possible prefix differences.
     */

    return [
        'db'      =>  [
            'legacy' => [
                'driver'    => getenv('DB_DRIVER') ?: 'mysql',
                'host'      => getenv('DB_HOST') ?: null,
                'port'      => getenv('DB_PORT') ?: null,
                'database'  => getenv('DB_NAME') ?: null,
                'username'  => getenv('DB_USER') ?: null,
                'password'  => getenv('DB_PASSWORD') ?: null,
                'charset'   => 'utf8',
                'collation' => 'utf8_unicode_ci',
                'prefix'    => ''
            ]
        ]
    ];
