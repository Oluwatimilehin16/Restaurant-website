<?php
require_once __DIR__ . '/vendor/autoload.php';

use Cloudinary\Cloudinary;

function cloudinary() {
    static $cloud;
    if ($cloud) return $cloud;

    $cloudName = getenv('CLOUDINARY_CLOUD_NAME');
    $apiKey    = getenv('CLOUDINARY_API_KEY');
    $apiSecret = getenv('CLOUDINARY_API_SECRET');

    $cloud = new Cloudinary([
        'cloud' => ['cloud_name' => $cloudName],
        'api'   => ['key' => $apiKey, 'secret' => $apiSecret],
        'url'   => ['secure' => true],
    ]);

    return $cloud;
}
