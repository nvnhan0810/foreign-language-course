<?php

namespace Tests\Unit;

use Flc\Puzzle\Domain\HangmanGrader;
use PHPUnit\Framework\TestCase;

class HangmanGraderTest extends TestCase
{
    public function test_masks_unguessed_letters(): void
    {
        $this->assertSame(
            [null, null, null, null, null],
            HangmanGrader::mask('groom', []),
        );

        $this->assertSame(
            ['g', null, 'o', 'o', null],
            HangmanGrader::mask('groom', ['g', 'o']),
        );
    }

    public function test_apply_guess_tracks_hits_and_misses(): void
    {
        $first = HangmanGrader::applyGuess('penal', [], 'p');
        $this->assertNotNull($first);
        $this->assertTrue($first['hit']);
        $this->assertSame(0, $first['wrong_count']);
        $this->assertFalse($first['finished']);

        $miss = HangmanGrader::applyGuess('penal', $first['guessed_letters'], 'z');
        $this->assertNotNull($miss);
        $this->assertFalse($miss['hit']);
        $this->assertSame(1, $miss['wrong_count']);
    }

    public function test_rejects_duplicate_or_invalid_letters(): void
    {
        $this->assertNull(HangmanGrader::applyGuess('penal', ['p'], 'p'));
        $this->assertNull(HangmanGrader::applyGuess('penal', [], '1'));
    }

    public function test_loses_after_max_wrong_guesses(): void
    {
        $guessed = [];
        foreach (['z', 'q', 'x', 'y', 'v', 'w'] as $letter) {
            $result = HangmanGrader::applyGuess('alpha', $guessed, $letter);
            $this->assertNotNull($result);
            $guessed = $result['guessed_letters'];
        }

        $this->assertTrue($result['lost']);
        $this->assertTrue($result['finished']);
        $this->assertFalse($result['won']);
        $this->assertSame(HangmanGrader::MAX_WRONG, $result['wrong_count']);
    }

    public function test_wins_when_all_letters_revealed(): void
    {
        $guessed = [];
        foreach (['c', 'a', 't'] as $letter) {
            $result = HangmanGrader::applyGuess('cat', $guessed, $letter);
            $this->assertNotNull($result);
            $guessed = $result['guessed_letters'];
        }

        $this->assertTrue($result['won']);
        $this->assertTrue($result['finished']);
        $this->assertSame(['c', 'a', 't'], $result['mask']);
    }

    public function test_eligibility_rules(): void
    {
        $this->assertTrue(HangmanGrader::isEligibleWord('groom'));
        $this->assertFalse(HangmanGrader::isEligibleWord('to'));
        $this->assertFalse(HangmanGrader::isEligibleWord('ice-cream'));
        $this->assertFalse(HangmanGrader::isEligibleWord('supercalifrag'));
    }
}
