<?php

namespace UserFrosting\Sprinkle\Upgrade\Bakery;

use Carbon\Carbon;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use UserFrosting\Sprinkle\Account\Database\Models\Permission;
use UserFrosting\Sprinkle\Account\Database\Models\Role;
use UserFrosting\System\Bakery\BaseCommand;
use UserFrosting\System\Bakery\DatabaseTest;

class Upgrade extends BaseCommand
{
    use DatabaseTest;

    /**
     * @var @Illuminate\Database\Schema
     */
    protected $schema;

    /**
     * @var array The tables containing the data to be migrated.
     */
    protected $sourceTables = [
        'authorize_group',
        'authorize_user',
        'configuration',
        'group',
        'group_user',
        'user',
        'user_event',
        'user_rememberme'
    ];

    /**
     * @var Collection
     */
    protected $defaultRoles;

    /**
     * @var Collection
     */
    protected $defaultPermissions;

    /**
     * @var array
     */
    protected $legacyGroupRoleMappings = [
        'user' => 'user',
        'administrator' => 'site-admin'
    ];

    /**
     * @var string the prefix on the legacy (source) tables.
     */
    protected $legacyPrefix = '';

    /**
     * @var The id of the last permission that should be ignored when migrating old permissions.
     */
    protected $lastOldDefaultPermissionId = 13;

    protected function configure()
    {
        // the name of the command (the part after "php bakery")
        $this->setName("upgrade");

        // the short description shown while running "php bakery list"
        $this->setDescription("Upgrade a UF 3.1 database to UF 4.1.");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        // Set up DB connection
        $this->testDB();

        // Get schema required to run the table blueprints
        $this->schema = DB::schema();

        $this->io->writeln("<info>This command will attempt to install UserFrosting 4.1 on top of an existing UF 3.1 database, and migrate your users, groups, and events.  Once this is complete, you should begin migrating the code from your UF 3.1 application to a new UF4 Sprinkle.</info>");

        $command = $this->getApplication()->find('setup');
        $command->run($input, $output);

        $command = $this->getApplication()->find('debug');
        $command->run($input, $output);

        // Database has connected successfully, so we'll begin the migration process

        // First, get the prefix for the 3.1 tables and confirm the tables that will be migrated.
        $hasPrefix = $this->io->confirm('Do your UserFrosting 3.1 tables have a prefix?', true);

        if ($hasPrefix) {
            $this->legacyPrefix = $this->io->ask('What is the table prefix?', 'uf_');
        }

        $renamedSourceTables = $this->mapOldTableNames($this->sourceTables);
        $notFoundTables = [];

        $this->io->note('The following tables in your database will be renamed before being migrated to UF4');
        foreach ($renamedSourceTables as $oldName => $newName) {
            if ($this->schema->hasTable($oldName)) {
                $this->io->text($oldName . ' => ' . $newName);
            } else {
                $notFoundTables[] = $oldName;
            }
        }

        if (!empty($notFoundTables)) {
            $this->io->caution('The following expected tables could not be found:');
            $this->io->text($notFoundTables);
        }

        if (!$this->io->confirm('Continue?', true)) {
            exit(0);
        }

        DB::transaction( function() use ($renamedSourceTables, $input, $output) {
            // Rename the 3.1 tables
            foreach ($renamedSourceTables as $oldName => $newName) {
                $this->schema->rename($oldName, $newName);
            }

            // Install the UF4 tables
            $command = $this->getApplication()->find('migrate');
            $command->run($input, $output);

            // Get the default roles, and their permissions
            $this->defaultPermissions = Permission::get();
            $this->defaultRoles = Role::with('permissions')->get()->keyBy('slug');

            // Migrate data from 3.1 -> 4.1
            // Groups become both Roles and Groups in UF4
            $this->migrateGroups($renamedSourceTables[$this->legacyPrefix . 'group']);
            $this->migrateRoles($renamedSourceTables[$this->legacyPrefix . 'group']);
            $this->migratePermissions($renamedSourceTables[$this->legacyPrefix . 'authorize_group']);

            $this->migrateUsers($renamedSourceTables[$this->legacyPrefix . 'user']);
            $this->migrateUserRoles($renamedSourceTables[$this->legacyPrefix . 'group_user']);
            $this->migrateActivities($renamedSourceTables[$this->legacyPrefix . 'user_event']);

            // Re-add the default roles and permissions
            $this->reAddDefaultRolesAndPermissions();
        });

        // Complete installation
        $command = $this->getApplication()->find('build-assets');
        $command->run($input, $output);

        $command = $this->getApplication()->find('clear-cache');
        $command->run($input, $output);
    }

