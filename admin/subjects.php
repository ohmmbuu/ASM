<?php
// === ส่วน Logic: การประมวลผลฟอร์ม (ต้องอยู่บนสุดเสมอ) ===
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$teacher_id = $_SESSION['user_id'];

// --- จัดการการเพิ่ม / แก้ไข / ลบ / อัปเดตชั้นเรียน ---

// Handle POST actions (Add/Edit Subject, Update Classes for Subject)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'add_subject') {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_code, subject_name, max_score_keep, max_score_midterm, max_score_final, teacher_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_POST['subject_code'], $_POST['subject_name'], $_POST['max_score_keep'], $_POST['max_score_midterm'], $_POST['max_score_final'], $teacher_id]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'เพิ่มรายวิชาสำเร็จ'];
    } elseif ($action === 'edit_subject') {
        $stmt = $conn->prepare("UPDATE subjects SET subject_code=?, subject_name=?, max_score_keep=?, max_score_midterm=?, max_score_final=? WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$_POST['subject_code'], $_POST['subject_name'], $_POST['max_score_keep'], $_POST['max_score_midterm'], $_POST['max_score_final'], $_POST['subject_id'], $teacher_id]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'แก้ไขรายวิชาสำเร็จ'];
    } elseif ($action === 'update_subject_classes') {
        $subject_id = $_POST['subject_id'];
        $class_ids = $_POST['class_ids'] ?? [];

        $conn->beginTransaction();
        try {
            $delete_stmt = $conn->prepare("DELETE FROM subject_classes WHERE subject_id = ?");
            $delete_stmt->execute([$subject_id]);

            if (!empty($class_ids)) {
                $insert_stmt = $conn->prepare("INSERT INTO subject_classes (subject_id, class_id) VALUES (?, ?)");
                foreach ($class_ids as $class_id) {
                    $insert_stmt->execute([$subject_id, $class_id]);
                }
            }
            $conn->commit();
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'อัปเดตชั้นเรียนสำหรับวิชาสำเร็จ'];
        } catch (Exception $e) {
            $conn->rollBack();
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
        }
    }
    header("Location: subjects.php");
    exit();
}

// Handle GET actions (Delete Subject)
if (isset($_GET['action']) && $_GET['action'] === 'delete_subject') {
    $stmt = $conn->prepare("DELETE FROM subjects WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$_GET['id'], $teacher_id]);
    $_SESSION['toast'] = ['type' => 'warning', 'message' => 'ลบรายวิชาสำเร็จ'];
    header("Location: subjects.php");
    exit();
}

// --- เตรียมข้อมูลสำหรับ Modal ---
$modal_info = ['type' => 'none', 'data' => null];
if (isset($_GET['modal'])) {
    $modal_type = $_GET['modal'];
    $id = $_GET['id'] ?? null;
    
    if (($modal_type === 'add_subject' || $modal_type === 'edit_subject') && $id) {
        $stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$id, $teacher_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) $modal_info = ['type' => $modal_type, 'data' => $data];
    } elseif ($modal_type === 'add_subject') {
        $modal_info['type'] = 'add_subject';
    } elseif ($modal_type === 'manage_classes' && $id) {
        $modal_info['type'] = 'manage_classes';
        $modal_info['data']['id'] = $id;
    }
}

// === ส่วน View: การแสดงผล HTML ===
include 'header.php';

