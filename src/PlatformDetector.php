<?php

declare(strict_types=1);

namespace Codewithkyrian\Whisper;

use RuntimeException;

/**
 * Handles platform detection and library path resolution
 */
class PlatformDetector
{
    private const SUPPORTED_PLATFORMS = [
        'linux' => [
            'x86_64' => ['so', 'linux', 'x86_64'],
            'aarch64' => ['so', 'linux', 'arm64'],
            'arm64' => ['so', 'linux', 'arm64'],
        ],
        'darwin' => [
            'x86_64' => ['dylib', 'darwin', 'x86_64'],
            'arm64' => ['dylib', 'darwin', 'arm64'],
        ],
        'windows' => [
            'x86_64' => ['dll', 'windows', 'x86_64'],
            'AMD64' => ['dll', 'windows', 'x86_64'],
        ],
    ];

    private string $os;

    private string $arch;

    public function __construct()
    {
        $this->os = strtolower(PHP_OS_FAMILY);
        $this->arch = php_uname('m');

        if (! $this->isPlatformSupported()) {
            throw new RuntimeException(
                "Unsupported platform: {$this->os} {$this->arch}"
            );
        }
    }

    public function getLibraryExtension(): string
    {
        return self::SUPPORTED_PLATFORMS[$this->os][$this->arch][0];
    }

    public function getPlatformIdentifier(): string
    {
        $platform = self::SUPPORTED_PLATFORMS[$this->os][$this->arch];

        return "{$platform[1]}-{$platform[2]}";
    }

    private function isPlatformSupported(): bool
    {
        return isset(self::SUPPORTED_PLATFORMS[$this->os][$this->arch]);
    }

    public function isWindows(): bool
    {
        return $this->os === 'windows';
    }
}
