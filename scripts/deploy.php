<?php

declare(strict_types=1);

/**
 * Film Finder deployment helper.
 *
 * Uploads the current project to a remote FTP/SFTP target compatible with Jolt hosting.
 * Requires PHP CLI with the FTP extension enabled.
 *
 * Usage:
 *  php scripts/deploy.php
 *
 * Configuration priority:
 * 1. Environment variables (DEPLOY_HOST, DEPLOY_USER, DEPLOY_PASS, DEPLOY_PATH, DEPLOY_PORT, DEPLOY_SSL, DEPLOY_PASSIVE)
 * 2. Key/value pairs in .env.deploy (present in project root, not committed)
 */

$root = dirname(__DIR__);
$env = array_merge(
    loadEnvFile($root . '/.env.deploy'),
    getenv()
);

$config = [
    'host' => resolveEnv($env, 'DEPLOY_HOST'),
    'user' => resolveEnv($env, 'DEPLOY_USER'),
    'pass' => resolveEnv($env, 'DEPLOY_PASS'),
    'path' => rtrim(resolveEnv($env, 'DEPLOY_PATH', '/public_html/filmfinder'), '/'),
    'port' => (int) resolveEnv($env, 'DEPLOY_PORT', '21'),
    'ssl' => filter_var(resolveEnv($env, 'DEPLOY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN),
    'passive' => filter_var(resolveEnv($env, 'DEPLOY_PASSIVE', 'true'), FILTER_VALIDATE_BOOLEAN),
];

validateConfig($config);

$connection = connectFtp($config);

echo sprintf("Connected to %s:%d as %s\n", $config['host'], $config['port'], $config['user']);
echo "Uploading files…\n";

$uploader = new FtpUploader($connection, $config['path']);
$uploader->uploadDirectory($root);

ftp_close($connection);
echo "Deployment complete.\n";

/**
 * @param array<string,string|false> $config
 */
function validateConfig(array $config): void
{
    foreach (['host', 'user', 'pass', 'path'] as $key) {
        if (empty($config[$key])) {
            fwrite(STDERR, "Missing deployment config: {$key}\n");
            exit(1);
        }
    }
}

/**
 * @param array<string,string|false> $env
 */
function resolveEnv(array $env, string $key, string $default = ''): string
{
    if (!empty($_ENV[$key])) {
        return (string) $_ENV[$key];
    }
    if (!empty($env[$key])) {
        return (string) $env[$key];
    }
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return (string) $value;
    }

    return $default;
}

function loadEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    $vars = [];
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $vars[$key] = trim($value, "\"'");
    }

    return $vars;
}

/**
 * @param array<string,mixed> $config
 * @return resource
 */
function connectFtp(array $config)
{
    if (!function_exists('ftp_connect')) {
        fwrite(STDERR, "PHP FTP extension is required to run this script.\n");
        exit(1);
    }

    $plain = ftp_connect($config['host'], $config['port'], 15);
    if ($plain && attemptLogin($plain, $config, true)) {
        return $plain;
    }

    if (is_resource($plain)) {
        @ftp_close($plain);
    }

    if (!function_exists('ftp_ssl_connect')) {
        fwrite(STDERR, "FTP login failed for {$config['user']} and SSL upgrade is unavailable.\n");
        exit(1);
    }

    $secure = @ftp_ssl_connect($config['host'], $config['port'], 15);
    if ($secure && attemptLogin($secure, $config, false)) {
        return $secure;
    }

    if (is_resource($secure)) {
        @ftp_close($secure);
    }

    fwrite(STDERR, "Unable to log in to {$config['host']} as {$config['user']}.\n");
    exit(1);
}

function attemptLogin($connection, array $config, bool $sendAuthTls): bool
{
    ftp_pasv($connection, $config['passive']);
    if ($sendAuthTls) {
        ftp_raw($connection, 'AUTH TLS');
    }
    return @ftp_login($connection, $config['user'], $config['pass']);
}

final class FtpUploader
{
    /** @var resource */
    private $connection;
    private string $remoteRoot;
    private array $ignore = [
        '.git',
        '.github',
        '.env',
        '.env.local',
        '.env.deploy',
        'node_modules',
        'storage/cache/*.cache.php',
    ];

    public function __construct($connection, string $remoteRoot)
    {
        $this->connection = $connection;
        $this->remoteRoot = rtrim($remoteRoot, '/');
    }

    public function uploadDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var SplFileInfo $item */
        foreach ($iterator as $item) {
            $relativePath = ltrim(str_replace($directory, '', $item->getPathname()), DIRECTORY_SEPARATOR);
            if ($this->shouldIgnore($relativePath)) {
                continue;
            }

            $remotePath = $this->remoteRoot . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

            if ($item->isDir()) {
                $this->ensureRemoteDirectory($remotePath);
            } else {
                $this->uploadFile($item->getPathname(), $remotePath);
            }
        }
    }

    private function shouldIgnore(string $relativePath): bool
    {
        $normalized = str_replace('\\', '/', $relativePath);
        foreach ($this->ignore as $pattern) {
            $patternNormalized = str_replace('\\', '/', $pattern);
            if (fnmatch($patternNormalized, $normalized)) {
                return true;
            }

            if (strncmp($normalized, rtrim($patternNormalized, '/'), strlen(rtrim($patternNormalized, '/'))) === 0) {
                $suffix = substr($normalized, strlen(rtrim($patternNormalized, '/')));
                if ($suffix === '' || $suffix[0] === '/') {
                    return true;
                }
            }
        }

        return false;
    }

    private function ensureRemoteDirectory(string $remotePath): void
    {
        $segments = explode('/', $remotePath);
        $path = '';

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            $path .= '/' . $segment;
            if (@ftp_chdir($this->connection, $path)) {
                ftp_chdir($this->connection, '/');
                continue;
            }
            if (!@ftp_mkdir($this->connection, $path)) {
                fwrite(STDERR, "Failed to create remote directory: {$path}\n");
            }
        }
    }

    private function uploadFile(string $localPath, string $remotePath): void
    {
        $remoteDir = dirname($remotePath);
        $this->ensureRemoteDirectory($remoteDir);

        $stream = fopen($localPath, 'r');
        if (!$stream) {
            fwrite(STDERR, "Unable to read file: {$localPath}\n");
            return;
        }

        if (!ftp_fput($this->connection, $remotePath, $stream, FTP_BINARY)) {
            fwrite(STDERR, "Failed to upload {$localPath} to {$remotePath}\n");
        } else {
            echo ".";
        }

        fclose($stream);
    }
}
