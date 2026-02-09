<?php
/**
 * Packages API
 * Handles package CRUD operations
 */

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Require authentication for all operations
requireAuth();

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            listPackages();
        } elseif ($action === 'get') {
            getPackage();
        } elseif ($action === 'stats') {
            getPackageStats();
        } else {
            sendError('Invalid action', 400);
        }
        break;
    
    case 'POST':
        if ($action === 'create') {
            requireAdmin();
            createPackage();
        } else {
            sendError('Invalid action', 400);
        }
        break;
    
    case 'PUT':
        if ($action === 'update') {
            updatePackage();
        } else {
            sendError('Invalid action', 400);
        }
        break;
    
    case 'DELETE':
        if ($action === 'delete') {
            requireAdmin();
            deletePackage();
        } else {
            sendError('Invalid action', 400);
        }
        break;
    
    default:
        sendError('Method not allowed', 405);
}

function listPackages() {
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    $status = $_GET['status'] ?? 'all';
    $driverId = $_GET['driver_id'] ?? null;
    
    // If driver, only show their packages
    if (isDriver()) {
        $driverStmt = $conn->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $driverStmt->bind_param("i", $_SESSION['user_id']);
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        if ($driverResult->num_rows > 0) {
            $driverData = $driverResult->fetch_assoc();
            $driverId = $driverData['id'];
        } else {
            sendSuccess('No packages found', []);
            return;
        }
        $driverStmt->close();
    }
    
    $query = "SELECT p.*, d.user_id as driver_user_id, u.full_name as driver_name 
              FROM packages p 
              LEFT JOIN drivers d ON p.driver_id = d.id 
              LEFT JOIN users u ON d.user_id = u.id 
              WHERE 1=1";
    
    $params = [];
    $types = "";
    
    if ($status !== 'all') {
        $query .= " AND p.status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($driverId !== null) {
        $query .= " AND p.driver_id = ?";
        $params[] = $driverId;
        $types .= "i";
    }
    
    $query .= " ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $packages = [];
    while ($row = $result->fetch_assoc()) {
        $packages[] = [
            'id' => $row['id'],
            'package_id' => $row['package_id'],
            'destination' => $row['destination'],
            'recipient_name' => $row['recipient_name'],
            'recipient_phone' => $row['recipient_phone'],
            'status' => $row['status'],
            'driver_id' => $row['driver_id'],
            'driver_name' => $row['driver_name'] ?? null,
            'assigned_at' => $row['assigned_at'],
            'picked_up_at' => $row['picked_up_at'],
            'in_transit_at' => $row['in_transit_at'],
            'delivered_at' => $row['delivered_at'],
            'notes' => $row['notes'],
            'created_at' => $row['created_at']
        ];
    }
    
    sendSuccess('Packages retrieved successfully', $packages);
    
    $stmt->close();
    closeDBConnection($conn);
}

