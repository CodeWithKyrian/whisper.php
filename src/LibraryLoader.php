<?php

declare(strict_types=1);

namespace Codewithkyrian\Whisper;

use FFI;
use RuntimeException;
use ZipArchive;

class LibraryLoader
{
    private const LIBRARY_CONFIGS = [
        'whisper' => [
            'header' => 'whisper.h',
            'lib_prefix' => 'libwhisper',
        ],
        'sndfile' => [
            'header' => 'sndfile.h',
            'lib_prefix' => 'libsndfile',
        ],
        'samplerate' => [
            'header' => 'samplerate.h',
            'lib_prefix' => 'libsamplerate',
        ],
    ];
    private const WHISPER_CPP_VERSION = '1.7.2';
    private const DOWNLOAD_URL = 'https://huggingface.co/codewithkyrian/whisper.php/resolve/%s/libs/%s.zip';

    private static array $instances = [];
    private ?PlatformDetector $platformDetector;
    private ?FFI $kernel32 = null;

    public function __construct(?PlatformDetector $platformDetector = null)
    {
        $this->platformDetector = $platformDetector ?? new PlatformDetector();
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

        $config = self::LIBRARY_CONFIGS[$library];

        $headerPath = self::getHeaderPath($config['header']);
        $libPath = self::getLibraryPath(
            $config['lib_prefix'],
            $this->platformDetector->getLibraryExtension(),
            $this->platformDetector->getPlatformIdentifier()
        );

        if (!file_exists($libPath)) {
            $this->downloadLibraries();
        }

        return FFI::cdef(file_get_contents($headerPath), $libPath);
    }

    private static function getHeaderPath(string $headerFile): string
    {
        return self::joinPaths(dirname(__DIR__), 'include', $headerFile);
    }

    private static function getLibraryPath(string $prefix, string $extension, string $platform): string
    {
        return self::joinPaths(self::getLibraryDirectory($platform), "$prefix.$extension");
    }

    private static function getLibraryDirectory(string $platform): string
    {
        return self::joinPaths(dirname(__DIR__), 'lib', $platform);
    }

    /**
     * Download libraries from Hugging Face
     */
    private function downloadLibraries(): void
    {
        $platform = $this->platformDetector->getPlatformIdentifier();

        $url = sprintf(self::DOWNLOAD_URL, self::WHISPER_CPP_VERSION, $platform);

        $tempFile = tempnam(sys_get_temp_dir(), 'whisper-cpp-libs');

        $ch = curl_init();
        $fp = fopen($tempFile, 'w');
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/octet-stream',
        ]);

        if (!curl_exec($ch)) {
            fclose($fp);
            unlink($tempFile);
            throw new \RuntimeException(sprintf('Failed to download libraries from %s: %s', $url, curl_error($ch)));
        }

        // Extract ZIP file
        $zip = new ZipArchive;
        if ($zip->open($tempFile) === true) {
            $platformLibDir = self::joinPaths(dirname(__DIR__), 'lib', $platform);
            if (!is_dir($platformLibDir)) {
                mkdir($platformLibDir, 0755, true);
            }
            $zip->extractTo($platformLibDir);
            $zip->close();

            unlink($tempFile);
        } else {
            throw new RuntimeException('Failed to downloaded ZIP');
        }
    }

    /**
     * Add DLL directory to search path for Windows
     */
    private function addDllDirectory(): void
    {
        if (!$this->platformDetector->isWindows()) return;

        $libDir = ($this->getLibraryDirectory($this->platformDetector->getPlatformIdentifier()));
        $this->kernel32 ??= FFI::cdef("
            int SetDllDirectoryA(const char* lpPathName);
            int SetDefaultDllDirectories(unsigned long DirectoryFlags);
        ", 'kernel32.dll');

        $this->kernel32->SetDllDirectoryA($libDir);
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
