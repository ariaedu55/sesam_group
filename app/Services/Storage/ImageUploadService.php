<?php

declare(strict_types=1);

namespace Sesam\Services\Storage;

use Sesam\Core\HttpException;
use Sesam\Core\Validation\ValidationException;

final class ImageUploadService
{
    private const MIME_WHITELIST = [
        'image/jpeg' => true,
        'image/png' => true,
        'image/webp' => true,
        'image/gif' => true,
    ];

    private const NAME_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function __construct(
        private readonly string $uploadsRoot,
        private readonly int $maxBytes,
        private readonly int $maxSide,
        private readonly int $quality,
    ) {
    }

    /**
     * @param array<string, mixed> $file
     */
    public function store(array $file, string $directory): string
    {
        if (!preg_match('#^[a-z]+/[1-9][0-9]*$#', $directory)) {
            throw new HttpException(500, 'Une erreur est survenue.');
        }
        if (!is_dir($this->uploadsRoot) && !mkdir($this->uploadsRoot, 0755, true) && !is_dir($this->uploadsRoot)) {
            throw new HttpException(500, 'Une erreur est survenue.');
        }
        $this->assertSafeUpload($file);
        $source = $this->loadImage((string) $file['tmp_name']);
        $relative = $directory . '/' . bin2hex(random_bytes(16)) . '.webp';
        $absolute = $this->absolutePath($relative);
        $folder = dirname($absolute);
        if (!is_dir($folder) && !mkdir($folder, 0755, true) && !is_dir($folder)) {
            imagedestroy($source);
            throw new HttpException(500, 'Une erreur est survenue.');
        }
        $written = $this->writeCompressed($source, $absolute);
        imagedestroy($source);
        if (!$written) {
            throw new ValidationException(['image' => 'La photo n’a pas pu être convertie.']);
        }

        return $relative;
    }

    public function delete(?string $relative): void
    {
        if ($relative === null || $relative === '') {
            return;
        }
        $absolute = $this->absolutePath($relative);
        if (is_file($absolute)) {
            unlink($absolute);
        }
    }

    /**
     * @return array{contents: string, mime: string}
     */
    public function read(string $relative): array
    {
        $absolute = $this->absolutePath($relative);
        $contents = is_file($absolute) ? file_get_contents($absolute) : false;
        if (!is_string($contents) || $contents === '') {
            throw new HttpException(404, 'Image introuvable.');
        }

        return [
            'contents' => $contents,
            'mime' => str_ends_with($relative, '.jpg') ? 'image/jpeg' : 'image/webp',
        ];
    }

    public function deleteDirectory(string $directory): void
    {
        if (!preg_match('#^[a-z]+/[1-9][0-9]*$#', $directory)) {
            return;
        }
        $folder = $this->uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
        if (!is_dir($folder)) {
            return;
        }
        $files = scandir($folder);
        if (!is_array($files)) {
            return;
        }
        foreach ($files as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $folder . DIRECTORY_SEPARATOR . $name;
            if (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($folder);
    }

    /**
     * @param array<string, mixed> $file
     */
    private function assertSafeUpload(array $file): void
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['image' => 'Ajoutez une photo.']);
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new ValidationException(['image' => 'La photo dépasse la taille autorisée (4 Mo).']);
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new ValidationException(['image' => 'Le transfert de la photo a échoué.']);
        }

        $original = strtolower((string) ($file['name'] ?? ''));
        if ($original === '' || preg_match('/\.(php\d*|phtml|phar|exe|js|html|htm|svg|cgi|pl)(\.|$)/', $original) === 1) {
            throw new ValidationException(['image' => 'Nom de fichier refusé.']);
        }
        $extension = pathinfo($original, PATHINFO_EXTENSION);
        if (!in_array($extension, self::NAME_EXTENSIONS, true)) {
            throw new ValidationException(['image' => 'Formats acceptés : JPEG, PNG, WebP, GIF.']);
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $declared = (int) ($file['size'] ?? 0);
        $bytes = is_file($tmp) ? (int) filesize($tmp) : 0;
        if ($tmp === '' || !is_file($tmp) || $bytes < 1) {
            throw new ValidationException(['image' => 'Photo illisible.']);
        }
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp)) {
            throw new ValidationException(['image' => 'Photo illisible.']);
        }
        if ($declared > $this->maxBytes || $bytes > $this->maxBytes) {
            throw new ValidationException(['image' => 'La photo dépasse la taille autorisée (4 Mo).']);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        if (!is_string($mime) || !isset(self::MIME_WHITELIST[$mime])) {
            throw new ValidationException(['image' => 'Formats acceptés : JPEG, PNG, WebP, GIF.']);
        }
        $info = @getimagesize($tmp);
        if (!is_array($info) || ($info[0] ?? 0) < 1 || ($info[1] ?? 0) < 1) {
            throw new ValidationException(['image' => 'Cette image n’est pas valide.']);
        }
    }

    /**
     * @return \GdImage
     */
    private function loadImage(string $tmp): \GdImage
    {
        $blob = file_get_contents($tmp);
        if (!is_string($blob) || $blob === '') {
            throw new ValidationException(['image' => 'Photo illisible.']);
        }
        $image = @imagecreatefromstring($blob);
        if (!$image instanceof \GdImage) {
            throw new ValidationException(['image' => 'Cette image n’est pas valide.']);
        }

        return $image;
    }

    /**
     * @param \GdImage $source
     */
    private function writeCompressed(\GdImage $source, string $absolute): bool
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1.0, $this->maxSide / max($width, $height, 1));
        $nextWidth = max(1, (int) round($width * $scale));
        $nextHeight = max(1, (int) round($height * $scale));
        $canvas = imagecreatetruecolor($nextWidth, $nextHeight);
        if (!$canvas instanceof \GdImage) {
            return false;
        }
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        $clear = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $nextWidth, $nextHeight, $clear);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nextWidth, $nextHeight, $width, $height);
        $quality = max(40, min(90, $this->quality));
        if (!function_exists('imagewebp')) {
            imagedestroy($canvas);

            return false;
        }
        $ok = imagewebp($canvas, $absolute, $quality);
        imagedestroy($canvas);

        return $ok === true;
    }

    private function absolutePath(string $relative): string
    {
        if (preg_match('#^[a-z]+/[1-9][0-9]*/[a-f0-9]{32}\.(webp|jpg)$#', $relative) !== 1) {
            throw new HttpException(404, 'Image introuvable.');
        }
        $root = realpath($this->uploadsRoot);
        if ($root === false) {
            throw new HttpException(404, 'Image introuvable.');
        }
        $candidate = $this->uploadsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $real = realpath($candidate);
        if ($real === false) {
            return $candidate;
        }
        if (!str_starts_with($real, $root . DIRECTORY_SEPARATOR) && $real !== $root) {
            throw new HttpException(404, 'Image introuvable.');
        }

        return $real;
    }
}
