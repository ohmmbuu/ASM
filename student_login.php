<?php
require_once 'config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_code = $_POST['student_code'];
    $password = $_POST['password'];

    if (empty($student_code) || empty($password)) {
        $error = 'กรุณากรอกรหัสนักเรียนและรหัสผ่าน';
    } else {
        $stmt = $conn->prepare("SELECT * FROM students WHERE student_code = ?");
        $stmt->execute([$student_code]);
        $student = $stmt->fetch();

        // ตรวจสอบว่ามีนักเรียนและรหัสผ่านถูกต้องหรือไม่
        if ($student && password_verify($password, $student['password'])) {
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_full_name'] = $student['first_name'] . ' ' . $student['last_name'];
            // --- บรรทัดที่เพิ่มใหม่ ---
            $_SESSION['student_profile_image'] = $student['profile_image']; 
            // ------------------------
            header("Location: student/dashboard.php");
            exit();
        }  else {
            $error = 'รหัสนักเรียนหรือรหัสผ่านไม่ถูกต้อง';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สำหรับนักเรียน - School Assistant</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style> body { font-family: 'Kanit', sans-serif; background-color: #0d1117; } /* ใช้พื้นหลังสีเข้มเหมือนหน้าแรก */ </style>
</head>
<body class="flex items-center justify-center h-screen px-4">
    <div class="w-full max-w-md bg-white/10 backdrop-blur-lg p-8 rounded-xl border border-blue-500/20">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold text-white">สำหรับนักเรียน</h1>
            <p class="text-gray-400">ตรวจสอบผลการเรียน</p>
        </div>
        
        <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-500/50 text-red-300 px-4 py-3 rounded-lg mb-4 text-sm" role="alert">
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="student_login.php" method="POST">
            <div class="mb-4">
                <label for="student_code" class="block text-gray-400 text-sm font-bold mb-2">รหัสนักเรียน</label>
                <input type="text" id="student_code" name="student_code" required class="w-full py-3 px-4 rounded-lg bg-gray-800/50 border border-blue-500/30 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <div class="mb-6">
                <label for="password" class="block text-gray-400 text-sm font-bold mb-2">รหัสผ่าน</label>
                <input type="password" id="password" name="password" required class="w-full py-3 px-4 rounded-lg bg-gray-800/50 border border-blue-500/30 text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg">เข้าสู่ระบบ</button>
        </form>
         <div class="text-center mt-6">
            <a href="index.php" class="text-sm text-blue-400 hover:text-blue-300">&lt; กลับสู่หน้าหลัก</a>
        </div>
    </div>
</body>
</html>