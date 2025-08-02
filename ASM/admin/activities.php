<?php
// === Logic Part ===
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$teacher_id = $_SESSION['user_id'];

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $subject_id = $_POST['subject_id'];

    if ($action === 'add_activity') {
        $stmt = $conn->prepare("INSERT INTO activities (subject_id, activity_name, max_score) VALUES (?, ?, ?)");
        $stmt->execute([$subject_id, $_POST['activity_name'], $_POST['max_score']]);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'เพิ่มกิจกรรมสำเร็จ'];
    }
    header("Location: activities.php?subject_id=" . $subject_id);
    exit();
}

// Handle GET actions
if (isset($_GET['action']) && $_GET['action'] === 'delete_activity') {
    $activity_id = $_GET['id'];
    $subject_id = $_GET['subject_id'];
    $stmt = $conn->prepare("DELETE FROM activities WHERE id = ?");
    $stmt->execute([$activity_id]);
    $_SESSION['toast'] = ['type' => 'warning', 'message' => 'ลบกิจกรรมสำเร็จ'];
    header("Location: activities.php?subject_id=" . $subject_id);
    exit();
}

// === View Part ===
include 'header.php';

// Fetch subjects for the dropdown
$subjects = $conn->query("SELECT * FROM subjects WHERE teacher_id = $teacher_id ORDER BY subject_code ASC")->fetchAll(PDO::FETCH_ASSOC);
$selected_subject_id = $_GET['subject_id'] ?? null;
?>

<div class="bg-white p-6 rounded-xl shadow-lg mb-8">
    <h3 class="text-xl font-semibold text-gray-800 mb-4">เลือกรายวิชาเพื่อจัดการกิจกรรม</h3>
    <form action="activities.php" method="GET">
        <select name="subject_id" onchange="this.form.submit()" class="w-full p-3 border-gray-300 rounded-lg shadow-sm">
            <option value="">-- กรุณาเลือกรายวิชา --</option>
            <?php foreach($subjects as $subject): ?>
                <option value="<?= $subject['id'] ?>" <?= ($selected_subject_id == $subject['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($subject['subject_code']) ?> - <?= htmlspecialchars($subject['subject_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($selected_subject_id): 
    // Fetch activities for the selected subject
    $activities_stmt = $conn->prepare("SELECT * FROM activities WHERE subject_id = ? ORDER BY id ASC");
    $activities_stmt->execute([$selected_subject_id]);
    $activities = $activities_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <div class="lg:col-span-1">
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-xl font-semibold mb-4">เพิ่มกิจกรรมคะแนนเก็บใหม่</h3>
            <form action="activities.php" method="POST">
                <input type="hidden" name="action" value="add_activity">
                <input type="hidden" name="subject_id" value="<?= $selected_subject_id ?>">
                <div class="mb-4">
                    <label class="block text-gray-700">ชื่อกิจกรรม</label>
                    <input type="text" name="activity_name" class="w-full p-2 border rounded" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">คะแนนเต็ม</label>
                    <input type="number" name="max_score" class="w-full p-2 border rounded" required>
                </div>
                <button type="submit" class="w-full btn-gradient text-white font-bold py-2 rounded-lg">เพิ่มกิจกรรม</button>
            </form>
        </div>
    </div>
    <div class="lg:col-span-2">
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <h3 class="text-xl font-semibold mb-4">กิจกรรมทั้งหมดในรายวิชานี้</h3>
            <table class="min-w-full bg-white">
                <thead class="bg-gray-50"><tr><th>ชื่อกิจกรรม</th><th class="text-center">คะแนนเต็ม</th><th class="text-center">จัดการ</th></tr></thead>
                <tbody>
                    <?php if(empty($activities)): ?>
                        <tr><td colspan="3" class="text-center py-4 text-gray-500">ยังไม่มีกิจกรรม</td></tr>
                    <?php else: ?>
                        <?php foreach($activities as $activity): ?>
                        <tr class="border-b">
                            <td class="py-2 px-3"><?= htmlspecialchars($activity['activity_name']) ?></td>
                            <td class="py-2 px-3 text-center"><?= htmlspecialchars($activity['max_score']) ?></td>
                            <td class="py-2 px-3 text-center">
                                <a href="#" onclick="confirmDelete('activities.php?action=delete_activity&id=<?= $activity['id'] ?>&subject_id=<?= $selected_subject_id ?>')" class="text-red-500 hover:text-red-700" title="ลบ"><i class="fas fa-trash-alt"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>