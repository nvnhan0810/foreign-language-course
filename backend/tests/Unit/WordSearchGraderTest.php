<?php

namespace Tests\Unit;

use Flc\Puzzle\Domain\WordSearchGrader;
use PHPUnit\Framework\TestCase;

class WordSearchGraderTest extends TestCase
{
    public function test_eligibility_rules(): void
    {
        $this->assertTrue(WordSearchGrader::isEligibleWord('groom'));
        $this->assertFalse(WordSearchGrader::isEligibleWord('to'));
        $this->assertFalse(WordSearchGrader::isEligibleWord('ice-cream'));
        $this->assertFalse(WordSearchGrader::isEligibleWord('supercalifrag'));
    }

    public function test_builds_grid_with_all_words(): void
    {
        $puzzle = WordSearchGrader::build([
            ['vocabulary_id' => 1, 'word' => 'cat'],
            ['vocabulary_id' => 2, 'word' => 'dog'],
            ['vocabulary_id' => 3, 'word' => 'bird'],
            ['vocabulary_id' => 4, 'word' => 'fish'],
        ]);

        $this->assertNotNull($puzzle);
        $this->assertSame(WordSearchGrader::GRID_SIZE, $puzzle['grid_size']);
        $this->assertCount(4, $puzzle['words']);
        $this->assertCount(4, $puzzle['placements']);
        $this->assertCount(WordSearchGrader::GRID_SIZE, $puzzle['grid']);

        foreach ($puzzle['placements'] as $placement) {
            $spelled = '';
            foreach ($placement['cells'] as $cell) {
                $spelled .= $puzzle['grid'][$cell['r']][$cell['c']];
            }
            $this->assertSame($placement['word'], $spelled);
        }
    }

    public function test_apply_find_hits_and_finishes(): void
    {
        $puzzle = WordSearchGrader::build([
            ['vocabulary_id' => 1, 'word' => 'cat'],
            ['vocabulary_id' => 2, 'word' => 'dog'],
            ['vocabulary_id' => 3, 'word' => 'bird'],
            ['vocabulary_id' => 4, 'word' => 'fish'],
        ]);
        $this->assertNotNull($puzzle);

        $found = [];
        $last = null;
        foreach ($puzzle['placements'] as $placement) {
            $last = WordSearchGrader::applyFind($puzzle['placements'], $found, $placement['cells']);
            $this->assertNotNull($last);
            $this->assertTrue($last['hit']);
            $this->assertSame($placement['vocabulary_id'], $last['vocabulary_id']);
            $found = $last['found_ids'];
        }

        $this->assertTrue($last['finished']);
        $this->assertTrue($last['won']);
        $this->assertCount(4, $last['found_ids']);
    }

    public function test_apply_find_accepts_reverse_path(): void
    {
        $puzzle = WordSearchGrader::build([
            ['vocabulary_id' => 1, 'word' => 'cat'],
            ['vocabulary_id' => 2, 'word' => 'dog'],
            ['vocabulary_id' => 3, 'word' => 'bird'],
            ['vocabulary_id' => 4, 'word' => 'fish'],
        ]);
        $this->assertNotNull($puzzle);

        $placement = $puzzle['placements'][0];
        $reverse = array_reverse($placement['cells']);
        $result = WordSearchGrader::applyFind($puzzle['placements'], [], $reverse);

        $this->assertNotNull($result);
        $this->assertTrue($result['hit']);
        $this->assertSame($placement['vocabulary_id'], $result['vocabulary_id']);
    }

    public function test_rejects_non_straight_path(): void
    {
        $this->assertFalse(WordSearchGrader::isStraightPath([
            ['r' => 0, 'c' => 0],
            ['r' => 0, 'c' => 1],
            ['r' => 1, 'c' => 2],
        ]));
    }

    public function test_path_spells_word_on_grid(): void
    {
        $grid = [
            ['c', 'a', 't', 'x'],
            ['d', 'o', 'g', 'y'],
            ['b', 'i', 'r', 'd'],
            ['f', 'i', 's', 'h'],
        ];

        $this->assertTrue(WordSearchGrader::pathSpellsWord(
            $grid,
            [['r' => 0, 'c' => 0], ['r' => 0, 'c' => 1], ['r' => 0, 'c' => 2]],
            'cat',
        ));
        $this->assertTrue(WordSearchGrader::pathSpellsWord(
            $grid,
            [['r' => 0, 'c' => 2], ['r' => 0, 'c' => 1], ['r' => 0, 'c' => 0]],
            'cat',
        ));
        $this->assertFalse(WordSearchGrader::pathSpellsWord(
            $grid,
            [['r' => 0, 'c' => 0], ['r' => 0, 'c' => 1], ['r' => 0, 'c' => 2]],
            'dog',
        ));
    }

    public function test_pick_hint_cell_from_unfound_words(): void
    {
        $puzzle = WordSearchGrader::build([
            ['vocabulary_id' => 1, 'word' => 'cat'],
            ['vocabulary_id' => 2, 'word' => 'dog'],
            ['vocabulary_id' => 3, 'word' => 'bird'],
            ['vocabulary_id' => 4, 'word' => 'fish'],
        ]);
        $this->assertNotNull($puzzle);

        $hint = WordSearchGrader::pickHintCell($puzzle['placements'], [1]);
        $this->assertNotNull($hint);
        $this->assertArrayHasKey('r', $hint);
        $this->assertArrayHasKey('c', $hint);
        $this->assertArrayHasKey('vocabulary_id', $hint);
        $this->assertNotSame(1, $hint['vocabulary_id']);

        $this->assertNull(WordSearchGrader::pickHintCell($puzzle['placements'], [1, 2, 3, 4]));
    }

    public function test_pick_hint_cell_prefers_different_letter_on_same_word(): void
    {
        $placements = [[
            'vocabulary_id' => 10,
            'word' => 'cat',
            'cells' => [
                ['r' => 0, 'c' => 0],
                ['r' => 0, 'c' => 1],
                ['r' => 0, 'c' => 2],
            ],
        ]];

        $first = WordSearchGrader::pickHintCell($placements, []);
        $this->assertNotNull($first);
        $this->assertSame(10, $first['vocabulary_id']);

        $second = WordSearchGrader::pickHintCell(
            $placements,
            [],
            10,
            [['r' => $first['r'], 'c' => $first['c']]],
        );
        $this->assertNotNull($second);
        $this->assertSame(10, $second['vocabulary_id']);
        $this->assertFalse(
            $first['r'] === $second['r'] && $first['c'] === $second['c'],
            'Second hint should highlight a different cell on the same word.',
        );
    }
}
