<?php
// === ส่วน Logic: ดึงข้อมูลทั้งหมดของนักเรียน 1 คน ===
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

if (!isset($_GET['student_id']) || !is_numeric($_GET['student_id'])) {
    header("Location: students.php");
    exit();
}

$student_id = $_GET['student_id'];

// === จุดที่แก้ไข 1: แก้ไข SQL ให้ JOIN ตาราง classes เพื่อเอาชื่อชั้นเรียน ===
$student_stmt = $conn->prepare("
    SELECT s.*, c.class_name 
    FROM students s 
    LEFT JOIN classes c ON s.class_id = c.id 
    WHERE s.id = ?
");
$student_stmt->execute([$student_id]);
$student = $student_stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header("Location: students.php");
    exit();
}

// 2. ดึงข้อมูลผลการเรียน
$grades_stmt = $conn->prepare("
    SELECT 
        s.subject_name,
        (
            SELECT COALESCE(SUM(score)/NULLIF(SUM(act.max_score), 0), 0) * s.max_score_keep FROM activity_scores sc JOIN activities act ON sc.activity_id = act.id WHERE act.subject_id = s.id AND sc.student_id = es.student_id
        ) + COALESCE(es.midterm_score, 0) + COALESCE(es.final_score, 0) AS total_score
    FROM subjects s
    LEFT JOIN exam_scores es ON s.id = es.subject_id AND es.student_id = ?
    WHERE s.id IN (SELECT subject_id FROM subject_classes WHERE class_id = ?)
    GROUP BY s.id ORDER BY s.subject_name
");
$grades_stmt->execute([$student_id, $student['class_id']]);
$grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

function getGradeFromScore($score) {
    if ($score >= 80) return '4.0'; if ($score >= 75) return '3.5'; if ($score >= 70) return '3.0';
    if ($score >= 65) return '2.5'; if ($score >= 60) return '2.0'; if ($score >= 55) return '1.5';
    if ($score >= 50) return '1.0'; return '0.0';
}

// 3. ดึงข้อมูลสรุปคุณลักษณะฯ
$char_stmt = $conn->prepare("SELECT AVG(score) as overall_avg FROM char_evaluation_scores WHERE evaluation_id IN (SELECT id FROM char_evaluations WHERE student_id = ?)");
$char_stmt->execute([$student_id]);
$char_result = $char_stmt->fetch(PDO::FETCH_ASSOC);

// 4. ดึงข้อมูลสรุปการอ่านฯ
$rtw_stmt = $conn->prepare("SELECT AVG(score) as overall_avg FROM rtw_evaluation_scores WHERE evaluation_id IN (SELECT id FROM rtw_evaluations_main WHERE student_id = ?)");
$rtw_stmt->execute([$student_id]);
$rtw_result = $rtw_stmt->fetch(PDO::FETCH_ASSOC);

function getLevelDetails($average_score) {
    if ($average_score >= 2.5) return ['level' => 'ดีเยี่ยม', 'color' => 'green'];
    if ($average_score >= 1.5) return ['level' => 'ดี', 'color' => 'blue'];
    if ($average_score >= 1) return ['level' => 'ผ่าน', 'color' => 'orange'];
    if ($average_score > 0) return ['level' => 'ต้องปรับปรุง', 'color' => 'red'];
    return ['level' => 'ยังไม่มีข้อมูล', 'color' => 'gray'];
}

// === ส่วน View ===
include 'header.php';
?>

<div class="mb-6 flex justify-between items-center">
    <div>
        <a href="students.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>กลับไปหน้ารายชื่อนักเรียน</a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">แฟ้มสะสมงานนักเรียน (Student Portfolio)</h2>
    </div>
    <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
        <i class="fas fa-print mr-2"></i>พิมพ์รายงาน
    </button>
</div>

<div class="bg-white p-8 rounded-lg shadow-lg">
    <div class="text-center border-b-2 border-gray-200 pb-6 mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h1>
        <p class="text-md text-gray-600 mt-2">
            รหัสนักเรียน: <?= htmlspecialchars($student['student_code']) ?> | 
            ระดับชั้น: <?= htmlspecialchars($student['class_name'] ?? 'N/A') ?> | 
            เลขที่: <?= htmlspecialchars($student['class_no']) ?>
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <div class="space-y-6">
            <div class="p-4 border rounded-lg">
                <h3 class="text-xl font-semibold mb-3 text-blue-800"><i class="fas fa-graduation-cap mr-2"></i>สรุปผลการเรียน</h3>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-2 px-3 text-left">รายวิชา</th>
                            <th class="py-2 px-3 text-center">คะแนนรวม</th>
                            <th class="py-2 px-3 text-center">เกรด</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($grades)): ?>
                             <tr><td colspan="3" class="text-center py-4 text-gray-500">ยังไม่มีข้อมูลผลการเรียน</td></tr>
                        <?php else: ?>
                            <?php foreach($grades as $grade): ?>
                            <tr class="border-t">
                                <td class="py-2 px-3"><?= htmlspecialchars($grade['subject_name']) ?></td>
                                <td class="py-2 px-3 text-center"><?= number_format($grade['total_score'] ?? 0, 2) ?></td>
                                <td class="py-2 px-3 text-center font-bold"><?= getGradeFromScore($grade['total_score'] ?? 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-6">
            <?php $char_details = getLevelDetails($char_result['overall_avg']); ?>
            <div class="p-4 border rounded-lg flex items-center gap-4 bg-<?= $char_details['color'] ?>-50 border-<?= $char_details['color'] ?>-200">
                <div class="text-3xl text-<?= $char_details['color'] ?>-500"><i class="fas fa-award"></i></div>
                <div>
                    <h3 class="text-xl font-semibold text-gray-800">คุณลักษณะอันพึงประสงค์</h3>
                    <p class="text-lg font-bold text-<?= $char_details['color'] ?>-700"><?= $char_details['level'] ?></p>
                </div>
            </div>

            <?php $rtw_details = getLevelDetails($rtw_result['overall_avg']); ?>
             <div class="p-4 border rounded-lg flex items-center gap-4 bg-<?= $rtw_details['color'] ?>-50 border-<?= $rtw_details['color'] ?>-200">
                <div class="text-3xl text-<?= $rtw_details['color'] ?>-500"><i class="fas fa-pen-nib"></i></div>
                <div>
                    <h3 class="text-xl font-semibold text-gray-800">การอ่าน คิดวิเคราะห์ และเขียน</h3>
                    <p class="text-lg font-bold text-<?= $rtw_details['color'] ?>-700"><?= $rtw_details['level'] ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>