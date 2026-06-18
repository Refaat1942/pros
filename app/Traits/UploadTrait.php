<?php

namespace App\Traits;

use File;

trait UploadTrait
{
    public function uploadAllTyps($file, $directory, $width = null, $height = null)
    {
        if (!File::isDirectory('storage/images/' . $directory)) {
            File::makeDirectory('storage/images/' . $directory, 0777, true, true);
        }

        $fileMimeType = $file->getClientMimeType();
        $imageCheck = explode('/', $fileMimeType);

        if ($imageCheck[0] == 'image') {
            $allowedImagesMimeTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($fileMimeType, $allowedImagesMimeTypes))
                return 'default.png';

            return $this->uploadeImage($file, $directory, $width, $height);
        }

        $allowedMimeTypes = [
            'application/pdf',
            'application/msword',
            'application/excel',
            'application/vnd.ms-excel',
            'application/vnd.msexcel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/octet-stream',
        ];

        if (!in_array($fileMimeType, $allowedMimeTypes))
            return 'default.png';

        return $this->uploadFile($file, $directory);
    }

    public function uploadFile($file, $directory)
    {
        $filename = time() . rand(1000000, 9999999) . '.' . $file->getClientOriginalExtension();
        $path = 'images/' . $directory;
        $file->storeAs($path, $filename);
        return $filename;
    }

    public function uploadeImage($file, $directory, $width = null, $height = null)
    {
        $thumbsPath = 'storage/images/' . $directory;
        $name       = time() . '_' . rand(1111, 9999) . '.' . $file->getClientOriginalExtension();

        // Move the file first
        $file->move(public_path($thumbsPath), $name);

        // Resize if needed using GD
        if ($width !== null && $height !== null) {
            $fullPath = public_path($thumbsPath . '/' . $name);
            $this->resizeImageGD($fullPath, $width, $height, $file->getClientOriginalExtension());
        }

        return (string) $name;
    }

    private function resizeImageGD(string $path, int $maxWidth, int $maxHeight, string $ext): void
    {
        $ext = strtolower($ext);

        $src = match ($ext) {
            'jpg', 'jpeg' => imagecreatefromjpeg($path),
            'png'         => imagecreatefrompng($path),
            default       => null,
        };

        if (!$src) return;

        [$origWidth, $origHeight] = getimagesize($path);

        // Aspect ratio
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight);
        $newWidth  = (int) ($origWidth  * $ratio);
        $newHeight = (int) ($origHeight * $ratio);

        $dst = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG
        if ($ext === 'png') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        match ($ext) {
            'jpg', 'jpeg' => imagejpeg($dst, $path, 90),
            'png'         => imagepng($dst, $path),
        };

        imagedestroy($src);
        imagedestroy($dst);
    }

    public function deleteFile($file_name, $directory = 'unknown'): void
    {
        if ($file_name && $file_name != 'default.png' && file_exists("storage/images/$directory/$file_name")) {
            unlink("storage/images/$directory/$file_name");
        }
    }

    public function defaultImage($directory)
    {
        return asset("/storage/images/$directory/default.png");
    }

    public static function getImage($name, $directory)
    {
        return asset("storage/images/$directory/" . $name);
    }
}