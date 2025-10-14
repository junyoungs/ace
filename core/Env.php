<?php declare(strict_types=1);

namespace CORE;

class Env
{
    /**
     * The directory where the .env file is located.
     * @var string
     */
    protected string $path;

    public function __construct(string $path)
    {
        if (!file_exists($path)) {
            // It's not an error if the .env file doesn't exist.
            // Production environments should use server-level environment variables.
            return;
        }
        $this->path = $path;
    }

    /**
     * Load the environment file.
     */
    public function load(): void
    {
        if (!is_readable($this->path)) {
            // You might want to throw an exception here in a real-world scenario
            // if the file exists but is not readable.
            return;
        }

        $lines = file($this->path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) {
                continue;
            }

            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }

    /**
     * Create and load a new environment file.
     */
    public static function create(string $path): void
    {
        (new static($path))->load();
    }
}