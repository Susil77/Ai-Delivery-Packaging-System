<?php
/**
 * Drivers API
 * Handles driver operations
 */

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Require authentication for all operations
requireAuth();

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            listDrivers();
        } elseif ($action === 'get') {
            getDriver();
        } elseif ($action === 'available') {
            getAvailableDrivers();
        } else {
            sendError('Invalid action', 400);
        }
        break;
    
    case 'PUT':
        if ($action === 'update') {
            updateDriver();
        } else {
            sendError('Invalid action', 400);
        }
        break;
    
    default:
        sendError('Method not allowed', 405);
}

function listDrivers() {
    requireAdmin();
    
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    $status = $_GET['status'] ?? 'all';
    
    $query = "SELECT d.*, u.id as user_id, u.username, u.full_name, u.email,
              (SELECT COUNT(*) FROM packages WHERE driver_id = d.id AND status != 'delivered') as active_packages,
              d.total_deliveries
              FROM drivers d
              INNER JOIN users u ON d.user_id = u.id
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($status !== 'all') {
        $query .= " AND d.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    $query .= " ORDER BY u.full_name ASC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $drivers = [];
    while ($row = $result->fetch_assoc()) {
        $drivers[] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'name' => $row['full_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'vehicle_number' => $row['vehicle_number'],
            'status' => $row['status'],
            'active_packages' => (int)$row['active_packages'],
            'total_deliveries' => (int)$row['total_deliveries']
        ];
    }
    
    sendSuccess('Drivers retrieved successfully', $drivers);
    
    $stmt->close();
    closeDBConnection($conn);
}

function getDriver() {
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    $driverId = $_GET['id'] ?? null;
    
    // If driver, get their own info
    if (isDriver() && !$driverId) {
        $stmt = $conn->prepare("SELECT d.*, u.id as user_id, u.username, u.full_name, u.email
                                FROM drivers d
                                INNER JOIN users u ON d.user_id = u.id
                                WHERE d.user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
    } else {
        if (!$driverId) {
            sendError('Driver ID is required', 400);
        }
        requireAdmin();
        $stmt = $conn->prepare("SELECT d.*, u.id as user_id, u.username, u.full_name, u.email
                                FROM drivers d
                                INNER JOIN users u ON d.user_id = u.id
                                WHERE d.id = ?");
        $stmt->bind_param("i", $driverId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendError('Driver not found', 404);
    }
    
    $row = $result->fetch_assoc();
    
    // Get active packages count
    $packageStmt = $conn->prepare("SELECT COUNT(*) as count FROM packages WHERE driver_id = ? AND status != 'delivered'");
    $packageStmt->bind_param("i", $row['id']);
    $packageStmt->execute();
    $packageResult = $packageStmt->get_result();
    $activePackages = $packageResult->fetch_assoc()['count'];
    
    sendSuccess('Driver retrieved successfully', [
        'id' => $row['id'],
        'user_id' => $row['user_id'],
        'username' => $row['username'],
        'name' => $row['full_name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'vehicle_number' => $row['vehicle_number'],
        'status' => $row['status'],
        'active_packages' => (int)$activePackages,
        'total_deliveries' => (int)$row['total_deliveries']
    ]);
    
    $stmt->close();
    $packageStmt->close();
    closeDBConnection($conn);
}

function getAvailableDrivers() {
    requireAdmin();
    
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    $stmt = $conn->prepare("SELECT d.*, u.full_name, u.email,
                           (SELECT COUNT(*) FROM packages WHERE driver_id = d.id AND status != 'delivered') as active_packages
                           FROM drivers d
                           INNER JOIN users u ON d.user_id = u.id
                           WHERE d.status = 'available'
                           ORDER BY active_packages ASC, u.full_name ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $drivers = [];
    while ($row = $result->fetch_assoc()) {
        $drivers[] = [
            'id' => $row['id'],
            'name' => $row['full_name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'vehicle_number' => $row['vehicle_number'],
            'active_packages' => (int)$row['active_packages']
        ];
    }
    
    sendSuccess('Available drivers retrieved successfully', $drivers);
    
    $stmt->close();
    closeDBConnection($conn);
}

function updateDriver() {
    $input = json_decode(file_get_contents('php://input'), true);
    $driverId = $_GET['id'] ?? null;
    
    // If driver, only allow updating their own status
    if (isDriver()) {
        $conn = getDBConnection();
        if (!$conn) {
            sendError('Database connection failed', 500);
        }
        
        $stmt = $conn->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            sendError('Driver not found', 404);
        }
        
        $driverData = $result->fetch_assoc();
        $driverId = $driverData['id'];
        
        // Drivers can only update their status
        if (isset($input['status'])) {
            $status = $input['status'];
            $updateStmt = $conn->prepare("UPDATE drivers SET status = ? WHERE id = ?");
            $updateStmt->bind_param("si", $status, $driverId);
            
            if ($updateStmt->execute()) {
                sendSuccess('Driver status updated successfully');
            } else {
                sendError('Failed to update driver status', 500);
            }
            $updateStmt->close();
        } else {
            sendError('Status update required', 400);
        }
        
        $stmt->close();
        closeDBConnection($conn);
    } else {
        requireAdmin();
        
        if (!$driverId) {
            sendError('Driver ID is required', 400);
        }
        
        $conn = getDBConnection();
        if (!$conn) {
            sendError('Database connection failed', 500);
        }
        
        $updates = [];
        $params = [];
        $types = "";
        
        if (isset($input['status'])) {
            $updates[] = "status = ?";
            $params[] = $input['status'];
            $types .= "s";
        }
        if (isset($input['phone'])) {
            $updates[] = "phone = ?";
            $params[] = $input['phone'];
            $types .= "s";
        }
        if (isset($input['vehicle_number'])) {
            $updates[] = "vehicle_number = ?";
            $params[] = $input['vehicle_number'];
            $types .= "s";
        }
        
        if (empty($updates)) {
            sendError('No fields to update', 400);
        }
        
        $params[] = $driverId;
        $types .= "i";
        
        $updateQuery = "UPDATE drivers SET " . implode(", ", $updates) . " WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param($types, ...$params);
        
        if ($updateStmt->execute()) {
            sendSuccess('Driver updated successfully');
        } else {
            sendError('Failed to update driver', 500);
        }
        
        $updateStmt->close();
        closeDBConnection($conn);
    }
}

?>

