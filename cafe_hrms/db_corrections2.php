<?php
require 'init.php';

// Expand interview_scorecards table
$pdo->query("ALTER TABLE interview_scorecards ADD hourly_wage DECIMAL(10,2) NULL, ADD monthly_wage DECIMAL(10,2) NULL, ADD global_pool_reason VARCHAR(255) NULL");

echo "DB corrections executed.";
