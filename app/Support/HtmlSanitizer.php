<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class HtmlSanitizer
{
    /** Tags the instructions editor can produce. All attributes are dropped. */
    private const ALLOWED = ['p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li'];

    /** Tags removed along with their content; other unknown tags keep their text. */
    private const REMOVED = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'head', 'title', 'meta', 'link'];

    /**
     * Reduce HTML to the allowed formatting tags. Returns null when nothing visible remains.
     */
    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="root">' . $html . '</div></body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('root');
        if (!$root) {
            return null;
        }

        self::cleanChildren($root);

        if (trim($root->textContent, " \t\n\r\0\x0B\u{A0}") === '') {
            return null;
        }

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof DOMElement) {
                $tag = strtolower($node->tagName);

                if (in_array($tag, self::REMOVED, true)) {
                    $parent->removeChild($node);
                    continue;
                }

                self::cleanChildren($node);

                if (!in_array($tag, self::ALLOWED, true)) {
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                    continue;
                }

                foreach (iterator_to_array($node->attributes) as $attribute) {
                    $node->removeAttributeNode($attribute);
                }
            } elseif ($node->nodeType !== XML_TEXT_NODE) {
                $parent->removeChild($node);
            }
        }
    }
}
