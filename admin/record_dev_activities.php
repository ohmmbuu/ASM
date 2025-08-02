<?php
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$teacher_id = $_SESSION['user_id'];

// --- Logic Part ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activity_id'])) {
    $activity_id = $_POST['activity_id'];
    $results = $_POST['results'] ?? [];
    
    $stmt = $conn->prepare("
        INSERT INTO dev_activity_results (student_id, activity_id, result, evaluation_date)
        VALUES (?, ?, ?, CURDATE())
        ON DUPLICATE KEY UPDATE result = VALUES(result), evaluation_date = CURDATE()
    ");
    foreach ($results as $student_id => $result) {
        if (!empty($result)) {
            $stmt->execute([$student_id, $activity_id, $result]);
        }
    }
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'บันทึกผลกิจกรรมสำเร็จ'];
    // Redirect back with filters
    $redirect_url = "record_dev_activities.php?activity_id=" . $activity_id;
    if (isset($_POST['class_id'])) {
        $redirect_url .= "&class_id=" . $_POST['class_id'];
    }
    header("Location: " . $redirect_url);
    exit();
}

// --- View Part ---
include 'header.php';
$activities = $conn->query("SELECT * FROM dev_activities WHERE teacher_id = $teacher_id ORDER BY academic_year DESC, activity_name ASC")->fetchAll(PDO::FETCH_ASSOC);
$classes = $conn->query("SELECT id, class_name, academic_year FROM classes ORDER BY academic_year DESC, class_name ASC")->fetchAll(PDO::FETCH_ASSOC);

$selected_activity_id = $_GET['activity_id'] ?? null;
$selected_class_id = $_GET['class_id'] ?? null;
?>

<div class="bg-white p-6 rounded-xl shadow-lg mb-8">
    <h3 class="text-xl font-semibold text-gray-800 mb-4"><i class="fas fa-filter mr-2 text-blue-500"></i>เลือกกิจกรรมและชั้นเรียน</h3>
    <form action="record_dev_activities.php" method="GET" id="filterForm" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="activity_id" class="block text-sm font-medium text-gray-700 mb-1">กิจกรรม</label>
            <select name="activity_id" id="activity_id" class="w-full p-2 border-gray-300 rounded-lg shadow-sm">
                <option value="">-- กรุณาเลือกกิจกรรม --</option>
                <?php foreach($activities as $activity): ?>
                    <option value="<?= $activity['id'] ?>" <?= ($selected_activity_id == $activity['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($activity['activity_name']) ?> (ปีการศึกษา <?= htmlspecialchars($activity['academic_year']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="class_id" class="block text-sm font-medium text-gray-700 mb-1">ชั้นเรียน (กรองนักเรียน)</label>
            <select name="class_id" id="class_id" class="w-full p-2 border-gray-300 rounded-lg shadow-sm">
                <option value="">-- ทุกชั้นเรียน --</option>
                <?php foreach($classes as $class): ?>
                    <option value="<?= $class['id'] ?>" <?= ($selected_class_id == $class['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($class['class_name']) ?> (ปี <?= htmlspecialchars($class['academic_year']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="md:col-span-2 text-right">
             <button type="submit" class="btn-gradient text-white font-bold py-2 px-6 rounded-lg">แสดงรายชื่อ</button>
        </div>
    </form>
</div>

<?php if($selected_activity_id && is_numeric($selected_activity_id)): 
    // Build student query with class filter
    $student_query_sql = "SELECT * FROM students";
    $params = [];
    if ($selected_class_id) {
        $student_query_sql .= " WHERE class_id = ?";
        $params[] = $selected_class_id;
    }
    $student_query_sql .= " ORDER BY class_no, student_code";
    $students_stmt = $conn->prepare($student_query_sql);
    $students_stmt->execute($params);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch existing results for this activity
    $results_stmt = $conn->prepare("SELECT student_id, result FROM dev_activity_results WHERE activity_id = ?");
    $results_stmt->execute([$selected_activity_id]);
    $existing_results = $results_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<div class="bg-white p-6 rounded-xl shadow-lg">
    <form action="record_dev_activities.php" method="POST">
        <input type="hidden" name="activity_id" value="<?= $selected_activity_id ?>">
        <input type="hidden" name="class_id" value="<?= $selected_class_id ?>">
        
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-semibold text-gray-800">บันทึกผลการประเมิน</h3>
            <input type="text" id="studentSearch" placeholder="ค้นหาชื่อนักเรียน..." class="w-full md:w-64 pl-4 pr-4 py-2 border rounded-lg">
        </div>

        <div id="studentGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <?php foreach($students as $student): 
                $current_result = $existing_results[$student['id']] ?? 'ผ่าน'; // Default to "ผ่าน"
            ?>
            <div class="student-card border rounded-lg p-4 bg-gray-50" data-name="<?= strtolower(htmlspecialchars($student['first_name'].' '.$student['last_name'])) ?>">
                <p class="font-bold text-gray-800"><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></p>
                <div class="mt-4 flex gap-2">
                    <input type="radio" name="results[<?= $student['id'] ?>]" id="pass_<?= $student['id'] ?>" value="ผ่าน" class="hidden peer/pass" <?= $current_result == 'ผ่าน' ? 'checked' : '' ?>>
                    <label for="pass_<?= $student['id'] ?>" class="w-1/2 text-center py-2 rounded-md cursor-pointer text-sm font-semibold bg-gray-200 text-gray-700 peer-checked/pass:bg-green-600 peer-checked/pass:text-white transition-colors">ผ่าน</label>

                    <input type="radio" name="results[<?= $student['id'] ?>]" id="fail_<?= $student['id'] ?>" value="ไม่ผ่าน" class="hidden peer/fail" <?= $current_result == 'ไม่ผ่าน' ? 'checked' : '' ?>>
                    <label for="fail_<?= $student['id'] ?>" class="w-1/2 text-center py-2 rounded-md cursor-pointer text-sm font-semibold bg-gray-200 text-gray-700 peer-checked/fail:bg-red-600 peer-checked/fail:text-white transition-colors">ไม่ผ่าน</label>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-8 border-t pt-6 text-right">
            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-8 rounded-lg shadow-lg">บันทึกผลทั้งหมด</button>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
document.getElementById('studentSearch').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    document.querySelectorAll('.student-card').forEach(card => {
        let name = card.getAttribute('data-name');
        card.style.display = name.includes(filter) ? '' : 'none';
    });
});
</script>

<?php include 'footer.php'; ?>