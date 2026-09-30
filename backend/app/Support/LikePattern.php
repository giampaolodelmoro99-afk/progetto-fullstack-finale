<?php

namespace App\Support;

final class LikePattern
{
    /**
     * A LIKE pattern matching values that contain the term, with wildcards escaped.
     */
    public static function contains(string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';
    }
}
