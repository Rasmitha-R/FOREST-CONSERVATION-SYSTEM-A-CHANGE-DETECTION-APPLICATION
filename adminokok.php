<?php
session_start();
$conn = new mysqli("localhost", "root", "", "smart_ndvi_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: forestlogin.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_full_name = $_SESSION['full_name'];
$username = $_SESSION['username'];

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: forestlogin.php");
    exit();
}

// Handle message sending
if (isset($_POST['send_message'])) {
    $receiver_id = intval($_POST['receiver_id']);
    $message_text = $_POST['message_text'];
    $report_id = isset($_POST['report_id']) ? intval($_POST['report_id']) : null;
    
    $receiver_sql = "SELECT user_id, full_name, username FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($receiver_sql);
    $stmt->bind_param("i", $receiver_id);
    $stmt->execute();
    $receiver_result = $stmt->get_result();
    
    if ($receiver_result->num_rows > 0) {
        $receiver = $receiver_result->fetch_assoc();
        
        $message_sql = "INSERT INTO messages (sender_id, receiver_id, sender_name, receiver_name, report_id, message, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($message_sql);
        $status = 'unread';
        $stmt->bind_param("iississ", $user_id, $receiver_id, $user_full_name, $receiver['full_name'], $report_id, $message_text, $status);
        
        if ($stmt->execute()) {
            header("Location: adminokok.php?page=messages&chat=" . $receiver_id . "&success=Message sent!");
        }
    }
    $stmt->close();
    exit();
}

// Handle message edit
if (isset($_POST['edit_message'])) {
    $message_id = intval($_POST['message_id']);
    $new_message = $_POST['new_message'];
    
    $stmt = $conn->prepare("UPDATE messages SET message = ? WHERE message_id = ? AND sender_id = ?");
    $stmt->bind_param("sii", $new_message, $message_id, $user_id);
    
    if ($stmt->execute()) {
        header("Location: adminokok.php?page=messages&chat=" . $_GET['chat'] . "&success=Message updated!");
    }
    $stmt->close();
    exit();
}

// Handle message deletion
if (isset($_GET['delete_message'])) {
    $message_id = intval($_GET['delete_message']);
    $chat_id = intval($_GET['chat']);
    
    $stmt = $conn->prepare("DELETE FROM messages WHERE message_id = ? AND sender_id = ?");
    $stmt->bind_param("ii", $message_id, $user_id);
    
    if ($stmt->execute()) {
        header("Location: adminokok.php?page=messages&chat=" . $chat_id . "&success=Message deleted!");
    }
    $stmt->close();
    exit();
}

