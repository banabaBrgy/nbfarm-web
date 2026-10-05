<?php

if (! function_exists('versioned_asset')) {
    /**
     * Asset URL with filemtime query string so browsers/CDN fetch new CSS/JS after deploy.
     */
    function versioned_asset(string $path): string
    {
        $url = asset($path);
        $fullPath = public_path($path);

        if (! is_file($fullPath)) {
            return $url;
        }

        return $url.'?v='.filemtime($fullPath);
    }
}
