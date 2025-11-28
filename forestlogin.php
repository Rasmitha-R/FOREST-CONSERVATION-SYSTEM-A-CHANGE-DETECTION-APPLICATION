<?php
session_start();
$conn = new mysqli("localhost", "root", "", "smart_ndvi_system");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// LOGIN HANDLER
if (isset($_POST['login'])) {
    $full_name = $_POST['full_name'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $role = $_POST['role'];

    // check if user exists
    $checkUser = $conn->query("SELECT * FROM users WHERE email='$email' AND role='$role'");
    if ($checkUser->num_rows > 0) {
        $row = $checkUser->fetch_assoc();
        if ($row['password'] === $password) {
            // Set session variables
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['full_name'] = $row['full_name'];
            $_SESSION['username'] = $row['username'];
            
            // Update last login timestamp
            $user_id = $row['user_id'];
            $conn->query("UPDATE users SET last_login = NOW() WHERE user_id = $user_id");
            
            // Redirect based on role
            if ($row['role'] === 'admin') {
                header("Location: adminokok.php");
            } else {
                header("Location: officerokok.php");
            }
            exit();
        } else {
            echo "<script>alert('Incorrect password!');</script>";
        }
    } else {
        // auto-save new user
        $conn->query("INSERT INTO users(full_name,username,email,password,role,last_login) VALUES('$full_name','$username','$email','$password','$role',NOW())");
        // Get the inserted user ID
        $user_id = $conn->insert_id;
        
        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['role'] = $role;
        $_SESSION['full_name'] = $full_name;
        $_SESSION['username'] = $username;
        
        echo "<script>alert('New user saved successfully.');</script>";
        if ($role === 'admin') {
            header("Refresh:1; url=adminokok.php");
        } else {
            header("Refresh:1; url=officerokok.php");
        }
        exit();
    }
}

// CHANGE PASSWORD HANDLER
if (isset($_POST['change_password'])) {
    $email = $_POST['email'];
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    if ($new_password !== $confirm_password) {
        echo "<script>alert('New passwords do not match!');</script>";
    } else {
        $check = "SELECT * FROM users WHERE email='$email' AND password='$old_password' AND role='$role'";
        $res = $conn->query($check);

        if ($res->num_rows > 0) {
            $update = "UPDATE users SET password='$new_password' WHERE email='$email' AND role='$role'";
            if ($conn->query($update)) {
                echo "<script>alert('Password updated successfully!');</script>";
            } else {
                echo "<script>alert('Error updating password.');</script>";
            }
        } else {
            echo "<script>alert('Old password incorrect or user not found!');</script>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ECOSENSE - Conserve Forest</title>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;font-family:'Roboto',sans-serif;margin:0;padding:0;}
body{
  display:flex;flex-direction:column;align-items:center;
  min-height:100vh;background:linear-gradient(to bottom,#d0f0c0,#b0e0a0);
}
.marquee{
  width:100%;background:rgba(75,130,111,1);color:#eef4ee;font-size:1.5em;
  font-weight:bold;padding:15px 0;text-align:center;overflow:hidden;white-space:nowrap;
  box-shadow:0 3px 6px rgba(0,0,0,0.2);margin-bottom:30px;
}
.marquee span{display:inline-block;padding-left:100%;animation:marquee 15s linear infinite;}
@keyframes marquee{0%{transform:translateX(0%);}100%{transform:translateX(-100%);}}

.wrapper{display:flex;align-items:center;justify-content:center;gap:40px;position:relative;flex-wrap:wrap;}

.login-container{
  background:rgba(255,255,255,0.25);backdrop-filter:blur(10px);
  padding:50px 40px;border-radius:25px;box-shadow:0 20px 40px rgba(64,64,63,1);
  width:450px;text-align:center;animation:slideFade 1s forwards;
}
@keyframes slideFade{to{opacity:1;transform:translateY(0);}}
.login-container h3{color:#2e7d32;margin-bottom:30px;font-size:2.5em;font-weight:bold;}
.tree-icon{font-size:28px;color:#2e7d32;margin:0 5px;animation:sway 3s infinite alternate;}
@keyframes sway{0%{transform:rotate(-5deg);}100%{transform:rotate(5deg);}}

input[type="text"],input[type="email"],input[type="password"],select{
  width:100%;padding:15px 20px;margin:12px 0;border-radius:12px;
  border:none;outline:none;font-size:16px;
  background:rgba(255,255,255,0.7);backdrop-filter:blur(5px);
  box-shadow:inset 2px 2px 6px #fff;
}
.password-wrapper{position:relative;}
.password-wrapper i{position:absolute;right:15px;top:50%;transform:translateY(-50%);cursor:pointer;color:#555;}
select{cursor:pointer;}
.btn-login{
  width:90%;padding:18px;margin-top:20px;
  background:linear-gradient(45deg,rgba(75,130,111,1),rgba(144,234,199,1));
  border:none;border-radius:15px;color:#fff;font-size:18px;font-weight:bold;
  cursor:pointer;box-shadow:0 8px 20px #fff;transition:all 0.3s ease,transform 0.2s ease;
}
.btn-login:hover{transform:scale(1.05);box-shadow:0 12px 25px rgba(0,0,0,0.4);}
.change-btn{background:none;border:none;color:#0d47a1;cursor:pointer;font-weight:bold;margin-top:10px;text-decoration:underline;}

#changeForm{
  position:absolute;top:50%;right:-550px;transform:translateY(-50%);
  background:rgba(255,255,255,0.35);backdrop-filter:blur(12px);
  border-radius:25px;padding:40px;width:420px;text-align:center;
  box-shadow:0 20px 40px rgba(64,64,63,0.8);transition:all 0.6s ease;
  opacity:0;pointer-events:none;
}
#changeForm.active{right:-500px;opacity:1;pointer-events:auto;}
#changeForm h4{color:#2e7d32;margin-bottom:20px;font-size:2em;font-weight:bold;}
#changeForm input,#changeForm select{
  width:100%;padding:15px 20px;margin:10px 0;border-radius:12px;
  border:none;background:rgba(255,255,255,0.8);backdrop-filter:blur(5px);
}
</style>
</head>
<body>
<div class="marquee"><span><h3>ECOSENSE - FOREST CONSERVATION SYSTEM</h3></span></div>

<div class="wrapper" id="wrapper">
  <div class="login-container">
    <h2><span class="tree-icon">🌳</span>ECOSENSE<span class="tree-icon">🌲</span></h2>
    <form method="POST">
      <input type="text" name="full_name" placeholder="Full Name" required>
      <input type="text" name="username" placeholder="Username" required>
      <input type="email" name="email" placeholder="Email Address" required>
      <div class="password-wrapper">
        <input type="password" name="password" id="password" placeholder="Password" required>
        <i onclick="togglePassword('password')">👁️</i>
      </div>
      <select name="role" required>
        <option value="" disabled selected>Select Role</option>
        <option value="admin">Admin</option>
        <option value="officer">Officer</option>
      </select>
      <button type="submit" name="login" class="btn-login">Login</button>
    </form>
    <button class="change-btn" onclick="toggleChangeForm()">Change Password</button>
  </div>

  <div id="changeForm">
    <h3>Change Password</h3>
    <form method="POST">
      <input type="email" name="email" placeholder="Email Address" required>
      <div class="password-wrapper">
        <input type="password" name="old_password" id="old_password" placeholder="Old Password" required>
        <i onclick="togglePassword('old_password')">👁️</i>
      </div>
      <div class="password-wrapper">
        <input type="password" name="new_password" id="new_password" placeholder="New Password" required>
        <i onclick="togglePassword('new_password')">👁️</i>
      </div>
      <div class="password-wrapper">
        <input type="password" name="confirm_password" id="confirm_password" placeholder="Confirm New Password" required>
        <i onclick="togglePassword('confirm_password')">👁️</i>
      </div>
      <select name="role" required>
        <option value="" disabled selected>Select Role</option>
        <option value="admin">Admin</option>
        <option value="officer">Officer</option>
      </select>
      <button type="submit" name="change_password" class="btn-login">Update Password</button>
    </form>
  </div>
</div>

<script>
function togglePassword(id){
  const input=document.getElementById(id);
  input.type=input.type==='password'?'text':'password';
}
function toggleChangeForm(){
  const form=document.getElementById('changeForm');
  form.classList.toggle('active');
}
document.addEventListener('click',function(e){
  const form=document.getElementById('changeForm');
  const button=document.querySelector('.change-btn');
  if(!form.contains(e.target)&&!button.contains(e.target)){
    form.classList.remove('active');
  }
});
</script>
</body>
</html>