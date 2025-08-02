<?php
// ดึงชื่อไฟล์ปัจจุบันเพื่อใช้กำหนดสถานะ active ของเมนู
$student_current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Student Dashboard' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <style> body { font-family: 'Kanit', sans-serif; background-color: #f3f4f6; } </style>
</head>
<body>
    <header class="bg-white shadow-sm sticky top-0 z-10">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center gap-8">
                    <a href="dashboard.php" class="text-lg font-bold text-gray-800">Student Dashboard</a>
                    <nav class="hidden md:flex gap-6">
                        <a href="profile.php" class="text-sm font-medium <?= $student_current_page == 'profile.php' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-900' ?>">
                            แก้ไขข้อมูลส่วนตัว
                        </a>
                    </nav>
                </div>
                <div class="flex items-center gap-4">
                    <div class="text-right">
                        <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($_SESSION['student_full_name']) ?></p>
                        <a href="../logout.php" class="text-xs text-red-500 hover:text-red-700">ออกจากระบบ</a>
                    </div>
                    <a href="profile.php">
                        <img src="../assets/student_images/<?= $_SESSION['student_profile_image'] ?? 'default_avatar.png' ?>" alt="Profile" class="w-12 h-12 rounded-full object-cover border-2 border-gray-300 hover:border-blue-500 transition-colors">
                    </a>
                </div>
            </div>
        </div>
    </header>
    
    <?php
        if (isset($_SESSION['toast'])) {
            echo "<script>
                // ใช้ SweetAlert2 สร้าง Toast ที่มุมจอ
                const Toast = Swal.mixin({
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true,
                    didOpen: (toast) => {
                        toast.onmouseenter = Swal.stopTimer;
                        toast.onmouseleave = Swal.resumeTimer;
                    }
                });
                Toast.fire({
                    icon: '{$_SESSION['toast']['type']}',
                    title: '{$_SESSION['toast']['message']}'
                });
            </script>";
            unset($_SESSION['toast']);
        }
    ?>