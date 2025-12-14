<?php
session_start();
include '../../conn.php'; 

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit();
}

$appointmentId = $_POST['appointment_id'] ?? null;
$appointmentId2 = $_POST['appointment_id'];


$senderId = $_POST['sender_id'] ?? null;

$message = $_POST['message'] ?? null;

// Generate a new message_id in the format: {appointment_id}-{n}
function generateMessageId($con, $appointmentId) {
    $stmt = $con->prepare("SELECT COUNT(*) as count FROM chat_messages WHERE appointment_id = ?");
    $stmt->bind_param("i", $appointmentId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $count = $result['count'] ?? 0;
    $stmt->close();
    // Message number is count + 1
    return $appointmentId . '-' . ($count + 1);
}

$messageId = generateMessageId($con, $appointmentId);

if (!$appointmentId || !$senderId || empty($message)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing chat data.']);
    exit();
}

echo $appointmentId."---";  
$insertQuery = $con->prepare("
    INSERT INTO chat_messages (message_id, appointment_id, sender_id, message)
    VALUES (?, ?, ?, ?)
");
$insertQuery->bind_param("ssss", $messageId, $appointmentId, $senderId, $message); 

if ($insertQuery->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $con->error]);
}

$insertQuery->close();
$con->close();
?>