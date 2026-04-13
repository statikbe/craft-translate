<?php

namespace statikbe\translate\events;

use yii\base\Event;

class RegisterPluginTranslationEvent extends Event
{
    public $plugins;
}