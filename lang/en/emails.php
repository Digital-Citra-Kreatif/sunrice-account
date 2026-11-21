<?php

declare(strict_types=1);

return [
    'trouble' => 'If the ":action" button doesn\'t work, copy this address into your browser:',
    'welcome' => [
        'subject' => 'Welcome to :site',
        'greeting' => 'Welcome, :name!',
        'intro' => 'Your account on :site is ready.',
        'action' => 'Open my account',
    ],
    'staff_registration' => [
        'subject' => 'New account on :site: :name',
        'intro' => 'Someone created an account on :site.',
        'details' => ':name (:email)',
    ],
    'staff_deletion' => [
        'subject' => 'Account deletion request on :site: :name',
        'intro' => 'An account on :site asked to be deleted.',
        'details' => ':name (:email). Handle it in the admin.',
    ],
];
