<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserShortNameTest extends TestCase
{
    public function test_short_name_is_generated_with_utf8_safe_initials(): void
    {
        $shortNames = [
            User::make_short_name('шерзод', 'ўктамов', 'шавкатович'),
            User::make_short_name('Ázamat', 'Ótemuratov', 'Ǵaniyevich'),
            User::make_short_name('Sherzod', 'Karimov', 'Sharifovich'),
            User::make_short_name('Aziz', 'Karimov', 'Sharifovich'),
        ];

        $this->assertSame([
            'ЎКТАМОВ Ш.Ш.',
            'ÓTEMURATOV Á.Ǵ.',
            'KARIMOV SH.SH.',
            'KARIMOV A.SH.',
        ], $shortNames);

        foreach ($shortNames as $shortName) {
            $this->assertTrue(mb_check_encoding($shortName, 'UTF-8'));
            $this->assertJson(json_encode(['short' => $shortName], JSON_THROW_ON_ERROR));
        }

        $user = new User;
        $user->fill([
            'name' => [
                'full' => 'Ázamat Ótemuratov Ǵaniyevich',
                'first' => 'Ázamat',
                'last' => 'Ótemuratov',
                'third' => 'Ǵaniyevich',
                'short' => $shortNames[1],
            ],
        ]);

        $this->assertJson($user->getAttributes()['name']);
    }
}
