<?php

declare(strict_types=1);

namespace Codewithkyrian\Whisper;

use Codewithkyrian\PlatformPackageInstaller\Platform;
use FFI;
use RuntimeException;

class LibraryLoader
{
    private const LIBRARY_CONFIGS = [
        'whisper' => ['header' => 'whisper.h', 'library' => 'libwhisper'],
        'sndfile' => ['header' => 'sndfile.h', 'library' => 'libsndfile',],
        'samplerate' => ['header' => 'samplerate.h', 'library' => 'libsamplerate'],
    ];

    private const PLATFORM_CONFIGS = [
        'linux-x86_64' => ['directory' => 'linux-x86_64', 'extension' => 'so',],
        'linux-arm64' => ['directory' => 'linux-arm64', 'extension' => 'so',],
        'darwin-x86_64' => ['directory' => 'darwin-x86_64', 'extension' => 'dylib',],
        'darwin-arm64' => ['directory' => 'darwin-arm64', 'extension' => 'dylib',],
        'windows-x86_64' => ['directory' => 'windows-x86_64', 'extension' => 'dll',],
    ];

    private static array $instances = [];
    private ?FFI $kernel32 = null;

    public function __construct()
    {
        $this->addDllDirectory();
    }

    public function __destruct()
    {
        $this->resetDllDirectory();
    }

    /**
     * Gets the FFI instance for the specified library
     */
    public function get(string $library): FFI
    {
        if (!isset(self::$instances[$library])) {
            self::$instances[$library] = $this->load($library);
        }

        return self::$instances[$library];
    }

    /**
     * Loads a new FFI instance for the specified library
     */
    private function load(string $library): FFI
    {
        if (!isset(self::LIBRARY_CONFIGS[$library])) {
            throw new RuntimeException("Unsupported library: {$library}");
        }

        $platformConfig = Platform::findBestMatch(self::PLATFORM_CONFIGS);
        if (!$platformConfig) {
            throw new RuntimeException("No matching platform configuration found");
        }

        $config = self::LIBRARY_CONFIGS[$library];
        $headerPath = $this->getHeaderPath($config['header']);
        $libraryPath = $this->getLibraryPath($config['library'], $platformConfig['extension'], $platformConfig['directory']);

        return FFI::cdef(file_get_contents($headerPath), $libraryPath);
    }

    private static function getHeaderPath(string $headerFile): string
    {
        return self::joinPaths(dirname(__DIR__), 'include', $headerFile);
    }

    /**
     * Get path to library file
     */
    private function getLibraryPath(string $libName, string $extension, string $platformDir): string
    {
        return self::joinPaths(dirname(__DIR__), 'lib', $platformDir, "$libName.$extension");
    }

    private static function getLibraryDirectory(string $platformDir): string
    {
        return self::joinPaths(dirname(__DIR__), 'lib', $platformDir);
    }

    /**
     * Add DLL directory to search path for Windows
     */
    private function addDllDirectory(): void
    {
        if (!Platform::isWindows()) return;

        $platformConfig = Platform::findBestMatch(self::PLATFORM_CONFIGS);
        $libraryDir = self::getLibraryDirectory($platformConfig['directory']);

        $this->kernel32 ??= FFI::cdef("
            int SetDllDirectoryA(const char* lpPathName);
            int SetDefaultDllDirectories(unsigned long DirectoryFlags);
        ", 'kernel32.dll');

        $this->kernel32->SetDllDirectoryA($libraryDir);
    }

    /**
     * Reset DLL directory search path
     */
    private function resetDllDirectory(): void
    {
        if ($this->kernel32 !== null) {
            $this->kernel32->SetDllDirectoryA(null);
        }
    }

    private static function joinPaths(string ...$args): string
    {
        $paths = [];

        foreach ($args as $key => $path) {
            if ($path === '') {
                continue;
            } elseif ($key === 0) {
                $paths[$key] = rtrim($path, DIRECTORY_SEPARATOR);
            } elseif ($key === count($paths) - 1) {
                $paths[$key] = ltrim($path, DIRECTORY_SEPARATOR);
            } else {
                $paths[$key] = trim($path, DIRECTORY_SEPARATOR);
            }
        }

        return preg_replace('#(?<!:)//+#', '/', implode(DIRECTORY_SEPARATOR, $paths));
    }
}
