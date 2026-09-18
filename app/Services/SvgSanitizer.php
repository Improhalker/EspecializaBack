<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Validation\ValidationException;

class SvgSanitizer
{
    private const ELEMENTS = [
        'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'defs', 'linearGradient', 'radialGradient', 'stop', 'clipPath', 'title', 'desc',
    ];

    private const ATTRIBUTES = [
        'id', 'viewBox', 'width', 'height', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'cx', 'cy',
        'r', 'rx', 'ry', 'd', 'points', 'transform', 'fill', 'fill-opacity', 'fill-rule',
        'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit',
        'stroke-dasharray', 'stroke-dashoffset', 'stroke-opacity', 'opacity', 'clip-path',
        'clip-rule', 'offset', 'stop-color', 'stop-opacity', 'gradientUnits',
        'gradientTransform', 'spreadMethod', 'fx', 'fy', 'fr', 'preserveAspectRatio',
        'version', 'clipPathUnits',
    ];

    /** @return array{content: string, width: int, height: int, original_width: int, original_height: int} */
    public function process(string $content): array
    {
        if (strlen($content) > config('media.max_svg_kb') * 1024 ||
            preg_match('/<!DOCTYPE|<!ENTITY|<\?(?!xml\s)/i', $content)) {
            $this->reject();
        }

        $previous = libxml_use_internal_errors(true);
        try {
            $document = new DOMDocument;
            $document->resolveExternals = false;
            $document->substituteEntities = false;
            $valid = $document->loadXML($content, LIBXML_NONET | LIBXML_NOBLANKS);
            if (! $valid || $document->doctype || ! $document->documentElement) {
                $this->reject();
            }
            $root = $document->documentElement;
            if ($root->localName !== 'svg' || $root->namespaceURI !== 'http://www.w3.org/2000/svg') {
                $this->reject();
            }
            $count = 0;
            $this->inspect($root, $count, 0);
            $width = $this->dimension($root->getAttribute('width'));
            $height = $this->dimension($root->getAttribute('height'));
            $viewBox = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox')));
            if ((! $width || ! $height) && count($viewBox) === 4 &&
                count(array_filter($viewBox, 'is_numeric')) === 4) {
                $width = (float) $viewBox[2];
                $height = (float) $viewBox[3];
            }
            if ($width <= 0 || $height <= 0 || ! is_finite($width) || ! is_finite($height) ||
                $width > 100000 || $height > 100000) {
                $this->reject();
            }
            $originalWidth = (int) ceil($width);
            $originalHeight = (int) ceil($height);
            $scale = min(1, config('media.max_dimension') / max($width, $height));
            if (! $root->hasAttribute('viewBox')) {
                $root->setAttribute('viewBox', '0 0 '.$width.' '.$height);
            }
            $width = max(1, (int) round($width * $scale));
            $height = max(1, (int) round($height * $scale));
            $root->setAttribute('width', (string) $width);
            $root->setAttribute('height', (string) $height);

            return ['content' => $document->saveXML($root), 'width' => $width, 'height' => $height,
                'original_width' => $originalWidth, 'original_height' => $originalHeight];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function inspect(DOMNode $node, int &$count, int $depth): void
    {
        if (++$count > 5000 || $depth > 40) {
            $this->reject();
        }
        if ($node instanceof DOMElement) {
            if ($node->namespaceURI !== 'http://www.w3.org/2000/svg' ||
                ! in_array($node->tagName, self::ELEMENTS, true)) {
                $this->reject();
            }
            foreach ($node->attributes as $attribute) {
                if (! in_array($attribute->name, self::ATTRIBUTES, true) ||
                    $attribute->namespaceURI ||
                    preg_match('/[<>\\\\]|(?:javascript|data|https?|file):|@|expression\s*\(/i', $attribute->value)) {
                    $this->reject();
                }
                if (stripos($attribute->value, 'url') !== false &&
                    ! preg_match('/^url\(#[a-zA-Z_][\w.-]*\)$/D', $attribute->value)) {
                    $this->reject();
                }
            }
        } elseif (! in_array($node->nodeType, [XML_TEXT_NODE, XML_COMMENT_NODE], true)) {
            $this->reject();
        }
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);
            } else {
                $this->inspect($child, $count, $depth + 1);
            }
        }
    }

    private function dimension(string $value): float
    {
        return preg_match('/^([0-9]+(?:\.[0-9]+)?)(?:px)?$/D', $value, $matches) ? (float) $matches[1] : 0;
    }

    private function reject(): never
    {
        throw ValidationException::withMessages([
            'file' => 'SVG inválido ou inseguro. Envie um SVG estático com dimensões, sem scripts, estilos, links ou conteúdo externo.',
        ]);
    }
}
