<?php
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

$teacher_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity_name = $_POST['activity_name'];
    $academic_year = $_POST['academic_year'];
    $stmt = $conn->prepare("INSERT INTO dev_activities (activity_name, academic_year, teacher_id) VALUES (?, ?, ?)");
    $stmt->execute([$activity_name, $academic_year, $teacher_id]);
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'สร้างกิจกรรมสำเร็จ'];
    header("Location: manage_dev_activities.php");
    exit();
}

if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $stmt = $conn->prepare("DELETE FROM dev_activities WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$_GET['id'], $teacher_id]);
    $_SESSION['toast'] = ['type' => 'warning', 'message' => 'ลบกิจกรรมสำเร็จ'];
    header("Location: manage_dev_activities.php");
    exit();
}

include 'header.php';
$activities = $conn->query("SELECT * FROM dev_activities WHERE teacher_id = $teacher_id ORDER BY academic_year DESC, activity_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h3 class="text-xl font-semibold mb-4">สร้างกิจกรรมพัฒนาผู้เรียนใหม่</h3>
            <form action="manage_dev_activities.php" method="POST">
                <div class="mb-4">
                    <label for="activity_name" class="block text-gray-700">ชื่อกิจกรรม (เช่น ลูกเสือ-เนตรนารี, ชุมนุมหุ่นยนต์)</label>
                    <input type="text" name="activity_name" class="w-full px-3 py-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label for="academic_year" class="block text-gray-700">ปีการศึกษา</label>
                    <input type="text" name="academic_year" value="<?= date('Y') + 543 ?>" class="w-full px-3 py-2 border rounded" required>
                </div>
                <button type="submit" class="w-full btn-gradient text-white font-bold py-2 px-4 rounded-lg">สร้างกิจกรรม</button>
            </form>
        </div>
    </div>
    <div class="lg:col-span-2">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h3 class="text-xl font-semibold mb-4">กิจกรรมของคุณ</h3>
            <table class="min-w-full bg-white">
                <thead class="bg-gray-100"><tr><th>ชื่อกิจกรรม</th><th>ปีการศึกษา</th><th>จัดการ</th></tr></thead>
                <tbody>
                    <?php foreach($activities as $activity): ?>
                    <tr class="border-b">
                        <td class="py-2 px-4"><?= htmlspecialchars($activity['activity_name']) ?></td>
                        <td class="py-2 px-4 text-center"><?= htmlspecialchars($activity['academic_year']) ?></td>
                        <td class="py-2 px-4 text-center">
                             <a href="#" onclick="confirmDelete('manage_dev_activities.php?action=delete&id=<?= $activity['id'] ?>')" class="text-red-500"><i class="fas fa-trash-alt"></i></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>