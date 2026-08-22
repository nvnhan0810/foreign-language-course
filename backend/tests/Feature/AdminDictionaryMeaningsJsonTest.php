<?php

namespace Tests\Feature;

use Flc\Dictionary\Application\DictionaryMeaningsEditor;
use Tests\TestCase;

class AdminDictionaryMeaningsJsonTest extends TestCase
{
    public function test_edit_form_exposes_json_editor_controls(): void
    {
        $this->withViewErrors([]);

        $html = view('admin.dictionary.form', [
            'entry' => null,
            'formMeanings' => [[
                'part_of_speech' => 'adjective',
                'definition' => 'Feeling pleasure',
                'examples_text' => 'She is happy.',
                'synonyms_text' => 'glad',
                'antonyms_text' => 'sad',
            ]],
            'meaningsJson' => DictionaryMeaningsEditor::toPrettyJson([
                [
                    'part_of_speech' => 'adjective',
                    'definition' => 'Feeling pleasure',
                    'examples' => ['She is happy.'],
                    'synonyms' => ['glad'],
                    'antonyms' => ['sad'],
                ],
            ]),
            'meaningsEditor' => DictionaryMeaningsEditor::MODE_FORM,
            'meaningsAiPrompt' => DictionaryMeaningsEditor::aiPrompt('happy'),
            'entrySynonymsText' => '',
            'entryAntonymsText' => '',
        ])->render();

        $this->assertStringContainsString('data-meanings-mode="form"', $html);
        $this->assertStringContainsString('data-meanings-mode="json"', $html);
        $this->assertStringContainsString('id="meanings-json"', $html);
        $this->assertStringContainsString('id="copy-meanings-prompt"', $html);
        $this->assertStringContainsString('Feeling pleasure', $html);
        $this->assertStringContainsString('Copy prompt AI', $html);
    }

    public function test_json_editor_mode_keeps_textarea_visible_in_markup(): void
    {
        $this->withViewErrors([]);

        $html = view('admin.dictionary.form', [
            'entry' => null,
            'formMeanings' => [[
                'part_of_speech' => '',
                'definition' => 'A greeting',
                'examples_text' => '',
                'synonyms_text' => '',
                'antonyms_text' => '',
            ]],
            'meaningsJson' => "[\n  {\n    \"definition\": \"A greeting\"\n  }\n]",
            'meaningsEditor' => DictionaryMeaningsEditor::MODE_JSON,
            'meaningsAiPrompt' => DictionaryMeaningsEditor::aiPrompt('hello'),
            'entrySynonymsText' => '',
            'entryAntonymsText' => '',
        ])->render();

        $this->assertStringContainsString('id="meanings-list" class="is-hidden"', $html);
        $this->assertStringContainsString('meanings-json-editor', $html);
        $this->assertStringNotContainsString('meanings-json-editor is-hidden', $html);
    }
}
