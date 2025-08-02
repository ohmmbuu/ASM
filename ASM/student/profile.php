<?php
session_start();
if (!isset($_SESSION['student_id'])) { header("Location: ../student_login.php"); exit(); }
require_once '../config/db.php';
$student_id = $_SESSION['student_id'];

// === Logic สำหรับเปลี่ยนรหัสผ่าน ===
if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
    // ... (โค้ดส่วนนี้เหมือนกับของผู้ดูแลระบบ)
}

// === Logic สำหรับอัปโหลดรูปภาพ ===
if (isset($_POST['action']) && $_POST['action'] === 'upload_image') {
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (in_array($_FILES['profile_image']['type'], $allowed_types)) {
            
            // ดึงชื่อไฟล์รูปเก่า (ถ้ามี) เพื่อลบ
            $old_img_stmt = $conn->prepare("SELECT profile_image FROM students WHERE id = ?");
            $old_img_stmt->execute([$student_id]);
            $old_img = $old_img_stmt->fetchColumn();

            // สร้างชื่อไฟล์ใหม่
            $file_extension = pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION);
            $new_filename = 'student_' . $student_id . '_' . time() . '.' . $file_extension;
            $upload_path = '../assets/student_images/' . $new_filename;

            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                // อัปเดตฐานข้อมูล
                $update_stmt = $conn->prepare("UPDATE students SET profile_image = ? WHERE id = ?");
                $update_stmt->execute([$new_filename, $student_id]);

                // ลบไฟล์เก่า
                if ($old_img && file_exists('../assets/student_images/' . $old_img)) {
                    unlink('../assets/student_images/' . $old_img);
                }
                
                // อัปเดต session
                $_SESSION['student_profile_image'] = $new_filename;
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'อัปโหลดรูปภาพสำเร็จ'];
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => 'ไม่สามารถย้ายไฟล์ไปยังโฟลเดอร์ได้'];
            }
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'รองรับเฉพาะไฟล์ JPG, PNG, GIF เท่านั้น'];
        }
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'เกิดข้อผิดพลาดในการอัปโหลด'];
    }
    header("Location: profile.php");
    exit();
}

// === ส่วน View ===
include 'student_header.php'; // ใช้ header ของนักเรียน
$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch();
?>
<main class="container mx-auto p-4 sm:p-6 lg:p-8">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        <div class="md:col-span-1">
            <div class="bg-white p-6 rounded-xl shadow-lg text-center">
                <img src="../assets/student_images/<?= htmlspecialchars($student['profile_image'] ?? 'default_avatar.png') ?>" 
                     alt="Profile Picture" class="w-40 h-40 rounded-full mx-auto mb-4 object-cover border-4 border-gray-200">
                <h3 class="text-xl font-bold"><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></h3>
                <p class="text-gray-500">รหัสนักเรียน: <?= htmlspecialchars($student['student_code']) ?></p>
                <form action="profile.php" method="post" enctype="multipart/form-data" class="mt-4">
                    <input type="hidden" name="action" value="upload_image">
                    <label for="profile_image_upload" class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-bold py-2 px-4 rounded-lg cursor-pointer">
                        <i class="fas fa-upload mr-2"></i>เลือกรูปภาพใหม่
                    </label>
                    <input type="file" name="profile_image" id="profile_image_upload" class="hidden" onchange="this.form.submit()">
                    <p class="text-xs text-gray-400 mt-2">ไฟล์ JPG, PNG, GIF</p>
                </form>
            </div>
        </div>
        <div class="md:col-span-2 bg-white p-6 rounded-xl shadow-lg">
            <h2 class="text-2xl font-semibold mb-4 text-gray-800">เปลี่ยนรหัสผ่าน</h2>
             <form action="profile.php" method="POST">
                <input type="hidden" name="action" value="change_password">
                <div class="mb-4">
                    <label for="current_password" class="block text-gray-700 font-semibold">รหัสผ่านปัจจุบัน</label>
                    <input type="password" name="current_password" class="w-full px-3 py-2 border rounded-lg mt-1" required>
                </div>
                <div class="mb-4">
                    <label for="new_password" class="block text-gray-700 font-semibold">รหัสผ่านใหม่</label>
                    <input type="password" name="new_password" class="w-full px-3 py-2 border rounded-lg mt-1" required>
                </div>
                <div class="mb-4">
                    <label for="confirm_password" class="block text-gray-700 font-semibold">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" name="confirm_password" class="w-full px-3 py-2 border rounded-lg mt-1" required>
                </div>
                <div class="text-right mt-6">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-6 rounded-lg">บันทึกรหัสผ่านใหม่</button>
                </div>
            </form>
        </div>
    </div>
</main>
<?php include 'student_footer.php'; ?>