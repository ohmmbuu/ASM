<?php
session_start();
// ตรวจสอบการล็อกอินของนักเรียน
if (!isset($_SESSION['student_id'])) {
    header("Location: ../student_login.php");
    exit();
}
require_once '../config/db.php';
$student_id = $_SESSION['student_id'];

// --- ส่วน Logic: ดึงข้อมูลทั้งหมด ---

// 1. ดึงข้อมูลนักเรียนพื้นฐาน
$student = $conn->query("SELECT * FROM students WHERE id = $student_id")->fetch(PDO::FETCH_ASSOC);

// 2. ดึงข้อมูลผลการเรียน
$grades_stmt = $conn->prepare("
    SELECT s.subject_name,
        (SELECT COALESCE(SUM(score)/NULLIF(SUM(act.max_score), 0), 0) * s.max_score_keep FROM activity_scores sc JOIN activities act ON sc.activity_id = act.id WHERE act.subject_id = s.id AND sc.student_id = es.student_id) + COALESCE(es.midterm_score, 0) + COALESCE(es.final_score, 0) AS total_score
    FROM subjects s
    LEFT JOIN exam_scores es ON s.id = es.subject_id AND es.student_id = ?
    GROUP BY s.id ORDER BY s.subject_name
");
$grades_stmt->execute([$student_id]);
$grades = $grades_stmt->fetchAll(PDO::FETCH_ASSOC);

function getGradeFromScore($score) {
    if ($score >= 80) return '4.0'; if ($score >= 75) return '3.5'; if ($score >= 70) return '3.0';
    if ($score >= 65) return '2.5'; if ($score >= 60) return '2.0'; if ($score >= 55) return '1.5';
    if ($score >= 50) return '1.0'; return '0.0';
}

// 3. ดึงข้อมูลสรุปคุณลักษณะฯ
$char_stmt = $conn->prepare("
    SELECT t.trait_name, AVG(s.score) AS average_score FROM char_evaluations e
    JOIN char_evaluation_scores s ON e.id = s.evaluation_id
    JOIN char_items i ON s.item_id = i.id
    JOIN char_traits t ON i.trait_id = t.id
    WHERE e.student_id = ? GROUP BY t.id, t.trait_name ORDER BY t.trait_order
");
$char_stmt->execute([$student_id]);
$char_results = $char_stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. ดึงข้อมูลสรุปการอ่านฯ
$rtw_stmt = $conn->prepare("
    SELECT a.aspect_name, AVG(s.score) AS average_score FROM rtw_evaluations_main e
    JOIN rtw_evaluation_scores s ON e.id = s.evaluation_id
    JOIN rtw_items i ON s.item_id = i.id
    JOIN rtw_aspects a ON i.aspect_id = a.id
    WHERE e.student_id = ? GROUP BY a.id, a.aspect_name ORDER BY a.aspect_order
");
$rtw_stmt->execute([$student_id]);
$rtw_results = $rtw_stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. ดึงข้อมูลกิจกรรมพัฒนาผู้เรียน
$dev_activities_stmt = $conn->prepare("
    SELECT da.activity_name, da.academic_year, dr.result FROM dev_activity_results dr
    JOIN dev_activities da ON dr.activity_id = da.id WHERE dr.student_id = ?
    ORDER BY da.academic_year DESC, da.activity_name ASC
");
$dev_activities_stmt->execute([$student_id]);
$dev_activities = $dev_activities_stmt->fetchAll(PDO::FETCH_ASSOC);

function getLevelDetails($average_score) {
    if ($average_score >= 2.5) return ['level' => 'ดีเยี่ยม', 'color' => 'green'];
    if ($average_score >= 1.5) return ['level' => 'ดี', 'color' => 'blue'];
    if ($average_score >= 1) return ['level' => 'ผ่าน', 'color' => 'orange'];
    if ($average_score > 0) return ['level' => 'ต้องปรับปรุง', 'color' => 'red'];
    return ['level' => 'ไม่มีข้อมูล', 'color' => 'gray'];
}

// === ฟังก์ชันใหม่สำหรับกำหนดสีของป้ายเกรด ===
function getGradeBadgeClass($grade) {
    if ($grade >= 3.5) return 'bg-green-500'; // A
    if ($grade >= 2.5) return 'bg-blue-500';  // B
    if ($grade >= 1.5) return 'bg-yellow-500'; // C
    if ($grade >= 1.0) return 'bg-orange-500'; // D
    return 'bg-red-500'; // F
}

// --- ส่วน View ---
$page_title = "Dashboard ของฉัน";
include 'student_header.php';
?>
<main class="container mx-auto p-4 sm:p-6 lg:p-8">
    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 text-white p-8 rounded-xl shadow-lg mb-8">
        <h1 class="text-3xl font-bold">สวัสดี, <?= htmlspecialchars($student['first_name']) ?>!</h1>
        <p class="mt-2 text-blue-100">นี่คือสรุปภาพรวมผลการเรียนและการประเมินทั้งหมดของคุณ</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="lg:col-span-2 space-y-8">
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-2xl font-semibold mb-4 text-gray-800"><i class="fas fa-running mr-2 text-purple-500"></i>ผลกิจกรรมพัฒนาผู้เรียน</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <tbody>
                            <?php if(empty($dev_activities)): ?>
                                <tr><td class="text-center py-6 text-gray-500">ยังไม่มีข้อมูล</td></tr>
                            <?php else: ?>
                                <?php foreach($dev_activities as $dev_act): ?>
                                <tr class="border-b last:border-b-0">
                                    <td class="py-3 px-3 font-medium text-gray-700"><?= htmlspecialchars($dev_act['activity_name']) ?> (<?= htmlspecialchars($dev_act['academic_year']) ?>)</td>
                                    <td class="py-3 px-3 text-right">
                                        <?php 
                                            $res = htmlspecialchars($dev_act['result']);
                                            $color_class = $res == 'ผ่าน' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                                            echo "<span class='font-bold py-1 px-3 rounded-full text-xs {$color_class}'>{$res}</span>";
                                        ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h2 class="text-2xl font-semibold mb-4 text-gray-800"><i class="fas fa-graduation-cap mr-2 text-blue-500"></i>สรุปผลการเรียน</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="border-b-2 border-gray-200">
                            <tr>
                                <th class="py-3 px-4 text-left font-semibold text-gray-600">รายวิชา</th>
                                <th class="py-3 px-4 text-center font-semibold text-gray-600">คะแนนรวม</th>
                                <th class="py-3 px-4 text-center font-semibold text-gray-600">เกรด</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($grades) || count(array_filter($grades, fn($g) => $g['total_score'] !== null)) === 0): ?>
                                <tr><td colspan="3" class="text-center py-8 text-gray-500">ยังไม่มีข้อมูลผลการเรียน</td></tr>
                            <?php else: ?>
                                <?php foreach($grades as $grade): ?>
                                <?php if($grade['total_score'] === null) continue; ?>
                                <tr class="border-t">
                                    <td class="py-4 px-4 font-medium text-gray-700"><?= htmlspecialchars($grade['subject_name']) ?></td>
                                    <td class="py-4 px-4 text-center text-gray-600"><?= number_format($grade['total_score'], 2) ?></td>
                                    <td class="py-4 px-4 text-center">
                                        <?php 
                                            $gradeValue = getGradeFromScore($grade['total_score']);
                                            $badgeClass = getGradeBadgeClass(floatval($gradeValue));
                                        ?>
                                        <div class="w-10 h-10 mx-auto rounded-full flex items-center justify-center font-bold text-white text-lg <?= $badgeClass ?>">
                                            <?= $gradeValue ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="lg:col-span-1 space-y-8">
            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h3 class="text-xl font-semibold text-gray-800 mb-4"><i class="fas fa-award mr-2 text-green-500"></i>คุณลักษณะอันพึงประสงค์</h3>
                <div class="space-y-4">
                    <?php if(empty($char_results)): ?>
                        <p class="text-center text-gray-500 py-4">ยังไม่มีข้อมูล</p>
                    <?php else: ?>
                        <?php foreach($char_results as $result): 
                            $details = getLevelDetails($result['average_score']);
                            $progress = ($result['average_score'] / 3) * 100;
                        ?>
                        <div>
                            <div class="flex justify-between items-center mb-1 text-sm">
                                <p class="font-medium text-gray-700"><?= htmlspecialchars($result['trait_name']) ?></p>
                                <span class="font-semibold text-<?= $details['color'] ?>-600"><?= $details['level'] ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-<?= $details['color'] ?>-500 h-2 rounded-full" style="width: <?= $progress ?>%"></div></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-lg">
                <h3 class="text-xl font-semibold text-gray-800 mb-4"><i class="fas fa-pen-nib mr-2 text-indigo-500"></i>การอ่าน คิดวิเคราะห์ และเขียน</h3>
                <div class="space-y-4">
                     <?php if(empty($rtw_results)): ?>
                        <p class="text-center text-gray-500 py-4">ยังไม่มีข้อมูล</p>
                    <?php else: ?>
                        <?php foreach($rtw_results as $result): 
                            $details = getLevelDetails($result['average_score']);
                            $progress = ($result['average_score'] / 3) * 100;
                        ?>
                        <div>
                            <div class="flex justify-between items-center mb-1 text-sm">
                                <p class="font-medium text-gray-700"><?= htmlspecialchars($result['aspect_name']) ?></p>
                                <span class="font-semibold text-<?= $details['color'] ?>-600"><?= $details['level'] ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2"><div class="bg-<?= $details['color'] ?>-500 h-2 rounded-full" style="width: <?= $progress ?>%"></div></div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'student_footer.php'; ?>