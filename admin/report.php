<?php
include 'header.php';

// --- Helper function for calculating grade ---
function calculateGrade($total_score) {
    if ($total_score >= 80) return '4.0';
    if ($total_score >= 75) return '3.5';
    if ($total_score >= 70) return '3.0';
    if ($total_score >= 65) return '2.5';
    if ($total_score >= 60) return '2.0';
    if ($total_score >= 55) return '1.5';
    if ($total_score >= 50) return '1.0';
    return '0.0';
}

// --- Validation and Initial Data Fetching ---
if (!isset($_GET['subject_id']) || !is_numeric($_GET['subject_id'])) {
    header("Location: subjects.php");
    exit();
}
$subject_id = $_GET['subject_id'];
$teacher_id = $_SESSION['user_id'];

$stmt_subject = $conn->prepare("SELECT * FROM subjects WHERE id = ? AND teacher_id = ?");
$stmt_subject->execute([$subject_id, $teacher_id]);
$subject = $stmt_subject->fetch(PDO::FETCH_ASSOC);
if (!$subject) {
    header("Location: subjects.php");
    exit();
}

// --- Fetch all necessary data for display ---
$students = $conn->query("SELECT * FROM students ORDER BY student_code ASC")->fetchAll(PDO::FETCH_ASSOC);
$activities = $conn->query("SELECT * FROM activities WHERE subject_id = $subject_id ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing scores and organize them
$all_activity_scores = [];
$stmt_scores = $conn->prepare("SELECT sa.student_id, sa.activity_id, sa.score FROM activity_scores sa JOIN activities a ON sa.activity_id = a.id WHERE a.subject_id = ?");
$stmt_scores->execute([$subject_id]);
while($row = $stmt_scores->fetch(PDO::FETCH_ASSOC)) {
    $all_activity_scores[$row['student_id']][$row['activity_id']] = $row['score'];
}

$all_exam_scores = [];
$stmt_exam_scores = $conn->prepare("SELECT * FROM exam_scores WHERE subject_id = ?");
$stmt_exam_scores->execute([$subject_id]);
while($row = $stmt_exam_scores->fetch(PDO::FETCH_ASSOC)) {
    $all_exam_scores[$row['student_id']] = $row;
}
?>

<div class="mb-4 flex justify-between items-center">
    <div>
        <a href="subjects.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>กลับไปหน้ารายวิชา</a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">รายงานผลคะแนน: <?= htmlspecialchars($subject['subject_name']) ?></h2>
    </div>
    <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
        <i class="fas fa-print mr-2"></i>พิมพ์รายงาน
    </button>
</div>

<div class="bg-white p-6 rounded-lg shadow-lg overflow-x-auto">
    <table class="min-w-full bg-white text-sm">
        <thead class="bg-blue-100">
            <tr>
                <th class="py-3 px-2 text-left sticky left-0 bg-blue-100 z-10 w-48">ชื่อ-สกุล</th>
                <?php foreach ($activities as $activity): ?>
                    <th class="py-3 px-2 text-center w-28"><?= htmlspecialchars(mb_strimwidth($activity['activity_name'], 0, 15, "...")) ?></th>
                <?php endforeach; ?>
                <th class="py-3 px-2 text-center w-28 bg-purple-200">คะแนนเก็บ (<?= $subject['max_score_keep'] ?>)</th>
                <th class="py-3 px-2 text-center w-28 bg-yellow-200">กลางภาค (<?= $subject['max_score_midterm'] ?>)</th>
                <th class="py-3 px-2 text-center w-28 bg-green-200">ปลายภาค (<?= $subject['max_score_final'] ?>)</th>
                <th class="py-3 px-2 text-center w-28 bg-red-200 font-bold">รวม (100)</th>
                <th class="py-3 px-2 text-center w-28 bg-blue-200 font-bold">เกรด</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($students as $student): 
                // --- Calculations for each student ---
                $total_activity_score_gained = 0;
                $total_activity_max_score = 0;
                
                foreach ($activities as $activity) {
                    $score = $all_activity_scores[$student['id']][$activity['id']] ?? 0;
                    $total_activity_score_gained += floatval($score);
                    $total_activity_max_score += floatval($activity['max_score']);
                }

                $keep_score_scaled = ($total_activity_max_score > 0) 
                    ? ($total_activity_score_gained / $total_activity_max_score) * $subject['max_score_keep'] 
                    : 0;
                
                $midterm_score = floatval($all_exam_scores[$student['id']]['midterm_score'] ?? 0);
                $final_score = floatval($all_exam_scores[$student['id']]['final_score'] ?? 0);

                $total_score_100 = $keep_score_scaled + $midterm_score + $final_score;
                $grade = calculateGrade($total_score_100);
            ?>
                <tr class="border-b hover:bg-gray-50">
                    <td class="py-2 px-2 font-semibold sticky left-0 bg-white hover:bg-gray-50 z-10 w-48">
                        <?= htmlspecialchars($student['student_code'] . ' ' . $student['first_name'] . ' ' . $student['last_name']) ?>
                        </td>
                    <?php foreach ($activities as $activity): 
                        $score = $all_activity_scores[$student['id']][$activity['id']] ?? '-';
                    ?>
                        <td class="py-2 px-2 text-center"><?= $score ?></td>
                    <?php endforeach; ?>
                    <td class="py-2 px-2 text-center font-semibold bg-purple-50"><?= number_format($keep_score_scaled, 2) ?></td>
                    <td class="py-2 px-2 text-center font-semibold bg-yellow-50"><?= $midterm_score ?></td>
                    <td class="py-2 px-2 text-center font-semibold bg-green-50"><?= $final_score ?></td>
                    <td class="py-2 px-2 text-center font-bold text-red-600 bg-red-50"><?= number_format($total_score_100, 2) ?></td>
                    <td class="py-2 px-2 text-center font-bold text-blue-600 bg-blue-50"><?= $grade ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>