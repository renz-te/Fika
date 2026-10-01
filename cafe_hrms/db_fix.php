<?php
require 'init.php';
$pdo->exec("DROP TABLE IF EXISTS interview_scorecards;");
$pdo->exec("CREATE TABLE interview_scorecards (
    id int(11) NOT NULL AUTO_INCREMENT, 
    interview_id int(11) NOT NULL, 
    technical_score int(1) NOT NULL, 
    culture_score int(1) NOT NULL, 
    strengths text DEFAULT NULL, 
    concerns text DEFAULT NULL, 
    recommendation enum('Hire','Reject','Next Round','Global Pool') NOT NULL, 
    created_by varchar(255) DEFAULT NULL, 
    created_at datetime DEFAULT current_timestamp(), 
    PRIMARY KEY (id), 
    FOREIGN KEY (interview_id) REFERENCES interviews (id) ON DELETE CASCADE
);");
echo "Success";
