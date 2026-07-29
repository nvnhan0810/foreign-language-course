<?php

namespace Tests\Unit;

use Flc\Puzzle\Domain\WordleKeyboardBuilder;
use PHPUnit\Framework\TestCase;

class WordleKeyboardBuilderTest extends TestCase
{
    public function test_build_includes_target_letters_and_decoys(): void
    {
        $keyboard = WordleKeyboardBuilder::build('happy', 4, 42);

        $this->assertSame(1, $keyboard['h']);
        $this->assertSame(1, $keyboard['a']);
        $this->assertSame(2, $keyboard['p']);
        $this->assertSame(1, $keyboard['y']);
        $this->assertGreaterThanOrEqual(5, count($keyboard));
        $this->assertLessThanOrEqual(9, count($keyboard));
    }

    public function test_build_is_deterministic_with_seed(): void
    {
        $first = WordleKeyboardBuilder::build('stare', 4, 99);
        $second = WordleKeyboardBuilder::build('stare', 4, 99);

        $this->assertSame($first, $second);
    }

    public function test_is_guess_allowed_respects_letter_counts(): void
    {
        $keyboard = WordleKeyboardBuilder::build('happy', 0, 1);

        $this->assertTrue(WordleKeyboardBuilder::isGuessAllowed('happy', $keyboard));
        $this->assertFalse(WordleKeyboardBuilder::isGuessAllowed('ppppp', $keyboard));
        $this->assertFalse(WordleKeyboardBuilder::isGuessAllowed('zebra', $keyboard));
    }
}
