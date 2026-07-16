<?php
/**
 * Application configuration
 */
return [
    'name'       => 'Lexora Legal',
    'tagline'    => 'Legal Case Management System',
    'url'        => 'http://localhost/legal-system',
    'timezone'   => 'Indian/Mauritius',
    'language'   => 'en',
    'upload_max' => 10 * 1024 * 1024, // 10MB
    'currency'   => 'MUR',
    'currency_symbol' => 'Rs ',
    // Set your OpenAI API key to enable live AI responses
    'openai_api_key' => '',
    'openai_model'   => 'gpt-4o-mini',
];
