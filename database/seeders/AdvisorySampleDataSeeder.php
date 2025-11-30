<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdvisorySampleDataSeeder extends Seeder
{
    public function run()
    {
        // Sample Categories
        $categories = [
            ['name' => 'Crop Management', 'description' => 'Best practices for growing and maintaining crops', 'icon' => 'fa-seedling', 'order' => 1, 'status' => 'Active', 'created_by_id' => 1],
            ['name' => 'Pest Control', 'description' => 'Managing pests and diseases effectively', 'icon' => 'fa-bug', 'order' => 2, 'status' => 'Active', 'created_by_id' => 1],
            ['name' => 'Weather & Climate', 'description' => 'Understanding weather patterns and climate adaptation', 'icon' => 'fa-cloud-rain', 'order' => 3, 'status' => 'Active', 'created_by_id' => 1],
            ['name' => 'Post-Harvest', 'description' => 'Proper handling and storage of harvested crops', 'icon' => 'fa-warehouse', 'order' => 4, 'status' => 'Active', 'created_by_id' => 1],
        ];

        foreach ($categories as $category) {
            DB::table('advisory_categories')->insert(array_merge($category, [
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]));
        }

        // Sample Posts
        $posts = [
            [
                'category_id' => 1,
                'title' => 'Best Practices for Maize Cultivation',
                'content' => 'Maize is one of the most important crops in East Africa. Here are key practices for successful cultivation: 1) Proper land preparation, 2) Use of improved seeds, 3) Correct spacing, 4) Timely weeding, 5) Appropriate fertilizer application. Remember to plant at the onset of rains for optimal yields.',
                'author_id' => 1,
                'author_name' => 'Agricultural Extension Officer',
                'published_at' => Carbon::now(),
                'status' => 'Published',
                'featured' => 'Yes',
                'language' => 'English',
                'tags' => 'maize,cultivation,farming',
            ],
            [
                'category_id' => 2,
                'title' => 'Organic Pest Control Methods',
                'content' => 'Effective pest control without harmful chemicals is possible. Use natural methods like: companion planting, neem oil spray, garlic solution, crop rotation, and attracting beneficial insects. These methods protect your crops while maintaining soil health.',
                'author_id' => 1,
                'author_name' => 'Agricultural Extension Officer',
                'published_at' => Carbon::now(),
                'status' => 'Published',
                'featured' => 'Yes',
                'language' => 'English',
                'tags' => 'pests,organic,natural',
            ],
            [
                'category_id' => 3,
                'title' => 'Understanding Seasonal Rainfall Patterns',
                'content' => 'Knowing your local rainfall patterns is crucial for farming success. Monitor weather forecasts, understand the difference between long and short rains, and plan your planting accordingly. Consider installing rain gauges to track local precipitation.',
                'author_id' => 1,
                'author_name' => 'Agricultural Extension Officer',
                'published_at' => Carbon::now(),
                'status' => 'Published',
                'featured' => 'No',
                'language' => 'English',
                'tags' => 'weather,rainfall,climate',
            ],
            [
                'category_id' => 4,
                'title' => 'Proper Grain Storage Techniques',
                'content' => 'Post-harvest losses can be reduced significantly with proper storage. Ensure grains are properly dried before storage, use hermetic bags or improved granaries, protect from rodents and insects, and store in a cool, dry place away from direct sunlight.',
                'author_id' => 1,
                'author_name' => 'Agricultural Extension Officer',
                'published_at' => Carbon::now(),
                'status' => 'Published',
                'featured' => 'No',
                'language' => 'English',
                'tags' => 'storage,post-harvest,preservation',
            ],
        ];

        foreach ($posts as $post) {
            DB::table('advisory_posts')->insert(array_merge($post, [
                'view_count' => rand(10, 100),
                'likes_count' => rand(5, 50),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]));
        }

        // Sample Farmer Questions
        $questions = [
            [
                'title' => 'What is the best time to plant beans?',
                'content' => 'I am in Mbale district and want to know when is the best time to plant beans for maximum yield. Should I wait for the long rains or can I plant during short rains?',
                'author_id' => 2,
                'author_name' => 'John Farmer',
                'author_location' => 'Mbale District',
                'status' => 'Open',
            ],
            [
                'title' => 'How to control aphids on vegetables?',
                'content' => 'My vegetable garden is being attacked by aphids. I have tried spraying with soapy water but they keep coming back. What other organic methods can I use?',
                'author_id' => 3,
                'author_name' => 'Mary Cultivator',
                'author_location' => 'Wakiso District',
                'status' => 'Open',
            ],
        ];

        foreach ($questions as $question) {
            DB::table('farmer_questions')->insert(array_merge($question, [
                'view_count' => rand(5, 30),
                'likes_count' => rand(1, 10),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]));
        }

        echo "Sample advisory data seeded successfully!\n";
        echo "- " . count($categories) . " categories created\n";
        echo "- " . count($posts) . " posts created\n";
        echo "- " . count($questions) . " questions created\n";
    }
}
