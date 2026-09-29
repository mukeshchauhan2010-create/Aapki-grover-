<?php
declare(strict_types=1);

function image_upload_error(int $code): string {
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Image is too large.',
        UPLOAD_ERR_PARTIAL => 'Image upload was incomplete.',
        UPLOAD_ERR_NO_FILE => 'Please select an image.',
        default => 'Image upload failed.'
    };
}

function sanitize_svg(string $svg): string {
    $svg = preg_replace('/<\?xml.*?\?>/is', '', $svg) ?? '';
    $svg = preg_replace('/<!DOCTYPE.*?>/is', '', $svg) ?? '';
    $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $svg) ?? '';
    $svg = preg_replace('/\s(on[a-z]+)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg) ?? '';
    $svg = preg_replace('/(href|xlink:href)\s*=\s*(["\'])\s*(javascript:|data:|https?:)[^"\']*\2/i', '', $svg) ?? '';
    if (stripos($svg, '<svg') === false) throw new RuntimeException('Invalid SVG file.');
    if (strlen($svg) > 3 * 1024 * 1024) throw new RuntimeException('SVG is too large.');
    return trim($svg);
}

function save_product_image(array $file, string $uploadDir, string $publicDir): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException(image_upload_error((int)$file['error']));
    if (($file['size'] ?? 0) > 8 * 1024 * 1024) throw new RuntimeException('Maximum image size is 8 MB.');
    $tmp = (string)$file['tmp_name'];
    $original = (string)$file['name'];
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','webp','svg'];
    if (!in_array($ext, $allowed, true)) throw new RuntimeException('Only JPG, PNG, WEBP or SVG images are allowed.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
    $safeMime = ['image/jpeg','image/png','image/webp','image/svg+xml'];
    if ($ext !== 'svg' && !in_array($mime, $safeMime, true)) throw new RuntimeException('The uploaded file is not a valid image.');
    if ($ext === 'svg') {
        $svg = sanitize_svg((string)file_get_contents($tmp));
        $id = bin2hex(random_bytes(12));
        $svgName = $id . '.svg';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0750, true);
        file_put_contents($uploadDir . '/' . $svgName, $svg, LOCK_EX);
        $webpPath = $uploadDir . '/' . $id . '.webp';
        $webpCreated = false;
        if (class_exists('Imagick')) {
            try {
                $im = new Imagick();
                $im->setBackgroundColor(new ImagickPixel('transparent'));
                $im->readImageBlob($svg);
                $im->setImageFormat('webp');
                $im->thumbnailImage(1200, 1200, true, true);
                $im->writeImage($webpPath);
                $im->clear(); $im->destroy();
                $webpCreated = true;
            } catch (Throwable $e) { $webpCreated = false; }
        }
        return ['source'=>$publicDir.'/'.$svgName, 'webp'=>$webpCreated ? $publicDir.'/'.$id.'.webp' : $publicDir.'/'.$svgName, 'filename'=>$svgName];
    }
    if (!function_exists('imagecreatefromjpeg')) throw new RuntimeException('PHP GD extension is required for image processing.');
    $src = match ($mime) {
        'image/jpeg' => @imagecreatefromjpeg($tmp),
        'image/png' => @imagecreatefrompng($tmp),
        'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : false,
        default => false
    };
    if (!$src) throw new RuntimeException('Could not decode the image.');
    $w = imagesx($src); $h = imagesy($src); $max = 1600;
    $scale = min(1, $max / max($w, $h)); $nw = max(1, (int)round($w*$scale)); $nh = max(1, (int)round($h*$scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagecopyresampled($dst, $src, 0,0,0,0,$nw,$nh,$w,$h);
    $id = bin2hex(random_bytes(12));
    if (!is_dir($uploadDir)) mkdir($uploadDir,0750,true);
    $webpFile = $id.'.webp'; $path = $uploadDir.'/'.$webpFile;
    if (!function_exists('imagewebp') || !imagewebp($dst, $path, 82)) { imagedestroy($src); imagedestroy($dst); throw new RuntimeException('WEBP processing is unavailable on this server.'); }
    imagedestroy($src); imagedestroy($dst);
    return ['source'=>$publicDir.'/'.$webpFile, 'webp'=>$publicDir.'/'.$webpFile, 'filename'=>$webpFile];
}