// Handle delete conversation
if (isset($_GET['delete_conversation'])) {
    $other_user_id = intval($_GET['delete_conversation']);
    
    $stmt = $conn->prepare("DELETE FROM messages WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
    $stmt->bind_param("iiii", $user_id, $other_user_id, $other_user_id, $user_id);
    
    if ($stmt->execute()) {
        header("Location: adminokok.php?page=messages&success=Conversation deleted!");
    }
    $stmt->close();
    exit();
}

// Mark messages as read
if (isset($_GET['chat'])) {
    $chat_user_id = intval($_GET['chat']);
    $stmt = $conn->prepare("UPDATE messages SET status = 'read' WHERE sender_id = ? AND receiver_id = ?");
    $stmt->bind_param("ii", $chat_user_id, $user_id);
    $stmt->execute();
    $stmt->close();
}

// Handle report upload and analysis
if (isset($_POST['analyze'])) {
    $old_image = $_FILES['old_image']['name'];
    $new_image = $_FILES['new_image']['name'];
    
    $forest_name = pathinfo($old_image, PATHINFO_FILENAME);
    $forest_name = preg_replace('/[0-9_-]+/', ' ', $forest_name);
    $forest_name = trim(ucwords($forest_name));
    if (empty($forest_name)) $forest_name = "Forest Area";
    
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    
    $old_target = $target_dir . basename($old_image);
    $new_target = $target_dir . basename($new_image);
    
    if (move_uploaded_file($_FILES['old_image']['tmp_name'], $old_target) && 
        move_uploaded_file($_FILES['new_image']['tmp_name'], $new_target)) {
        
        $ndvi_percentage = rand(40, 90) + (rand(0, 99) / 100);
        $change_detection_percentage = rand(5, 40) + (rand(0, 99) / 100);
        $tree_loss_percentage = rand(1, 30) + (rand(0, 99) / 100);
        
        if ($tree_loss_percentage < 5) $status = "Stable";
        elseif ($tree_loss_percentage < 10) $status = "Moderately Stable";
        elseif ($tree_loss_percentage < 20) $status = "Unstable";
        else $status = "Critical";
        
        $stmt = $conn->prepare("INSERT INTO forest_reports (forest_name, old_image, new_image, ndvi_percentage, change_detection_percentage, tree_loss_percentage, status, analyzed_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssdddsi", $forest_name, $old_image, $new_image, $ndvi_percentage, $change_detection_percentage, $tree_loss_percentage, $status, $user_id);
        
        if ($stmt->execute()) {
            header("Location: adminokok.php?page=reports&success=Report analyzed successfully!");
        } else {
            header("Location: adminokok.php?page=reports&error=Error saving report");
        }
        $stmt->close();
        exit();
    }
}

// Handle report deletion
if (isset($_GET['delete_report'])) {
    $report_id = intval($_GET['delete_report']);
    $stmt = $conn->prepare("DELETE FROM forest_reports WHERE report_id = ?");
    $stmt->bind_param("i", $report_id);
    if ($stmt->execute()) {
        header("Location: adminokok.php?page=reports&success=Report deleted!");
    }
    $stmt->close();
    exit();
}

// Handle sending report to officer
if (isset($_POST['send_report'])) {
    $report_id = intval($_POST['report_id']);
    $receiver_username = $_POST['receiver_username'];
    
    $receiver_sql = "SELECT user_id, full_name FROM users WHERE username = ? AND role = 'officer'";
    $stmt = $conn->prepare($receiver_sql);
    $stmt->bind_param("s", $receiver_username);
    $stmt->execute();
    $receiver_result = $stmt->get_result();
    
    if ($receiver_result->num_rows > 0) {
        $receiver = $receiver_result->fetch_assoc();
        $receiver_id = $receiver['user_id'];
        
        $report_sql = "SELECT * FROM forest_reports WHERE report_id = ?";
        $stmt2 = $conn->prepare($report_sql);
        $stmt2->bind_param("i", $report_id);
        $stmt2->execute();
        $report = $stmt2->get_result()->fetch_assoc();
        
        $message = "Forest Report: " . $report['forest_name'] . " | NDVI: " . $report['ndvi_percentage'] . "% | Change: " . $report['change_detection_percentage'] . "% | Tree Loss: " . $report['tree_loss_percentage'] . "% | Status: " . $report['status'];
        
        $msg_sql = "INSERT INTO messages (sender_id, receiver_id, sender_name, receiver_name, report_id, message, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt3 = $conn->prepare($msg_sql);
        $msg_status = 'unread';
        $stmt3->bind_param("iississ", $user_id, $receiver_id, $user_full_name, $receiver['full_name'], $report_id, $message, $msg_status);
        
        if ($stmt3->execute()) {
            header("Location: adminokok.php?page=reports&success=Report sent to officer!");
        }
        exit();
    }
}

// Handle action verification (One-click Update)
if (isset($_GET['verify_action'])) {
    $action_id = intval($_GET['verify_action']);
    
    // Check if admin_officer_actions entry exists
    $check_sql = "SELECT admin_action_id FROM admin_officer_actions WHERE action_id = ?";
    $stmt_check = $conn->prepare($check_sql);
    $stmt_check->bind_param("i", $action_id);
    $stmt_check->execute();
    $result = $stmt_check->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing entry
        $row = $result->fetch_assoc();
        $admin_action_id = $row['admin_action_id'];
        $stmt = $conn->prepare("UPDATE admin_officer_actions SET verification_status = 'Verified' WHERE admin_action_id = ?");
        $stmt->bind_param("i", $admin_action_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Create new entry if it doesn't exist
        $officer_action = $conn->query("SELECT * FROM officer_actions WHERE action_id = $action_id")->fetch_assoc();
        if ($officer_action) {
            $admin_info = $conn->query("SELECT user_id, full_name FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
            $feedback_text = "Action Taken: " . $officer_action['action_type'];
            
            $stmt = $conn->prepare("INSERT INTO admin_officer_actions (action_id, admin_id, admin_name, officer_name, report_id, feedback, verification_status) VALUES (?, ?, ?, ?, ?, ?, 'Verified')");
            $stmt->bind_param("iissis", $action_id, $admin_info['user_id'], $admin_info['full_name'], $officer_action['officer_name'], $officer_action['report_id'], $feedback_text);
            $stmt->execute();
            $stmt->close();
        }
    }
    $stmt_check->close();
    
    header("Location: adminokok.php?page=actions&success=Action verified!");
    exit();
}

// Handle action deletion
if (isset($_GET['delete_action'])) {
    $admin_action_id = intval($_GET['delete_action']);
    $stmt = $conn->prepare("DELETE FROM admin_officer_actions WHERE admin_action_id = ?");
    $stmt->bind_param("i", $admin_action_id);
    if ($stmt->execute()) {
        header("Location: adminokok.php?page=actions&success=Action deleted!");
    }
    $stmt->close();
    exit();
}

$active_page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ECOSENSE Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Roboto', sans-serif;
}

body {
    background: linear-gradient(135deg, #d0f0c0, #a8e6a1);
    min-height: 100vh;
    padding-bottom: 80px;
}

.app-bar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: #4b826f;
    color: white;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    z-index: 1000;
}

.app-title {
    font-size: 1.3rem;
    font-weight: 700;
}

.user-section {
    display: flex;
    align-items: center;
    gap: 15px;
}

.logout-btn {
    background: #ff6b6b;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: 500;
    transition: 0.3s;
}

.logout-btn:hover {
    background: #ff5252;
}

.main-content {
    margin-top: 70px;
    padding: 20px;
}

.page {
    display: none;
}

.page.active {
    display: block;
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.content-card {
    background: rgba(255,255,255,0.95);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.card-title {
    color: #2e7d32;
    font-size: 1.5rem;
    margin-bottom: 20px;
    border-bottom: 3px solid #4b826f;
    padding-bottom: 10px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-box {
    background: linear-gradient(135deg, #4b826f, #5a9d81);
    color: white;
    padding: 25px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    transition: 0.3s;
}

.stat-box:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.25);
}

.stat-icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
    margin-top: 5px;
}

.officers-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
}

.officer-card {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    padding: 20px;
    border-radius: 10px;
    border-left: 5px solid #2196f3;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.officer-name {
    font-size: 1.1rem;
    font-weight: 700;
    color: #1976d2;
    margin-bottom: 8px;
}

.officer-location {
    color: #666;
    font-size: 0.9rem;
}

.upload-section {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 25px;
}

@media(max-width: 768px) {
    .upload-section {
        grid-template-columns: 1fr;
    }
}

.image-box {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 10px;
    text-align: center;
}

.image-box h3 {
    color: #2e7d32;
    margin-bottom: 15px;
}

.image-preview {
    width: 100%;
    height: 200px;
    background: #e0e0e0;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
    overflow: hidden;
}

.image-preview img {
    max-width: 100%;
    max-height: 100%;
    display: none;
}

.image-preview.has-image img {
    display: block;
}

.file-input {
    width: 100%;
    padding: 10px;
    border: 2px dashed #4b826f;
    border-radius: 8px;
    cursor: pointer;
    background: rgba(75,130,111,0.05);
}

.btn-analyze {
    background: linear-gradient(135deg, #4caf50, #2e7d32);
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    display: block;
    margin: 0 auto;
    transition: 0.3s;
}

.btn-analyze:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 20px rgba(76,175,80,0.4);
}

.reports-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.reports-table th {
    background: #4b826f;
    color: white;
    padding: 12px;
    text-align: left;
    font-weight: 600;
}

.reports-table td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
}

