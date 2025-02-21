<?php

/**
 * PHP version of the LibMan script - client-side library acquisition tool
 *
 * https://learn.microsoft.com/en-us/aspnet/core/client-side/libman/libman-vs
 * with .NET libman-like behavior:
 *  - Supports unpkg, jsdelivr, cdnjs, local file-system paths, or arbitrary URLs
 *  - Skips re-downloading on future runs by storing packages in a local file cache
 *  - Supports renaming files on copy (absent in .NET libman):
 *    - e.g. in "files" instead of string "dist/index.mjs" use { "src": "dist/index.mjs", "to": "dist/index.js" }
 *
 * Example usage:
 *
 *   $jsonPath  = __DIR__ . '/libman.json';    // Path to the JSON file
 *   $rootPath  = __DIR__;                    // Path to project root
 *
 *   $libman = new PhpLibMan($jsonPath, $rootPath);
 *   $libman->install(); // Download/copy everything
 *
 *   $libman->clearCache(); // Clear the local cache if full re-downloading is needed
 *
 * Optionally, call install(false) if a “strict” run needed
 * that fails fast on errors. The default is to continue on errors.
 */
class PhpLibMan {
    public const int  LOG_LEVEL_NONE   = 0;  // No logs at all
    public const int  LOG_LEVEL_ERRORS = 1;  // Errors/warnings only
    public const int  LOG_LEVEL_KEY    = 2;  // Key events + errors (DEFAULT)
    public const int  LOG_LEVEL_DEBUG  = 3;  // Log everything

    /** @var int Controls how verbose logging is. Defaults to medium (“key” + errors). */
    protected int $logLevel = self::LOG_LEVEL_KEY;

    protected string $jsonPath;
    protected string $rootPath;
    protected ?object $config = null;

    /**
     * @param string $jsonPath Full path to the JSON file
     * @param string $rootPath Full path to the project root (where libraries will be placed)
     */
    public function __construct(string $jsonPath, string $rootPath) {
        $this->jsonPath = $jsonPath;
        // Ensure we have no trailing slash
        $this->rootPath = rtrim($rootPath, DIRECTORY_SEPARATOR);

        $this->loadConfig();
    }

    /**
     * Set the log level. Use constants LOG_LEVEL_*
     * @param int $logLevel
     * @return void
     */
    public function setLogLevel(int $logLevel): void {
        $this->logLevel = $logLevel;
    }

