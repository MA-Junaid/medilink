<?php
session_start();
include '../../conn.php'; 

header('Content-Type: application/json');

$appointmentId = $_GET['appointment_id'] ?? null;
$lastMessageId = $_GET['last_id'] ?? 0;

if (!$appointmentId) {
    echo json_encode([]);
    exit();
}

// Fetch messages greater than the last received message ID
$fetchQuery = $con->prepare("
    SELECT row_id AS message_id, sender_id, message, sent_at 
    FROM chat_messages 
    WHERE appointment_id = ? AND row_id > ?
    ORDER BY row_id ASC
");
$fetchQuery->bind_param("si", $appointmentId, $lastMessageId); 
$fetchQuery->execute();
$result = $fetchQuery->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($messages);

$fetchQuery->close();
$con->close();
?>