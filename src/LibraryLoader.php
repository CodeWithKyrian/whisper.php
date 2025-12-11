<?php

declare(strict_types=1);

namespace Codewithkyrian\Whisper;

use Codewithkyrian\PlatformPackageInstaller\Platform;
use FFI;
use RuntimeException;

class LibraryLoader
{
    private const LIBRARIES = [
        'whisper' => [
            'name' => 'whisper',
            'header' => 'whisper.h',
            'version' => '1.7.2',
        ],
        'sndfile' => [
            'name' => 'sndfile',
            'header' => 'sndfile.h',
            'version' => '1.2.2',
        ],
        'samplerate' => [
            'name' => 'samplerate',
            'header' => 'samplerate.h',
            'version' => '0.2.2',
        ],
    ];

    private const PLATFORMS = [
        'linux-x86_64' => [
            'directory' => 'linux-x86_64',
            'path' => 'lib{name}.so.{version}',
        ],
        'linux-arm64' => [
            'directory' => 'linux-arm64',
            'path' => 'lib{name}.so.{version}',
        ],
        'darwin-x86_64' => [
            'directory' => 'darwin-x86_64',
            'path' => 'lib{name}.{version}.dylib',
        ],
        'darwin-arm64' => [
            'directory' => 'darwin-arm64',
            'path' => 'lib{name}.{version}.dylib',
        ],
        'windows-x86_64' => [
            'directory' => 'windows-x86_64',
            'path' => '{name}-{version}.dll',
        ],
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
        $config = self::LIBRARIES[$library] ?? null;
        if ($config === null) {
            throw new RuntimeException("Unsupported library: {$library}");
        }

        $platform = Platform::findBestMatch(self::PLATFORMS);
        if (!$platform) {
            throw new RuntimeException("Unsupported platform: ".json_encode(Platform::current()));
        }

        $headerPath = $this->getHeaderPath($config['header']);
        $libraryPath = $this->getLibraryPath($config['name'],$config['version'],$platform);

        return FFI::cdef(file_get_contents($headerPath), $libraryPath);
    }

    private static function getHeaderPath(string $headerFile): string
    {
        return self::joinPaths(dirname(__DIR__), 'include', $headerFile);
    }

    /**
     * Get path to library file
     */
    private function getLibraryPath(string $libName, string $version, array $platform): string
    {
        $libraryDir = self::getLibraryDirectory($platform['directory']);
        $template = $platform['path'];

        $filename = str_replace(['{name}', '{version}'], [$libName, $version], $template);
        $candidate = self::joinPaths($libraryDir, $filename);

        if (file_exists($candidate)) {
            return $candidate;
        }

        throw new RuntimeException("Unable to locate library file for {$libName} in {$libraryDir}");
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

        $platform = Platform::findBestMatch(self::PLATFORMS);
        $libraryDir = self::getLibraryDirectory($platform['directory']);

        $this->kernel32 ??= FFI::cdef("
            int SetDllDirectoryA(const char* lpPathName);
            int SetDefaultDllDirectories(unsigned long DirectoryFlags);
        ", 'kernel32.dll');

        $this->kernel32?->{'SetDllDirectoryA'}($libraryDir);
    }

    /**
     * Reset DLL directory search path
     */
    private function resetDllDirectory(): void
    {
        if ($this->kernel32 !== null) {
            $this->kernel32?->{'SetDllDirectoryA'}(null);
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
