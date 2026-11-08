<?php

namespace Database\Seeders;

use App\Models\Lesson;
use Illuminate\Database\Seeder;

class QuizSeeder extends Seeder
{
    /**
     * lesson slug => list of [prompt, options (first is correct), explanation]
     *
     * @var array<string, list<array{0: string, 1: list<string>, 2: string}>>
     */
    public const QUIZZES = [
        'the-verb-to-be' => [
            ['She ___ a teacher.', ['is', 'are', 'am'], 'Use "is" with he, she and it.'],
            ['We ___ from Spain.', ['are', 'is', 'am'], 'Use "are" with we, you and they.'],
            ['I ___ hungry.', ['am', 'is', 'are'], '"I" always takes "am".'],
        ],
        'hello-greetings-and-introductions' => [
            ['Which is a formal greeting?', ['Good morning', 'Hey', 'What’s up?'], '"Good morning" works in any formal context.'],
            ['How do you answer "How are you?"', ['I’m fine, thanks', 'I’m 25', 'I’m from London'], 'It asks about how you feel.'],
        ],
        'present-simple-vs-present-continuous' => [
            ['Listen! The baby ___.', ['is crying', 'cries', 'cry'], '"Listen!" shows the action is happening now.'],
            ['Water ___ at 100 °C.', ['boils', 'is boiling', 'boil'], 'General truths use the present simple.'],
            ['I ___ to the gym every Monday.', ['go', 'am going', 'goes'], 'Routines use the present simple.'],
        ],
        'present-perfect-explained' => [
            ['I ___ to Japan twice.', ['have been', 'went', 'have go'], 'Life experience without a time reference.'],
            ['She ___ her keys. She can’t open the door.', ['has lost', 'lost', 'loses'], 'A past action with a present result.'],
            ['Have you ever ___ sushi?', ['eaten', 'ate', 'eat'], 'Use the past participle after "have".'],
        ],
    ];

    public function run(): void
    {
        foreach (self::QUIZZES as $slug => $questions) {
            $lesson = Lesson::where('slug', $slug)->first();

            if ($lesson === null || $lesson->questions()->exists()) {
                continue;
            }

            foreach ($questions as $position => [$prompt, $options, $explanation]) {
                $question = $lesson->questions()->create([
                    'prompt' => $prompt,
                    'explanation' => $explanation,
                    'position' => $position + 1,
                ]);

                // Shuffle deterministically so the correct answer is not always first.
                $order = array_keys($options);
                $order = [...array_slice($order, $position % count($order)), ...array_slice($order, 0, $position % count($order))];

                foreach ($order as $displayPosition => $optionIndex) {
                    $question->options()->create([
                        'text' => $options[$optionIndex],
                        'is_correct' => $optionIndex === 0,
                        'position' => $displayPosition,
                    ]);
                }
            }
        }
    }
}
