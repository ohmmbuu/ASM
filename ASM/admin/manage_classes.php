<?php
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

// Handle Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    if ($action === 'add_class') {
        $stmt = $conn->prepare("INSERT INTO classes (class_name, academic_year, teacher_id) VALUES (?, ?, ?)");
        $stmt->execute([$_POST['class_name'], $_POST['academic_year'], $_SESSION['user_id']]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'สร้างชั้นเรียนสำเร็จ'];
    }
    header("Location: manage_classes.php");
    exit();
}
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $stmt = $conn->prepare("DELETE FROM classes WHERE id = ?");
    $stmt->execute([$_GET['id']]);
    $_SESSION['toast'] = ['type' => 'warning', 'message' => 'ลบชั้นเรียนสำเร็จ'];
    header("Location: manage_classes.php");
    exit();
}

include 'header.php';
$classes = $conn->query("SELECT c.*, u.full_name as teacher_name FROM classes c LEFT JOIN users u ON c.teacher_id = u.id ORDER BY c.academic_year DESC, c.class_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-xl font-semibold mb-4">สร้างชั้นเรียนใหม่</h3>
            <form action="manage_classes.php" method="POST">
                <input type="hidden" name="action" value="add_class">
                <div class="mb-4">
                    <label class="block text-gray-700">ชื่อชั้นเรียน (เช่น ม.1/1)</label>
                    <input type="text" name="class_name" class="w-full p-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">ปีการศึกษา</label>
                    <input type="text" name="academic_year" value="<?= date('Y') + 543 ?>" class="w-full p-2 border rounded" required>
                </div>
                <button type="submit" class="w-full btn-gradient text-white font-bold py-2 rounded-lg">สร้างชั้นเรียน</button>
            </form>
        </div>
    </div>
    <div class="lg:col-span-2">
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-xl font-semibold mb-4">ชั้นเรียนทั้งหมด</h3>
            <table class="min-w-full bg-white">
                <thead class="bg-gray-50"><tr><th>ชื่อชั้นเรียน</th><th>ปีการศึกษา</th><th>ครูที่ปรึกษา</th><th>จัดการ</th></tr></thead>
                <tbody>
                    <?php foreach($classes as $class): ?>
                    <tr class="border-b">
                        <td class="py-2 px-3"><?= htmlspecialchars($class['class_name']) ?></td>
                        <td class="py-2 px-3 text-center"><?= htmlspecialchars($class['academic_year']) ?></td>
                        <td class="py-2 px-3"><?= htmlspecialchars($class['teacher_name']) ?></td>
                        <td class="py-2 px-3 text-center">
                            <a href="#" onclick="confirmDelete('manage_classes.php?action=delete&id=<?= $class['id'] ?>')" class="text-red-500"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>