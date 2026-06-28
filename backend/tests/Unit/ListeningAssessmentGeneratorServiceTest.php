<?php

namespace Tests\Unit;

use App\Models\ListeningQuestion;
use App\Services\CursorAgentService;
use App\Services\ListeningAssessmentGeneratorService;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class ListeningAssessmentGeneratorServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_local_generator_does_not_use_placeholder_vocabulary_options(): void
    {
        $cursor = Mockery::mock(CursorAgentService::class);
        $cursor->shouldReceive('isConfigured')->andReturn(false);

        $service = new ListeningAssessmentGeneratorService($cursor);
        $method = new ReflectionMethod($service, 'generateLocally');
        $method->setAccessible(true);

        /** @var array<int, array<string, mixed>> $questions */
        $questions = $method->invoke(
            $service,
            'Being thoughtful means considering other people\'s feelings carefully. Thoughtful people listen before they speak.',
            'Thoughtfulness in conversation',
            'quiz',
            5,
            [
                'key_vocabulary' => [
                    ['word' => 'thoughtful', 'definition' => 'showing consideration for the needs of others'],
                    ['word' => 'considerate', 'definition' => 'careful not to inconvenience others'],
                ],
                'topics' => ['empathy and kindness'],
            ]
        );

        $this->assertNotEmpty($questions);

        foreach ($questions as $question) {
            $options = array_map('strtolower', $question['options'] ?? []);

            $this->assertNotContains('random', $options);
            $this->assertNotContains('unknown', $options);
            $this->assertNotContains('missing', $options);
        }

        $vocabularyQuestion = collect($questions)->first(
            fn (array $question) => str_contains($question['prompt'], 'matches this meaning')
        );

        $this->assertNotNull($vocabularyQuestion);
        $this->assertSame(ListeningQuestion::TYPE_MCQ, $vocabularyQuestion['question_type']);
        $this->assertSame('thoughtful', $vocabularyQuestion['correct_answer']);
        $this->assertStringContainsString('showing consideration', $vocabularyQuestion['prompt']);
        $this->assertStringNotContainsString('related to "thoughtful"', $vocabularyQuestion['prompt']);
    }
}
