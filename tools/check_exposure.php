<?php
// Security check file
// If this file can be executed from a web browser, it means the `tools/` directory 
// (and likely other core directories) are improperly exposed to the public web.
http_response_code(403);
header('Content-Type: application/json');
echo json_encode([
    'status' => 'vulnerable',
    'message' => 'CRITICAL SECURITY ALERT: Core directories are publicly accessible.'
]);
exit;
