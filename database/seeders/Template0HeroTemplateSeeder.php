<?php

namespace Database\Seeders;

use App\Models\HeroTemplate;
use App\Models\HeroTemplateField;
use Illuminate\Database\Seeder;

class Template0HeroTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $heroTemplate = HeroTemplate::updateOrCreate(
            ['slug' => 'hero_template_free'],
            [
                'name' => 'Hero Template Free',
                'slug' => 'hero_template_Free',
                'blade_view' => 'hero.hero_template_free',
                'sports' => null,
                'preview_image' => null,
                'description' => 'A clean stat-driven athlete hero template with a large left jersey number, centered player image, right-side statistics, bottom PlyrCard, and mobile fallback.',
                'is_active' => true,
                'settings' => null,
            ]
        );

        $fields = [
            [
                'name' => 'hero_player_name',
                'label' => 'Hero Player Name',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 10,
                'options' => null,
            ],
            [
                'name' => 'hero_jersey_number',
                'label' => 'Hero Jersey Number',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 20,
                'options' => null,
            ],
            [
                'name' => 'hero_bg_jersey_number',
                'label' => 'Hero Background Jersey Number',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 30,
                'options' => null,
            ],
            [
                'name' => 'hero_display_position',
                'label' => 'Hero Display Position',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 40,
                'options' => null,
            ],
            [
                'name' => 'hero_stats_title',
                'label' => 'Hero Stats Title',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 50,
                'options' => null,
            ],
            [
                'name' => 'hero_player_image',
                'label' => 'Hero Player Image',
                'type' => 'image',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 60,
                'options' => null,
            ],
            [
                'name' => 'hero_mobile_image',
                'label' => 'Hero Mobile Image',
                'type' => 'image',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 70,
                'options' => null,
            ],
            [
                'name' => 'hero_background_image',
                'label' => 'Hero Background Image',
                'type' => 'image',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 80,
                'options' => null,
            ],
            [
                'name' => 'hero_ball_logo',
                'label' => 'Hero Ball Logo',
                'type' => 'image',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 90,
                'options' => null,
            ],
            [
                'name' => 'hero_plyrcard_image',
                'label' => 'Hero PlyrCard Image',
                'type' => 'image',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 100,
                'options' => null,
            ],
            [
                'name' => 'hero_brand_logo',
                'label' => 'Hero Brand Logo',
                'type' => 'image',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 110,
                'options' => null,
            ],
            [
                'name' => 'hero_sport',
                'label' => 'Hero Sport',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 120,
                'options' => null,
            ],
            [
                'name' => 'hero_stat_gpa',
                'label' => 'Hero Stat GPA',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 130,
                'options' => null,
            ],
            [
                'name' => 'hero_stat_dob',
                'label' => 'Hero Stat Date of Birth',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 140,
                'options' => null,
            ],
            [
                'name' => 'hero_stat_hometown',
                'label' => 'Hero Stat Hometown',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 150,
                'options' => null,
            ],
            [
                'name' => 'hero_stat_position',
                'label' => 'Hero Stat Position',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 160,
                'options' => null,
            ],
            [
                'name' => 'hero_stat_club',
                'label' => 'Hero Stat Club',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 170,
                'options' => null,
            ],
            [
                'name' => 'hero_stat_league',
                'label' => 'Hero Stat League',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 180,
                'options' => null,
            ],
            [
                'name' => 'hero_stat_high_school',
                'label' => 'Hero Stat High School',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 190,
                'options' => null,
            ],
            [
                'name' => 'hero_stat_height',
                'label' => 'Hero Stat Height',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 200,
                'options' => null,
            ],
            [
                'name' => 'hero_stat_weight',
                'label' => 'Hero Stat Weight',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 210,
                'options' => null,
            ],
            [
                'name' => 'hero_stat_class',
                'label' => 'Hero Stat Class',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 220,
                'options' => null,
            ],
            [
                'name' => 'hero_stat_coach',
                'label' => 'Hero Stat Coach',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 230,
                'options' => null,
            ],
            [
                'name' => 'hero_stat_championship',
                'label' => 'Hero Stat Championship',
                'type' => 'textarea',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 240,
                'options' => null,
            ],
        ];

        foreach ($fields as $field) {
            HeroTemplateField::updateOrCreate(
                [
                    'hero_template_id' => $heroTemplate->id,
                    'name' => $field['name'],
                ],
                $field
            );
        }
    }
}