    /**
     * Main entry point to install (download/copy) all libraries from the configuration.
     *
     * @param bool $continueOnError If false, will throw on the first error; otherwise logs and continues.
     */
    public function install(bool $continueOnError = true): void {
        $this->logger("Starting LibMan install...", self::LOG_LEVEL_KEY);

        if (!$this->config) {
            throw new \RuntimeException("LibMan: no config loaded");
        }
        if (empty($this->config->libraries)) {
            $this->logger("No libraries in config; nothing to do", self::LOG_LEVEL_KEY);
            $this->logger("LibMan completed.", self::LOG_LEVEL_KEY);
            return;
        }

        $defaultProvider    = $this->config->defaultProvider ?? 'unpkg';
        $defaultDestination = $this->config->defaultDestination ?? 'wwwroot/assets/lib';

        foreach ($this->config->libraries as $idx => $libEntry) {
            try {
                $provider    = $libEntry->provider ?? $defaultProvider;
                $libraryName = $libEntry->library ?? '';
                $destination = $libEntry->destination ?? $defaultDestination;
                $files       = $libEntry->files ?? [];

                if (!$libraryName) {
                    throw new \RuntimeException(
                        "Library entry #{$idx} missing 'library' field."
                    );
                }

                // Normalize destination folder
                $destFullPath = $this->normalizePath($destination);
                if (!is_dir($destFullPath) && !mkdir($destFullPath, 0777, true)) {
                    throw new \RuntimeException("Could not create destination folder: $destFullPath");
                }

                $this->logger("Installing from $provider, library=$libraryName => $destination", self::LOG_LEVEL_KEY);

                // Remove the old library files
                $this->removeOldLibrary($destFullPath);

                if (strtolower($provider) === 'filesystem') {
                    if (is_array($files) && count($files) > 0) {
                        $this->logger("Using partial copy from local filesystem path=$libraryName", self::LOG_LEVEL_KEY);
                        $this->copyPartialFromLocal($libraryName, $files, $destFullPath);
                    } else {
                        $this->logger("Copying entire library from local filesystem path=$libraryName", self::LOG_LEVEL_KEY);
                        $this->copyEntireLocalFolder($libraryName, $destFullPath);
                    }
                } else {
                    // For other providers, use local cache
                    $cachePath = $this->ensureLibraryCached($provider, $libraryName);

                    if (is_array($files) && count($files) > 0) {
                        $this->logger("Copying partial subset of library from local cache => $destination", self::LOG_LEVEL_KEY);
                        foreach ($files as $fileSpec) {
                            //  support for { "src": "dist/index.mjs", "to": "dist/index.js" } syntax
                            if (is_string($fileSpec)) {
                                // Original usage: "dist/index.mjs"
                                $src = $cachePath . DIRECTORY_SEPARATOR . $fileSpec;
                                $dst = $destFullPath . DIRECTORY_SEPARATOR . $fileSpec;
                            } elseif (is_object($fileSpec)) {
                                $srcRelative = $fileSpec->src ?? '';
                                $dstRelative = $fileSpec->to ?? $srcRelative;
                                $src         = $cachePath . DIRECTORY_SEPARATOR . $srcRelative;
                                $dst         = $destFullPath . DIRECTORY_SEPARATOR . $dstRelative;
                            } else {
                                throw new \RuntimeException("Invalid file spec format in 'files'");
                            }
                            $this->copyOneFile($src, $dst);
                        }
                    } else {
                        $this->logger("Copying entire library from local cache => $destination", self::LOG_LEVEL_KEY);
                        $this->copyRecursive($cachePath, $destFullPath);
                    }
                }
            } catch (\Throwable $ex) {
                if (!$continueOnError) {
                    throw $ex;
                }
                $this->logger("LibMan WARNING: [{$ex->getMessage()}]", self::LOG_LEVEL_ERRORS);
            }
        }

        $this->logger("LibMan completed.", self::LOG_LEVEL_KEY);
    }

    /**
     * Logs a message to the PHP error log (and to console if CLI), depending on $logLevel.
     *
     * @param string $message
     * @param int $level One of the LOG_LEVEL_* constants
     */
    protected function logger(string $message, int $level = self::LOG_LEVEL_DEBUG): void {
        if ($level <= $this->logLevel) {
            // Send to PHP’s error log
            error_log($message);

            // If running from CLI, also echo
            if (php_sapi_name() === 'cli') {
                echo $message, PHP_EOL;
            }
        }
    }

