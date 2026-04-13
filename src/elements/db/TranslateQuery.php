<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://www.statik.be
 * @copyright Copyright (c) 2017 Statik.be
 */

namespace statikbe\translate\elements\db;

use craft\elements\db\ElementQuery;

class TranslateQuery extends ElementQuery
{

    // General - Properties
    // =========================================================================
    public mixed $id = null;
    public $source;
    public $translateStatus;
    public $pluginHandle;

    /**
     * @inheritdoc
     */
    public function __set($name, $value)
    {
        parent::__set($name, $value);
    }

    /**
     * @inheritdoc
     */
    public function source($value)
    {
        $this->source = $value;
    }

    /**
     * @inheritdoc
     */
    public function getStatus(): array|string|null
    {
        return $this->status;
    }

    /**
     * @inheritdoc
     */
    protected function beforePrepare(): bool
    {
        return false;
    }
}
