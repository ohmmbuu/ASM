<?php
// === Logic Part ===
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$teacher_id = $_SESSION['user_id'];

// Validate Subject ID from URL and check ownership
if (!isset($_GET['subject_id']) || !is_numeric($_GET['subject_id'])) {
    header("Location: subjects.php");
    exit();
}
$subject_id = $_GET['subject_id'];
$subject_stmt = $conn->prepare("SELECT * FROM subjects WHERE id = ? AND teacher_id = ?");
$subject_stmt->execute([$subject_id, $teacher_id]);
$subject = $subject_stmt->fetch(PDO::FETCH_ASSOC);
if (!$subject) {
    $_SESSION['toast'] = ['type' => 'error', 'message' => 'ไม่พบรายวิชาที่ระบุ'];
    header("Location: subjects.php");
    exit();
}

// Handle POST request for saving all scores
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activity_scores_data = $_POST['activity_scores'] ?? [];
    $exam_scores_data = $_POST['exam_scores'] ?? [];

    $conn->beginTransaction();
    try {
        $stmt_activity = $conn->prepare("INSERT INTO activity_scores (student_id, activity_id, score) VALUES (:student_id, :activity_id, :score) ON DUPLICATE KEY UPDATE score = :score");
        foreach ($activity_scores_data as $student_id => $activities) {
            foreach ($activities as $activity_id => $score) {
                $stmt_activity->execute(['student_id' => $student_id, 'activity_id' => $activity_id, 'score' => ($score === '' ? null : $score)]);
            }
        }
        
        $stmt_exam = $conn->prepare("INSERT INTO exam_scores (student_id, subject_id, midterm_score, final_score) VALUES (:student_id, :subject_id, :midterm_score, :final_score) ON DUPLICATE KEY UPDATE midterm_score = :midterm_score, final_score = :final_score");
        foreach ($exam_scores_data as $student_id => $scores) {
            $stmt_exam->execute([
                'student_id' => $student_id, 'subject_id' => $subject_id,
                'midterm_score' => ($scores['midterm'] === '' ? null : $scores['midterm']),
                'final_score' => ($scores['final'] === '' ? null : $scores['final'])
            ]);
        }
        $conn->commit();
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'บันทึกคะแนนเรียบร้อยแล้ว'];
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'เกิดข้อผิดพลาด: ' . $e->getMessage()];
    }
    header("Location: record_scores.php?subject_id=" . $subject_id);
    exit();
}

// === View Part ===
include 'header.php';

