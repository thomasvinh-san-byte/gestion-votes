<?php

declare(strict_types=1);

namespace Tests\Unit;

use League\CommonMark\CommonMarkConverter;
use PHPUnit\Framework\TestCase;

/**
 * Confirms league/commonmark renders the markdown surfaces we used to feed
 * to Parsedown without behaviour drift.
 *
 * The doc viewer (DocController::view) emits the result straight into the
 * page, so the safe-mode contract matters: raw HTML in source markdown must
 * be escaped, and `javascript:` / `data:` hrefs must be neutralized.
 *
 * Refs: M-INFRA-CLEANUP / CLEANUP-INFRA-PARSEDOWN.
 */
final class CommonMarkRenderingTest extends TestCase {
    private CommonMarkConverter $converter;

    protected function setUp(): void {
        $this->converter = new CommonMarkConverter([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }

    public function testRendersHeadingsAndParagraphs(): void {
        $html = (string) $this->converter->convert("# Titre\n\nUn paragraphe.");
        $this->assertStringContainsString('<h1>Titre</h1>', $html);
        $this->assertStringContainsString('<p>Un paragraphe.</p>', $html);
    }

    public function testRendersBoldItalicAndCode(): void {
        $html = (string) $this->converter->convert("**gras** *italique* `code`");
        $this->assertStringContainsString('<strong>gras</strong>', $html);
        $this->assertStringContainsString('<em>italique</em>', $html);
        $this->assertStringContainsString('<code>code</code>', $html);
    }

    public function testRendersUnorderedListAndLinks(): void {
        $md = "- un\n- [lien](https://example.org)\n";
        $html = (string) $this->converter->convert($md);
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<a href="https://example.org">lien</a>', $html);
    }

    public function testEscapesRawHtmlInSource(): void {
        // html_input=escape: an injected <script> must be rendered as text,
        // not executed.
        $html = (string) $this->converter->convert('<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testRejectsUnsafeLinkSchemes(): void {
        $html = (string) $this->converter->convert('[xss](javascript:alert(1))');
        // CommonMark drops the href entirely when the scheme is forbidden.
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testHandlesUtf8Accents(): void {
        $html = (string) $this->converter->convert("# Évènement\n\nÇa marche.");
        $this->assertStringContainsString('Évènement', $html);
        $this->assertStringContainsString('Ça marche', $html);
    }
}
