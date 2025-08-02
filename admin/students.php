<?php
// === ส่วน Logic: การประมวลผลฟอร์ม (ต้องอยู่บนสุดเสมอ) ===
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

// --- จัดการ Action ต่างๆ ---
if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $filePath = '../assets/templates/student_template.csv';
    if (!file_exists($filePath)) {
        // สร้างไฟล์ถ้ายังไม่มี
        $file = fopen($filePath, 'w');
        fputcsv($file, ['student_code', 'first_name', 'last_name', 'class_name', 'academic_year', 'class_no']);
        fclose($file);
    }
    header('Content-Description: File Transfer');
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="student_template_'.date('Y-m-d').'.csv"');
    readfile($filePath);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'upload_csv') {
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] == 0) {
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            fgetcsv($file); // Skip header

            // ดึงข้อมูลชั้นเรียนทั้งหมดมาเตรียมไว้เพื่อค้นหา ID
            $classes_lookup = [];
            $all_classes = $conn->query("SELECT id, class_name, academic_year FROM classes")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($all_classes as $class) {
                $classes_lookup[trim($class['class_name']) . '|' . trim($class['academic_year'])] = $class['id'];
            }
            
            $conn->beginTransaction();
            try {
                $stmt = $conn->prepare("
                    INSERT INTO students (student_code, first_name, last_name, class_id, class_no) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE first_name=VALUES(first_name), last_name=VALUES(last_name), class_id=VALUES(class_id), class_no=VALUES(class_no)
                ");
                while (($col = fgetcsv($file)) !== FALSE) {
                    $class_name = mb_convert_encoding(trim($col[3]), 'UTF-8', 'auto');
                    $academic_year = mb_convert_encoding(trim($col[4]), 'UTF-8', 'auto');
                    // ค้นหา class_id จากชื่อและปีการศึกษา
                    $class_id = $classes_lookup[$class_name . '|' . $academic_year] ?? null;

                    $stmt->execute([
                        mb_convert_encoding($col[0], 'UTF-8', 'auto'), // student_code
                        mb_convert_encoding($col[1], 'UTF-8', 'auto'), // first_name
                        mb_convert_encoding($col[2], 'UTF-8', 'auto'), // last_name
                        $class_id, // class_id ที่หาเจอ
                        mb_convert_encoding($col[5], 'UTF-8', 'auto')  // class_no
                    ]);
                }
                $conn->commit();
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'นำเข้าข้อมูลนักเรียนสำเร็จ'];
            } catch (Exception $e) {
                $conn->rollBack();
                $_SESSION['toast'] = ['type' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
            }
            fclose($file);
        } else {
             $_SESSION['toast'] = ['type' => 'error', 'message' => 'ไม่สามารถอัปโหลดไฟล์ได้'];
        }
    } elseif ($action === 'add' || $action === 'edit') {
        $class_id = empty($_POST['class_id']) ? null : $_POST['class_id'];
        if ($action === 'add') {
            $stmt = $conn->prepare("INSERT INTO students (student_code, first_name, last_name, class_id, class_no) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$_POST['student_code'], $_POST['first_name'], $_POST['last_name'], $class_id, $_POST['class_no']]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'เพิ่มข้อมูลนักเรียนสำเร็จ'];
        } elseif ($action === 'edit') {
            $stmt = $conn->prepare("UPDATE students SET student_code=?, first_name=?, last_name=?, class_id=?, class_no=? WHERE id = ?");
            $stmt->execute([$_POST['student_code'], $_POST['first_name'], $_POST['last_name'], $class_id, $_POST['class_no'], $_POST['student_id']]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'แก้ไขข้อมูลนักเรียนสำเร็จ'];
        }
    }
    header("Location: students.php");
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $stmt = $conn->prepare("DELETE FROM students WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $_SESSION['toast'] = ['type' => 'warning', 'message' => 'ลบข้อมูลนักเรียนสำเร็จ'];
    header("Location: students.php");
    exit();
}

// --- เตรียมข้อมูลสำหรับ Modal และ Dropdown ---
$modal_info = ['type' => 'none', 'data' => []];
$classes_for_dropdown = $conn->query("SELECT id, class_name, academic_year FROM classes ORDER BY academic_year DESC, class_name ASC")->fetchAll(PDO::FETCH_ASSOC);

if (isset($_GET['modal'])) {
    $modal_info['type'] = $_GET['modal'];
    if ($_GET['modal'] === 'edit_student' && isset($_GET['id'])) {
        $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) $modal_info['data'] = $data;
    }
}

