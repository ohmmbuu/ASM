<?php
require_once '../config/db.php';

// Security check: if user is not logged in, redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ระบบบันทึกคะแนน</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"/>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Kanit', sans-serif; }
        .gradient-header { background: linear-gradient(90deg, #1e3a8a 0%, #2563eb 100%); }
        .btn-gradient { background-image: linear-gradient(to right, #3b82f6 0%, #60a5fa 51%, #3b82f6 100%); }
        .btn-gradient:hover { background-position: right center; }
        .card-header-gradient { background: linear-gradient(135deg, #60a5fa, #3b82f6); }
        .swal2-toast { z-index: 9999 !important; }
    </style>
</head>
<body class="bg-blue-50">

<div class="flex h-screen bg-gray-100">
    <div class="hidden md:flex flex-col w-64 bg-white shadow-lg">
        <div class="flex items-center justify-center h-20 shadow-md gradient-header text-white">
            <img src="https://www.i-pic.info/i/krVq1004512.jpg" class="h-10 w-10 rounded-full mr-3 border-2 border-white" alt="Logo">
            <h1 class="text-xl font-semibold">เมนูหลัก</h1>
        </div>
        <div class="flex flex-col flex-grow p-4">
            <nav>
                <a class="flex items-center px-4 py-3 mt-2 text-gray-700 rounded-lg <?= $current_page == 'dashboard.php' ? 'bg-blue-200 text-blue-800' : 'hover:bg-blue-100' ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt w-6 text-center"></i><span class="mx-3">แดชบอร์ด</span>
                </a>
                <a class="flex items-center px-4 py-3 mt-2 text-gray-700 rounded-lg <?= in_array($current_page, ['subjects.php', 'activities.php', 'record_scores.php', 'report.php']) ? 'bg-blue-200 text-blue-800' : 'hover:bg-blue-100' ?>" href="subjects.php">
                    <i class="fas fa-book w-6 text-center"></i><span class="mx-3">รายวิชาของฉัน</span>
                </a>
                <a class="flex items-center px-4 py-2 mt-1 text-sm text-gray-600 rounded-lg <?= $current_page == 'activities.php' ? 'bg-blue-100 text-blue-800' : 'hover:bg-blue-100' ?>" href="activities.php">
                    <i class="fas fa-tasks w-6 text-center"></i><span class="mx-3">กำหนดกิจกรรมคะแนนเก็บ</span>
                </a>
                <a class="flex items-center px-4 py-3 mt-2 text-gray-700 rounded-lg <?= $current_page == 'students.php' ? 'bg-blue-200 text-blue-800' : 'hover:bg-blue-100' ?>" href="students.php">
                    <i class="fas fa-users w-6 text-center"></i><span class="mx-3">จัดการนักเรียน</span>
                </a>
                  <a class="flex items-center px-4 py-3 mt-2 text-gray-700 rounded-lg <?= $current_page == 'manage_classes.php' ? 'bg-blue-200 text-blue-800' : 'hover:bg-blue-100' ?>" href="manage_classes.php">
                    <i class="fas fa-chalkboard-teacher w-6 text-center"></i><span class="mx-3">จัดการชั้นเรียน</span>
                </a>
                
                <hr class="my-3 border-t border-gray-200">

                <p class="px-4 text-gray-500 text-sm font-semibold">ระบบประเมินผล</p>
                
                <a class="flex items-center px-4 py-3 mt-2 text-gray-700 rounded-lg <?= $current_page == 'manage_dev_activities.php' ? 'bg-blue-200 text-blue-800' : 'hover:bg-blue-100' ?>" href="manage_dev_activities.php">
                    <i class="fas fa-cogs w-6 text-center"></i><span class="mx-3">จัดการกิจกรรมพัฒนาผู้เรียน</span>
                </a>
                <a class="flex items-center px-4 py-3 mt-2 text-gray-700 rounded-lg <?= in_array($current_page, ['record_dev_activities.php', 'report_dev_activities.php']) ? 'bg-blue-200 text-blue-800' : 'hover:bg-blue-100' ?>" href="record_dev_activities.php">
                    <i class="fas fa-running w-6 text-center"></i><span class="mx-3">ประเมินกิจกรรมพัฒนาผู้เรียน</span>
                </a>
                <a class="flex items-center px-4 py-3 mt-2 text-gray-700 rounded-lg <?= in_array($current_page, ['evaluate_characteristics.php', 'report_characteristics.php', 'manage_characteristics.php']) ? 'bg-blue-200 text-blue-800' : 'hover:bg-blue-100' ?>" href="evaluate_characteristics.php">
                    <i class="fas fa-award w-6 text-center"></i><span class="mx-3">ประเมินคุณลักษณะ</span>
                </a>
                <a class="flex items-center px-4 py-3 mt-2 text-gray-700 rounded-lg <?= in_array($current_page, ['evaluate_rtw.php', 'report_rtw.php', 'manage_rtw.php']) ? 'bg-blue-200 text-blue-800' : 'hover:bg-blue-100' ?>" href="evaluate_rtw.php">
                    <i class="fas fa-pen-nib w-6 text-center"></i><span class="mx-3">ประเมินการอ่านฯ</span>
                </a>

                <hr class="my-3 border-t border-gray-200">

                <a class="flex items-center px-4 py-3 mt-2 text-gray-700 rounded-lg <?= $current_page == 'profile.php' ? 'bg-blue-200 text-blue-800' : 'hover:bg-blue-100' ?>" href="profile.php">
                    <i class="fas fa-user-cog w-6 text-center"></i><span class="mx-3">ข้อมูลส่วนตัว</span>
                </a>

                <a class="flex items-center px-4 py-3 mt-auto text-red-700 rounded-lg hover:bg-red-100" href="../logout.php">
                    <i class="fas fa-sign-out-alt w-6 text-center"></i><span class="mx-3">ออกจากระบบ</span>
                </a>
            </nav>
        </div>
    </div>

    <div class="flex flex-col flex-1 overflow-y-auto">
        <div class="flex items-center justify-between h-20 px-6 bg-white border-b">
            <div class="flex items-center">
                 <h2 class="text-2xl font-semibold text-gray-800">
                    <?php 
                        if ($current_page == 'dashboard.php') echo 'แดชบอร์ด';
                        if ($current_page == 'subjects.php') echo 'จัดการรายวิชา';
                        if ($current_page == 'activities.php') echo 'จัดการกิจกรรม';
                        if ($current_page == 'record_scores.php') echo 'บันทึกคะแนน';
                        if ($current_page == 'report.php') echo 'รายงานผลคะแนน';
                        if ($current_page == 'students.php') echo 'จัดการข้อมูลนักเรียน';
                        if ($current_page == 'profile.php') echo 'จัดการข้อมูลส่วนตัว';
                        if ($current_page == 'evaluate_characteristics.php') echo 'ประเมินคุณลักษณะอันพึงประสงค์';
                        if ($current_page == 'report_characteristics.php') echo 'รายงานผลการประเมินคุณลักษณะฯ';
                        if ($current_page == 'manage_characteristics.php') echo 'จัดการรายการประเมินคุณลักษณะฯ';
                        if ($current_page == 'evaluate_rtw.php') echo 'ประเมินการอ่าน คิดวิเคราะห์ และเขียน';
                        if ($current_page == 'report_rtw.php') echo 'รายงานผลการอ่าน คิดวิเคราะห์ และเขียน';
                        if ($current_page == 'manage_rtw.php') echo 'จัดการรายการประเมินการอ่านฯ';
                        if ($current_page == 'manage_dev_activities.php') echo 'จัดการกิจกรรมพัฒนาผู้เรียน';
                        if ($current_page == 'record_dev_activities.php') echo 'บันทึกผลกิจกรรมพัฒนาผู้เรียน';
                        if ($current_page == 'report_dev_activities.php') echo 'รายงานผลกิจกรรมพัฒนาผู้เรียน';
                    ?>
                 </h2>
            </div>
            <div class="flex items-center">
                <span class="text-gray-600 mr-3 hidden sm:block">ยินดีต้อนรับ, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
                <a href="profile.php" class="text-gray-500 hover:text-blue-600 p-2 rounded-full" title="จัดการข้อมูลส่วนตัว">
                    <i class="fas fa-user-circle text-2xl"></i>
                </a>
            </div>
        </div>
        <div class="p-4 md:p-8">
        
        <?php
        if (isset($_SESSION['toast'])) {
            echo "<script>
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