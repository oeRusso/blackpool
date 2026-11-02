<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public const CATEGORIES = [
        'Grammar' => 'Tenses, structures and the rules that hold English together.',
        'Vocabulary' => 'Words, collocations and idioms for real-life situations.',
        'Listening' => 'Train your ear with dialogues, interviews and podcasts.',
        'Speaking' => 'Pronunciation drills and conversation practice.',
        'Reading' => 'Short stories, articles and comprehension exercises.',
        'Writing' => 'Emails, essays and everyday written communication.',
        'Business English' => 'Meetings, negotiations and professional emails.',
        'Travel' => 'Everything you need for airports, hotels and restaurants.',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $description) {
            Category::updateOrCreate(['name' => $name], ['description' => $description]);
        }
    }
}
