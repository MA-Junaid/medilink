<?php
session_start();
include '../../conn.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php");
    exit();
}

// Handle Verification Action
$msg = "";
$msgType = "";

if (isset($_GET['id']) && isset($_GET['action'])) {
    $reqId = $_GET['id'];
    $action = $_GET['action'];
    $validActions = ['approve', 'reject'];

    if (in_array($action, $validActions)) {
        // Fetch request details primarily filtering by row_id or request_id? 
        // The index.php passed request_id (string).
        
        $fetchStmt = $con->prepare("SELECT doctor_id FROM doctor_verification_requests WHERE request_id = ?");
        $fetchStmt->bind_param("s", $reqId);
        $fetchStmt->execute();
        $res = $fetchStmt->get_result();
        
        if ($res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $docId = $row['doctor_id'];
            
            // Start Transaction
            $con->begin_transaction();
            try {
                // Update Request Status
                $newStatus = ($action === 'approve') ? 'approved' : 'rejected';
                $updateReq = $con->prepare("UPDATE doctor_verification_requests SET status = ?, verified_at = NOW() WHERE request_id = ?");
                $updateReq->bind_param("ss", $newStatus, $reqId);
                $updateReq->execute();
                
                // If Approved, Update Doctors Table
                if ($action === 'approve') {
                    $verifyDoc = $con->prepare("UPDATE doctors SET verified = 1 WHERE doctor_id = ?");
                    $verifyDoc->bind_param("s", $docId);
                    $verifyDoc->execute();
                }
                
                $con->commit();
                
                // Redirect back to referring page (index or verifications)
                $redirect = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'verifications.php';
                header("Location: $redirect");
                exit();
                
            } catch (Exception $e) {
                $con->rollback();
                die("Error processing verification: " . $e->getMessage());
            }
        }
    }
} else {
    // If accessed directly without params, redirect
    header("Location: verifications.php");
    exit();
}
?>
