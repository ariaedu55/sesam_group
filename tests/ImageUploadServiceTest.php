<?php

declare(strict_types=1);

namespace Sesam\Tests;

use PHPUnit\Framework\TestCase;
use Sesam\Core\Validation\ValidationException;
use Sesam\Services\Storage\ImageUploadService;

final class ImageUploadServiceTest extends TestCase
{
    private string $root = '';

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sesam-upl-' . bin2hex(random_bytes(4));
        mkdir($this->root, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->wipe($this->root);
    }

    public function testPngIsConvertedToCompressedWebp(): void
    {
        $service = $this->service();
        $png = $this->pngFile(640, 480);
        $path = $service->store($this->upload($png, 'boite.png', 'image/png'), 'boutique/12');
        $this->assertMatchesRegularExpression('#^boutique/12/[a-f0-9]{32}\.webp$#', $path);
        $stored = $this->root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $this->assertFileExists($stored);
        $info = getimagesize($stored);
        $this->assertIsArray($info);
        $this->assertSame('image/webp', $info['mime'] ?? '');
        $this->assertLessThanOrEqual(960, max((int) $info[0], (int) $info[1]));
        $this->assertLessThan((int) filesize($png), (int) filesize($stored));
        $read = $service->read($path);
        $this->assertSame('image/webp', $read['mime']);
        unlink($png);
    }

    public function testOversizedAndExecutableNamesAreRejected(): void
    {
        $service = $this->service(2_000);
        $png = $this->pngFile(40, 40);
        try {
            $service->store($this->upload($png, 'ok.png', 'image/png', 9_000_000), 'boutique/12');
            $this->fail('Taille trop grande acceptée.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        try {
            $service->store($this->upload($png, 'shell.php.png', 'image/png'), 'boutique/12');
            $this->fail('Double extension exécutable acceptée.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        $text = $this->root . DIRECTORY_SEPARATOR . 'plain.txt';
        file_put_contents($text, 'not-an-image');
        try {
            $service->store($this->upload($text, 'fake.jpg', 'image/jpeg'), 'boutique/12');
            $this->fail('Fichier non image accepté.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('image', $exception->errors());
        }
        unlink($png);
        unlink($text);
    }

    private function service(int $maxBytes = 4_194_304): ImageUploadService
    {
        return new ImageUploadService($this->root, $maxBytes, 960, 78);
    }

    /**
     * @return array<string, mixed>
     */
    private function upload(string $tmp, string $name, string $type, ?int $size = null): array
    {
        return [
            'name' => $name,
            'type' => $type,
            'tmp_name' => $tmp,
            'error' => UPLOAD_ERR_OK,
            'size' => $size ?? (int) filesize($tmp),
        ];
    }

    private function pngFile(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        $this->assertInstanceOf(\GdImage::class, $image);
        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocate($image, 8, 124, 255));
        $path = $this->root . DIRECTORY_SEPARATOR . 'src-' . bin2hex(random_bytes(3)) . '.png';
        imagepng($image, $path);
        imagedestroy($image);

        return $path;
    }

    private function wipe(string $folder): void
    {
        if (!is_dir($folder)) {
            return;
        }
        $items = scandir($folder);
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $path = $folder . DIRECTORY_SEPARATOR . $name;
            if (is_dir($path)) {
                $this->wipe($path);
            } elseif (is_file($path)) {
                unlink($path);
            }
        }
        rmdir($folder);
    }
}