// Fetch data for display
// ** The crucial change is here: Fetch only students linked to this subject **
$students_stmt = $conn->prepare("
    SELECT s.*, c.class_name 
    FROM students s
    JOIN classes c ON s.class_id = c.id
    WHERE s.class_id IN (SELECT class_id FROM subject_classes WHERE subject_id = ?)
    ORDER BY c.class_name, s.class_no
");
$students_stmt->execute([$subject_id]);
$students = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

$activities = $conn->query("SELECT * FROM activities WHERE subject_id = $subject_id ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing scores for all relevant students at once for efficiency
$student_ids = array_column($students, 'id');
$all_activity_scores = [];
$all_exam_scores = [];
if (!empty($student_ids)) {
    $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
    
    $stmt_scores = $conn->prepare("SELECT student_id, activity_id, score FROM activity_scores WHERE student_id IN ($placeholders)");
    $stmt_scores->execute($student_ids);
    while($row = $stmt_scores->fetch(PDO::FETCH_ASSOC)) {
        $all_activity_scores[$row['student_id']][$row['activity_id']] = $row['score'];
    }
    
    $stmt_exam_scores = $conn->prepare("SELECT * FROM exam_scores WHERE subject_id = ? AND student_id IN ($placeholders)");
    $params = array_merge([$subject_id], $student_ids);
    $stmt_exam_scores->execute($params);
    while($row = $stmt_exam_scores->fetch(PDO::FETCH_ASSOC)) {
        $all_exam_scores[$row['student_id']] = $row;
    }
}
?>

<div class="mb-4">
    <a href="subjects.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>กลับไปหน้ารายวิชา</a>
    <h2 class="text-2xl font-bold text-gray-800 mt-2">บันทึกคะแนน: <?= htmlspecialchars($subject['subject_name']) ?></h2>
</div>

<form action="record_scores.php?subject_id=<?= $subject_id ?>" method="POST">
<div class="bg-white p-6 rounded-lg shadow-lg overflow-x-auto">
    <?php if (empty($students)): ?>
        <div class="text-center py-8 text-gray-500">
            <i class="fas fa-info-circle text-4xl mb-3"></i>
            <p>ไม่พบรายชื่อนักเรียน</p>
            <p class="text-sm">กรุณาไปที่หน้า "จัดการรายวิชา" เพื่อกำหนดชั้นเรียนที่เรียนวิชานี้ก่อน</p>
        </div>
    <?php else: ?>
    <table class="min-w-full bg-white text-sm">
        <thead class="bg-blue-100">
            <tr>
                <th class="py-3 px-2 text-left sticky left-0 bg-blue-100 z-10 w-48">ชื่อ-สกุลนักเรียน</th>
                <?php foreach ($activities as $activity): ?>
                    <th class="py-3 px-2 text-center w-28" title="<?= htmlspecialchars($activity['activity_name']) ?>">
                        <?= htmlspecialchars(mb_strimwidth($activity['activity_name'], 0, 15, "...")) ?><br>
                        (<?= htmlspecialchars($activity['max_score']) ?>)
                    </th>
                <?php endforeach; ?>
                <th class="py-3 px-2 text-center w-28 bg-yellow-100">กลางภาค (<?= htmlspecialchars($subject['max_score_midterm']) ?>)</th>
                <th class="py-3 px-2 text-center w-28 bg-green-100">ปลายภาค (<?= htmlspecialchars($subject['max_score_final']) ?>)</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $student): ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-2 px-2 font-semibold sticky left-0 bg-white hover:bg-gray-50 z-10 w-48">
                        <?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?><br>
                        <span class="text-xs text-gray-500"><?= htmlspecialchars($student['class_name']) ?></span>
                    </td>
                    <?php foreach ($activities as $activity): 
                        $score = $all_activity_scores[$student['id']][$activity['id']] ?? '';
                    ?>
                        <td class="py-2 px-2 text-center">
                            <input type="number" step="0.01" min="0" max="<?= $activity['max_score'] ?>" name="activity_scores[<?= $student['id'] ?>][<?= $activity['id'] ?>]" value="<?= htmlspecialchars($score) ?>" class="w-20 text-center border rounded py-1">
                        </td>
                    <?php endforeach; ?>
                    
                    <?php 
                        $midterm_score = $all_exam_scores[$student['id']]['midterm_score'] ?? '';
                        $final_score = $all_exam_scores[$student['id']]['final_score'] ?? '';
                    ?>
                    <td class="py-2 px-2 text-center bg-yellow-50">
                         <input type="number" step="0.01" min="0" max="<?= $subject['max_score_midterm'] ?>" name="exam_scores[<?= $student['id'] ?>][midterm]" value="<?= htmlspecialchars($midterm_score) ?>" class="w-20 text-center border rounded py-1">
                    </td>
                    <td class="py-2 px-2 text-center bg-green-50">
                         <input type="number" step="0.01" min="0" max="<?= $subject['max_score_final'] ?>" name="exam_scores[<?= $student['id'] ?>][final]" value="<?= htmlspecialchars($final_score) ?>" class="w-20 text-center border rounded py-1">
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div class="mt-6 text-right">
        <button type="submit" class="btn-gradient text-white font-bold py-3 px-6 rounded-lg shadow-lg hover:shadow-xl transition-all duration-300">
            <i class="fas fa-save mr-2"></i>บันทึกคะแนนทั้งหมด
        </button>
    </div>
    <?php endif; ?>
</div>
</form>

<?php include 'footer.php'; ?>