function getPackage() {
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    $packageId = $_GET['id'] ?? null;
    if (!$packageId) {
        sendError('Package ID is required', 400);
    }
    
    $stmt = $conn->prepare("SELECT p.*, d.user_id as driver_user_id, u.full_name as driver_name 
                            FROM packages p 
                            LEFT JOIN drivers d ON p.driver_id = d.id 
                            LEFT JOIN users u ON d.user_id = u.id 
                            WHERE p.id = ? OR p.package_id = ?");
    $stmt->bind_param("is", $packageId, $packageId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendError('Package not found', 404);
    }
    
    $row = $result->fetch_assoc();
    
    // If driver, verify they own this package
    if (isDriver()) {
        $driverStmt = $conn->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $driverStmt->bind_param("i", $_SESSION['user_id']);
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        if ($driverResult->num_rows > 0) {
            $driverData = $driverResult->fetch_assoc();
            if ($row['driver_id'] != $driverData['id']) {
                sendError('Access denied', 403);
            }
        }
        $driverStmt->close();
    }
    
    sendSuccess('Package retrieved successfully', [
        'id' => $row['id'],
        'package_id' => $row['package_id'],
        'destination' => $row['destination'],
        'recipient_name' => $row['recipient_name'],
        'recipient_phone' => $row['recipient_phone'],
        'status' => $row['status'],
        'driver_id' => $row['driver_id'],
        'driver_name' => $row['driver_name'] ?? null,
        'assigned_at' => $row['assigned_at'],
        'picked_up_at' => $row['picked_up_at'],
        'in_transit_at' => $row['in_transit_at'],
        'delivered_at' => $row['delivered_at'],
        'notes' => $row['notes'],
        'created_at' => $row['created_at']
    ]);
    
    $stmt->close();
    closeDBConnection($conn);
}

function getPackageStats() {
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    $stats = [];
    
    // Total packages
    $result = $conn->query("SELECT COUNT(*) as total FROM packages");
    $stats['total'] = $result->fetch_assoc()['total'];
    
    // By status
    $result = $conn->query("SELECT status, COUNT(*) as count FROM packages GROUP BY status");
    while ($row = $result->fetch_assoc()) {
        $stats[$row['status']] = (int)$row['count'];
    }
    
    sendSuccess('Stats retrieved successfully', $stats);
    closeDBConnection($conn);
}

function createPackage() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $packageId = trim($input['package_id'] ?? '');
    $destination = trim($input['destination'] ?? '');
    $recipientName = trim($input['recipient_name'] ?? '');
    
    if (empty($packageId) || empty($destination) || empty($recipientName)) {
        sendError('Package ID, destination, and recipient name are required', 400);
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    // Check if package_id already exists
    $checkStmt = $conn->prepare("SELECT id FROM packages WHERE package_id = ?");
    $checkStmt->bind_param("s", $packageId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    if ($checkResult->num_rows > 0) {
        sendError('Package ID already exists. Please use a different ID.', 400);
    }
    $checkStmt->close();
    
    $stmt = $conn->prepare("INSERT INTO packages (package_id, destination, recipient_name, recipient_phone, notes) VALUES (?, ?, ?, ?, ?)");
    $recipientPhone = !empty($input['recipient_phone']) ? trim($input['recipient_phone']) : null;
    $notes = !empty($input['notes']) ? trim($input['notes']) : null;
    $stmt->bind_param("sssss", $packageId, $destination, $recipientName, $recipientPhone, $notes);
    
    if ($stmt->execute()) {
        $dbId = $conn->insert_id;
        
        // Create notification for admin (use the package_id string, not database ID)
        $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $adminStmt->execute();
        $adminResult = $adminStmt->get_result();
        if ($adminResult->num_rows > 0) {
            $adminData = $adminResult->fetch_assoc();
            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'package')");
            $message = "New package created: " . $packageId;
            $notifStmt->bind_param("is", $adminData['id'], $message);
            $notifStmt->execute();
            $notifStmt->close();
        }
        $adminStmt->close();
        
        sendSuccess('Package created successfully', ['id' => $dbId, 'package_id' => $packageId]);
    } else {
        // Check if it's a duplicate package_id error
        if ($conn->errno == 1062) {
            sendError('Package ID already exists. Please use a different ID.', 400);
        } else {
            sendError('Failed to create package: ' . $conn->error, 500);
        }
    }
    
    $stmt->close();
    closeDBConnection($conn);
}

function updatePackage() {
    $input = json_decode(file_get_contents('php://input'), true);
    $packageId = $_GET['id'] ?? $input['id'] ?? null;
    
    if (!$packageId) {
        sendError('Package ID is required', 400);
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    // Get current package
    $stmt = $conn->prepare("SELECT * FROM packages WHERE id = ? OR package_id = ?");
    $stmt->bind_param("is", $packageId, $packageId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendError('Package not found', 404);
    }
    
    $package = $result->fetch_assoc();
    
    // If driver, verify ownership and only allow status updates
    if (isDriver()) {
        $driverStmt = $conn->prepare("SELECT id FROM drivers WHERE user_id = ?");
        $driverStmt->bind_param("i", $_SESSION['user_id']);
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        if ($driverResult->num_rows > 0) {
            $driverData = $driverResult->fetch_assoc();
            if ($package['driver_id'] != $driverData['id']) {
                sendError('Access denied', 403);
            }
        }
        $driverStmt->close();
        
        // Drivers can only update status
        if (isset($input['status'])) {
            $newStatus = $input['status'];
            $updateQuery = "UPDATE packages SET status = ?";
            $params = [$newStatus];
            $types = "s";
            
            // Update timestamps based on status
            if ($newStatus === 'picked-up' && !$package['picked_up_at']) {
                $updateQuery .= ", picked_up_at = NOW()";
            } elseif ($newStatus === 'in-transit' && !$package['in_transit_at']) {
                $updateQuery .= ", in_transit_at = NOW()";
            } elseif ($newStatus === 'delivered' && !$package['delivered_at']) {
                $updateQuery .= ", delivered_at = NOW()";
                // Update driver total deliveries
                $conn->query("UPDATE drivers SET total_deliveries = total_deliveries + 1 WHERE id = " . (int)$package['driver_id']);
                
                // Create notification for admin
                $adminStmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
                $adminStmt->execute();
                $adminResult = $adminStmt->get_result();
                if ($adminResult->num_rows > 0) {
                    $adminData = $adminResult->fetch_assoc();
                    $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'delivery')");
                    $message = "Package " . $package['package_id'] . " has been delivered";
                    $notifStmt->bind_param("is", $adminData['id'], $message);
                    $notifStmt->execute();
                    $notifStmt->close();
                }
                $adminStmt->close();
            }
            
            $updateQuery .= " WHERE id = ?";
            $params[] = $package['id'];
            $types .= "i";
            
            $updateStmt = $conn->prepare($updateQuery);
            $updateStmt->bind_param($types, ...$params);
            
            if ($updateStmt->execute()) {
                sendSuccess('Package status updated successfully');
            } else {
                sendError('Failed to update package', 500);
            }
            $updateStmt->close();
        } else {
            sendError('Status update required', 400);
        }
    } else {
        // Admin can update all fields
        $updates = [];
        $params = [];
        $types = "";
        
        if (isset($input['destination'])) {
            $updates[] = "destination = ?";
            $params[] = $input['destination'];
            $types .= "s";
        }
        if (isset($input['recipient_name'])) {
            $updates[] = "recipient_name = ?";
            $params[] = $input['recipient_name'];
            $types .= "s";
        }
        if (isset($input['recipient_phone'])) {
            $updates[] = "recipient_phone = ?";
            $params[] = $input['recipient_phone'];
            $types .= "s";
        }
        if (isset($input['status'])) {
            $updates[] = "status = ?";
            $params[] = $input['status'];
            $types .= "s";
        }
        if (isset($input['notes'])) {
            $updates[] = "notes = ?";
            $params[] = $input['notes'];
            $types .= "s";
        }
        
        if (empty($updates)) {
            sendError('No fields to update', 400);
        }
        
        $params[] = $package['id'];
        $types .= "i";
        
        $updateQuery = "UPDATE packages SET " . implode(", ", $updates) . " WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param($types, ...$params);
        
        if ($updateStmt->execute()) {
            sendSuccess('Package updated successfully');
        } else {
            sendError('Failed to update package', 500);
        }
        $updateStmt->close();
    }
    
    $stmt->close();
    closeDBConnection($conn);
}

function deletePackage() {
    $packageId = $_GET['id'] ?? null;
    
    if (!$packageId) {
        sendError('Package ID is required', 400);
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    $stmt = $conn->prepare("DELETE FROM packages WHERE id = ? OR package_id = ?");
    $stmt->bind_param("is", $packageId, $packageId);
    
    if ($stmt->execute()) {
        sendSuccess('Package deleted successfully');
    } else {
        sendError('Failed to delete package', 500);
    }
    
    $stmt->close();
    closeDBConnection($conn);
}

?>

