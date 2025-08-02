<?php
// === ส่วนที่ 1: การประมวลผลฟอร์ม (Logic) ต้องอยู่บนสุดเสมอ ===
require_once '../config/db.php'; // นำ db.php มาไว้บนสุดด้วย

// Security check: if user is not logged in, redirect to login page
// ย้ายส่วนนี้มาจาก header.php เพื่อตรวจสอบก่อนแสดงผล
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// --- Handle Profile Information Update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $full_name = $_POST['full_name'];

    if (!empty($full_name)) {
        $stmt = $conn->prepare("UPDATE users SET full_name = ? WHERE id = ?");
        $stmt->execute([$full_name, $user_id]);
        
        $_SESSION['full_name'] = $full_name;
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'อัปเดตข้อมูลส่วนตัวสำเร็จ'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'กรุณากรอกชื่อ-สกุล'];
    }
    header("Location: profile.php");
    exit(); // exit() เป็นสิ่งสำคัญมากหลังการ redirect
}

// --- Handle Password Change ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($current_password, $user['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->execute([$new_password_hash, $user_id]);
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'เปลี่ยนรหัสผ่านสำเร็จ'];
            } else {
                 $_SESSION['toast'] = ['type' => 'error', 'message' => 'รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร'];
            }
        } else {
             $_SESSION['toast'] = ['type' => 'error', 'message' => 'รหัสผ่านใหม่และการยืนยันไม่ตรงกัน'];
        }
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'รหัสผ่านปัจจุบันไม่ถูกต้อง'];
    }
    header("Location: profile.php");
    exit(); // exit() เป็นสิ่งสำคัญมากหลังการ redirect
}

// === ส่วนที่ 2: การแสดงผล (View) เริ่มต้นที่นี่ ===
// เมื่อโค้ดส่วนบนทำงานเสร็จ (ถ้ามีการ redirect ก็จะออกไปก่อน) เราจึงค่อย include header.php เพื่อแสดงผล HTML
include 'header.php';

// Fetch current user data for display
$stmt = $conn->prepare("SELECT username, full_name FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="card-header-gradient text-white p-4 rounded-t-md -m-6 mb-6">
            <h3 class="text-xl font-semibold"><i class="fas fa-user-edit mr-2"></i>แก้ไขข้อมูลส่วนตัว</h3>
        </div>
        
        <form action="profile.php" method="POST">
            <input type="hidden" name="action" value="update_profile">
            <div class="mb-4">
                <label for="username" class="block text-gray-700 font-semibold">ชื่อผู้ใช้ (Username)</label>
                <input type="text" id="username" value="<?= htmlspecialchars($current_user['username']) ?>" class="w-full px-3 py-2 border rounded bg-gray-100 mt-1" readonly>
                <p class="text-xs text-gray-500 mt-1">ชื่อผู้ใช้ไม่สามารถเปลี่ยนแปลงได้</p>
            </div>
            <div class="mb-4">
                <label for="full_name" class="block text-gray-700 font-semibold">ชื่อ-สกุล</label>
                <input type="text" name="full_name" id="full_name" value="<?= htmlspecialchars($current_user['full_name']) ?>" class="w-full px-3 py-2 border rounded mt-1" required>
            </div>
            <div class="text-right mt-6">
                <button type="submit" class="btn-gradient text-white font-bold py-2 px-4 rounded-lg shadow-md hover:shadow-lg transition-all duration-300">
                    <i class="fas fa-save mr-2"></i>บันทึกข้อมูล
                </button>
            </div>
        </form>
    </div>

    <div class="bg-white p-6 rounded-lg shadow-lg">
        <div class="card-header-gradient text-white p-4 rounded-t-md -m-6 mb-6">
            <h3 class="text-xl font-semibold"><i class="fas fa-key mr-2"></i>เปลี่ยนรหัสผ่าน</h3>
        </div>

        <form action="profile.php" method="POST">
            <input type="hidden" name="action" value="change_password">
            <div class="mb-4">
                <label for="current_password" class="block text-gray-700 font-semibold">รหัสผ่านปัจจุบัน</label>
                <input type="password" name="current_password" id="current_password" class="w-full px-3 py-2 border rounded mt-1" required>
            </div>
            <div class="mb-4">
                <label for="new_password" class="block text-gray-700 font-semibold">รหัสผ่านใหม่</label>
                <input type="password" name="new_password" id="new_password" class="w-full px-3 py-2 border rounded mt-1" required>
            </div>
            <div class="mb-4">
                <label for="confirm_password" class="block text-gray-700 font-semibold">ยืนยันรหัสผ่านใหม่</label>
                <input type="password" name="confirm_password" id="confirm_password" class="w-full px-3 py-2 border rounded mt-1" required>
            </div>
            <div class="text-right mt-6">
                <button type="submit" class="btn-gradient text-white font-bold py-2 px-4 rounded-lg shadow-md hover:shadow-lg transition-all duration-300">
                    <i class="fas fa-lock mr-2"></i>เปลี่ยนรหัสผ่าน
                </button>
            </div>
        </form>
    </div>
</div>

<?php include 'footer.php'; ?>