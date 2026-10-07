<?php

namespace myprojecttests;

use Codeception\Test\Unit;
use statikbe\translate\services\Translate;
use UnitTester;

class TwigExpressionUnitTest extends Unit
{
    public $translator;
    public $expressions;

    /**
     * @var UnitTester
     */
    protected $tester;

    public function _before()
    {
        $this->translator = new Translate();
        $this->expressions = $this->translator->_expressions['twig'];
    }

    public function testSingleQuotes()
    {
        $string = "{{ 'hier'|t }}";
        $result = $this->tester->parseRegex($this->expressions, $string);
        self::assertEquals(['hier'], $result);

        $string = "{{ 'hier'|t|raw }}";
        $result = $this->tester->parseRegex($this->expressions, $string);
        self::assertEquals(['hier'], $result);
    }

    public function testDoubleQuotes()
    {
        $string = '{{ "hier"|t }}';
        $result = $this->tester->parseRegex($this->expressions, $string);
        self::assertEquals(['hier'], $result);

        $string = '{{ "hier"|t|raw }}';
        $result = $this->tester->parseRegex($this->expressions, $string);
        self::assertEquals(['hier'], $result);
    }

    public function testNotATranslation()
    {
        $string = '{% set today = "now"|date("Ym") %}';
        $result = $this->tester->parseRegex($this->expressions, $string);
        self::assertEquals([], $result);
    }

    public function testFilterStartingWithT()
    {
        $string = "{{ ' hier '|trim }} {{ \"hier\"|title }}";
        $result = $this->tester->parseRegex($this->expressions, $string);
        self::assertEquals([], $result);
    }

    public function testStringWithReturns()
    {
        $string = '{{  "craft
            cms"|t }}';
        $result = $this->tester->parseRegex($this->expressions, $string);
        self::assertEquals(['craft cms'], $result);
    }

    public function testMultiple()
    {
        $string = '{{ "here"|t }} {{ "there"|t }}';
        $result = $this->tester->parseRegex($this->expressions, $string);
        self::assertEquals(['here', 'there'], $result);
    }

    public function testWithoutCategoryIsSite()
    {
        $string = "{{ 'hier'|t }} {{ \"daar\"|translate }}";
        $result = $this->tester->parseRegexWithCategory($this->expressions, $string);
        self::assertEquals([['hier', 'site'], ['daar', 'site']], $result);
    }

    public function testWithCategory()
    {
        $string = "{{ 'account.login.email'|t('website_name') }}";
        $result = $this->tester->parseRegexWithCategory($this->expressions, $string);
        self::assertEquals([['account.login.email', 'website_name']], $result);

        $string = '{{ "account.login.email"|t("website_name") }}';
        $result = $this->tester->parseRegexWithCategory($this->expressions, $string);
        self::assertEquals([['account.login.email', 'website_name']], $result);

        $string = "{{ 'account.login.email'|translate( \"website_name\" ) }}";
        $result = $this->tester->parseRegexWithCategory($this->expressions, $string);
        self::assertEquals([['account.login.email', 'website_name']], $result);
    }

    public function testWithCategoryAndParams()
    {
        $string = '{{ "search.results"|t("website_name", {total: totalEntries}) }}';
        $result = $this->tester->parseRegexWithCategory($this->expressions, $string);
        self::assertEquals([['search.results', 'website_name']], $result);
    }

    public function testWithParamsOnlyIsSite()
    {
        $string = '{{ "{total} results"|t({total: totalEntries}) }}';
        $result = $this->tester->parseRegexWithCategory($this->expressions, $string);
        self::assertEquals([['{total} results', 'site']], $result);
    }

    public function testMixedCategories()
    {
        $string = "{{ 'nav.home'|t('website_name') }} {{ 'Home'|t }} {{ 'nav.home'|t('website_name_2') }}";
        $result = $this->tester->parseRegexWithCategory($this->expressions, $string);
        self::assertEquals([
            ['nav.home', 'website_name'],
            ['Home', 'site'],
            ['nav.home', 'website_name_2'],
        ], $result);
    }
}
