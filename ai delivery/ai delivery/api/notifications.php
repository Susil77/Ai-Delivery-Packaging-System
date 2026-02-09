<?php
/**
 * Notifications API
 * Handles user notifications
 */

require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Require authentication
requireAuth();

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            listNotifications();
        } elseif ($action === 'unread') {
            getUnreadCount();
        } else {
            sendError('Invalid action', 400);
        }
        break;
    
    case 'PUT':
        if ($action === 'read') {
            markAsRead();
        } else {
            sendError('Invalid action', 400);
        }
        break;
    
    default:
        sendError('Method not allowed', 405);
}

function listNotifications() {
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
    
    $query = "SELECT * FROM notifications WHERE user_id = ?";
    $params = [$_SESSION['user_id']];
    $types = "i";
    
    if ($unreadOnly) {
        $query .= " AND is_read = FALSE";
    }
    
    $query .= " ORDER BY created_at DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => $row['id'],
            'message' => $row['message'],
            'type' => $row['type'],
            'is_read' => (bool)$row['is_read'],
            'created_at' => $row['created_at']
        ];
    }
    
    sendSuccess('Notifications retrieved successfully', $notifications);
    
    $stmt->close();
    closeDBConnection($conn);
}

function getUnreadCount() {
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = FALSE");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    
    sendSuccess('Unread count retrieved', ['count' => (int)$count]);
    
    $stmt->close();
    closeDBConnection($conn);
}

function markAsRead() {
    $input = json_decode(file_get_contents('php://input'), true);
    $notificationId = $input['id'] ?? $_GET['id'] ?? null;
    
    if (!$notificationId) {
        sendError('Notification ID is required', 400);
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        sendError('Database connection failed', 500);
    }
    
    // Verify notification belongs to user
    $stmt = $conn->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notificationId, $_SESSION['user_id']);
    
    if ($stmt->execute()) {
        sendSuccess('Notification marked as read');
    } else {
        sendError('Failed to update notification', 500);
    }
    
    $stmt->close();
    closeDBConnection($conn);
}

?>

