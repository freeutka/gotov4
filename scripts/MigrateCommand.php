<?php
namespace Scripts;

class MigrateCommand
{
    private const COLOR_RESET  = "\033[0m";
    private const COLOR_GREEN  = "\033[1;32m";
    private const COLOR_RED    = "\033[1;31m";
    private const COLOR_YELLOW = "\033[1;33m";
    private const COLOR_CYAN   = "\033[1;36m";

    public static function run()
    {
        echo self::COLOR_YELLOW . "Select directory:\n" . self::COLOR_RESET;
        echo "1) /var/www/jexactyl\n2) /var/www/pterodactyl\n";
        $choice = trim(fgets(STDIN));

        $dir = $choice === "2" ? "/var/www/pterodactyl" : "/var/www/jexactyl";
        echo self::COLOR_CYAN . "Working directory: $dir\n" . self::COLOR_RESET;

        echo self::COLOR_YELLOW . "Create backup? (y/n): " . self::COLOR_RESET;
        $backup = trim(fgets(STDIN));
        if (strtolower($backup) === "y") {
            self::exec("cp -R $dir {$dir}-backup");
        }

        chdir($dir);

        self::exec("php artisan down");

        if (file_exists(".env")) {
            self::exec("cp .env .env.backup");
        }
        if (is_dir("storage")) {
            self::exec("cp -R storage storage.backup");
        }

        self::exec("curl -L -o panel.tar.gz https://github.com/Jexactyl/Jexactyl/releases/download/v4.0.0-beta7/panel.tar.gz");
        self::exec("mkdir -p panel-temp");
        self::exec("tar -xzf panel.tar.gz -C panel-temp --strip-components=1");
        self::exec("rsync -a --exclude='.env' --exclude='storage' panel-temp/ ./");
        self::exec("rm -rf panel-temp panel.tar.gz");

        if (is_dir("storage.backup")) {
            self::exec("cp -R storage.backup/* storage/ || true");
            self::exec("rm -rf storage.backup");
        }
        if (file_exists(".env.backup")) {
            self::exec("mv .env.backup .env");
        }

        self::exec("chmod -R 755 storage bootstrap/cache");

        $emailFile = $dir . "/app/Console/Commands/Environment/EmailSettingsCommand.php";
        if (file_exists($emailFile)) {
            $contents = file_get_contents($emailFile);
            $contents = str_replace(
                "Jexactyl\\Traits\\Commands\\EnvironmentWriterTrait",
                "Everest\\Traits\\Commands\\EnvironmentWriterTrait",
                $contents
            );
            file_put_contents($emailFile, $contents);
            echo self::COLOR_CYAN . "Applied EmailSettingsCommand.php fix\n" . self::COLOR_RESET;
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
                echo self::COLOR_CYAN . "Deleted migration: $path\n" . self::COLOR_RESET;
            }
        }

        self::exec("php artisan migrate --seed --force");
        self::exec("chown -R www-data:www-data $dir/*"); 
        self::exec("php artisan queue:restart");
        self::exec("php artisan up");

        echo self::COLOR_GREEN . "✅ Migration to v4 completed successfully!\n" . self::COLOR_RESET;
    }

    private static function exec(string $cmd)
    {
        echo self::COLOR_CYAN . ">>> $cmd\n" . self::COLOR_RESET;
        passthru($cmd, $code);
        if ($code !== 0) {
            echo self::COLOR_RED . "⚠️ Error during execution: $cmd (code $code)\n" . self::COLOR_RESET;
            exit($code);
        }
    }
}
