<?php

namespace myprojecttests;

use Codeception\Test\Unit;
use statikbe\translate\helpers\PostedTranslations;

class PostedTranslationsTest extends Unit
{
    public function testReadsEveryGroup()
    {
        // A message starting with a number gets another element ID (and so another group) than the rest
        $posted = [
            'element-1' => ['translation' => ['1. Kies een ingreep' => '1. Choose a procedure']],
            'element-0' => ['translation' => ['Kostenraming' => 'Cost estimate', 'Wissen' => 'Clear']],
        ];
        self::assertSame([
            '1. Kies een ingreep' => '1. Choose a procedure',
            'Kostenraming' => 'Cost estimate',
            'Wissen' => 'Clear',
        ], PostedTranslations::messages($posted));
    }

    public function testKeepsNumericMessages()
    {
        $posted = [
            'element-0' => ['translation' => [2026 => '2026', 'a' => 'A']],
            'element-2026' => ['translation' => [7 => 'Zeven']],
        ];
        self::assertSame([2026 => '2026', 'a' => 'A', 7 => 'Zeven'], PostedTranslations::messages($posted));
    }

    public function testIgnoresGroupsWithoutTranslations()
    {
        self::assertSame([], PostedTranslations::messages([]));
        self::assertSame(['a' => 'A'], PostedTranslations::messages([
            'element-0' => 'unexpected',
            'element-1' => [],
            'element-2' => ['translation' => ['a' => 'A']],
        ]));
    }
}
