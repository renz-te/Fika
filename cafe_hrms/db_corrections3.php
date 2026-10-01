<?php
require 'init.php';

$pdo->query("ALTER TABLE interview_scorecards ADD communication_score int(1) NULL AFTER technical_score, ADD reliability_score int(1) NULL AFTER communication_score, ADD problem_solving_score int(1) NULL AFTER culture_score");

echo "DB corrections executed.";
