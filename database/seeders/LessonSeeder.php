<?php

namespace Database\Seeders;

use App\Enums\LessonStatus;
use App\Models\Category;
use App\Models\Lesson;
use App\Models\Level;
use App\Models\User;
use Illuminate\Database\Seeder;

class LessonSeeder extends Seeder
{
    /**
     * Demo catalogue: level => [title, summary, categories, is_free].
     *
     * @var array<string, list<array{0: string, 1: string, 2: list<string>, 3: bool}>>
     */
    public const LESSONS = [
        'A1' => [
            ['Hello! Greetings and introductions', 'Say hello, introduce yourself and ask basic personal questions.', ['Speaking', 'Vocabulary'], true],
            ['The verb "to be"', 'Learn am, is and are with dozens of everyday examples.', ['Grammar'], true],
            ['Numbers, dates and time', 'Count, tell the time and talk about dates with confidence.', ['Vocabulary'], false],
            ['My family', 'Describe your family members and talk about relationships.', ['Vocabulary', 'Speaking'], false],
            ['At the café', 'Order food and drinks politely in a café.', ['Travel', 'Speaking'], false],
        ],
        'A2' => [
            ['Present simple vs present continuous', 'Know when to use each tense with clear rules and practice.', ['Grammar'], true],
            ['Shopping for clothes', 'Ask for sizes, prices and colours in a shop.', ['Travel', 'Vocabulary'], false],
            ['Talking about the past', 'Use the past simple to tell short stories about your weekend.', ['Grammar', 'Speaking'], false],
            ['Asking for directions', 'Find your way around a new city.', ['Travel', 'Listening'], false],
            ['Writing a short email', 'Write friendly emails to invite, thank and apologise.', ['Writing'], false],
        ],
        'B1' => [
            ['Present perfect explained', 'Experience, unfinished time and recent news with the present perfect.', ['Grammar'], true],
            ['Job interviews', 'Answer common interview questions and talk about your experience.', ['Business English', 'Speaking'], false],
            ['Phrasal verbs for daily life', 'The 30 phrasal verbs you will hear every day.', ['Vocabulary'], false],
            ['Listening: a travel podcast', 'Understand the main ideas of an authentic travel podcast.', ['Listening', 'Travel'], false],
            ['First and second conditionals', 'Talk about real and imaginary situations.', ['Grammar'], false],
        ],
        'B2' => [
            ['Mastering the passive voice', 'Use passive structures naturally in speech and writing.', ['Grammar', 'Writing'], true],
            ['Running effective meetings', 'Open, lead and close a meeting in English.', ['Business English'], false],
            ['Opinion essays', 'Structure an argument and support it with evidence.', ['Writing'], false],
            ['Idioms that natives really use', 'Sound natural with common idiomatic expressions.', ['Vocabulary', 'Speaking'], false],
            ['Reading: news articles', 'Strategies to understand news articles quickly.', ['Reading'], false],
        ],
        'C1' => [
            ['Advanced linking words', 'Connect complex ideas with cohesive devices.', ['Writing', 'Grammar'], true],
            ['Negotiation skills', 'Persuade, concede and close deals.', ['Business English', 'Speaking'], false],
            ['Inversion for emphasis', 'Never have you sounded so fluent: inversion explained.', ['Grammar'], false],
            ['Listening: TED-style talks', 'Follow long talks with complex arguments.', ['Listening'], false],
            ['Academic vocabulary', 'The academic word list in context.', ['Vocabulary', 'Reading'], false],
        ],
        'C2' => [
            ['Nuance and register', 'Choose the right word for the right situation.', ['Vocabulary', 'Writing'], true],
            ['Literary reading', 'Analyse tone and style in literary extracts.', ['Reading'], false],
            ['Debate like a native', 'Rebut arguments with precision and style.', ['Speaking'], false],
            ['Humour and wordplay', 'Understand puns, irony and British humour.', ['Vocabulary', 'Listening'], false],
            ['Professional reports', 'Write clear, concise and persuasive reports.', ['Business English', 'Writing'], false],
        ],
    ];

    public function run(): void
    {
        $author = User::where('email', 'editor@blackpool.test')->first();
        $categories = Category::pluck('id', 'name');

        foreach (self::LESSONS as $code => $lessons) {
            $level = Level::where('code', $code)->firstOrFail();

            foreach ($lessons as $position => [$title, $summary, $categoryNames, $isFree]) {
                $lesson = Lesson::create([
                    'level_id' => $level->id,
                    'author_id' => $author?->id,
                    'title' => $title,
                    'summary' => $summary,
                    'body' => "In this lesson you will practise: {$summary}\n\nWatch the video, download the PDF worksheet and complete the quiz at the end to track your progress.",
                    'status' => LessonStatus::Published,
                    'published_at' => now()->subDays(60 - $position),
                    'position' => $position + 1,
                    'duration_seconds' => 300 + ($position * 120),
                    'is_free' => $isFree,
                ]);

                $lesson->categories()->sync($categories->only($categoryNames)->values());
            }
        }

        // One draft so editors can see the workflow.
        Lesson::create([
            'level_id' => Level::where('code', 'B1')->value('id'),
            'author_id' => $author?->id,
            'title' => 'Reported speech (draft)',
            'summary' => 'Report what other people said, asked and ordered.',
            'status' => LessonStatus::Draft,
            'position' => 6,
        ]);
    }
}
