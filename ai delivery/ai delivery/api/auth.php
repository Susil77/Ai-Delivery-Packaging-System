<?php
/**
 * Authentication API
 * Handles login, logout, and session management
 */

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        if ($action === 'login') {
            handleLogin();
        } elseif ($action === 'logout') {
            handleLogout();
        } else {
            sendError('Invalid action', 400);
        }
        break;
    
    case 'GET':
        if ($action === 'check') {
            checkSession();
        } else {
            sendError('Invalid action', 400);
        }
        break;
    
    default:
        sendError('Method not allowed', 405);
}

function handleLogin() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['username']) || !isset($input['password']) || !isset($input['role'])) {
        sendError('Username, password, and role are required', 400);
    }
    
    $username = trim($input['username']);
    $password = $input['password'];
    $role = $input['role']; // 'admin' or 'driver'
    
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    // Get user from database
    $stmt = $conn->prepare("SELECT id, username, password, role, full_name, email FROM users WHERE username = ? AND role = ?");
    $stmt->bind_param("ss", $username, $role);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendError('Invalid username or password', 401);
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    // For demo: check plain text password (admin123, driver123)
    // In production, use password_verify($password, $user['password'])
    $validPassword = false;
    
    if ($role === 'admin' && $password === 'admin123') {
        $validPassword = true;
    } elseif ($role === 'driver' && $password === 'driver123') {
        $validPassword = true;
    }
    
    // Also check hashed password for compatibility
    if (!$validPassword && password_verify($password, $user['password'])) {
        $validPassword = true;
    }
    
    if (!$validPassword) {
        sendError('Invalid username or password', 401);
    }
    
    // Create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['full_name'] = $user['full_name'];
    
    // Get driver info if driver
    $driverInfo = null;
    if ($role === 'driver') {
        $driverStmt = $conn->prepare("SELECT id, status, vehicle_number FROM drivers WHERE user_id = ?");
        $driverStmt->bind_param("i", $user['id']);
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        if ($driverResult->num_rows > 0) {
            $driverInfo = $driverResult->fetch_assoc();
        }
    }
    
    sendSuccess('Login successful', [
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'email' => $user['email']
        ],
        'driver' => $driverInfo
    ]);
    
    $stmt->close();
    if (isset($driverStmt)) $driverStmt->close();
    closeDBConnection($conn);
}

function handleLogout() {
    session_destroy();
    sendSuccess('Logout successful');
}

function checkSession() {
    if (isLoggedIn()) {
        $conn = getDBConnection();
        if (!$conn) {
            sendError('Database connection failed', 500);
        }
        
        $userId = $_SESSION['user_id'];
        $role = $_SESSION['role'];
        
        $stmt = $conn->prepare("SELECT id, username, role, full_name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            session_destroy();
            sendError('Session invalid', 401);
        }
        
        $user = $result->fetch_assoc();
        
        $driverInfo = null;
        if ($role === 'driver') {
            $driverStmt = $conn->prepare("SELECT id, status, vehicle_number FROM drivers WHERE user_id = ?");
            $driverStmt->bind_param("i", $userId);
            $driverStmt->execute();
            $driverResult = $driverStmt->get_result();
            if ($driverResult->num_rows > 0) {
                $driverInfo = $driverResult->fetch_assoc();
            }
        }
        
        sendSuccess('Session valid', [
            'user' => $user,
            'driver' => $driverInfo
        ]);
        
        $stmt->close();
        if (isset($driverStmt)) $driverStmt->close();
        closeDBConnection($conn);
    } else {
        sendError('Not logged in', 401);
    }
}

?>

