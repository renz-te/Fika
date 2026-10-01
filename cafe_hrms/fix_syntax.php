<?php
// Fix dashboard.php syntax
$dash = file_get_contents('c:\laragon\www\cafe_hrms\dashboard.php');
$dash = str_replace('CONCAT(emp.first_name, " ", emp.last_name)', "CONCAT(emp.first_name, ' ', emp.last_name)", $dash);
file_put_contents('c:\laragon\www\cafe_hrms\dashboard.php', $dash);

// Fix attendance.php syntax
$att = file_get_contents('c:\laragon\www\cafe_hrms\attendance.php');
$att = str_replace('CONCAT(e.first_name, " ", e.last_name)', "CONCAT(e.first_name, ' ', e.last_name)", $att);
file_put_contents('c:\laragon\www\cafe_hrms\attendance.php', $att);

echo "Syntax fixed.\n";
