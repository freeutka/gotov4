<?php
namespace Scripts;

class MigrateCommand
{
    public static function run()
    {
        echo "Select directory:\n";
        echo "1) /var/www/jexactyl (choose this if you installed the panel using the official Jexactyl documentation)\n";
        echo "2) /var/www/pterodactyl (choose this if you migrated from Pterodactyl to Jexactyl)\n";
        $choice = trim(fgets(STDIN));

        $dir = $choice === "2" ? "/var/www/pterodactyl" : "/var/www/jexactyl";
        echo "Working directory: $dir\n";

        echo "Create backup? (y/n): ";
        $backup = trim(fgets(STDIN));
        if (strtolower($backup) === "y") {
            self::exec("cp -R $dir {$dir}-backup");
        }

        chdir($dir);

        // Put application into maintenance mode
        self::exec("php artisan down");

        // Download the latest v4 panel
        self::exec("curl -L -o panel.tar.gz https://github.com/Jexactyl/Jexactyl/releases/download/v4.0.0-beta7/panel.tar.gz");

        // Extract panel archive into current directory, replacing old files
        self::exec("tar -xzf panel.tar.gz --strip-components=1");

        // Set correct permissions for Laravel cache/storage
        self::exec("chmod -R 755 storage/* bootstrap/cache");

        // Install PHP dependencies
        self::exec("composer install --no-dev --optimize-autoloader");

        // Clear all Laravel caches
        self::exec("php artisan optimize:clear");

        // Remove specific old migrations if they exist
        $migrations = [
            "2024_03_30_211213_create_tickets_table.php",
            "2024_03_30_211447_create_ticket_messages_table.php",
            "2024_04_15_203406_add_theme_table.php",
            "2024_05_01_124250_add_deployable_column_to_nodes_table.php",
        ];
        foreach ($migrations as $mig) {
            $path = "$dir/database/migrations/$mig";
            if (file_exists($path)) {
                unlink($path);
                echo "Deleted disturbing migration: $path\n";
            }
        }

        // Run database migrations and seeders
        self::exec("php artisan migrate --seed --force");

        // Restart queues
        self::exec("php artisan queue:restart");

        // Bring the application back online
        self::exec("php artisan up");

        echo "✅ Migration to v4 completed successfully! Your panel should now display v4.\n";
    }

    private static function exec(string $cmd)
    {
        echo ">>> $cmd\n";
        passthru($cmd, $code);
        if ($code !== 0) {
            echo "⚠️ Error during execution: $cmd (code $code)\n";
            exit($code);
        }
    }
}
