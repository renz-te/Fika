<?php
try {
    \ = new PDO('mysql:host=localhost;dbname=fika_hrms', 'root', '');
    \->exec("ALTER TABLE payroll_runs ADD COLUMN scope ENUM('BRANCH', 'OFFICIALS', 'HQ') NOT NULL DEFAULT 'BRANCH' AFTER id");
    echo "Done";
} catch (Exception \) {
    echo \->getMessage();
}
