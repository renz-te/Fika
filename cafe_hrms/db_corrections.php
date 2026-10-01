<?php
require 'init.php';

// 1. Add rejection_reason to applicants
$pdo->query("ALTER TABLE applicants ADD rejection_reason VARCHAR(255) NULL");

// 2. Update interviews status
$pdo->query("UPDATE interviews i INNER JOIN interview_scorecards s ON i.id = s.interview_id SET i.status = 'Completed' WHERE i.status = 'Scheduled'");

echo "DB corrections executed.";
