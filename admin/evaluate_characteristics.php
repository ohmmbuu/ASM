<?php
// === ส่วน Logic: การประมวลผลฟอร์ม (ต้องอยู่บนสุดเสมอ) ===
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

$evaluator_id = $_SESSION['user_id'];

// Handle form submission when a user saves an evaluation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = $_POST['student_id'];
    $semester = $_POST['semester'] ?? '1';
    $academic_year = $_POST['academic_year'] ?? date('Y') + 543;

    // Use a transaction to ensure data integrity
    $conn->beginTransaction();
    try {
        // Step 1: Find or create a master evaluation record
        $stmt_eval = $conn->prepare("SELECT id FROM char_evaluations WHERE student_id = ? AND semester = ? AND academic_year = ?");
        $stmt_eval->execute([$student_id, $semester, $academic_year]);
        $evaluation_id = $stmt_eval->fetchColumn();

        if (!$evaluation_id) {
            $stmt_insert_eval = $conn->prepare("INSERT INTO char_evaluations (student_id, evaluator_id, semester, academic_year, evaluation_date) VALUES (?, ?, ?, ?, ?)");
            $stmt_insert_eval->execute([$student_id, $evaluator_id, $semester, $academic_year, date('Y-m-d')]);
            $evaluation_id = $conn->lastInsertId();
        } else {
            $stmt_update_eval = $conn->prepare("UPDATE char_evaluations SET evaluator_id = ?, evaluation_date = ? WHERE id = ?");
            $stmt_update_eval->execute([$evaluator_id, date('Y-m-d'), $evaluation_id]);
        }

        // Step 2: Insert or update individual scores
        $stmt_score = $conn->prepare("
            INSERT INTO char_evaluation_scores (evaluation_id, item_id, score) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE score = VALUES(score)
        ");
        
        if (!empty($_POST['scores'])) {
            foreach ($_POST['scores'] as $item_id => $score) {
                $stmt_score->execute([$evaluation_id, $item_id, $score]);
            }
        }

        $conn->commit();
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'บันทึกผลการประเมินสำเร็จ'];

    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'เกิดข้อผิดพลาดในการบันทึก: ' . $e->getMessage()];
    }
    
    header("Location: evaluate_characteristics.php?student_id=".$student_id);
    exit();
}

// === ส่วน View: การแสดงผล HTML ===
include 'header.php';

