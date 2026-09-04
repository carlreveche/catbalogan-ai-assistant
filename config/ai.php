<?php
/**
 * AI (LLM) integration settings — Groq API
 *
 * Get a free API key at https://console.groq.com (no credit card required).
 * Configure it through environment variables. Keep the API key out of
 * source control and server responses.
 */
define('AI_PROVIDER', getenv('CATBALOGAN_AI_PROVIDER') ?: 'groq');
define('AI_MODEL', getenv('CATBALOGAN_AI_MODEL') ?: 'openai/gpt-oss-20b');
define('AI_API_KEY', getenv('CATBALOGAN_AI_API_KEY') ?: '');
define('AI_TEMPERATURE', (float) (getenv('CATBALOGAN_AI_TEMPERATURE') ?: 0.2));
define('AI_MAX_TOKENS', (int) (getenv('CATBALOGAN_AI_MAX_TOKENS') ?: 800));
define('AI_TIMEOUT', (int) (getenv('CATBALOGAN_AI_TIMEOUT') ?: 10));
define('AI_FALLBACK_THRESHOLD', (int) (getenv('CATBALOGAN_AI_FALLBACK_THRESHOLD') ?: 40));