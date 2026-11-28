<?php

namespace App\Services\Ai;

/**
 * Replaces personal data with placeholders before text is sent to an AI provider (T6, RA 10173).
 *
 * Names are matched as whole words, case-insensitively, longest first, so "Dela Cruz"
 * is replaced before "Cruz" and "Ana" inside "Banana" is left alone.
 */
class Redactor
{
    /** @var array<string, string> placeholder => original (kept on the server only) */
    private array $map = [];

    /**
     * @param  array<int, array{0: string, 1: list<string>}>  $people  [placeholder, [name parts...]]
     */
    public function redact(string $text, array $people = []): string
    {
        $this->map = [];

        // Patterns first, so digits inside names never matter.
        $text = (string) preg_replace('/(?<!\d)\d{12}(?!\d)/', '[LRN]', $text);
        $text = (string) preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/u', '[EMAIL]', $text);
        $text = (string) preg_replace('/(?<!\d)(\+?63|0)9\d{2}[\s-]?\d{3}[\s-]?\d{4}(?!\d)/', '[PHONE]', $text);

        $terms = [];
        foreach ($people as [$placeholder, $parts]) {
            foreach ($parts as $part) {
                $part = trim($part);
                if (mb_strlen($part) >= 2) {
                    $terms[$part] = $placeholder;
                }
            }
        }
        uksort($terms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        foreach ($terms as $term => $placeholder) {
            $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($term, '/').'(?![\p{L}\p{N}])/iu';
            $text = (string) preg_replace_callback($pattern, function (array $m) use ($placeholder) {
                $this->map[$placeholder] ??= $m[0];

                return $placeholder;
            }, $text);
        }

        // "[STUDENT] [STUDENT]" (first + last name) reads better as one placeholder.
        return (string) preg_replace('/(\[[A-Z_0-9]+\])(?:[\s,]+\1)+/', '$1', $text);
    }

    /** @return array<string, string> */
    public function map(): array
    {
        return $this->map;
    }
}