// ดึงข้อมูลทั้งหมดที่ต้องใช้
$subjects = $conn->query("SELECT * FROM subjects WHERE teacher_id = $teacher_id ORDER BY subject_code ASC")->fetchAll(PDO::FETCH_ASSOC);
$all_classes = $conn->query("SELECT * FROM classes ORDER BY academic_year DESC, class_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$subject_classes_map = [];
$stmt_map = $conn->query("SELECT * FROM subject_classes");
while ($row = $stmt_map->fetch(PDO::FETCH_ASSOC)) {
    $subject_classes_map[$row['subject_id']][] = $row['class_id'];
}
?>

<div class="flex justify-between items-center mb-6">
    <h3 class="text-2xl font-semibold text-gray-800">จัดการรายวิชาและชั้นเรียน</h3>
    <a href="?modal=add_subject" class="btn-gradient text-white font-bold py-2 px-4 rounded-lg shadow-md">
        <i class="fas fa-plus mr-2"></i>เพิ่มรายวิชาใหม่
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
    <?php foreach ($subjects as $subject): ?>
    <div class="bg-white rounded-xl shadow-lg flex flex-col hover:shadow-xl transition-shadow duration-300">
        <div class="p-6">
            <div class="flex justify-between items-start">
                <p class="text-sm font-semibold text-blue-600"><?= htmlspecialchars($subject['subject_code']) ?></p>
                <div class="flex space-x-3">
                     <a href="?modal=edit_subject&id=<?= $subject['id'] ?>" class="text-gray-400 hover:text-yellow-500" title="แก้ไขรายวิชา"><i class="fas fa-pencil-alt"></i></a>
                     <a href="#" onclick="confirmDelete('subjects.php?action=delete_subject&id=<?= $subject['id'] ?>')" class="text-gray-400 hover:text-red-500" title="ลบรายวิชา"><i class="fas fa-trash-alt"></i></a>
                </div>
            </div>
            <h4 class="text-xl font-bold text-gray-800 mt-1"><?= htmlspecialchars($subject['subject_name']) ?></h4>
             <p class="text-xs text-gray-500 mt-2">คะแนน: เก็บ <?= $subject['max_score_keep'] ?>, กลางภาค <?= $subject['max_score_midterm'] ?>, ปลายภาค <?= $subject['max_score_final'] ?></p>
        </div>
        <div class="p-6 bg-gray-50 flex-grow">
            <h5 class="text-sm font-semibold text-gray-600 mb-3">ชั้นเรียนที่สอน:</h5>
            <div class="flex flex-wrap gap-2">
                <?php 
                    $linked_class_ids = $subject_classes_map[$subject['id']] ?? [];
                    if (empty($linked_class_ids)) {
                        echo '<span class="text-xs text-gray-500 italic">ยังไม่ได้กำหนดชั้นเรียน</span>';
                    } else {
                        foreach ($all_classes as $class) {
                            if (in_array($class['id'], $linked_class_ids)) {
                                echo '<span class="text-xs font-semibold bg-blue-100 text-blue-800 py-1 px-3 rounded-full">'.htmlspecialchars($class['class_name']).'</span>';
                            }
                        }
                    }
                ?>
            </div>
        </div>
        <div class="border-t p-4 flex justify-end items-center gap-2 bg-white rounded-b-xl">
            <a href="?modal=manage_classes&id=<?= $subject['id'] ?>" class="bg-blue-500 hover:bg-blue-600 text-white text-sm font-bold py-2 px-3 rounded-lg"><i class="fas fa-chalkboard-teacher mr-2"></i>จัดการชั้นเรียน</a>
            <a href="record_scores.php?subject_id=<?= $subject['id'] ?>" class="bg-green-500 hover:bg-green-600 text-white text-sm font-bold py-2 px-3 rounded-lg"><i class="fas fa-edit mr-2"></i>บันทึกคะแนน</a>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($modal_info['type'] === 'add_subject' || $modal_info['type'] === 'edit_subject'):
    $is_edit = $modal_info['type'] === 'edit_subject'; $data = $modal_info['data'] ?? []; ?>
<div class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full z-50 flex items-center justify-center p-4">
    <div class="relative w-full max-w-lg bg-white rounded-md shadow-lg">
         <form action="subjects.php" method="POST" class="p-6">
            <h3 class="text-lg font-semibold mb-4"><?= $is_edit ? 'แก้ไขรายวิชา' : 'เพิ่มรายวิชาใหม่' ?></h3>
            <input type="hidden" name="action" value="<?= $is_edit ? 'edit_subject' : 'add_subject' ?>">
            <input type="hidden" name="subject_id" value="<?= $data['id'] ?? '' ?>">
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div><label>รหัสวิชา</label><input type="text" name="subject_code" value="<?= htmlspecialchars($data['subject_code'] ?? '')?>" class="w-full p-2 border rounded" required></div>
                <div><label>ชื่อวิชา</label><input type="text" name="subject_name" value="<?= htmlspecialchars($data['subject_name'] ?? '')?>" class="w-full p-2 border rounded" required></div>
            </div>
             <div class="grid grid-cols-3 gap-4 mb-4">
                <div><label>คะแนนเก็บ</label><input type="number" name="max_score_keep" value="<?= htmlspecialchars($data['max_score_keep'] ?? '50')?>" class="w-full p-2 border rounded" required></div>
                <div><label>กลางภาค</label><input type="number" name="max_score_midterm" value="<?= htmlspecialchars($data['max_score_midterm'] ?? '25')?>" class="w-full p-2 border rounded" required></div>
                <div><label>ปลายภาค</label><input type="number" name="max_score_final" value="<?= htmlspecialchars($data['max_score_final'] ?? '25')?>" class="w-full p-2 border rounded" required></div>
            </div>
            <div class="flex justify-end pt-4 gap-2 border-t mt-4">
                <a href="subjects.php" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-lg">ยกเลิก</a>
                <button type="submit" class="btn-gradient text-white py-2 px-4 rounded-lg">บันทึก</button>
            </div>
         </form>
    </div>
</div>
<?php endif; ?>


<?php if ($modal_info['type'] === 'manage_classes'):
    $subject_id_for_modal = $modal_info['data']['id'];
    $subject_for_modal = $conn->query("SELECT * FROM subjects WHERE id = $subject_id_for_modal")->fetch();
    $linked_class_ids_for_modal = $subject_classes_map[$subject_id_for_modal] ?? [];
?>
<div class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full z-50 flex items-center justify-center p-4">
    <div class="relative w-full max-w-xl bg-white rounded-md shadow-lg">
        <div class="p-6">
            <h3 class="text-lg font-semibold">จัดการชั้นเรียนสำหรับวิชา: <span class="text-blue-600"><?= htmlspecialchars($subject_for_modal['subject_name']) ?></span></h3>
            <p class="text-sm text-gray-500 mb-4">เลือกชั้นเรียนทั้งหมดที่เรียนวิชานี้</p>
            <form action="subjects.php" method="POST">
                <input type="hidden" name="action" value="update_subject_classes">
                <input type="hidden" name="subject_id" value="<?= $subject_id_for_modal ?>">
                <div class="space-y-2 h-64 overflow-y-auto border p-4 rounded-lg">
                    <?php foreach($all_classes as $class): 
                        $is_checked = in_array($class['id'], $linked_class_ids_for_modal);
                    ?>
                        <label class="flex items-center p-2 rounded-lg hover:bg-gray-100 cursor-pointer">
                            <input type="checkbox" name="class_ids[]" value="<?= $class['id'] ?>" class="h-5 w-5 rounded text-blue-600 focus:ring-blue-500" <?= $is_checked ? 'checked' : '' ?>>
                            <span class="ml-3 text-gray-700"><?= htmlspecialchars($class['class_name']) ?> (ปีการศึกษา <?= htmlspecialchars($class['academic_year']) ?>)</span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="flex justify-end pt-4 gap-2 border-t mt-4">
                    <a href="subjects.php" class="bg-gray-200 text-gray-800 py-2 px-4 rounded-lg">ยกเลิก</a>
                    <button type="submit" class="btn-gradient text-white font-bold py-2 px-4 rounded-lg">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>