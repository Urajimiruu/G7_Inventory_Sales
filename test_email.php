<?php
require_once __DIR__ . '/includes/email_config.php';

echo 'Email was sent to: ' . MAIN_OFFICE_EMAIL . '<br>';

sendLowStockEmail('Test Product', 'Test Branch', 5);

echo "Test email triggered. Check that inbox (and Spam).";
