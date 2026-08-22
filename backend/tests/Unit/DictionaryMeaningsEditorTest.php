<?php

namespace Tests\Unit;

use Flc\Dictionary\Application\DictionaryMeaningsEditor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class DictionaryMeaningsEditorTest extends TestCase
{
    public function test_should_parse_form_rows_into_domain_meanings(): void
    {
        $meanings = DictionaryMeaningsEditor::fromFormRows([
            [
                'part_of_speech' => 'adjective',
                'definition' => ' Feeling pleasure ',
                'examples_text' => "She is happy.\nThey look glad.",
                'synonyms_text' => 'joyful, glad',
                'antonyms_text' => 'sad; unhappy',
            ],
            [
                'part_of_speech' => '',
                'definition' => '   ',
                'examples_text' => '',
                'synonyms_text' => '',
                'antonyms_text' => '',
            ],
        ]);

        $this->assertCount(1, $meanings);
        $this->assertSame('adjective', $meanings[0]['part_of_speech']);
        $this->assertSame('Feeling pleasure', $meanings[0]['definition']);
        $this->assertSame(['She is happy.', 'They look glad.'], $meanings[0]['examples']);
        $this->assertSame(['joyful', 'glad'], $meanings[0]['synonyms']);
        $this->assertSame(['sad', 'unhappy'], $meanings[0]['antonyms']);
    }

    public function test_should_parse_json_array_into_domain_meanings(): void
    {
        $json = <<<'JSON'
[
  {
    "part_of_speech": "noun",
    "definition": "A greeting",
    "examples": ["Hello there!"],
    "synonyms": ["hi"],
    "antonyms": ["goodbye"]
  }
]
JSON;

        $meanings = DictionaryMeaningsEditor::fromJson($json);

        $this->assertCount(1, $meanings);
        $this->assertSame('noun', $meanings[0]['part_of_speech']);
        $this->assertSame('A greeting', $meanings[0]['definition']);
        $this->assertSame(['Hello there!'], $meanings[0]['examples']);
        $this->assertSame(['hi'], $meanings[0]['synonyms']);
        $this->assertSame(['goodbye'], $meanings[0]['antonyms']);
    }

    public function test_should_accept_wrapped_meanings_object_in_json(): void
    {
        $json = '{"meanings":[{"definition":"Only definition"}]}';

        $meanings = DictionaryMeaningsEditor::fromJson($json);

        $this->assertCount(1, $meanings);
        $this->assertSame('Only definition', $meanings[0]['definition']);
        $this->assertSame([], $meanings[0]['examples']);
    }

    public function test_should_reject_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JSON không hợp lệ');

        DictionaryMeaningsEditor::fromJson('{not-json');
    }

    public function test_should_reject_json_without_meanings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cần ít nhất một nghĩa có definition');

        DictionaryMeaningsEditor::fromJson('[]');
    }

    public function test_should_convert_domain_meanings_to_form_rows(): void
    {
        $rows = DictionaryMeaningsEditor::toFormRows([
            [
                'part_of_speech' => 'verb',
                'definition' => 'To greet',
                'examples' => ['Say hello.', 'Wave hello.'],
                'synonyms' => ['greet'],
                'antonyms' => [],
            ],
        ]);

        $this->assertSame([
            [
                'part_of_speech' => 'verb',
                'definition' => 'To greet',
                'examples_text' => "Say hello.\nWave hello.",
                'synonyms_text' => 'greet',
                'antonyms_text' => '',
            ],
        ], $rows);
    }

    public function test_should_build_pretty_json_from_meanings(): void
    {
        $json = DictionaryMeaningsEditor::toPrettyJson([
            [
                'part_of_speech' => 'noun',
                'definition' => 'A greeting',
                'examples' => ['Hi!'],
                'synonyms' => [],
                'antonyms' => [],
            ],
        ]);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertSame('A greeting', $decoded[0]['definition']);
        $this->assertStringContainsString("\n", $json);
    }

    public function test_should_include_word_and_schema_in_ai_prompt(): void
    {
        $prompt = DictionaryMeaningsEditor::aiPrompt('happy');

        $this->assertStringContainsString('happy', $prompt);
        $this->assertStringContainsString('"part_of_speech"', $prompt);
        $this->assertStringContainsString('"definition"', $prompt);
        $this->assertStringContainsString('"examples"', $prompt);
        $this->assertStringContainsString('"synonyms"', $prompt);
        $this->assertStringContainsString('"antonyms"', $prompt);
        $this->assertStringContainsString('JSON array', $prompt);
    }
}
