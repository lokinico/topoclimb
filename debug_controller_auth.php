<?php

require_once 'bootstrap.php';

use TopoclimbCH\Core\Auth;
use TopoclimbCH\Core\Session;
use TopoclimbCH\Core\Database;

// Same session setup as login
$_SESSION['auth_user_id'] = 7;
$_SESSION['is_authenticated'] = true;

$db = new Database();
$session = new Session();
$auth = new Auth($session, $db);

echo 'Controller Auth Check:' . PHP_EOL;
echo '  $auth exists: ' . ($auth ? 'YES' : 'NO') . PHP_EOL;
echo '  $auth->check(): ' . ($auth->check() ? 'TRUE' : 'FALSE') . PHP_EOL; 
echo '  $auth->role(): ' . $auth->role() . PHP_EOL;
echo '  Role in [0,1,2]: ' . (in_array($auth->role(), [0,1,2]) ? 'TRUE' : 'FALSE') . PHP_EOL;

// Simulate the controller checks
if (!$auth || !$auth->check()) {
    echo 'RESULT: requireAuth() would FAIL' . PHP_EOL;
} else {
    echo 'RESULT: requireAuth() would PASS' . PHP_EOL;
    
    $userRole = $auth->role();
    if (!in_array($userRole, [0,1,2])) {
        echo 'RESULT: requireRole() would FAIL (role $userRole not in [0,1,2])' . PHP_EOL;
    } else {
        echo 'RESULT: requireRole() would PASS' . PHP_EOL;
    }
}