// Check if a specific student is being evaluated
if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
    $student_id = $_GET['student_id'];
    $stmt = $conn->prepare("SELECT s.*, c.class_name FROM students s LEFT JOIN classes c ON s.class_id = c.id WHERE s.id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    $traits = $conn->query("SELECT * FROM char_traits ORDER BY trait_order ASC")->fetchAll(PDO::FETCH_ASSOC);
    $items_stmt = $conn->prepare("SELECT * FROM char_items WHERE trait_id = ? ORDER BY item_order ASC");

    $current_semester = '1';
    $current_year = date('Y') + 543;
    
    $stmt_eval_scores = $conn->prepare("
        SELECT s.item_id, s.score FROM char_evaluation_scores s
        JOIN char_evaluations e ON s.evaluation_id = e.id
        WHERE e.student_id = ? AND e.semester = ? AND e.academic_year = ?
    ");
    $stmt_eval_scores->execute([$student_id, $current_semester, $current_year]);
    $existing_scores = $stmt_eval_scores->fetchAll(PDO::FETCH_KEY_PAIR);

    function render_dynamic_radios($item_id, $existing_score) {
        $html = '<div class="flex justify-center space-x-8 w-40">';
        for ($i = 3; $i >= 1; $i--) {
            $checked = ($existing_score !== null && $existing_score == $i) || ($existing_score === null && $i == 3) ? 'checked' : '';
            $html .= "<div class='flex items-center justify-center w-6'><input type='radio' name='scores[{$item_id}]' value='{$i}' {$checked} required class='h-4 w-4 text-blue-600'></div>";
        }
        $html .= '</div>';
        return $html;
    }
?>
    <div class="mb-4"><a href="evaluate_characteristics.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>กลับไปหน้ารายชื่อนักเรียน</a></div>
    <form action="evaluate_characteristics.php" method="POST">
        <input type="hidden" name="student_id" value="<?= $student_id ?>">
        <div class="bg-white p-6 rounded-xl shadow-lg">
            <div class="flex flex-wrap justify-between items-center border-b pb-4 mb-4">
                <h3 class="text-xl font-semibold">นักเรียน: <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?> (ชั้น <?= htmlspecialchars($student['class_name'] ?? 'N/A') ?>)</h3>
                 <p class="text-sm text-gray-600">ภาคเรียนที่: <?= $current_semester ?> / <?= $current_year ?></p>
                 <input type="hidden" name="semester" value="<?= $current_semester ?>">
                 <input type="hidden" name="academic_year" value="<?= $current_year ?>">
            </div>
            
            <div class="flex items-center font-semibold bg-gray-100 p-2 rounded-t-lg text-sm">
                <div class="flex-grow">รายการประเมิน</div>
                <div class="flex justify-center space-x-8 w-40"><div class="w-6 text-center">3</div><div class="w-6 text-center">2</div><div class="w-6 text-center">1</div></div>
            </div>

            <?php foreach($traits as $trait): ?>
                <div class="p-2 border-b">
                    <div class="font-bold text-lg text-blue-700"><?= htmlspecialchars($trait['trait_name']) ?></div>
                    <?php 
                        $items_stmt->execute([$trait['id']]);
                        $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach($items as $item):
                    ?>
                        <div class="flex items-center py-1 text-sm">
                            <div class="flex-grow pl-4"><?= htmlspecialchars($item['item_text']) ?></div>
                            <?= render_dynamic_radios($item['id'], $existing_scores[$item['id']] ?? null) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>

            <div class="mt-6 text-right"><button type="submit" class="btn-gradient text-white font-bold py-3 px-6 rounded-lg">บันทึกผลการประเมิน</button></div>
        </div>
    </form>
<?php
} else {
    // === ส่วนแสดงรายชื่อนักเรียน (เพิ่มตัวกรอง) ===
    $classes = $conn->query("SELECT id, class_name, academic_year FROM classes ORDER BY academic_year DESC, class_name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $selected_class_id = $_GET['class_id'] ?? null;

    $student_sql = "SELECT s.id, s.first_name, s.last_name, s.class_no, c.class_name 
                    FROM students s 
                    LEFT JOIN classes c ON s.class_id = c.id";
    $params = [];
    if ($selected_class_id) {
        $student_sql .= " WHERE s.class_id = ?";
        $params[] = $selected_class_id;
    }
    $student_sql .= " ORDER BY c.class_name, s.class_no";
    
    $students_stmt = $conn->prepare($student_sql);
    $students_stmt->execute($params);
    $students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
    <div class="flex flex-wrap justify-between items-center gap-4 mb-4">
        <form action="evaluate_characteristics.php" method="GET" class="flex items-center gap-2">
            <label for="class_id" class="font-semibold text-sm">กรองตามระดับชั้น:</label>
            <select name="class_id" id="class_id" onchange="this.form.submit()" class="p-2 border rounded-lg shadow-sm">
                <option value="">-- ทุกชั้นเรียน --</option>
                <?php foreach($classes as $class): ?>
                    <option value="<?= $class['id'] ?>" <?= ($selected_class_id == $class['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($class['class_name']) ?> (<?= $class['academic_year'] ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="flex gap-2">
            <a href="manage_characteristics.php" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">จัดการรายการประเมิน</a>
            <a href="report_characteristics.php" class="bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg">ดูรายงานสรุปผล</a>
        </div>
    </div>
    <div class="bg-white p-6 rounded-lg shadow-lg">
        <h3 class="text-xl font-semibold mb-4">เลือกนักเรียนเพื่อประเมินคุณลักษณะฯ</h3>
        <table class="min-w-full bg-white">
            <thead class="bg-gray-100">
                <tr>
                    <th class="py-3 px-4 text-left">ระดับชั้น</th>
                    <th class="py-3 px-4 text-center">เลขที่</th>
                    <th class="py-3 px-4 text-left">ชื่อ-สกุล</th>
                    <th class="py-3 px-4 text-center">ดำเนินการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($students)): ?>
                    <tr><td colspan="4" class="text-center py-4 text-gray-500">ไม่พบข้อมูลนักเรียน (หรือลองเลือกตัวกรองอื่น)</td></tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-4"><?= htmlspecialchars($student['class_name'] ?? 'N/A') ?></td>
                            <td class="py-2 px-4 text-center"><?= htmlspecialchars($student['class_no']) ?></td>
                            <td class="py-2 px-4 font-semibold"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></td>
                            <td class="py-2 px-4 text-center"><a href="evaluate_characteristics.php?student_id=<?= $student['id'] ?>" class="btn-gradient text-white text-xs font-bold py-2 px-3 rounded-md">ประเมิน</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php
}
include 'footer.php';
?>