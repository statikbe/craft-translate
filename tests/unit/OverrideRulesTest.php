<?php

namespace myprojecttests;

use Codeception\Test\Unit;
use statikbe\translate\helpers\OverrideRules;

class OverrideRulesTest extends Unit
{
    private array $defaults = [
        'nav.home' => 'Home',
        'nav.empty' => '',
    ];

    public function testBaseline()
    {
        self::assertSame('Home', OverrideRules::baseline('nav.home', $this->defaults));
        // Without a default (or an empty one) a message shows itself
        self::assertSame('nav.empty', OverrideRules::baseline('nav.empty', $this->defaults));
        self::assertSame('Read more', OverrideRules::baseline('Read more', $this->defaults));
    }

    public function testUnchangedValueStoresNothing()
    {
        self::assertSame(OverrideRules::NONE, OverrideRules::action('nav.home', 'Home', $this->defaults, null));
        self::assertSame(OverrideRules::NONE, OverrideRules::action('Read more', 'Read more', $this->defaults, null));
    }

    public function testChangedValueIsStored()
    {
        self::assertSame(OverrideRules::UPSERT, OverrideRules::action('nav.home', 'Start', $this->defaults, null));
        self::assertSame(OverrideRules::UPSERT, OverrideRules::action('nav.home', 'Begin', $this->defaults, 'Start'));
        self::assertSame(OverrideRules::NONE, OverrideRules::action('nav.home', 'Start', $this->defaults, 'Start'));
    }

    public function testBackToDefaultOrEmptyRemovesTheOverride()
    {
        self::assertSame(OverrideRules::DELETE, OverrideRules::action('nav.home', 'Home', $this->defaults, 'Start'));
        self::assertSame(OverrideRules::DELETE, OverrideRules::action('nav.home', '', $this->defaults, 'Start'));
        self::assertSame(OverrideRules::NONE, OverrideRules::action('nav.home', '', $this->defaults, null));
    }

    public function testImport()
    {
        self::assertSame(OverrideRules::IMPORT_DEFAULT, OverrideRules::classifyImport('nav.home', 'Home', $this->defaults, null));
        self::assertSame(OverrideRules::IMPORT_OVERRIDE, OverrideRules::classifyImport('nav.home', 'Start', $this->defaults, null));
        self::assertSame(OverrideRules::IMPORT_UNCHANGED, OverrideRules::classifyImport('nav.home', 'Start', $this->defaults, 'Start'));
        self::assertSame(OverrideRules::IMPORT_EMPTY, OverrideRules::classifyImport('nav.home', '', $this->defaults, null));
        // A key the code doesn't have any more: kept, and reported
        self::assertSame(OverrideRules::IMPORT_UNKNOWN, OverrideRules::classifyImport('old.key', 'Oud', $this->defaults, null));
        // ... unless it shows the same as without it
        self::assertSame(OverrideRules::IMPORT_DEFAULT, OverrideRules::classifyImport('old.key', 'old.key', $this->defaults, null));
    }

    public function testMergeOrder()
    {
        $files = ['a' => 'file', 'b' => 'file', 'c' => 'file'];
        $merged = OverrideRules::merge($files, ['b' => 'generic', 'c' => 'generic'], ['c' => 'exact', 'a' => '']);
        self::assertSame(['a' => 'file', 'b' => 'generic', 'c' => 'exact'], $merged);
    }

    public function testFallbackLanguage()
    {
        self::assertSame('nl', OverrideRules::fallbackLanguage('nl-BE'));
        self::assertSame('', OverrideRules::fallbackLanguage('nl'));
    }
}
