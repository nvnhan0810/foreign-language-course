<?php

namespace Tests\Unit;

use Flc\WordChat\Application\WordChatExampleExtractor;
use PHPUnit\Framework\TestCase;

class WordChatExampleExtractorTest extends TestCase
{
    public function test_extracts_lines_from_more_examples_section(): void
    {
        $extractor = new WordChatExampleExtractor();

        $examples = $extractor->extractFromText(<<<'TEXT'
Penal means connected with punishment under the law.

More examples:

Penal code — The country's penal code lists crimes and their punishments.
Penal colony — In the past, some countries sent prisoners to a penal colony.
Penal system — Reform of the penal system aims to reduce crime, not only punish it.

Tip: use penal for legal punishment.
TEXT);

        $this->assertCount(3, $examples);
        $this->assertStringContainsString('penal code lists crimes', $examples[0]);
        $this->assertStringContainsString('penal colony', $examples[1]);
        $this->assertStringContainsString('penal system aims', $examples[2]);
    }
}
