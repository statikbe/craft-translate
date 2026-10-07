<?php

use Codeception\Actor;
use Codeception\Lib\Friend;

/**
 * Inherited Methods
 *
 * @method void wantToTest($text)
 * @method void wantTo($text)
 * @method void execute($callable)
 * @method void expectTo($prediction)
 * @method void expect($prediction)
 * @method void amGoingTo($argumentation)
 * @method void am($role)
 * @method void lookForwardTo($achieveValue)
 * @method void comment($description)
 * @method Friend haveFriend($name, $actorClass = null)
 *
 * @SuppressWarnings(PHPMD)
 *
 */
class UnitTester extends Actor
{
    use _generated\UnitTesterActions;

    public function parseRegex($expressions, $string, $pos = 1)
    {
        $translator = new \statikbe\translate\services\Translate();
        $translations = [];
        foreach ($expressions as $regex) {
            $matches = $translator->parseString($regex, $string);
            if (!$matches) {
                continue;
            }
            if (array_filter($matches)) {
                foreach ($matches[$pos] as $original) {
                    $translations[] = $original;
                }
                return $translations;
            }
        }
        return $translations;
    }

    /**
     * Returns all matches as [original, category] pairs, a translation without category means 'site'
     */
    public function parseRegexWithCategory($expressions, $string, $pos = 1)
    {
        $translator = new \statikbe\translate\services\Translate();
        $translations = [];
        foreach ($expressions as $regex) {
            $matches = $translator->parseString($regex, $string);
            foreach ($matches[$pos] ?? [] as $i => $original) {
                $translations[] = [$original, ($matches[2][$i] ?? '') ?: 'site'];
            }
        }
        return $translations;
    }
}
