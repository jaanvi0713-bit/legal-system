<?php
/**
 * Application configuration
 */
return [
    'name'       => 'Lexora Legal',
    'tagline'    => 'Legal Case Management System',
    'url'        => 'http://localhost/legal-system',
    'timezone'   => 'Asia/Dubai',
    'upload_max' => 10 * 1024 * 1024, // 10MB
    'currency'   => 'AED',
    'currency_symbol' => 'AED ',
    // Set your OpenAI API key to enable live AI responses
    'openai_api_key' => '',
    'openai_model'   => 'gpt-4o-mini',
];
