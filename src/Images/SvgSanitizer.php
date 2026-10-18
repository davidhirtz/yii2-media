<?php

declare(strict_types=1);

namespace Hirtz\Media\Images;

use Override;
use Rhukster\DomSanitizer\DOMSanitizer;

class SvgSanitizer extends DOMSanitizer
{
    public function __construct()
    {
        parent::__construct(self::SVG);

        // Icons reference their own symbols, so `<use>` stays while its href may only point inside the file
        $this->addAllowedTags(['use']);
        $this->disallowed_tags = array_values(array_diff($this->disallowed_tags, ['use']));
    }

    /**
     * Rewrites the file in place and answers whether an SVG is left of it.
     */
    public function sanitizeFile(string $path): bool
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            return false;
        }

        // The declaration names the encoding the markup is saved in
        $svg = $this->sanitize($contents, ['remove-xml-tags' => false]);

        if (!preg_match('/^(<\?xml[^>]*>\s*)?<svg[\s>]/', $svg)) {
            return false;
        }

        return @file_put_contents($path, $svg) !== false;
    }

    #[Override]
    protected function isDangerousUrl(string $attr_name, string $attr_value): bool
    {
        if (in_array(strtolower($attr_name), ['href', 'xlink:href'], true)) {
            return !preg_match('/^(#|data:image\/(png|jpe?g|gif|webp)[;,])/i', trim($attr_value));
        }

        return parent::isDangerousUrl($attr_name, $attr_value);
    }
}
