<?php

namespace App\Services;

use App\Models\Media;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Validation\ValidationException;

/**
 * Allowlist sanitizer for article bodies. The same instance is used for the
 * admin preview and for the stored version, so what is previewed is what is published.
 */
class BlogHtmlSanitizer
{
    public const MAX_INPUT_BYTES = 300_000;

    /** @var array<int, string> */
    private const ELEMENTS = [
        'p', 'br', 'hr', 'h2', 'h3', 'h4', 'h5', 'h6', 'div',
        'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'mark', 'small', 'sub', 'sup',
        'abbr', 'code', 'pre', 'kbd', 'blockquote', 'q', 'cite',
        'ul', 'ol', 'li', 'dl', 'dt', 'dd', 'a', 'img', 'figure', 'figcaption',
        'table', 'caption', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
    ];

    /** Elements removed together with everything inside them. */
    private const DROPPED = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form',
        'input', 'button', 'select', 'textarea', 'option', 'svg', 'math', 'template', 'noscript',
        'link', 'meta', 'base', 'title', 'head', 'video', 'audio', 'source', 'track', 'canvas',
        'portal', 'dialog', 'slot',
    ];

    /** @var array<string, array<int, string>> */
    private const ATTRIBUTES = [
        '*' => ['id', 'title'],
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'decoding'],
        'th' => ['colspan', 'rowspan', 'scope'],
        'td' => ['colspan', 'rowspan'],
        'ol' => ['start'],
    ];

    private const PROTOCOLS = ['http', 'https', 'mailto', 'tel'];

    /** @var array<int, string> */
    private array $removed = [];

    /** @var array<string, Media|null> */
    private array $mediaByUuid = [];

    /** @var array<int, int> */
    private array $mediaIds = [];

    private int $nodeCount = 0;

    /** @return array{html: string, media_ids: array<int, int>, removed: array<int, string>} */
    public function sanitize(string $html): array
    {
        if (strlen($html) > self::MAX_INPUT_BYTES) {
            throw ValidationException::withMessages(['body' => 'O corpo do artigo é muito longo. Divida o conteúdo em mais de um artigo.']);
        }
        $this->removed = [];
        $this->mediaIds = [];
        $this->nodeCount = 0;

        if (trim($html) === '') {
            return ['html' => '', 'media_ids' => [], 'removed' => []];
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->loadHTML(
                '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'.$html.'</body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT,
            );
            $body = $document->getElementsByTagName('body')->item(0);
            if (! $body) {
                return ['html' => '', 'media_ids' => [], 'removed' => []];
            }
            $this->cleanChildren($body, 0);

            $output = '';
            foreach ($body->childNodes as $child) {
                $output .= $document->saveHTML($child);
            }

            return [
                'html' => trim($output),
                'media_ids' => array_values(array_unique($this->mediaIds)),
                'removed' => array_values(array_unique($this->removed)),
            ];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** Text content length, used to reject bodies that become empty after sanitization. */
    public static function hasContent(string $html): bool
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '' || str_contains($html, '<img');
    }

    private function cleanChildren(DOMNode $parent, int $depth): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (++$this->nodeCount > 20000 || $depth > 60) {
                throw ValidationException::withMessages(['body' => 'O HTML tem estrutura muito profunda ou extensa. Simplifique a formatação.']);
            }
            if ($child instanceof DOMElement) {
                $this->cleanElement($child, $depth);
            } elseif ($child instanceof DOMText) {
                if ($child->nodeType !== XML_TEXT_NODE) {
                    $parent->replaceChild($parent->ownerDocument->createTextNode($child->textContent), $child);
                }
            } else {
                $parent->removeChild($child);
            }
        }
    }

    private function cleanElement(DOMElement $element, int $depth): void
    {
        $tag = strtolower($element->localName ?? $element->tagName);
        $parent = $element->parentNode;

        if (in_array($tag, self::DROPPED, true)) {
            $this->removed[] = '<'.$tag.'>';
            $parent?->removeChild($element);

            return;
        }

        if ($tag === 'h1') {
            $element = $this->rename($element, 'h2');
            $tag = 'h2';
        }

        if (! in_array($tag, self::ELEMENTS, true)) {
            if ($tag !== 'span' && $tag !== 'picture') {
                $this->removed[] = '<'.$tag.'>';
            }
            $this->cleanChildren($element, $depth + 1);
            while ($element->firstChild) {
                $parent?->insertBefore($element->firstChild, $element);
            }
            $parent?->removeChild($element);

            return;
        }

        $this->cleanAttributes($element, $tag);

        if ($tag === 'img' && ! $this->cleanImage($element)) {
            $parent?->removeChild($element);

            return;
        }
        if ($tag === 'a') {
            $this->cleanLink($element);
        }

        $this->cleanChildren($element, $depth + 1);
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = [...self::ATTRIBUTES['*'], ...(self::ATTRIBUTES[$tag] ?? [])];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = $attribute->value;
            $keep = in_array($name, $allowed, true) && ! $attribute->namespaceURI && match ($name) {
                'id' => (bool) preg_match('/^[A-Za-z][\w-]{0,63}$/D', $value),
                'title' => mb_strlen($value) <= 200,
                'colspan', 'rowspan' => (bool) preg_match('/^[1-9]\d?$/D', $value),
                'width', 'height' => (bool) preg_match('/^[1-9]\d{0,3}$/D', $value),
                'start' => (bool) preg_match('/^-?\d{1,5}$/D', $value),
                'scope' => in_array($value, ['row', 'col', 'rowgroup', 'colgroup'], true),
                'target' => $value === '_blank',
                'rel' => true,
                'loading' => in_array($value, ['lazy', 'eager'], true),
                'decoding' => in_array($value, ['async', 'sync', 'auto'], true),
                'alt' => mb_strlen($value) <= 500,
                default => true,
            };

            if (! $keep) {
                $this->removed[] = str_starts_with($name, 'on') ? 'atributo de evento ('.$name.')' : 'atributo '.$name;
                $element->removeAttribute($attribute->name);
            }
        }
    }

    private function cleanLink(DOMElement $link): void
    {
        $href = $link->getAttribute('href');
        $url = $href === '' ? null : $this->safeUrl($href);
        if ($href !== '' && $url === null) {
            $this->removed[] = 'link com endereço não permitido';
        }

        foreach (['href', 'target', 'rel'] as $attribute) {
            $link->removeAttribute($attribute);
        }
        if ($url === null) {
            return;
        }
        $link->setAttribute('href', $url);

        $host = parse_url($url, PHP_URL_HOST);
        $siteHosts = array_filter([parse_url((string) config('site.url'), PHP_URL_HOST), parse_url((string) config('app.url'), PHP_URL_HOST)]);
        if (is_string($host) && ! in_array(strtolower($host), array_map('strtolower', $siteHosts), true)) {
            $link->setAttribute('target', '_blank');
            $link->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function cleanImage(DOMElement $image): bool
    {
        $media = $this->libraryMedia($image->getAttribute('src'));
        if (! $media) {
            $this->removed[] = 'imagem fora da biblioteca de mídia';

            return false;
        }

        $this->mediaIds[] = $media->id;
        $image->setAttribute('src', (string) $media->deliveryUrl());
        if (! $image->hasAttribute('alt')) {
            $image->setAttribute('alt', $media->alt_is_custom && filled($media->alt_text) ? $media->alt_text : '');
        }
        if ($media->width && $media->height) {
            $image->setAttribute('width', (string) $media->width);
            $image->setAttribute('height', (string) $media->height);
        }
        $image->setAttribute('loading', 'lazy');
        $image->setAttribute('decoding', 'async');

        return true;
    }

    private function libraryMedia(string $src): ?Media
    {
        $src = trim($src);
        if (! preg_match('#^(?:(https?)://([^/?\#]+))?/api/media/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})$#iD', $src, $matches)) {
            return null;
        }
        if ($matches[2] !== '' && strcasecmp($matches[2], $this->apiAuthority()) !== 0) {
            return null;
        }
        $uuid = strtolower($matches[3]);

        return $this->mediaByUuid[$uuid] ??= Media::query()
            ->where('uuid', $uuid)->where('status', 'ready')->where('visibility', 'public')->first();
    }

    private function apiAuthority(): string
    {
        $url = (string) config('app.url');
        $port = parse_url($url, PHP_URL_PORT);

        return (string) parse_url($url, PHP_URL_HOST).($port ? ':'.$port : '');
    }

    private function safeUrl(string $value): ?string
    {
        $value = trim($value);
        $compact = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $value);
        if ($compact === '' || str_contains($compact, '\\') || str_starts_with($compact, '//') || mb_strlen($value) > 2048) {
            return null;
        }
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $compact, $matches)) {
            return in_array(strtolower($matches[1]), self::PROTOCOLS, true) ? $compact : null;
        }

        return str_contains(explode('/', $compact)[0], ':') ? null : $value;
    }

    private function rename(DOMElement $element, string $tag): DOMElement
    {
        $replacement = $element->ownerDocument->createElement($tag);
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $replacement->setAttribute($attribute->name, $attribute->value);
        }
        while ($element->firstChild) {
            $replacement->appendChild($element->firstChild);
        }
        $element->parentNode?->replaceChild($replacement, $element);

        return $replacement;
    }
}
