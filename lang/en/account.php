<?php

declare(strict_types=1);

return [
    'navigation' => 'My account',
    'dashboard' => [
        'title' => 'My account',
        'greeting' => 'Hello, :name.',
    ],
    'announcement' => [
        'title' => 'Announcement',
    ],
    'profile' => [
        'title' => 'Profile',
        'save' => 'Save',
        'saved' => 'Your profile is saved.',
        'verify_new_email' => 'Your profile is saved. Check your new email address: we\'ve sent a link to verify it.',
        'unverified' => 'This address isn\'t verified yet.',
        'current_password' => 'Current password',
        'current_password_help' => 'Needed to change your email address.',
        'email_needs_password' => 'Enter your current password to change your email address.',
    ],
    'password' => [
        'title' => 'Change your password',
        'set_title' => 'Choose a password',
        'save' => 'Save the password',
        'saved' => 'Your password is changed.',
        'current_wrong' => 'This isn\'t your current password.',
    ],
    'delete' => [
        'title' => 'Delete my account',
        'intro' => 'Ask us to delete your account and your personal details. We\'ll take care of it and let you know.',
        'confirm' => 'Yes, I want my account deleted',
        'submit' => 'Ask to delete my account',
        'requested' => 'We\'ve received your request to delete your account.',
        'pending' => 'You asked for your account to be deleted on :date. We\'re taking care of it.',
    ],
];
