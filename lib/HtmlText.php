<?php

declare(strict_types=1);

namespace GrommasDietz\Proofreader;

/**
 * Maps text replacements back to their original HTML nodes without serializing
 * tags or attributes. Inline elements share a run; blocks and protected content
 * are boundaries. This keeps quotation pairs and paragraph ends in context.
 *
 * @internal
 */
final class HtmlText
{
    private const BLOCKS = 'address|article|aside|blockquote|br|dd|div|dl|dt|fieldset|figcaption|figure|footer|form|h[1-6]|header|hr|li|main|nav|ol|p|section|table|tbody|td|th|thead|tr|ul';
    private const PROTECTED = ['code', 'pre', 'kbd', 'samp', 'script', 'style', 'math'];
    private const VOID = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];

    /**
     * @return list<string>|false
     */
    public static function split(string $html): array|false
    {
        return preg_split(
            '~(<!--.*?-->|<![^>]*>|</?[a-z](?:[^>"\']|"[^"]*"|\'[^\']*\')*>)~isu',
            $html,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
    }

    /**
     * @param callable(string, list<array{offset:int, length:int, language:?string}>, bool, bool):list<array{offset:int, length:int, value:string}> $transform
     */
    public static function transform(string $html, ?string $language, callable $transform): string
    {
        $parts = self::split($html);

        if ($parts === false) {
            return $html;
        }

        /** @var list<array{name:string, language:?string}> $stack */
        $stack = [];
        /** @var list<array{part:int, offset:int, length:int, language:?string}> $nodes */
        $nodes = [];
        $text = '';
        $blockStart = false;
        $protected = null;

        $flush = static function (bool $blockEnd = false) use (&$parts, &$nodes, &$text, &$blockStart, $transform): void {
            if ($nodes !== []) {
                $changes = $transform($text, array_map(
                    static fn (array $node): array => ['offset' => $node['offset'], 'length' => $node['length'], 'language' => $node['language']],
                    $nodes
                ), $blockStart, $blockEnd);
                usort($changes, static fn (array $a, array $b): int => $b['offset'] <=> $a['offset']);

                foreach ($changes as $change) {
                    $inserted = false;

                    foreach ($nodes as $node) {
                        $start = max($change['offset'], $node['offset']);
                        $end = min($change['offset'] + $change['length'], $node['offset'] + $node['length']);

                        if ($start >= $end) {
                            continue;
                        }

                        $parts[$node['part']] = substr_replace(
                            $parts[$node['part']],
                            $inserted ? '' : $change['value'],
                            $start - $node['offset'],
                            $end - $start
                        );
                        $inserted = true;
                    }
                }
            }

            $nodes = [];
            $text = '';
            $blockStart = false;
        };

        foreach ($parts as $index => $part) {
            if (preg_match('~^<\s*(/?)\s*([a-z][a-z0-9:-]*)\b~i', $part, $tag) !== 1) {
                if ($protected === null && !str_starts_with($part, '<!') && $part !== '') {
                    $nodes[] = [
                        'part' => $index,
                        'offset' => strlen($text),
                        'length' => strlen($part),
                        'language' => $stack === [] ? $language : $stack[array_key_last($stack)]['language'],
                    ];
                    $text .= $part;
                }
                continue;
            }

            $name = strtolower($tag[2]);
            $closing = $tag[1] === '/';

            if ($protected !== null) {
                if ($closing && $name === $protected) {
                    $protected = null;
                }
                continue;
            }

            if (in_array($name, self::PROTECTED, true)) {
                $flush();
                if (!$closing && !str_ends_with($part, '/>')) {
                    $protected = $name;
                }
                continue;
            }

            if (preg_match('~^(?:' . self::BLOCKS . ')$~i', $name) === 1) {
                $flush(true);
                $blockStart = !$closing;
            }

            if ($closing) {
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if ($stack[$i]['name'] === $name) {
                        $stack = array_slice($stack, 0, $i);
                        break;
                    }
                }
                continue;
            }

            if (in_array($name, self::VOID, true) || str_ends_with($part, '/>')) {
                // Replaced content must not disappear from the logical text.
                if (in_array($name, ['img', 'input', 'embed'], true)) {
                    $flush();
                }
                continue;
            }

            $current = $stack === [] ? $language : $stack[array_key_last($stack)]['language'];
            preg_match_all('~\s+([^\s=/>]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?~u', $part, $attributes, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
            foreach ($attributes as $attribute) {
                $attributeName = strtolower($attribute[1] ?? '');
                if (in_array($attributeName, ['lang', 'xml:lang'], true)) {
                    $current = html_entity_decode($attribute[2] ?? $attribute[3] ?? $attribute[4] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if ($attributeName === 'lang') {
                        break;
                    }
                }
            }

            $stack[] = ['name' => $name, 'language' => $current];
        }

        $flush(true);

        return implode('', $parts);
    }
}
