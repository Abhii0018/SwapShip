<?php

return [
  /*
    | Emails that always receive the admin role (lowercase).
    */
    'emails' => array_filter(array_map(
        static fn (string $email): string => mb_strtolower(trim($email)),
        explode(',', (string) env('ADMIN_EMAILS', 'abhisheksah018@gmail.com'))
    )),

    /*
    | Maximum minutes an admin stays logged in after completing OTP sign-in.
    */
    'session_lifetime_minutes' => (int) env('ADMIN_SESSION_LIFETIME', 60),
];