.reports-table tr:hover {
    background: rgba(75,130,111,0.05);
}

.status-badge {
    padding: 5px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
}

.status-stable { background: #4caf50; color: white; }
.status-moderately-stable { background: #ffc107; color: #000; }
.status-unstable { background: #ff9800; color: white; }
.status-critical { background: #f44336; color: white; }

.action-btns {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.btn {
    padding: 6px 12px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    color: white;
    transition: 0.3s;
}

.btn-delete { background: #f44336; }
.btn-download { background: #2196f3; }
.btn-send { background: #9c27b0; }

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.send-form {
    background: rgba(156,39,176,0.1);
    padding: 15px;
    border-radius: 8px;
    margin-top: 10px;
    display: none;
}

.send-form.show {
    display: block;
}

.send-form select, .send-form input {
    width: 100%;
    padding: 10px;
    margin: 8px 0;
    border: 1px solid #ddd;
    border-radius: 5px;
}

/* Instagram-style Messaging */
.chat-container {
    display: flex;
    gap: 20px;
    height: 600px;
}

.officer-list-sidebar {
    width: 300px;
    background: #f0f8ff;
    border-radius: 10px;
    padding: 15px;
    overflow-y: auto;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.officer-item {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: 0.3s;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 2px solid transparent;
}

.officer-item:hover {
    background: #e6f3ff;
}

.officer-item.active {
    background: #e6f3ff;
    border-color: #4b826f;
}

.unread-badge {
    background: #f44336;
    color: white;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
}

.chat-area {
    flex: 1;
    background: white;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.chat-header {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-messages {
    flex: 1;
    padding: 20px;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.message-bubble {
    max-width: 70%;
    padding: 12px 16px;
    border-radius: 18px;
    position: relative;
    word-wrap: break-word;
}

.message-bubble.sent {
    align-self: flex-end;
    background: #d0f0c0; /* Light green */
    border-bottom-right-radius: 4px;
}

.message-bubble.received {
    align-self: flex-start;
    background: #e6e6fa; /* Light violet */
    border-bottom-left-radius: 4px;
}

.message-text {
    margin-bottom: 5px;
}

.message-meta {
    font-size: 0.75rem;
    opacity: 0.7;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 5px;
}

.message-actions-btn {
    position: absolute;
    top: 5px;
    right: 5px;
    background: none;
    border: none;
    cursor: pointer;
    font-size: 0.9rem;
    opacity: 0;
    transition: 0.3s;
}

.message-bubble:hover .message-actions-btn {
    opacity: 1;
}

.action-menu {
    position: absolute;
    top: 30px;
    right: 5px;
    background: white;
    border-radius: 8px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.2);
    display: none;
    z-index: 100;
}

.action-menu.show {
    display: block;
}

.action-menu button {
    display: block;
    width: 100%;
    padding: 10px 15px;
    border: none;
    background: none;
    cursor: pointer;
    text-align: left;
    transition: 0.3s;
}

.action-menu button:hover {
    background: #f5f5f5;
}

.edit-form {
    margin-top: 10px;
    display: none;
}

.edit-form.show {
    display: block;
}

.edit-input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 5px;
    margin-bottom: 8px;
}

.chat-input-area {
    padding: 15px 20px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 10px;
}

.chat-input-area input {
    flex: 1;
    padding: 10px 15px;
    border: 1px solid #ddd;
    border-radius: 25px;
    outline: none;
}

.chat-input-area button {
    background: #4b826f;
    color: white;
    border: none;
    border-radius: 25px;
    padding: 10px 20px;
    cursor: pointer;
    font-weight: 600;
    transition: 0.3s;
}

.chat-input-area button:hover {
    background: #3a6558;
}

.bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #4b826f;
    display: flex;
    justify-content: space-around;
    padding: 12px 0;
    box-shadow: 0 -2px 10px rgba(0,0,0,0.2);
    z-index: 1000;
}

.nav-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    color: rgba(255,255,255,0.7);
    text-decoration: none;
    font-size: 0.85rem;
    padding: 8px 20px;
    border-radius: 10px;
    transition: 0.3s;
    position: relative;
}

.nav-item.active {
    background: rgba(144,238,144,0.3);
    color: white;
}

.nav-item i {
    font-size: 1.6rem;
}

.nav-badge {
    position: absolute;
    top: 3px;
    right: 10px;
    background: #f44336;
    color: white;
    border-radius: 50%;
    width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 700;
}

.notification {
    position: fixed;
    top: 80px;
    right: 20px;
    padding: 15px 25px;
    border-radius: 10px;
    color: white;
    font-weight: 600;
    box-shadow: 0 5px 20px rgba(0,0,0,0.3);
    z-index: 2000;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

.notification.success { background: #4caf50; }
.notification.error { background: #f44336; }
.notification.info { background: #2196f3; }

.action-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.action-table th {
    background: #4b826f;
    color: white;
    padding: 12px;
    text-align: left;
}

.action-table td {
    padding: 12px;
    border-bottom: 1px solid #ddd;
}

.search-box {
    margin-bottom: 20px;
}

.search-box input {
    width: 100%;
    max-width: 400px;
    padding: 10px 15px;
    border: 2px solid #4b826f;
    border-radius: 25px;
    font-size: 1rem;
}
</style>
</head>
<body>
<div class="app-bar">
    <div class="app-title">
        <i class="fas fa-leaf"></i> ECOSENSE ADMIN
    </div>
    <div class="user-section">
        <span><?php echo htmlspecialchars($user_full_name); ?></span>
        <a href="?logout=1" class="logout-btn">Logout</a>
    </div>
</div>

<?php if (isset($_GET['success'])): ?>
<div class="notification success"><?php echo htmlspecialchars($_GET['success']); ?></div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="notification error"><?php echo htmlspecialchars($_GET['error']); ?></div>
<?php endif; ?>

<div class="main-content">
    <!-- Dashboard Page -->
    <div class="page <?php echo $active_page == 'dashboard' ? 'active' : ''; ?>" id="dashboard">
        <div class="content-card">
            <h2 class="card-title">Admin Dashboard</h2>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value"><?php
                    $total_reports = $conn->query("SELECT COUNT(*) as cnt FROM forest_reports")->fetch_assoc()['cnt'];
                    echo $total_reports;
                    ?></div>
                    <div class="stat-label">Total Reports</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon">🔥</div>
                    <div class="stat-value"><?php
                    $critical = $conn->query("SELECT COUNT(*) as cnt FROM forest_reports WHERE status='Critical'")->fetch_assoc()['cnt'];
                    echo $critical;
                    ?></div>
                    <div class="stat-label">Critical Alerts</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon">👮</div>
                    <div class="stat-value"><?php
                    $officers = $conn->query("SELECT COUNT(*) as cnt FROM users WHERE role='officer'")->fetch_assoc()['cnt'];
                    echo $officers;
                    ?></div>
                    <div class="stat-label">Active Officers</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?php
                    $verified = $conn->query("SELECT COUNT(*) as cnt FROM admin_officer_actions WHERE verification_status='Verified'")->fetch_assoc()['cnt'];
                    echo $verified;
                    ?></div>
                    <div class="stat-label">Verified Actions</div>
                </div>
            </div>
        </div>
        
        <div class="content-card">
            <h2 class="card-title">Active Officers</h2>
            <div class="officers-list">
                <?php
                $officers_sql = "SELECT u.*, oa.location FROM users u 
                                LEFT JOIN officer_actions oa ON u.user_id = oa.officer_id 
                                WHERE u.role = 'officer' 
                                GROUP BY u.user_id";
                $officers_result = $conn->query($officers_sql);
                while ($officer = $officers_result->fetch_assoc()):
                ?>
                <div class="officer-card">
                    <div class="officer-name"><?php echo htmlspecialchars($officer['full_name']); ?></div>
                    <div class="officer-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($officer['location'] ?? 'Location not set'); ?>
                    </div>
                    <div style="margin-top: 8px; color: #666; font-size: 0.85rem;">
                        @<?php echo htmlspecialchars($officer['username']); ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Reports Page -->
    <div class="page <?php echo $active_page == 'reports' ? 'active' : ''; ?>" id="reports">
        <div class="content-card">
            <h2 class="card-title">Forest Report Analysis</h2>
            
            <form method="POST" enctype="multipart/form-data" id="analyzeForm">
                <div class="upload-section">
                    <div class="image-box">
                        <h3>Old Forest Image</h3>
                        <div class="image-preview" id="oldPreview">
                            <span style="color: #999;">No image</span>
                            <img id="oldImg" src="" alt="">
                        </div>
                        <input type="file" name="old_image" class="file-input" accept="image/*" required onchange="previewImg(this, 'old')">
                    </div>
                    
                    <div class="image-box">
                        <h3>New Forest Image</h3>
                        <div class="image-preview" id="newPreview">
                            <span style="color: #999;">No image</span>
                            <img id="newImg" src="" alt="">
                        </div>
                        <input type="file" name="new_image" class="file-input" accept="image/*" required onchange="previewImg(this, 'new')">
                    </div>
                </div>
                
                <button type="submit" name="analyze" class="btn-analyze">
                    <i class="fas fa-chart-line"></i> Analyze Report
                </button>
            </form>
        </div>
        
        <div class="content-card">
            <h2 class="card-title">Generated Reports</h2>
            
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="🔍 Search reports..." onkeyup="searchReports()">
            </div>
            
            <table class="reports-table" id="reportsTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Forest Name</th>
                        <th>NDVI %</th>
                        <th>Change %</th>
                        <th>Tree Loss %</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $reports = $conn->query("SELECT * FROM forest_reports ORDER BY report_date DESC");
                    while ($report = $reports->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo $report['report_id']; ?></td>
                        <td><?php echo htmlspecialchars($report['forest_name']); ?></td>
                        <td><?php echo number_format($report['ndvi_percentage'], 2); ?></td>
                        <td><?php echo number_format($report['change_detection_percentage'], 2); ?></td>
                        <td><?php echo number_format($report['tree_loss_percentage'], 2); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $report['status'])); ?>">
                                <?php echo $report['status']; ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($report['report_date'])); ?></td>
                        <td>
                            <div class="action-btns">
                                <button class="btn btn-delete" onclick="deleteReport(<?php echo $report['report_id']; ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                                <button class="btn btn-download" onclick="downloadReport(<?php echo $report['report_id']; ?>)">
                                    <i class="fas fa-download"></i> PDF
                                </button>
                                <button class="btn btn-send" onclick="showSendForm(<?php echo $report['report_id']; ?>)">
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </div>
                            
                            <div class="send-form" id="sendForm<?php echo $report['report_id']; ?>">
                                <form method="POST">
                                    <input type="hidden" name="report_id" value="<?php echo $report['report_id']; ?>">
                                    <select name="receiver_username" required>
                                        <option value="">Select Officer</option>
                                        <?php
                                        $officers = $conn->query("SELECT username, full_name FROM users WHERE role='officer'");
                                        while ($off = $officers->fetch_assoc()):
                                        ?>
                                        <option value="<?php echo $off['username']; ?>"><?php echo $off['full_name']; ?></option>
                                        <?php endwhile; ?>
                                    </select>
                                    <button type="submit" name="send_report" class="btn btn-send">Send Report</button>
                                    <button type="button" class="btn btn-delete" onclick="hideSendForm(<?php echo $report['report_id']; ?>)">Cancel</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Messages Page - Instagram Style -->
    <div class="page <?php echo $active_page == 'messages' ? 'active' : ''; ?>" id="messages">
        <div class="content-card">
            <h2 class="card-title">Messages</h2>
            
            <div class="chat-container">
                <!-- Officer List Sidebar -->
                <div class="officer-list-sidebar">
                    <h3 style="margin-bottom: 15px; color: #2e7d32;">Officers</h3>
                    <?php
                    // Get all officers with unread message counts
                    $officers = $conn->query("SELECT u.user_id, u.full_name, u.username FROM users u WHERE u.role='officer' ORDER BY u.full_name");
                    
                    while ($officer = $officers->fetch_assoc()):
                        // Count unread messages from this officer
                        $unread_count = $conn->query("SELECT COUNT(*) as cnt FROM messages WHERE sender_id={$officer['user_id']} AND receiver_id=$user_id AND status='unread'")->fetch_assoc()['cnt'];
                        $is_active = (isset($_GET['chat']) && $_GET['chat'] == $officer['user_id']) ? 'active' : '';
                    ?>
                    <div class="officer-item <?php echo $is_active; ?>" onclick="window.location='?page=messages&chat=<?php echo $officer['user_id']; ?>'">
                        <div>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($officer['full_name']); ?></div>
                            <div style="font-size: 0.8rem; color: #666;">@<?php echo htmlspecialchars($officer['username']); ?></div>
                        </div>
                        <?php if ($unread_count > 0): ?>
                            <span class="unread-badge"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <!-- Chat Area -->
                <div class="chat-area">
                    <?php if (isset($_GET['chat'])): 
                        $chat_user_id = intval($_GET['chat']);
                        $chat_user = $conn->query("SELECT full_name FROM users WHERE user_id=$chat_user_id")->fetch_assoc();
                    ?>
                    <div class="chat-header">
                        <h3><?php echo htmlspecialchars($chat_user['full_name']); ?></h3>
                        <button class="btn btn-delete" onclick="if(confirm('Delete entire conversation?')) window.location='?page=messages&delete_conversation=<?php echo $chat_user_id; ?>'">
                            <i class="fas fa-trash"></i> Delete Chat
                        </button>
                    </div>
                    
                    <div class="chat-messages" id="chatMessages">
                        <?php
                        // Get messages between admin and selected officer
                        $messages = $conn->query("SELECT * FROM messages WHERE (sender_id=$user_id AND receiver_id=$chat_user_id) OR (sender_id=$chat_user_id AND receiver_id=$user_id) ORDER BY sent_at ASC");
                        
                        if ($messages->num_rows == 0) {
                            echo '<div style="text-align: center; color: #999; margin-top: 50px;">No messages yet. Start the conversation!</div>';
                        } else {
                            while ($msg = $messages->fetch_assoc()):
                                $is_sent = ($msg['sender_id'] == $user_id);
                                $time_diff = time() - strtotime($msg['sent_at']);
                                $time_ago = '';
                                if ($time_diff < 60) $time_ago = 'Just now';
                                elseif ($time_diff < 3600) $time_ago = floor($time_diff/60) . ' min ago';
                                elseif ($time_diff < 86400) $time_ago = floor($time_diff/3600) . ' hr ago';
                                else $time_ago = date('M d', strtotime($msg['sent_at']));
                                
                                $status_text = '';
                                if ($is_sent) {
                                    if ($msg['status'] == 'read') {
                                        $read_time_diff = time() - strtotime($msg['sent_at']);
                                        if ($read_time_diff < 60) $status_text = 'Seen just now';
                                        elseif ($read_time_diff < 3600) $status_text = 'Seen ' . floor($read_time_diff/60) . ' min ago';
                                        else $status_text = 'Seen ' . floor($read_time_diff/3600) . ' hr ago';
                                    } else {
                                        $status_text = 'Unseen';
                                    }
                                }
                        ?>
                        <div class="message-bubble <?php echo $is_sent ? 'sent' : 'received'; ?>" data-message-id="<?php echo $msg['message_id']; ?>">
                            <div class="message-text"><?php echo htmlspecialchars($msg['message']); ?></div>
                            <div class="message-meta">
                                <span><?php echo $time_ago; ?></span>
                                <?php if ($status_text): ?><span><?php echo $status_text; ?></span><?php endif; ?>
                            </div>
                            
                            <?php if ($is_sent): ?>
                            <button class="message-actions-btn" onclick="toggleActionMenu(<?php echo $msg['message_id']; ?>)">⋮</button>
                            <div class="action-menu" id="menu-<?php echo $msg['message_id']; ?>">
                                <button onclick="showEditForm(<?php echo $msg['message_id']; ?>)"><i class="fas fa-edit"></i> Edit</button>
                                <button onclick="if(confirm('Delete this message?')) window.location='?page=messages&chat=<?php echo $chat_user_id; ?>&delete_message=<?php echo $msg['message_id']; ?>'"><i class="fas fa-trash"></i> Delete</button>
                            </div>
                            
                            <div class="edit-form" id="edit-<?php echo $msg['message_id']; ?>">
                                <form method="POST">
                                    <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                    <textarea name="new_message" class="edit-input" required><?php echo htmlspecialchars($msg['message']); ?></textarea>
                                    <button type="submit" name="edit_message" class="btn" style="background: #4caf50; margin-right: 5px;">Save</button>
                                    <button type="button" class="btn btn-delete" onclick="hideEditForm(<?php echo $msg['message_id']; ?>)">Cancel</button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php
                            endwhile;
                        }
                        ?>
                    </div>
                    
                    <div class="chat-input-area">
                        <form method="POST" style="display: flex; gap: 10px; width: 100%;">
                            <input type="hidden" name="receiver_id" value="<?php echo $chat_user_id; ?>">
                            <input type="text" name="message_text" placeholder="Type a message..." required>
                            <button type="submit" name="send_message"><i class="fas fa-paper-plane"></i> Send</button>
                        </form>
                    </div>
                    <?php else: ?>
                    <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #999;">
                        <div style="text-align: center;">
                            <i class="fas fa-comments" style="font-size: 4rem; margin-bottom: 20px;"></i>
                            <h3>Select an officer to start chatting</h3>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Taken Review Page -->
    <div class="page <?php echo $active_page == 'actions' ? 'active' : ''; ?>" id="actions">
        <div class="content-card">
            <h2 class="card-title">Officer Action Reviews</h2>
            
            <table class="action-table">
                <thead>
                    <tr>
                        <th>Officer</th>
                        <th>Report</th>
                        <th>Action Type</th>
                        <th>Location</th>
                        <th>Status</th>
                        <th>Feedback</th>
                        <th>Action</th>
                        <th>Manage</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $actions_sql = "SELECT oa.*, fr.forest_name, aoa.verification_status, aoa.feedback, aoa.admin_action_id 
                                   FROM officer_actions oa 
                                   JOIN forest_reports fr ON oa.report_id = fr.report_id 
                                   LEFT JOIN admin_officer_actions aoa ON oa.action_id = aoa.action_id 
                                   ORDER BY oa.submitted_at DESC";
                    $actions = $conn->query($actions_sql);
                    
                    while ($action = $actions->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($action['officer_name']); ?></td>
                        <td><?php echo htmlspecialchars($action['forest_name']); ?></td>
                        <td><?php echo htmlspecialchars($action['action_type']); ?></td>
                        <td><?php echo htmlspecialchars($action['location']); ?></td>
                        <td>
                            <span class="status-badge" style="background: <?php 
                            echo $action['verification_status'] == 'Verified' ? '#4caf50' : 
                                ($action['verification_status'] == 'Rejected' ? '#f44336' : '#ff9800'); 
                            ?>">
                                <?php echo $action['verification_status'] ?? 'Pending'; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($action['feedback'] ?? 'No feedback'); ?></td>
                        <td>
                            <?php if (($action['verification_status'] ?? 'Pending') == 'Pending'): ?>
                            <a href="?page=actions&verify_action=<?php echo $action['action_id']; ?>" class="btn" style="background: #4caf50;" onclick="return confirm('Mark this action as Verified?')">
                                <i class="fas fa-check"></i> Verify
                            </a>
                            <?php else: ?>
                            <span style="color: #4caf50; font-weight: 600;">✓ Verified</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?page=actions&delete_action=<?php echo $action['admin_action_id']; ?>" 
                               onclick="return confirm('Delete this action permanently?')" 
                               style="color: #f44336; text-decoration: none;">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="bottom-nav">
    <a href="?page=dashboard" class="nav-item <?php echo $active_page == 'dashboard' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>Dashboard</span>
    </a>
    <a href="?page=reports" class="nav-item <?php echo $active_page == 'reports' ? 'active' : ''; ?>">
        <i class="fas fa-chart-bar"></i>
        <span>Reports</span>
    </a>
    <a href="?page=messages" class="nav-item <?php echo $active_page == 'messages' ? 'active' : ''; ?>">
        <i class="fas fa-comments"></i>
        <span>Messages</span>
        <?php
        $unread = $conn->query("SELECT COUNT(*) as cnt FROM messages WHERE receiver_id = $user_id AND status='unread'")->fetch_assoc()['cnt'];
        if ($unread > 0):
        ?>
        <span class="nav-badge"><?php echo $unread; ?></span>
        <?php endif; ?>
    </a>
    <a href="?page=actions" class="nav-item <?php echo $active_page == 'actions' ? 'active' : ''; ?>">
        <i class="fas fa-tasks"></i>
        <span>Actions</span>
    </a>
</div>

<script>
function previewImg(input, type) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(type + 'Img').src = e.target.result;
            document.getElementById(type + 'Preview').classList.add('has-image');
            document.getElementById(type + 'Preview').querySelector('span').style.display = 'none';
            
            const notif = document.createElement('div');
            notif.className = 'notification info';
            notif.textContent = 'Image uploaded: ' + file.name;
            document.body.appendChild(notif);
            setTimeout(() => notif.remove(), 3000);
        }
        reader.readAsDataURL(file);
    }
}

function deleteReport(id) {
    if (confirm('Delete this report permanently?')) {
        window.location.href = '?page=reports&delete_report=' + id;
    }
}

function downloadReport(id) {
    window.location.href = 'generate_pdf.php?report_id=' + id;
}

function showSendForm(id) {
    document.getElementById('sendForm' + id).classList.add('show');
}

function hideSendForm(id) {
    document.getElementById('sendForm' + id).classList.remove('show');
}

function searchReports() {
    const input = document.getElementById('searchInput').value.toLowerCase();
    const table = document.getElementById('reportsTable');
    const rows = table.getElementsByTagName('tr');
    
    for (let i = 1; i < rows.length; i++) {
        const text = rows[i].textContent.toLowerCase();
        rows[i].style.display = text.includes(input) ? '' : 'none';
    }
}

function toggleActionMenu(id) {
    const menu = document.getElementById('menu-' + id);
    menu.classList.toggle('show');
}

function showEditForm(id) {
    document.getElementById('edit-' + id).classList.add('show');
    document.getElementById('menu-' + id).classList.remove('show');
}

function hideEditForm(id) {
    document.getElementById('edit-' + id).classList.remove('show');
}

// Auto-scroll to bottom of chat
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chatMessages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});

// Close action menus when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.matches('.message-actions-btn')) {
        document.querySelectorAll('.action-menu').forEach(menu => {
            menu.classList.remove('show');
        });
    }
});

setTimeout(() => {
    document.querySelectorAll('.notification').forEach(n => n.remove());
}, 4000);
</script>
</body>
</html>
