<?php

declare(strict_types=1);

namespace Sunrice\Account\Settings;

use Sunrice\Admin\Resources\Forms\Form;
use Sunrice\Admin\Settings\Settings;

/**
 * Where the account settings are kept: one panel settings row
 * ("account") holding every section as one array. Not registered with
 * the panel (the account settings page is the package's own):
 * AccountSettings reads and writes it.
 *
 * @internal
 */
final class StoredValues extends Settings
{
    /** @var array<string, mixed> section => values */
    public array $values = [];

    public static function key(): string
    {
        return AccountSettings::KEY;
    }

    public static function form(Form $form): Form
    {
        return $form;
    }
}
