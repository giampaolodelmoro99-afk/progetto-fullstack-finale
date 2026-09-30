<?php

use App\Support\LikePattern;

it('wraps the term in wildcards and escapes the like special characters', function (string $term, string $pattern) {
    expect(LikePattern::contains($term))->toBe($pattern);
})->with([
    'plain text' => ['cuffie', '%cuffie%'],
    'percent sign' => ['100%', '%100\%%'],
    'underscore' => ['CDS_AUD', '%CDS\_AUD%'],
    'backslash' => ['a\b', '%a\\\\b%'],
    'empty term' => ['', '%%'],
]);
