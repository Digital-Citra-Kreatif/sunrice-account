<?php

declare(strict_types=1);

arch('every file declares strict types')
    ->expect('Sunrice\Account')
    ->toUseStrictTypes();

arch('accounts never depend on the plugins built on them')
    ->expect('Sunrice\Account')
    ->not->toUse(['Sunrice\Commerce']);

arch('no debugging left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();
