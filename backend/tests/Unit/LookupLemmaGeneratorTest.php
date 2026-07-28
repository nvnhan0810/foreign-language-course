<?php

namespace Tests\Unit;

use Flc\Dictionary\Application\LookupLemmaGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LookupLemmaGeneratorTest extends TestCase
{
    private LookupLemmaGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->generator = new LookupLemmaGenerator();
    }

    #[DataProvider('lemmaExamples')]
    public function test_generates_expected_candidates(string $word, array $expected): void
    {
        $this->assertSame($expected, $this->generator->candidates($word));
    }

    public static function lemmaExamples(): array
    {
        return [
            'plural s' => ['outlets', ['outlet']],
            'plural es' => ['watches', ['watch', 'watche']],
            'ies to y' => ['studies', ['study', 'studie']],
            'exact unchanged not included' => ['happy', []],
            'phrase ignored' => ['run fast', []],
        ];
    }
}
