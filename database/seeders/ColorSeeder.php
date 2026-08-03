<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    /**
     * Known Bengal colors (from the original site's menu), seeded once as
     * reference data. Idempotent: safe to re-run in any environment.
     */
    public function run(): void
    {
        $colors = [
            ['name' => 'Silver', 'hex_code' => '#C0C0C0'],
            ['name' => 'Brown', 'hex_code' => '#8B5A2B'],
            ['name' => 'Charcoal', 'hex_code' => '#36454F'],
            ['name' => 'Snow', 'hex_code' => '#FFFAFA'],
            ['name' => 'Noir / Mélanistique', 'hex_code' => '#0B0B0B'],
            ['name' => 'Bleu', 'hex_code' => '#6699CC'],
        ];

        foreach ($colors as $color) {
            Color::firstOrCreate(['name' => $color['name']], $color);
        }
    }
}
