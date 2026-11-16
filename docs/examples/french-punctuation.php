<?php

declare(strict_types=1);

use GrommasDietz\Proofreader\Typography;

// Copy into the client project's config partials and enable explicitly.
// Missing punctuation spaces are inferred only at a following text boundary.
return [
    'label' => 'Regional French punctuation',
    'callback' => static function (string $text, ?string $language): string {
        if (preg_match('/^fr(?:[-_]|$)/i', (string) $language) !== 1) {
            return $text;
        }

        $profile = Typography::forLanguage($language);
        $spaces = [
            ':' => $profile->option('space.colon'),
            ';' => $profile->option('space.semicolon'),
            '!' => $profile->option('space.marks'),
            '?' => $profile->option('space.marks'),
        ];
        if (in_array(null, $spaces, true)) {
            return $text;
        }

        // Numbers, compact technical syntax and punctuation clusters are left
        // alone. URL/identifier protection belongs in the project's protect list.
        return preg_replace_callback(
            '/(?<=\p{L})([:;!?])(?=$|[\p{Zs}\t\r\n»”’\)\]])/u',
            static function (array $match) use ($spaces): string {
                $mark = (string) $match[1];

                return ($spaces[$mark] ?? '') . $mark;
            },
            $text
        ) ?? $text;
    },
];
