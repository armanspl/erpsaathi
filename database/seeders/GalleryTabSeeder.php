<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GalleryTab;
use App\Models\GalleryImage;

class GalleryTabSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default tabs
        $tabs = [
            [
                'name' => 'Academics',
                'slug' => 'academics',
                'icon' => 'fas fa-graduation-cap',
                'order' => 1,
                'is_active' => true,
            ],
            [
                'name' => 'Activities',
                'slug' => 'activities',
                'icon' => 'fas fa-running',
                'order' => 2,
                'is_active' => true,
            ],
            [
                'name' => 'Infrastructure',
                'slug' => 'infrastructure',
                'icon' => 'fas fa-building',
                'order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($tabs as $tabData) {
            $tab = GalleryTab::create($tabData);
        }
    }
}
