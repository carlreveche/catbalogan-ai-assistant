<?php
/**
 * Google (Gmail) OAuth 2.0 credentials.
 *
 * HOW TO GET THESE:
 * 1. Go to https://console.cloud.google.com and create a project.
 * 2. APIs & Services -> OAuth consent screen -> configure (External).
 * 3. APIs & Services -> Credentials -> Create Credentials
 *    -> OAuth Client ID -> type "Web application".
 * 4. Add the authorized redirect URI exactly as GOOGLE_REDIRECT_URI
 *    below (must match character-for-character).
 * 5. Set the environment variables listed in README.md.
 *
 * SECURITY: never commit real secrets to version control.
 * This file sits inside config/ which is blocked from web access.
 */
function google_config_value(string $name): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = $_SERVER[$name] ?? '';
    }
    return is_string($value) ? trim($value) : '';
}

define('GOOGLE_CLIENT_ID', google_config_value('CATBALOGAN_GOOGLE_CLIENT_ID'));
define('GOOGLE_CLIENT_SECRET', google_config_value('CATBALOGAN_GOOGLE_CLIENT_SECRET'));
define(
    'GOOGLE_REDIRECT_URI',
    google_config_value('CATBALOGAN_GOOGLE_REDIRECT_URI')
        ?: 'http://localhost/catbalogan-ai-assistant/google_auth/callback.php'
);
