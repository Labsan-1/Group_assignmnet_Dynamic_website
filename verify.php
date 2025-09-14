<?php
// verify.php — mark account verified then redirect to Login.php

// Show errors while developing (optional; remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'db.php';

// small helper
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Require token
if (!isset($_GET['token']) || $_GET['token'] === '') {
    // No token -> go to login with error
    header("Location: Login.php?verified=0");
    exit;
}

$token = $_GET['token'];

// 1) Look for an unverified account with this token
$stmt = $con->prepare("SELECT user_id FROM users WHERE verify_token = ? AND is_verified = 0 LIMIT 1");
if (!$stmt) {
    // If prepare fails, show debug if requested
    if (isset($_GET['debug'])) {
        echo "SQL prepare error: " . h($con->error);
        exit;
    }
    header("Location: Login.php?verified=0");
    exit;
}

$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // 2) Mark as verified
    $uid = (int)$row['user_id'];
    $upd = $con->prepare("UPDATE users SET is_verified = 1, verify_token = NULL WHERE user_id = ?");
    if ($upd) {
        $upd->bind_param("i", $uid);
        $upd->execute();
        $upd->close();
        // Success → redirect to login
        header("Location: Login.php?verified=1");
        exit;
    } else {
        if (isset($_GET['debug'])) {
            echo "SQL update error: " . h($con->error);
            exit;
        }
        header("Location: Login.php?verified=0");
        exit;
    }
}

// 3) Not found as unverified; maybe already verified or invalid token
// If you want a readable page while testing, visit with &debug=1
if (isset($_GET['debug'])) {
    // Try to tell if it was already verified
    $stmt2 = $con->prepare("SELECT user_id FROM users WHERE verify_token IS NULL AND is_verified = 1 LIMIT 1");
    // (This is just a heuristic; tokens are cleared after use.)
    echo "This verification link is invalid or already used.";
    exit;
}

// Fall back to login with error flag
header("Location: Login.php?verified=0");
exit;
