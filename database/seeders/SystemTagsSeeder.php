<?php

namespace Database\Seeders;

use App\Models\ContactTag;
use Illuminate\Database\Seeder;

class SystemTagsSeeder extends Seeder
{
    public function run(): void
    {
        $tags = ContactTag::SYSTEM_TAGS;

        foreach ($tags as $tag) {
            ContactTag::withoutGlobalScopes()->firstOrCreate(
                ['is_system' => true, 'name' => $tag['name']],
                ['user_id' => null, 'color' => $tag['color'], 'is_system' => true]
            );
        }

        $this->command->info('System tags seeded: ' . implode(', ', array_column($tags, 'name')));
    }
}
