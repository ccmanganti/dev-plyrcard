<?php

namespace Database\Seeders;

use App\Models\HeroTemplate;
use App\Models\HeroTemplateField;
use Illuminate\Database\Seeder;

class Template2HeroTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $heroTemplate = HeroTemplate::updateOrCreate(
            ['slug' => 'hero_template_2'],
            [
                'name' => 'Hero Template 2',
                'slug' => 'hero_template_2',
                'blade_view' => 'template.hero_template_2',
                'sports' => null,
                'preview_image' => null,
                'description' => 'A bold athlete hero template with a large stacked name layout, top-right player card, bottom-right player image, center action image, bottom-left info panel, and mobile fallback image.',
                'is_active' => true,
                'settings' => null,
            ]
        );

        $fields = [
            [
                'name' => 'hero_jersey_number',
                'label' => 'Hero Jersey Number',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 10,
                'options' => null,
            ],
            [
                'name' => 'hero_first_name',
                'label' => 'Hero First Name',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 20,
                'options' => null,
            ],
            [
                'name' => 'hero_last_name',
                'label' => 'Hero Last Name',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 30,
                'options' => null,
            ],
            [
                'name' => 'hero_position',
                'label' => 'Hero Position',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 40,
                'options' => null,
            ],
            [
                'name' => 'hero_date_of_birth',
                'label' => 'Hero Date of Birth',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 50,
                'options' => null,
            ],
            [
                'name' => 'hero_club',
                'label' => 'Hero Club',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 60,
                'options' => null,
            ],
            [
                'name' => 'hero_high_school',
                'label' => 'Hero High School',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 70,
                'options' => null,
            ],
            [
                'name' => 'hero_gpa',
                'label' => 'Hero GPA',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 80,
                'options' => null,
            ],
            [
                'name' => 'hero_coach',
                'label' => 'Hero Coach',
                'type' => 'text',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 90,
                'options' => null,
            ],
            [
                'name' => 'hero_player_card',
                'label' => 'Hero Player Card',
                'type' => 'image',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 100,
                'options' => null,
            ],
            [
                'name' => 'hero_player_image',
                'label' => 'Hero Player Image',
                'type' => 'image',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 110,
                'options' => null,
            ],
            [
                'name' => 'hero_player_action_image',
                'label' => 'Hero Player Action Image',
                'type' => 'image',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 120,
                'options' => null,
            ],
            [
                'name' => 'hero_two_mobile_image',
                'label' => 'Hero Mobile Image',
                'type' => 'image',
                'guide_image' => null,
                'is_required' => false,
                'sort_order' => 130,
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

        HeroTemplateField::query()
            ->where('hero_template_id', $heroTemplate->id)
            ->whereNotIn('name', collect($fields)->pluck('name')->all())
            ->delete();
    }
}