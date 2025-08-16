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

        self::exec("php artisan down");

        self::exec("curl -L -o panel.tar.gz https://github.com/Jexactyl/Jexactyl/releases/download/v4.0.0-beta7/panel.tar.gz");

        self::exec("chmod -R 755 storage/* bootstrap/cache");

        $file = "$dir/app/Console/Commands/Environment/EmailSettingsCommand.php";
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $content = str_replace(
                "Jexactyl\\Traits\\Commands\\EnvironmentWriterTrait",
                "Jexactyl\\Traits\\Commands\\EnvironmentWriterTrait",
                $content
            );
            file_put_contents($file, $content);
        } else {
            echo "⚠️ File $file not found!\n";
        }

        self::exec("composer install --no-dev --optimize-autoloader");
        self::exec("php artisan optimize:clear");

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
                echo "Deleted disturbing migrations: $path\n";
            }
        }

        self::exec("php artisan migrate --seed --force");
        self::exec("chown -R www-data:www-data $dir/*");
        self::exec("php artisan queue:restart");
        self::exec("php artisan up");

        echo "✅ Migration to v4 completed successfully! Thanks for choosing Jexactyl\n";
    }

    private static function exec(string $cmd)
    {
        echo ">>> $cmd\n";
        passthru($cmd, $code);
        if ($code !== 0) {
            echo "⚠️ Error during execution: $cmd (код $code)\n";
            exit($code);
        }
    }
}
