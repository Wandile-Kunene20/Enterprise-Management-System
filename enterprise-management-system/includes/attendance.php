<?php
require_once 'auth.php';
require_once 'database.php';
require_once 'security.php';

Auth::checkSession();

$db = Database::getInstance()->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = Security::sanitizeInput($_POST['action'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    switch ($action) {
        case 'check_in':
            $today = date('Y-m-d');
            $check_in_time = date('H:i:s');
            
            // Check if already checked in today
            $stmt = $db->prepare("SELECT id FROM attendance WHERE user_id = ? AND date = ?");
            $stmt->execute([$user_id, $today]);
            
            if ($stmt->fetch()) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Already checked in today'
                ]);
            } else {
                $stmt = $db->prepare("
                    INSERT INTO attendance (user_id, date, check_in, status) 
                    VALUES (?, ?, ?, 'present')
                ");
                $stmt->execute([$user_id, $today, $check_in_time]);
                
                echo json_encode([
                    'success' => true,
                    'check_in_time' => date('h:i A', strtotime($check_in_time)),
                    'message' => 'Checked in successfully'
                ]);
            }
            break;
            
        case 'check_out':
            $today = date('Y-m-d');
            $check_out_time = date('H:i:s');
            
            // Get check-in time
            $stmt = $db->prepare("SELECT check_in FROM attendance WHERE user_id = ? AND date = ?");
            $stmt->execute([$user_id, $today]);
            $attendance = $stmt->fetch();
            
            if (!$attendance || !$attendance['check_in']) {
                echo json_encode([
                    'success' => false,
                    'message' => 'You need to check in first'
                ]);
            } else {
                // Calculate total hours
                $check_in = strtotime($attendance['check_in']);
                $check_out = strtotime($check_out_time);
                $total_hours = round(($check_out - $check_in) / 3600, 2);
                
                $stmt = $db->prepare("
                    UPDATE attendance 
                    SET check_out = ?, total_hours = ? 
                    WHERE user_id = ? AND date = ?
                ");
                $stmt->execute([$check_out_time, $total_hours, $user_id, $today]);
                
                echo json_encode([
                    'success' => true,
                    'check_out_time' => date('h:i A', strtotime($check_out_time)),
                    'total_hours' => $total_hours,
                    'message' => 'Checked out successfully'
                ]);
            }
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'message' => 'Invalid action'
            ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>