// === ส่วน View: การแสดงผล HTML ===
include 'header.php';
// ดึงข้อมูลนักเรียนพร้อมชื่อชั้นเรียน
$students = $conn->query("
    SELECT s.*, c.class_name 
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id 
    ORDER BY c.class_name, s.class_no, s.student_code ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="bg-white p-6 rounded-lg shadow-lg mb-8">
    <h3 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3"><i class="fas fa-file-csv mr-2"></i>นำเข้าข้อมูลนักเรียนจากไฟล์ CSV</h3>
    <div class="flex flex-wrap items-center gap-4">
        <a href="students.php?action=download_template" class="bg-green-600 text-white font-bold py-2 px-4 rounded-lg"><i class="fas fa-download mr-2"></i>ดาวน์โหลดแม่แบบ</a>
        <form action="students.php" method="post" enctype="multipart/form-data" class="flex items-center gap-2">
            <input type="hidden" name="action" value="upload_csv">
            <input type="file" name="csv_file" accept=".csv" required class="text-sm file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:font-semibold file:bg-blue-50 file:text-blue-700">
            <button type="submit" class="bg-blue-600 text-white font-bold py-2 px-4 rounded-lg"><i class="fas fa-upload mr-2"></i>อัปโหลด</button>
        </form>
    </div>
    <p class="text-xs text-gray-500 mt-2">หมายเหตุ: คอลัมน์ `class_name` และ `academic_year` ในไฟล์ CSV ต้องตรงกับข้อมูลในหน้าจัดการชั้นเรียน</p>
</div>

<div class="bg-white p-6 rounded-lg shadow-lg">
    <div class="flex justify-between items-center mb-4">
        <h3 class="text-xl font-semibold text-gray-800">ข้อมูลนักเรียนทั้งหมด</h3>
        <a href="?modal=add_student" class="btn-gradient text-white font-bold py-2 px-4 rounded-lg"><i class="fas fa-user-plus mr-2"></i>เพิ่มนักเรียน</a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full bg-white">
            <thead class="bg-gray-100">
                <tr>
                    <th class="py-3 px-4 text-left">รหัสนักเรียน</th><th class="py-3 px-4 text-left">ชื่อ-สกุล</th>
                    <th class="py-3 px-4 text-left">ระดับชั้น</th><th class="py-3 px-4 text-center">เลขที่</th>
                    <th class="py-3 px-4 text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $student): ?>
                    <tr class="border-b">
                        <td class="py-3 px-4"><?= htmlspecialchars($student['student_code']) ?></td>
                        <td class="py-3 px-4 font-semibold"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                        <td class="py-3 px-4"><?= htmlspecialchars($student['class_name'] ?? 'N/A') ?></td>
                        <td class="py-3 px-4 text-center"><?= htmlspecialchars($student['class_no']) ?></td>
                        <td class="py-3 px-4 text-center">
                            <a href="student_portfolio.php?student_id=<?= $student['id'] ?>" class="text-blue-500 mr-3" title="ดูรายงานสรุป"><i class="fas fa-id-card"></i></a>
                            <a href="?modal=edit_student&id=<?= $student['id'] ?>" class="text-yellow-500 mr-3" title="แก้ไข"><i class="fas fa-pencil-alt"></i></a>
                            <a href="#" onclick="confirmDelete('students.php?action=delete&id=<?= $student['id'] ?>')" class="text-red-500" title="ลบ"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($modal_info['type'] === 'add_student' || $modal_info['type'] === 'edit_student'): 
    $is_edit = $modal_info['type'] === 'edit_student'; $data = $modal_info['data']; ?>
<div class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full z-50">
    <div class="relative top-10 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white">
        <div class="card-header-gradient text-white p-4 rounded-t-md flex justify-between items-center">
             <h3 class="text-lg font-semibold"><?= $is_edit ? 'แก้ไขข้อมูลนักเรียน' : 'เพิ่มนักเรียนใหม่' ?></h3>
            <a href="students.php" class="text-white">&times;</a>
        </div>
        <form action="students.php" method="POST" class="p-4">
            <input type="hidden" name="action" value="<?= $is_edit ? 'edit' : 'add' ?>">
            <input type="hidden" name="student_id" value="<?= $data['id'] ?? '' ?>">
            <div class="mb-4"><label class="block">รหัสนักเรียน</label><input type="text" name="student_code" value="<?= htmlspecialchars($data['student_code'] ?? '') ?>" class="w-full p-2 border rounded" required></div>
            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div><label class="block">ชื่อ</label><input type="text" name="first_name" value="<?= htmlspecialchars($data['first_name'] ?? '') ?>" class="w-full p-2 border rounded" required></div>
                <div><label class="block">นามสกุล</label><input type="text" name="last_name" value="<?= htmlspecialchars($data['last_name'] ?? '') ?>" class="w-full p-2 border rounded" required></div>
            </div>
            <div class="grid md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block">ระดับชั้น</label>
                    <select name="class_id" class="w-full p-2 border rounded">
                        <option value="">-- ไม่ระบุ --</option>
                        <?php foreach($classes_for_dropdown as $class): ?>
                        <option value="<?= $class['id'] ?>" <?= ($data['class_id'] ?? '') == $class['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['class_name']) ?> (<?= htmlspecialchars($class['academic_year']) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label class="block">เลขที่</label><input type="number" name="class_no" value="<?= htmlspecialchars($data['class_no'] ?? '') ?>" class="w-full p-2 border rounded"></div>
            </div>
            <div class="flex justify-end pt-4 border-t">
                <a href="students.php" class="bg-gray-300 text-gray-800 py-2 px-4 rounded-lg mr-2">ยกเลิก</a>
                <button type="submit" class="btn-gradient text-white py-2 px-4 rounded-lg">บันทึกข้อมูล</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>