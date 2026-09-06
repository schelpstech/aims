<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

[$script, $source, $destination, $maximumWidth, $quality] = array_pad($argv, 5, null);

if ($source === null || $destination === null) {
    fwrite(STDERR, "Usage: php bin/optimize-image.php <source> <destination> [maximum-width] [quality]\n");
    exit(1);
}

$maximumWidth = max(320, (int) ($maximumWidth ?? 1800));
$quality = max(40, min(95, (int) ($quality ?? 82)));
$details = @getimagesize($source);

if ($details === false) {
    fwrite(STDERR, "The source is not a supported image.\n");
    exit(1);
}

$sourceImage = match ($details[2]) {
    IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
    IMAGETYPE_PNG => @imagecreatefrompng($source),
    IMAGETYPE_WEBP => @imagecreatefromwebp($source),
    default => false,
};

if ($sourceImage === false) {
    fwrite(STDERR, "Unable to decode the source image.\n");
    exit(1);
}

$width = imagesx($sourceImage);
$height = imagesy($sourceImage);
$targetWidth = min($width, $maximumWidth);
$targetHeight = (int) round($height * ($targetWidth / $width));
$outputImage = $sourceImage;

if ($targetWidth !== $width) {
    $scaled = imagescale($sourceImage, $targetWidth, $targetHeight, IMG_BICUBIC_FIXED);
    if ($scaled === false) {
        imagedestroy($sourceImage);
        fwrite(STDERR, "Unable to resize the image.\n");
        exit(1);
    }
    $outputImage = $scaled;
}

$directory = dirname($destination);
if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
    fwrite(STDERR, "Unable to create the output directory.\n");
    exit(1);
}

$extension = strtolower(pathinfo($destination, PATHINFO_EXTENSION));
$written = match ($extension) {
    'jpg', 'jpeg' => imagejpeg($outputImage, $destination, $quality),
    'webp' => imagewebp($outputImage, $destination, $quality),
    'png' => imagepng($outputImage, $destination, 8),
    default => false,
};

if ($outputImage !== $sourceImage) {
    imagedestroy($outputImage);
}
imagedestroy($sourceImage);

if (!$written) {
    fwrite(STDERR, "Unable to write the optimized image.\n");
    exit(1);
}

fwrite(STDOUT, sprintf("Created %s (%dx%d).\n", $destination, $targetWidth, $targetHeight));

