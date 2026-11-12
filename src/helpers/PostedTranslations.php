<?php

namespace statikbe\translate\helpers;

/**
 * Reads the translations posted by the control panel list. Craft's element index namespaces each row's inputs by its
 * element ID, cast to an int (`translation[element-0][translation][…]`). The ID is the slug of the message, so a
 * message that starts with a number ("1. Choose…") lands in another group than the rest, and every group must be read.
 */
class PostedTranslations
{
    /**
     * Returns all posted translations as `message => translation`.
     *
     * @param array<string, mixed> $posted The `translation` body param
     * @return array<string, string>
     */
    public static function messages(array $posted): array
    {
        $messages = [];
        foreach ($posted as $group) {
            if (is_array($group) && is_array($group['translation'] ?? null)) {
                // array_replace() keeps keys PHP turned into ints (a message like "2026"), array_merge() would renumber them
                $messages = array_replace($messages, $group['translation']);
            }
        }

        return $messages;
    }
}
