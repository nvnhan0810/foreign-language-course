<?php

namespace Tests\Unit;

use Flc\Dictionary\Domain\DictionaryEntry;
use PHPUnit\Framework\TestCase;

class DictionaryEntryExamplesTest extends TestCase
{
    public function test_merge_examples_into_primary_meaning(): void
    {
        $meanings = [[
            'part_of_speech' => 'adjective',
            'definition' => 'Relating to punishment.',
            'examples' => ['Existing example.'],
            'synonyms' => [],
            'antonyms' => [],
        ]];

        $merged = DictionaryEntry::mergeExamplesIntoMeanings($meanings, [
            'A penal offence can lead to imprisonment.',
            'Existing example.',
        ]);

        $this->assertSame([
            'Existing example.',
            'A penal offence can lead to imprisonment.',
        ], $merged[0]['examples']);
    }

    public function test_merge_meanings_from_chat_merges_per_sense_fields(): void
    {
        $existing = [[
            'part_of_speech' => 'adjective',
            'definition' => 'Relating to punishment by law',
            'examples' => ['Old example.'],
            'synonyms' => ['punitive'],
            'antonyms' => [],
        ]];

        $incoming = [[
            'part_of_speech' => 'adjective',
            'definition' => 'Relating to punishment by law',
            'examples' => ['A penal offence can lead to imprisonment.'],
            'synonyms' => ['disciplinary'],
            'antonyms' => ['lenient'],
        ]];

        $merged = DictionaryEntry::mergeMeaningsFromChat($existing, $incoming);

        $this->assertCount(1, $merged);
        $this->assertSame([
            'Old example.',
            'A penal offence can lead to imprisonment.',
        ], $merged[0]['examples']);
        $this->assertSame(['punitive', 'disciplinary'], $merged[0]['synonyms']);
        $this->assertSame(['lenient'], $merged[0]['antonyms']);
    }
}
