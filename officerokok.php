<?php
session_start();
$conn = new mysqli("localhost", "root", "", "smart_ndvi_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'officer') {
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
    
    $receiver_sql = "SELECT user_id, full_name FROM users WHERE user_id = ?";
    $stmt = $conn->prepare($receiver_sql);
    $stmt->bind_param("i", $receiver_id);
    $stmt->execute();
    $receiver_result = $stmt->get_result();
    
    if ($receiver_result->num_rows > 0) {
        $receiver = $receiver_result->fetch_assoc();
        
        $message_sql = "INSERT INTO messages (sender_id, receiver_id, sender_name, receiver_name, message, status) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($message_sql);
        $status = 'unread';
        $stmt->bind_param("iissss", $user_id, $receiver_id, $user_full_name, $receiver['full_name'], $message_text, $status);
        
        if ($stmt->execute()) {
            header("Location: officerokok.php?page=messages&chat=" . $receiver_id . "&success=Message sent!");
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
        header("Location: officerokok.php?page=messages&chat=" . $_GET['chat'] . "&success=Message updated!");
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
        header("Location: officerokok.php?page=messages&chat=" . $chat_id . "&success=Message deleted!");
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
        header("Location: officerokok.php?page=messages&success=Conversation deleted!");
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

// Handle report deletion
if (isset($_GET['delete_report'])) {
    $report_id = intval($_GET['delete_report']);
    $stmt = $conn->prepare("DELETE FROM forest_reports WHERE report_id = ?");
    $stmt->bind_param("i", $report_id);
    if ($stmt->execute()) {
        header("Location: officerokok.php?page=reports&success=Report deleted!");
    }
    $stmt->close();
    exit();
}

// Handle action taken submission
if (isset($_POST['submit_action'])) {
    $report_id = intval($_POST['report_id']);
    $action_type = $_POST['action_type'];
    $location = $_POST['location'];
    $completion_date = $_POST['completion_date'];
    $has_issue = $_POST['has_issue'];
    $issue_description = isset($_POST['issue_description']) ? $_POST['issue_description'] : '';
    
    // Insert into officer_actions
    $stmt = $conn->prepare("INSERT INTO officer_actions (report_id, officer_id, officer_name, location, action_type, completion_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iissss", $report_id, $user_id, $user_full_name, $location, $action_type, $completion_date);
    
    if ($stmt->execute()) {
        $action_id = $conn->insert_id;
        
        // Get admin info
        $admin = $conn->query("SELECT user_id, full_name FROM users WHERE role='admin' LIMIT 1")->fetch_assoc();
        
        // Insert into admin_officer_actions
        $feedback_text = "Action Taken: " . $action_type . " | Issue: " . ($has_issue == 'yes' ? $issue_description : 'None');
        $stmt2 = $conn->prepare("INSERT INTO admin_officer_actions (action_id, admin_id, admin_name, officer_name, report_id, feedback, verification_status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt2->bind_param("iissis", $action_id, $admin['user_id'], $admin['full_name'], $user_full_name, $report_id, $feedback_text);
        $stmt2->execute();
        
        header("Location: officerokok.php?page=actions&success=Action submitted!");
    }
    exit();
}

// Handle action deletion
if (isset($_GET['delete_action'])) {
    $action_id = intval($_GET['delete_action']);
    $stmt = $conn->prepare("DELETE FROM officer_actions WHERE action_id = ? AND officer_id = ?");
    $stmt->bind_param("ii", $action_id, $user_id);
    if ($stmt->execute()) {
        // Also delete from admin_officer_actions
        $stmt2 = $conn->prepare("DELETE FROM admin_officer_actions WHERE action_id = ?");
        $stmt2->bind_param("i", $action_id);
        $stmt2->execute();
        header("Location: officerokok.php?page=actions&success=Action deleted!");
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
<title>ECOSENSE Officer</title>
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
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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

.quote-box {
    background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
    padding: 20px;
    border-radius: 10px;
    text-align: center;
    font-style: italic;
    color: #2e7d32;
    font-size: 1.1rem;
    margin: 20px 0;
    border-left: 5px solid #4caf50;
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

.message-list {
    max-height: 500px;
    overflow-y: auto;
    margin-top: 20px;
}

.message-item {
    padding: 15px;
    border-radius: 15px;
    margin-bottom: 15px;
    display: flex;
    gap: 15px;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
}

.message-item.sent {
    background: #000;
    color: white;
    margin-left: 50px;
    flex-direction: row-reverse;
}

.message-item.received {
    background: #ffb6c1;
    margin-right: 50px;
}

.message-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #4b826f;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: 700;
    flex-shrink: 0;
}

.message-content {
    flex: 1;
}

.message-sender {
    font-weight: 700;
    margin-bottom: 5px;
}

.message-text {
    line-height: 1.6;
    margin-bottom: 8px;
}

.message-time {
    font-size: 0.8rem;
    opacity: 0.7;
}

.compose-box {
    background: #f5f5f5;
    padding: 20px;
    border-radius: 10px;
    margin-top: 20px;
}

.compose-box select, .compose-box textarea {
    width: 100%;
    padding: 12px;
    margin: 10px 0;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
}

.compose-box textarea {
    min-height: 100px;
    resize: vertical;
}

.btn-send-msg {
    background: #4b826f;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    transition: 0.3s;
}

.btn-send-msg:hover {
    background: #3a6558;
    transform: translateY(-2px);
}

.form-group {
    margin: 15px 0;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
}

.form-group input, .form-group select, .form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 1rem;
}

.form-group textarea {
    min-height: 100px;
    resize: vertical;
}

.radio-group {
    display: flex;
    gap: 20px;
    margin-top: 5px;
}

.radio-group label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-weight: normal;
}

.btn-submit {
    background: #4caf50;
    color: white;
    border: none;
    padding: 12px 30px;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: 0.3s;
}

.btn-submit:hover {
    background: #45a049;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(76,175,80,0.3);
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

.latest-reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 15px;
    margin-top: 20px;
}

.report-card {
    background: linear-gradient(135deg, #fff, #f5f5f5);
    padding: 20px;
    border-radius: 10px;
    border-left: 5px solid #4caf50;
    box-shadow: 0 3px 10px rgba(0,0,0,0.1);
    transition: 0.3s;
}

.report-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.report-card.critical {
    border-left-color: #f44336;
}

.report-card h3 {
    color: #2e7d32;
    margin-bottom: 10px;
}

.report-detail {
    margin: 8px 0;
    font-size: 0.95rem;
    color: #555;
}

/* Enhanced Messaging Styles */
.messaging-container {
    display: flex;
    height: 70vh;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
}

.admin-list-sidebar {
    width: 300px;
    border-right: 1px solid #e0e0e0;
    overflow-y: auto;
    background: #f0f8ff;
}

.admin-item {
    padding: 15px;
    border-bottom: 1px solid #e0e0e0;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: background 0.2s;
}

.admin-item:hover {
    background: #e6f3ff;
}

.admin-item.active {
    background: #e6f3ff;
    border-left: 3px solid #4b826f;
}

.unread-badge {
    background: #f44336;
    color: white;
    border-radius: 50%;
    min-width: 22px;
    height: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    font-weight: 600;
}

.chat-area {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.chat-header {
    padding: 15px 20px;
    background: #4b826f;
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
}

.chat-header h3 {
    margin: 0;
    font-size: 1.1rem;
}

.delete-chat-btn {
    background: #f44336;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.85rem;
    transition: background 0.3s;
}

.delete-chat-btn:hover {
    background: #d32f2f;
}

.messages-container {
    flex: 1;
    overflow-y: auto;
    padding: 20px;
    background: #f9f9f9;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.message-bubble {
    max-width: 70%;
    padding: 12px 16px;
    border-radius: 18px;
    position: relative;
    word-wrap: break-word;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.message-bubble.sent {
    align-self: flex-end;
    background: #d0f0c0;
    border-bottom-right-radius: 4px;
}

.message-bubble.received {
    align-self: flex-start;
    background: #e6e6fa;
    border-bottom-left-radius: 4px;
}

.message-bubble:hover .message-actions {
    opacity: 1;
}

.message-text {
    margin-bottom: 5px;
    color: #333;
    font-size: 0.95rem;
}

.message-meta {
    font-size: 0.75rem;
    color: #666;
    margin-top: 5px;
}

.message-actions {
    position: absolute;
    top: 8px;
    right: 8px;
    opacity: 0;
    transition: opacity 0.2s;
}

.action-btn {
    background: rgba(0,0,0,0.6);
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.8rem;
    margin-left: 5px;
}

.action-btn:hover {
    background: rgba(0,0,0,0.8);
}

.edit-form {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid rgba(0,0,0,0.1);
}

.edit-form input {
    width: calc(100% - 80px);
    padding: 6px 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 0.9rem;
}

.edit-form button {
    padding: 6px 12px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.85rem;
    margin-left: 5px;
}

.message-input-area {
    padding: 15px 20px;
    background: white;
    border-top: 1px solid #e0e0e0;
}

.message-input-form {
    display: flex;
    gap: 10px;
}

.message-input-form textarea {
    flex: 1;
    padding: 12px 15px;
    border: 1px solid #ddd;
    border-radius: 25px;
    resize: none;
    font-size: 0.95rem;
    font-family: 'Roboto', sans-serif;
    max-height: 120px;
}

.message-input-form button {
    background: #4b826f;
    color: white;
    border: none;
    padding: 0 25px;
    border-radius: 25px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.3s;
}

.message-input-form button:hover {
    background: #3a6958;
}

.empty-chat {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #999;
    font-size: 1.1rem;
}

.nav-badge {
    position: absolute;
    top: 5px;
    right: 10px;
    background: #f44336;
    color: white;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 0.7rem;
    font-weight: 600;
}
</style>
</head>
<body>
<div class="app-bar">
    <div class="app-title">
        <i class="fas fa-user-shield"></i> ECOSENSE OFFICER
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
            <h2 class="card-title">Officer Dashboard</h2>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-icon">📊</div>
                    <div class="stat-value"><?php
                    $assigned = $conn->query("SELECT COUNT(DISTINCT fr.report_id) as cnt FROM forest_reports fr 
                                             WHERE fr.report_id IN (SELECT report_id FROM messages WHERE receiver_id = $user_id AND report_id IS NOT NULL)")->fetch_assoc()['cnt'];
                    echo $assigned;
                    ?></div>
                    <div class="stat-label">Reports Assigned</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon">🔥</div>
                    <div class="stat-value"><?php
                    $critical = $conn->query("SELECT COUNT(DISTINCT fr.report_id) as cnt 
                                             FROM forest_reports fr 
                                             WHERE fr.report_id IN (SELECT report_id FROM messages WHERE receiver_id = $user_id AND report_id IS NOT NULL) AND fr.status = 'Critical'")->fetch_assoc()['cnt'];
                    echo $critical;
                    ?></div>
                    <div class="stat-label">Critical Reports</div>
                </div>
                
                <div class="stat-box">
                    <div class="stat-icon">✅</div>
                    <div class="stat-value"><?php
                    $completed = $conn->query("SELECT COUNT(*) as cnt FROM officer_actions WHERE officer_id = $user_id")->fetch_assoc()['cnt'];
                    echo $completed;
                    ?></div>
                    <div class="stat-label">Actions Taken</div>
                </div>
            </div>
            
            <div class="quote-box">
                "The forest is a peculiar organism of unlimited kindness that makes no demands for its sustenance and extends generously the products of its life and activity."
            </div>
        </div>
        
        <div class="content-card">
            <h2 class="card-title">Latest Assigned Reports</h2>
            
            <div class="latest-reports-grid">
                <?php
                $latest_sql = "SELECT DISTINCT fr.* FROM forest_reports fr 
                              WHERE fr.report_id IN (SELECT report_id FROM messages WHERE receiver_id = $user_id AND report_id IS NOT NULL)
                              ORDER BY fr.report_date DESC LIMIT 6";
                $latest = $conn->query($latest_sql);
                
                while ($report = $latest->fetch_assoc()):
                    $card_class = $report['status'] == 'Critical' ? 'critical' : '';
                ?>
                <div class="report-card <?php echo $card_class; ?>">
                    <h3><?php echo htmlspecialchars($report['forest_name']); ?></h3>
                    <div class="report-detail"><strong>NDVI:</strong> <?php echo number_format($report['ndvi_percentage'], 2); ?>%</div>
                    <div class="report-detail"><strong>Tree Loss:</strong> <?php echo number_format($report['tree_loss_percentage'], 2); ?>%</div>
                    <div class="report-detail">
                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $report['status'])); ?>">
                            <?php echo $report['status']; ?>
                        </span>
                    </div>
                    <div class="report-detail" style="font-size: 0.85rem; color: #999;">
                        <?php echo date('M d, Y', strtotime($report['report_date'])); ?>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Reports Page -->
    <div class="page <?php echo $active_page == 'reports' ? 'active' : ''; ?>" id="reports">
        <div class="content-card">
            <h2 class="card-title">Assigned Forest Reports</h2>
            
            <table class="reports-table">
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
                    $reports_sql = "SELECT DISTINCT fr.* FROM forest_reports fr 
                                   WHERE fr.report_id IN (SELECT report_id FROM messages WHERE receiver_id = $user_id AND report_id IS NOT NULL)
                                   ORDER BY fr.report_date DESC";
                    $reports = $conn->query($reports_sql);
                    
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
                            <a href="?page=reports&delete_report=<?php echo $report['report_id']; ?>" 
                               onclick="return confirm('Delete this report permanently?')" 
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

    <!-- Messages Page -->
    <div class="page <?php echo $active_page == 'messages' ? 'active' : ''; ?>" id="messages">
        <div class="content-card">
            <h2 class="card-title">Messages</h2>
            
            <div class="messaging-container">
                <!-- Admin List Sidebar -->
                <div class="admin-list-sidebar">
                    <?php
                    $admins = $conn->query("SELECT u.user_id, u.full_name, u.username FROM users u WHERE u.role='admin' ORDER BY u.full_name");
                    while ($admin = $admins->fetch_assoc()):
                        // Get unread count for this admin
                        $unread_count_query = $conn->query("SELECT COUNT(*) as cnt FROM messages WHERE sender_id={$admin['user_id']} AND receiver_id=$user_id AND status='unread'");
                        $unread_count = $unread_count_query->fetch_assoc()['cnt'];
                        
                        $is_active = (isset($_GET['chat']) && $_GET['chat'] == $admin['user_id']) ? 'active' : '';
                    ?>
                    <div class="admin-item <?php echo $is_active; ?>" onclick="window.location='?page=messages&chat=<?php echo $admin['user_id']; ?>'">
                        <div>
                            <div style="font-weight: 600;"><?php echo htmlspecialchars($admin['full_name']); ?></div>
                            <div style="font-size: 0.85rem; color: #666;">@<?php echo htmlspecialchars($admin['username']); ?></div>
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
                        $chat_user = $conn->query("SELECT * FROM users WHERE user_id = $chat_user_id")->fetch_assoc();
                    ?>
                        <!-- Chat Header -->
                        <div class="chat-header">
                            <h3><?php echo htmlspecialchars($chat_user['full_name']); ?></h3>
                            <a href="?page=messages&delete_conversation=<?php echo $chat_user_id; ?>" 
                               class="delete-chat-btn" 
                               onclick="return confirm('Delete entire conversation with <?php echo htmlspecialchars($chat_user['full_name']); ?>?')">
                                <i class="fas fa-trash"></i> Delete Chat
                            </a>
                        </div>
                        
                        <!-- Messages Container -->
                        <div class="messages-container">
                            <?php
                            $messages_sql = "SELECT m.*, 
                                            (SELECT sent_at FROM messages WHERE message_id = m.message_id AND status = 'read') as read_time
                                            FROM messages m
                                            WHERE (m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?)
                                            ORDER BY m.sent_at ASC";
                            $stmt = $conn->prepare($messages_sql);
                            $stmt->bind_param("iiii", $user_id, $chat_user_id, $chat_user_id, $user_id);
                            $stmt->execute();
                            $messages = $stmt->get_result();
                            
                            while ($msg = $messages->fetch_assoc()):
                                $is_sent = ($msg['sender_id'] == $user_id);
                                $bubble_class = $is_sent ? 'sent' : 'received';
                                
                                // Calculate time ago
                                $time_diff = time() - strtotime($msg['sent_at']);
                                if ($time_diff < 60) {
                                    $time_ago = 'Just now';
                                } elseif ($time_diff < 3600) {
                                    $time_ago = floor($time_diff / 60) . ' min ago';
                                } elseif ($time_diff < 86400) {
                                    $time_ago = floor($time_diff / 3600) . ' hr ago';
                                } else {
                                    $time_ago = floor($time_diff / 86400) . ' day' . (floor($time_diff/86400) > 1 ? 's' : '') . ' ago';
                                }
                                
                                // Status text for sent messages
                                $status_text = '';
                                if ($is_sent) {
                                    if ($msg['status'] == 'read') {
                                        $read_time_diff = time() - strtotime($msg['sent_at']);
                                        if ($read_time_diff < 60) {
                                            $status_text = 'Seen just now';
                                        } elseif ($read_time_diff < 3600) {
                                            $status_text = 'Seen ' . floor($read_time_diff / 60) . ' min ago';
                                        } else {
                                            $status_text = 'Seen ' . floor($read_time_diff / 3600) . ' hr ago';
                                        }
                                    } else {
                                        $status_text = 'Unseen';
                                    }
                                }
                            ?>
                            <div class="message-bubble <?php echo $bubble_class; ?>" id="msg-<?php echo $msg['message_id']; ?>">
                                <?php if ($is_sent): ?>
                                <div class="message-actions">
                                    <button class="action-btn" onclick="toggleEdit(<?php echo $msg['message_id']; ?>)">⋮</button>
                                </div>
                                <?php endif; ?>
                                
                                <div class="message-text"><?php echo htmlspecialchars($msg['message']); ?></div>
                                <div class="message-meta">
                                    <?php echo $time_ago; ?>
                                    <?php if ($status_text): ?>
                                        • <span style="<?php echo $msg['status'] == 'read' ? 'color: #4caf50;' : 'color: #ff9800;'; ?>"><?php echo $status_text; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($is_sent): ?>
                                <div class="edit-form" id="edit-form-<?php echo $msg['message_id']; ?>" style="display: none;">
                                    <form method="POST" style="display: inline-block; width: 100%;">
                                        <input type="hidden" name="message_id" value="<?php echo $msg['message_id']; ?>">
                                        <input type="text" name="new_message" value="<?php echo htmlspecialchars($msg['message']); ?>" required>
                                        <button type="submit" name="edit_message" style="background: #4caf50; color: white;">✓</button>
                                        <button type="button" onclick="toggleEdit(<?php echo $msg['message_id']; ?>)" style="background: #999; color: white;">✕</button>
                                    </form>
                                    <a href="?page=messages&chat=<?php echo $chat_user_id; ?>&delete_message=<?php echo $msg['message_id']; ?>" 
                                       onclick="return confirm('Delete this message?')" 
                                       style="display: inline-block; margin-top: 5px; color: #f44336; font-size: 0.85rem; text-decoration: none;">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        
                        <!-- Message Input Area -->
                        <div class="message-input-area">
                            <form method="POST" class="message-input-form">
                                <input type="hidden" name="receiver_id" value="<?php echo $chat_user_id; ?>">
                                <textarea name="message_text" placeholder="Type a message..." rows="1" required></textarea>
                                <button type="submit" name="send_message">
                                    <i class="fas fa-paper-plane"></i> Send
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="empty-chat">
                            <div style="text-align: center;">
                                <i class="fas fa-comments" style="font-size: 3rem; color: #ddd; margin-bottom: 15px;"></i>
                                <div>Select an admin to start messaging</div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Taken Page -->
    <div class="page <?php echo $active_page == 'actions' ? 'active' : ''; ?>" id="actions">
        <div class="content-card">
            <h2 class="card-title">Submit Action Taken</h2>
            
            <form method="POST">
                <div class="form-group">
                    <label>Select Report</label>
                    <select name="report_id" required>
                        <option value="">Choose a report...</option>
                        <?php
                        $rep = $conn->query("SELECT DISTINCT fr.report_id, fr.forest_name 
                                           FROM forest_reports fr 
                                           WHERE fr.report_id IN (SELECT report_id FROM messages WHERE receiver_id = $user_id AND report_id IS NOT NULL)");
                        while ($r = $rep->fetch_assoc()):
                        ?>
                        <option value="<?php echo $r['report_id']; ?>"><?php echo $r['forest_name']; ?> (ID: <?php echo $r['report_id']; ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Action Type</label>
                    <input type="text" name="action_type" placeholder="e.g., Field Survey, Tree Planting" required>
                </div>
                
                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" placeholder="e.g., Western Zone, Grid A2" required>
                </div>
                
                <div class="form-group">
                    <label>Completion Date</label>
                    <input type="date" name="completion_date" required>
                </div>
                
                <div class="form-group">
                    <label>Any Issues Faced?</label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="has_issue" value="no" checked onclick="toggleIssueBox(false)"> No
                        </label>
                        <label>
                            <input type="radio" name="has_issue" value="yes" onclick="toggleIssueBox(true)"> Yes
                        </label>
                    </div>
                </div>
                
                <div class="form-group" id="issueBox" style="display: none;">
                    <label>Describe the Issue</label>
                    <textarea name="issue_description" placeholder="Explain the issues you faced..."></textarea>
                </div>
                
                <button type="submit" name="submit_action" class="btn-submit">
                    <i class="fas fa-check-circle"></i> Submit Action
                </button>
            </form>
        </div>
        
        <div class="content-card">
            <h2 class="card-title">My Submitted Actions</h2>
            
            <table class="reports-table">
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Action Type</th>
                        <th>Location</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $actions_sql = "SELECT oa.*, fr.forest_name, aoa.verification_status 
                                   FROM officer_actions oa 
                                   JOIN forest_reports fr ON oa.report_id = fr.report_id 
                                   LEFT JOIN admin_officer_actions aoa ON oa.action_id = aoa.action_id 
                                   WHERE oa.officer_id = $user_id 
                                   ORDER BY oa.submitted_at DESC";
                    $actions = $conn->query($actions_sql);
                    
                    while ($action = $actions->fetch_assoc()):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($action['forest_name']); ?></td>
                        <td><?php echo htmlspecialchars($action['action_type']); ?></td>
                        <td><?php echo htmlspecialchars($action['location']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($action['completion_date'])); ?></td>
                        <td>
                            <span class="status-badge" style="background: <?php 
                            echo $action['verification_status'] == 'Verified' ? '#4caf50' : 
                                ($action['verification_status'] == 'Rejected' ? '#f44336' : '#ff9800'); 
                            ?>">
                                <?php echo $action['verification_status'] ?? 'Pending'; ?>
                            </span>
                        </td>
                        <td>
                            <a href="?page=actions&delete_action=<?php echo $action['action_id']; ?>" 
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
        <i class="fas fa-file-alt"></i>
        <span>Reports</span>
    </a>
    <a href="?page=messages" class="nav-item <?php echo $active_page == 'messages' ? 'active' : ''; ?>">
        <i class="fas fa-comments"></i>
        <span>Messages</span>
        <?php
        $unread = $conn->query("SELECT COUNT(*) as cnt FROM messages WHERE receiver_id = $user_id AND status = 'unread'")->fetch_assoc()['cnt'];
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
function toggleIssueBox(show) {
    document.getElementById('issueBox').style.display = show ? 'block' : 'none';
}

function toggleEdit(messageId) {
    const editForm = document.getElementById('edit-form-' + messageId);
    editForm.style.display = editForm.style.display === 'none' ? 'block' : 'none';
}

setTimeout(() => {
    document.querySelectorAll('.notification').forEach(n => n.remove());
}, 4000);
</script>
</body>
</html>
