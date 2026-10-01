<?php
// Fix dashboard.php
$dash = file_get_contents('c:\laragon\www\cafe_hrms\dashboard.php');
$dash = str_replace('emp.full_name', 'CONCAT(emp.first_name, " ", emp.last_name) AS full_name', $dash);
file_put_contents('c:\laragon\www\cafe_hrms\dashboard.php', $dash);

// Fix employees.php
$emp = file_get_contents('c:\laragon\www\cafe_hrms\employees.php');
$emp = str_replace('e.full_name LIKE ?', 'CONCAT(e.first_name, " ", e.last_name) LIKE ?', $emp);
file_put_contents('c:\laragon\www\cafe_hrms\employees.php', $emp);

// Fix attendance.php
$att = file_get_contents('c:\laragon\www\cafe_hrms\attendance.php');
$att = str_replace('e.full_name', 'CONCAT(e.first_name, " ", e.last_name) AS full_name', $att);
$att = str_replace('CONCAT(e.first_name, " ", e.last_name) AS full_name LIKE ?', 'CONCAT(e.first_name, " ", e.last_name) LIKE ?', $att); // fix the where clause
file_put_contents('c:\laragon\www\cafe_hrms\attendance.php', $att);

// Fix applications.php (SELECT full_name)
$app = file_get_contents('c:\laragon\www\cafe_hrms\applications.php');
$app = str_replace('SELECT full_name, email', 'SELECT CONCAT(first_name, " ", last_name) AS full_name, email', $app);
// Fix applications.php (INSERT INTO employees)
$app = str_replace('employee_id, full_name, email', 'employee_id, first_name, last_name, email', $app);
$app = str_replace('$applicant[\'full_name\'],', '$applicant[\'first_name\'], $applicant[\'last_name\'],', $app);
file_put_contents('c:\laragon\www\cafe_hrms\applications.php', $app);

echo "Remaining bugs fixed.\n";