    protected function migrateGroups($tableName)
    {
        // Clear out the default UF4 groups
        DB::connection()->table('groups')->truncate();

        $legacyRows = DB::connection()->table($tableName)->get();

        foreach ($legacyRows as $legacyRow) {
            DB::connection()->table('groups')->insert([
                'id' => $legacyRow->id,
                'slug' => Str::slug($legacyRow->name),
                'name' => $legacyRow->name,
                'description' => '',
                'icon' => $legacyRow->icon,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        }
    }

    protected function migrateRoles($tableName)
    {
        // Clear out the default UF4 roles
        DB::connection()->table('roles')->truncate();

        $legacyRows = DB::connection()->table($tableName)->get();

        foreach ($legacyRows as $legacyRow) {
            $legacySlug = Str::slug($legacyRow->name);

            $newRow = [
                'id' => $legacyRow->id,
                'slug' => $legacySlug,
                'name' => $legacyRow->name,
                'description' => '',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ];

            // If the legacy record is replaceable by one of the default roles in UF4, replace it
            if (isset($this->legacyGroupRoleMappings[$legacySlug])) {
                $uf4Slug = $this->legacyGroupRoleMappings[$legacySlug];

                $uf4Role = $this->defaultRoles->get($uf4Slug);
                // Set the id to the same as the legacy row's id.
                // This way, anything that was keyed to the old legacy record will be sure to point to the
                // corresponding new default role.
                $newRow = [
                    'id' => $legacyRow->id,
                    'slug' => $uf4Role->slug,
                    'name' => $uf4Role->name,
                    'description' => $uf4Role->description,
                    'created_at' => $uf4Role->created_at,
                    'updated_at' => $uf4Role->updated_at
                ];
            }

            DB::connection()->table('roles')->insert($newRow);
        }
    }

    protected function migratePermissions($tableName)
    {
        // Clear out the default UF4 permissions
        DB::connection()->table('permissions')->truncate();
        DB::connection()->table('permission_roles')->truncate();

        $legacyRows = DB::connection()->table($tableName)->where('id', '>', $this->lastOldDefaultPermissionId)->get();

        foreach ($legacyRows as $legacyRow) {
            DB::connection()->table('permissions')->insert([
                'id' => $legacyRow->id,
                'slug' => $legacyRow->hook,
                'name' => $legacyRow->hook,
                'conditions' => $legacyRow->conditions,
                'description' => '',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            DB::connection()->table('permission_roles')->insert([
                'permission_id' => $legacyRow->id,
                'role_id' => $legacyRow->group_id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        }
    }

    protected function migrateUsers($tableName)
    {
        $legacyRows = DB::connection()->table($tableName)->get();

        foreach ($legacyRows as $legacyRow) {
            list($firstName, $lastName) = explode(' ', $legacyRow->display_name, 1);

            DB::connection()->table('users')->insert([
                'id' => $legacyRow->id,
                'user_name' => $legacyRow->user_name,
                'email' => $legacyRow->email,
                'first_name' => $firstName ? $firstName : '',
                'last_name' => $lastName ? $lastName : '',
                'locale' => $legacyRow->locale,
                'theme' => null,
                'group_id' => $legacyRow->primary_group_id,
                'flag_verified' => $legacyRow->flag_verified,
                'flag_enabled' => $legacyRow->flag_enabled,
                'last_activity_id' => null,
                'password' => $legacyRow->password,
                'deleted_at' => null,
                'created_at' => $legacyRow->created_at,
                'updated_at' => $legacyRow->updated_at
            ]);
        }

        // TODO: set theme, last_activity_id for each user
    }

    protected function migrateUserRoles($tableName)
    {
        // Clear out the default UF4 mappings
        DB::connection()->table('role_users')->truncate();

        $legacyRows = DB::connection()->table($tableName)->get();

        foreach ($legacyRows as $legacyRow) {
            DB::connection()->table('role_users')->insert([
                'user_id' => $legacyRow->user_id,
                'role_id' => $legacyRow->group_id
            ]);
        }
    }

    protected function migrateActivities($tableName)
    {
        $legacyRows = DB::connection()->table($tableName)->get();

        foreach ($legacyRows as $legacyRow) {
            DB::connection()->table('activities')->insert([
                'ip_address' => null,
                'user_id' => $legacyRow->user_id,
                'type' => $legacyRow->event_type,
                'occurred_at' => $legacyRow->occurred_at,
                'description' => $legacyRow->description
            ]);
        }
    }

    protected function reAddDefaultRolesAndPermissions()
    {
        // Map original permission ids to new permission ids
        $newPermissionsDictionary = [];
        foreach ($this->defaultPermissions as $permission) {
            $newId = DB::connection()->table('permissions')->insertGetId([
                'slug' => $permission->slug,
                'name' => $permission->name,
                'conditions' => $permission->conditions,
                'description' => $permission->description,
                'created_at' => $permission->created_at,
                'updated_at' => $permission->updated_at
            ]);

            $newPermissionsDictionary[$permission->id] = $newId;
        }

        $newPermissionMappings = [];
        foreach ($this->defaultRoles as $role) {
            // Determine if this role has already been added
            $newRoleId = DB::connection()->table('roles')->where('slug', $role->slug)->first()->id;

            if (!$newRoleId) {
                $newRoleId = DB::connection()->table('roles')->insertGetId([
                    'slug' => $role->slug,
                    'name' => $role->name,
                    'description' => $role->description,
                    'created_at' => $role->created_at,
                    'updated_at' => $role->updated_at
                ]);
            }

            foreach ($role->permissions as $permission) {
                $newPermissionId = $newPermissionsDictionary[$permission->id];
                $newPermissionMappings[] = [
                    'permission_id' => $newPermissionId,
                    'role_id' => $newRoleId,
                    'created_at' => $permission->pivot->created_at,
                    'updated_at' => $permission->pivot->updated_at
                ];
            }
        }

        DB::connection()->table('permission_roles')->insert($newPermissionMappings);
    }

    protected function mapOldTableNames($sourceTables)
    {
        $randomPrefix = str_random(10);

        $renamedSourceTables = [];
        foreach ($sourceTables as $baseName) {
            $oldName = $this->legacyPrefix . $baseName;
            $newName = '_' . $randomPrefix . '_' . $oldName;
            $renamedSourceTables[$oldName] = $newName;
        }

        return $renamedSourceTables;
    }
}
