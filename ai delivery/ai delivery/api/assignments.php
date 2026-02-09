<?php
/**
 * Assignments API
 * Handles package assignment to drivers
 */

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Require admin for assignments
requireAdmin();

switch ($method) {
    case 'POST':
        if ($action === 'assign') {
            assignPackage();
        } else {
            sendError('Invalid action', 400);
        }
        break;
    
    case 'DELETE':
        if ($action === 'unassign') {
            unassignPackage();
        } else {
            sendError('Invalid action', 400);
        }
        break;
    
    default:
        sendError('Method not allowed', 405);
}

function assignPackage() {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $packageId = $input['package_id'] ?? null;
    $driverId = $input['driver_id'] ?? null;
    
    if (!$packageId || !$driverId) {
        sendError('Package ID and Driver ID are required', 400);
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    // Verify package exists - try to find by package_id first (string), then by id (integer)
    $package = null;
    
    // First try to find by package_id (string) - this is the most common case
    $stmt = $conn->prepare("SELECT id, package_id, status FROM packages WHERE package_id = ?");
    $stmt->bind_param("s", $packageId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $package = $result->fetch_assoc();
    }
    $stmt->close();
    
    // If not found by package_id and packageId is numeric, try by numeric id
    if (!$package && is_numeric($packageId)) {
        $stmt = $conn->prepare("SELECT id, package_id, status FROM packages WHERE id = ?");
        $stmt->bind_param("i", $packageId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $package = $result->fetch_assoc();
        }
        $stmt->close();
    }
    
    if (!$package) {
        sendError('Package not found: ' . $packageId, 404);
    }
    
    if ($package['status'] !== 'pending') {
        sendError('Package is not available for assignment. Current status: ' . $package['status'], 400);
    }
    
    // Verify driver exists and is available
    $driverId = (int)$driverId;
    $driverStmt = $conn->prepare("SELECT id, status FROM drivers WHERE id = ?");
    $driverStmt->bind_param("i", $driverId);
    $driverStmt->execute();
    $driverResult = $driverStmt->get_result();
    
    if ($driverResult->num_rows === 0) {
        sendError('Driver not found with ID: ' . $driverId, 404);
    }
    
    $driver = $driverResult->fetch_assoc();
    
    if ($driver['status'] !== 'available') {
        sendError('Driver is not available. Current status: ' . $driver['status'], 400);
    }
    
    // Assign package using the numeric package id from database
    $packageDbId = (int)$package['id'];
    $assignStmt = $conn->prepare("UPDATE packages SET driver_id = ?, status = 'assigned', assigned_at = NOW() WHERE id = ?");
    $assignStmt->bind_param("ii", $driverId, $packageDbId);
    
    if ($assignStmt->execute()) {
        // Create notification for driver
        $userStmt = $conn->prepare("SELECT user_id FROM drivers WHERE id = ?");
        $userStmt->bind_param("i", $driverId);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        if ($userResult->num_rows > 0) {
            $userData = $userResult->fetch_assoc();
            $notifStmt = $conn->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'assignment')");
            $message = "New package assigned: " . $package['package_id'];
            $notifStmt->bind_param("is", $userData['user_id'], $message);
            $notifStmt->execute();
            $notifStmt->close();
        }
        $userStmt->close();
        
        sendSuccess('Package assigned successfully', [
            'package_id' => $package['package_id'],
            'driver_id' => $driverId
        ]);
    } else {
        sendError('Failed to assign package: ' . $conn->error, 500);
    }
    
    $driverStmt->close();
    $assignStmt->close();
    closeDBConnection($conn);
}

function unassignPackage() {
    $packageId = $_GET['id'] ?? null;
    
    if (!$packageId) {
        sendError('Package ID is required', 400);
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    $stmt = $conn->prepare("UPDATE packages SET driver_id = NULL, status = 'pending', assigned_at = NULL WHERE id = ? OR package_id = ?");
    $stmt->bind_param("is", $packageId, $packageId);
    
    if ($stmt->execute()) {
        sendSuccess('Package unassigned successfully');
    } else {
        sendError('Failed to unassign package', 500);
    }
    
    $stmt->close();
    closeDBConnection($conn);
}

?>