    /**
     * Helper to remove everything inside the specified folder (but leave the folder itself).
     */
    protected function removeOldLibrary(string $folderPath): void {
        if (!is_dir($folderPath)) {
            return;
        }
        $this->logger("Removing old files from $folderPath", self::LOG_LEVEL_DEBUG);

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($folderPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getRealPath());
            } else {
                @unlink($item->getRealPath());
            }
        }
    }

    /**
     * For local filesystem partial copying.
     */
    protected function copyPartialFromLocal(string $localRoot, array $files, string $destFullPath): void {
        foreach ($files as $fileSpec) {
            if (is_string($fileSpec)) {
                $srcRel = $fileSpec;
                $dstRel = $fileSpec;
            } elseif (is_object($fileSpec)) {
                // e.g. { "src": "dist/index.mjs", "to": "dist/index.js" }
                $srcRel = $fileSpec->src ?? '';
                $dstRel = $fileSpec->to ?? $srcRel;
            } else {
                throw new \RuntimeException("Invalid file spec format in 'files'");
            }

            $srcFull = rtrim($localRoot, '\\/') . DIRECTORY_SEPARATOR . ltrim($srcRel, '\\/');
            $dstFull = $destFullPath . DIRECTORY_SEPARATOR . $dstRel;

            $dstDir = dirname($dstFull);
            if (!is_dir($dstDir) && !mkdir($dstDir, 0777, true)) {
                throw new \RuntimeException("Could not create subfolder: $dstDir");
            }
            if (!is_file($srcFull)) {
                throw new \RuntimeException("File not found in local source: $srcFull");
            }
            $this->copyOneFile($srcFull, $dstFull);
        }
    }

    /**
     * For local filesystem entire-folder copying.
     */
    protected function copyEntireLocalFolder(string $localRoot, string $destFullPath): void {
        if (!is_dir($localRoot)) {
            throw new \RuntimeException("Source path '$localRoot' is not a directory.");
        }
        $this->copyRecursive($localRoot, $destFullPath);
    }

    /**
     * Ensure that the library with given provider + libraryName is present in the local file cache.
     * If it isn’t cached, we download it. Then return the absolute path to the library’s cache folder.
     */
    protected function ensureLibraryCached(string $provider, string $libraryName): string {
        $parsed = $this->parseLibraryName($libraryName); // returns [scopeOrName, subLibPath, version]
        [$scope, $subLibPath, $version] = $parsed;

        // Construct the cache path
        $cacheRoot = $this->getCacheRoot();
        $scopeDir  = $scope ?: $libraryName;
        // put scope and sub-library together. For example:
        //   if library is "@vue/devtools-api@7.6.8"
        //   scope => "@vue"   subLib => "devtools-api"   version => "7.6.8"
        // Folder structure =>  {cacheRoot}/{provider}/@vue/devtools-api/7.6.8
        // If there was no sub-library, it's just {provider}/{scopeOrName}/{version}
        $finalPath = $cacheRoot
            . DIRECTORY_SEPARATOR . $provider
            . DIRECTORY_SEPARATOR . $scope
            . (($subLibPath !== '') ? DIRECTORY_SEPARATOR . $subLibPath : '')
            . DIRECTORY_SEPARATOR . $version;

        // if dir is outdated (24h), re-download
        if (is_dir($finalPath)) {
            $dirTime = filemtime($finalPath);
            if (time() - $dirTime > 86400) {
                $this->logger("Cache dir is outdated: $finalPath", self::LOG_LEVEL_DEBUG);
                $this->removeRecursive($finalPath);
            } else {
                $this->logger("Cache hit for $provider|$libraryName => $finalPath", self::LOG_LEVEL_DEBUG);
                return $finalPath;
            }
        }

        // Not cached => we need to download it. For npm-based libraries, we grab from registry.
        // For cdnjs/jsdelivr/unpkg partial files, we might either do a file-by-file fetch, or do a npm approach.
        // Here we’ll treat everything like a npm package if it has a @version. If cdnjs, we might want a different approach.
        $this->logger("Cache miss => downloading library $libraryName from $provider", self::LOG_LEVEL_KEY);
        $this->logger(" => $finalPath", self::LOG_LEVEL_DEBUG);
        if (!mkdir($finalPath, 0777, true)) {
            throw new \RuntimeException("Could not create cache folder: $finalPath");
        }

        // For now let's just do the “npm registry” approach if $provider is unpkg/jsdelivr/cdnjs:
        $this->downloadAllFromRegistry($libraryName, $finalPath);

        return $finalPath;
    }

    /**
     * Download entire package from npm registry into $destFullPath.
     */
    protected function downloadAllFromRegistry(string $libraryName, string $destFullPath): void {
        [$pkgName, $version] = $this->splitPackage($libraryName);

        // 1) Ask the npm registry for package info
        $registryUrl = 'https://registry.npmjs.org/' . $this->encodePackageName($pkgName);
        $json        = $this->httpGet($registryUrl);
        $pkgInfo     = json_decode($json, false);

        if (json_last_error() !== JSON_ERROR_NONE || empty($pkgInfo->versions->$version->dist->tarball)) {
            throw new \RuntimeException("Could not find tarball for '$pkgName@$version'");
        }

        $tarUrl = $pkgInfo->versions->$version->dist->tarball;

        // 2) Download to temp .tgz
        $tempTgz = tempnam(sys_get_temp_dir(), 'libman_');
        if (!$tempTgz) {
            throw new \RuntimeException("Failed to create temp file for tarball");
        }
        $realTgz = $tempTgz . '.tgz';
        rename($tempTgz, $realTgz);

        $this->logger("Downloading tarball: $tarUrl => $realTgz", self::LOG_LEVEL_DEBUG);
        $this->downloadFile($tarUrl, $realTgz);

        // 3) Extract to the cache path
        $phar = new \PharData($realTgz);
        $phar->extractTo($destFullPath, null, true);
        unset($phar);
        @unlink($realTgz);

        // If everything extracted under 'package/', move up one level:
        $packageDir = $destFullPath . DIRECTORY_SEPARATOR . 'package';
        if (is_dir($packageDir)) {
            $this->logger("Moving extracted 'package/' contents to $destFullPath", self::LOG_LEVEL_DEBUG);
            $this->moveDirectoryContentsUpOneLevel($packageDir, $destFullPath);
            @rmdir($packageDir);
        }
    }

    /**
     * Move the contents of $srcDir up one level into $destDir, then remove $srcDir.
     */
    protected function moveDirectoryContentsUpOneLevel(string $srcDir, string $destDir): void {
        $items = scandir($srcDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $src = $srcDir . DIRECTORY_SEPARATOR . $item;
            $dst = $destDir . DIRECTORY_SEPARATOR . $item;
            rename($src, $dst);
        }
    }

    /**
     * Clears the entire local cache, or only a specific provider’s folder if $provider is given.
     */
    public function clearCache(?string $provider = null): void {
        $cacheRoot = $this->getCacheRoot();
        if (!is_dir($cacheRoot)) {
            $this->logger("Cache root does not exist or is not a directory: $cacheRoot", self::LOG_LEVEL_DEBUG);
            return;
        }
        if ($provider) {
            $path = $cacheRoot . DIRECTORY_SEPARATOR . $provider;
            if (is_dir($path)) {
                $this->logger("Clearing cache for provider=$provider => $path", self::LOG_LEVEL_DEBUG);
                $this->removeRecursive($path);
            } else {
                $this->logger("No cache directory found for provider=$provider => $path", self::LOG_LEVEL_DEBUG);
            }
        } else {
            $this->logger("Clearing ALL cache => $cacheRoot", self::LOG_LEVEL_DEBUG);
            $this->removeRecursive($cacheRoot);
        }
    }

    /**
     * Return the OS-specific directory for caching - OS temp directory/.librarymanager/cache
     */
    protected function getCacheRoot(): string {
        $cacheRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . '.librarymanager' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($cacheRoot) && !mkdir($cacheRoot, 0777, true)) {
            throw new \RuntimeException("Could not create cache root: $cacheRoot");
        }
        return $cacheRoot;
    }

    /**
     * Loads and decodes the JSON libman config.
     */
    protected function loadConfig(): void {
        if (!is_file($this->jsonPath)) {
            throw new \RuntimeException("LibMan: JSON file not found at $this->jsonPath");
        }
        $raw = file_get_contents($this->jsonPath);
        if (!$raw) {
            throw new \RuntimeException("LibMan: could not read file $this->jsonPath");
        }
        $decoded = json_decode($raw, false);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("LibMan: invalid JSON in $this->jsonPath");
        }
        $this->config = $decoded;
    }

    /**
     * Simple GET wrapper around file_get_contents
     * to handle potential errors/exceptions.
     */
    protected function httpGet(string $url): string {
        $this->logger("HTTP GET: $url", self::LOG_LEVEL_DEBUG);
        $data = @file_get_contents($url);
        if ($data === false) {
            throw new \RuntimeException("Failed to GET: $url");
        }
        return $data;
    }

    /**
     * Download a single file (via file_get_contents) from $url
     * and store into $localPath.
     */
    protected function downloadFile(string $url, string $localPath): void {
        $this->logger("Downloading file from $url => $localPath", self::LOG_LEVEL_DEBUG);
        $data = $this->httpGet($url);
        file_put_contents($localPath, $data);
    }

    /**
     * Recursively copy contents of $sourceDir into $destDir.
     */
    protected function copyRecursive(string $sourceDir, string $destDir): void {
        $dir = opendir($sourceDir);
        if (!$dir) {
            throw new \RuntimeException("Unable to read $sourceDir for copying");
        }
        @mkdir($destDir, 0777, true);

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $srcPath = $sourceDir . DIRECTORY_SEPARATOR . $file;
            $dstPath = $destDir . DIRECTORY_SEPARATOR . $file;

            if (is_dir($srcPath)) {
                $this->copyRecursive($srcPath, $dstPath);
            } else {
                $this->copyOneFile($srcPath, $dstPath);
            }
        }
        closedir($dir);
    }

    /**
     * Copies a single file from $src to $dst, logging the action.
     */
    protected function copyOneFile(string $src, string $dst): void {
        $dstDir = dirname($dst);
        if (!is_dir($dstDir) && !mkdir($dstDir, 0777, true)) {
            throw new \RuntimeException("Could not create subfolder: $dstDir");
        }

        $this->logger("Copying file: $src => $dst", self::LOG_LEVEL_DEBUG);
        if (!@copy($src, $dst)) {
            throw new \RuntimeException("Failed to copy $src => $dst");
        }
    }

    /**
     * Recursively remove a directory.
     */
    protected function removeRecursive(string $dirPath): void {
        if (!is_dir($dirPath)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dirPath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }
        rmdir($dirPath);
    }

    /**
     * Normalizes the user's "destination" path with the $rootPath.
     */
    protected function normalizePath(string $destination): string {
        $destination = ltrim($destination, DIRECTORY_SEPARATOR);
        return $this->rootPath . DIRECTORY_SEPARATOR . $destination;
    }

    /**
     * Parse a library name with optional scope, sub-library, and version. Return array:
     *   [scopeOrName, subLibraryPath, version]
     * For example:
     *   "bootstrap@5.3.3" => [ "bootstrap", "", "5.3.3" ]
     *   "@vue/devtools-api@7.6.8" => [ "@vue", "devtools-api", "7.6.8" ]
     */
    protected function parseLibraryName(string $libName): array {
        [$pkgName, $version] = $this->splitPackage($libName);
        // If $pkgName looks like "@vue/devtools-api", then scope=@vue, subLib=devtools-api
        // If $pkgName is just "bootstrap", scope=bootstrap, subLib=""
        $slashPos = strpos($pkgName, '/');
        if ($slashPos === false) {
            return [$pkgName, '', $version];
        }
        // e.g. pkgName="@vue/devtools-api"
        $scope  = substr($pkgName, 0, $slashPos); // "@vue"
        $subLib = substr($pkgName, $slashPos + 1); // "devtools-api"
        return [$scope, $subLib, $version];
    }

    /**
     * Split "bootstrap@5.3.3" => ["bootstrap", "5.3.3"].
     * Also handles scope packages like "@vue/devtools-api@7.6.8".
     */
    protected function splitPackage(string $libraryName): array {
        // We'll look for the last '@' in the string, because
        // scope packages will have an '@' at the start.
        $pos = strrpos($libraryName, '@');
        if ($pos === 0) {
            // Means the entire string starts with '@' but no second '@'
            throw new \RuntimeException("Invalid library spec: $libraryName. No version found.");
        } elseif ($pos === false) {
            // No '@' at all => can't parse a version
            throw new \RuntimeException("Invalid library spec: $libraryName. Must have version.");
        }

        $pkgName = substr($libraryName, 0, $pos);
        $version = substr($libraryName, $pos + 1);

        if (!$pkgName || !$version) {
            throw new \RuntimeException("Invalid library spec: $libraryName. Missing name or version.");
        }

        return [$pkgName, $version];
    }

    /**
     * URL-encode an NPM package name, including scope. E.g. “@vue/devtools-api” => “%40vue%2Fdevtools-api”.
     */
    protected function encodePackageName(string $pkgName): string {
        return str_replace('%2F', '/', rawurlencode($pkgName));
    }
}
