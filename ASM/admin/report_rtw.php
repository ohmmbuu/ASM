<?php
include 'header.php';

// --- ส่วน Logic ---

// ฟังก์ชันสำหรับแปลงค่าเฉลี่ยเป็นข้อมูล (ระดับ, สี, ไอคอน)
function getRtwLevelDetails($average_score) {
    if ($average_score >= 2.5) return ['level' => 'ดีเยี่ยม', 'color' => 'green'];
    if ($average_score >= 1.5) return ['level' => 'ดี', 'color' => 'blue'];
    if ($average_score >= 1) return ['level' => 'ผ่าน', 'color' => 'orange'];
    if ($average_score > 0) return ['level' => 'ต้องปรับปรุง', 'color' => 'red'];
    return ['level' => '-', 'color' => 'gray'];
}

// 1. ดึงข้อมูลระดับชั้นทั้งหมดที่มีนักเรียนอยู่ เพื่อสร้างตัวกรอง
$classes = $conn->query("SELECT DISTINCT class_level FROM students WHERE class_level IS NOT NULL AND class_level != '' ORDER BY class_level ASC")->fetchAll(PDO::FETCH_COLUMN);

// 2. สร้างเงื่อนไข WHERE สำหรับการกรอง
$where_clause = "";
$filter_params = [];
if (!empty($_GET['filter_class'])) {
    $where_clause = "WHERE s.class_level = ?";
    $filter_params[] = $_GET['filter_class'];
}

// 3. ดึงข้อมูลการประเมินทั้งหมด (พร้อมตัวกรอง)
$sql = "
    SELECT 
        e.id AS evaluation_id, s.class_level, s.class_no, s.first_name, s.last_name, ri.aspect_id, AVG(res.score) AS average_score
    FROM rtw_evaluations_main e
    JOIN students s ON e.student_id = s.id
    JOIN rtw_evaluation_scores res ON e.id = res.evaluation_id
    JOIN rtw_items ri ON res.item_id = ri.id
    {$where_clause}
    GROUP BY e.id, ri.aspect_id
    ORDER BY s.class_level, s.class_no
";
$evaluations_stmt = $conn->prepare($sql);
$evaluations_stmt->execute($filter_params);


// 4. จัดระเบียบข้อมูลใหม่เพื่อให้ง่ายต่อการแสดงผล
$results = [];
while ($row = $evaluations_stmt->fetch(PDO::FETCH_ASSOC)) {
    $eval_id = $row['evaluation_id'];
    if (!isset($results[$eval_id])) {
        $results[$eval_id] = [
            'class_no' => $row['class_no'],
            'full_name' => $row['first_name'] . ' ' . $row['last_name'],
            'class_level' => $row['class_level'],
            'scores_by_aspect' => [],
            'total_score' => 0,
            'total_aspects' => 0
        ];
    }
    $results[$eval_id]['scores_by_aspect'][$row['aspect_id']] = $row['average_score'];
    $results[$eval_id]['total_score'] += $row['average_score'];
    $results[$eval_id]['total_aspects']++;
}

$aspects = $conn->query("SELECT * FROM rtw_aspects ORDER BY aspect_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$aspect_icons = [1 => 'fa-book-open', 2 => 'fa-brain', 3 => 'fa-pen-nib']; // กำหนดไอคอนสำหรับแต่ละด้าน

// --- ส่วน View ---
?>
<div class="mb-6">
    <div class="flex flex-wrap justify-between items-center gap-4">
        <div>
            <a href="evaluate_rtw.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>กลับไปหน้าประเมิน</a>
            <h2 class="text-2xl font-bold text-gray-800 mt-2">สรุปผลการประเมินการอ่าน คิดวิเคราะห์ และเขียน</h2>
        </div>
        <div class="flex items-center gap-4">
             <form action="report_rtw.php" method="GET" class="flex items-center gap-2">
                <label for="filter_class" class="text-sm font-semibold">กรองตามระดับชั้น:</label>
                <select name="filter_class" id="filter_class" onchange="this.form.submit()" class="w-32 rounded-lg border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                    <option value="">ทั้งหมด</option>
                    <?php foreach($classes as $class): ?>
                        <option value="<?= htmlspecialchars($class) ?>" <?= (($_GET['filter_class'] ?? '') == $class) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
                <i class="fas fa-print mr-2"></i>พิมพ์
            </button>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
    <?php if (empty($results)): ?>
        <div class="col-span-full bg-white p-8 rounded-lg shadow-lg text-center text-gray-500">
            <i class="fas fa-info-circle text-4xl mb-4"></i>
            <p>ไม่พบข้อมูลการประเมิน</p>
            <p class="text-sm">กรุณาลองเลือกตัวกรองอื่น หรือทำการประเมินข้อมูลนักเรียนก่อน</p>
        </div>
    <?php else: ?>
        <?php foreach ($results as $result): 
            $overall_avg = ($result['total_aspects'] > 0) ? $result['total_score'] / $result['total_aspects'] : 0;
            $overall_details = getRtwLevelDetails($overall_avg);
            $border_color_class = "border-{$overall_details['color']}-500";
        ?>
            <div class="bg-white rounded-lg shadow-lg border-t-4 <?= $border_color_class ?>">
                <div class="p-4 border-b flex justify-between items-start">
                    <div>
                        <p class="font-bold text-lg text-gray-800"><?= htmlspecialchars($result['full_name']) ?></p>
                        <p class="text-sm text-gray-500">ชั้น <?= htmlspecialchars($result['class_level']) ?> เลขที่ <?= htmlspecialchars($result['class_no']) ?></p>
                    </div>
                    <span class="text-xs font-bold py-1 px-3 rounded-full bg-<?= $overall_details['color'] ?>-100 text-<?= $overall_details['color'] ?>-800">
                        <?= $overall_details['level'] ?>
                    </span>
                </div>
                <div class="p-4 space-y-4">
                    <?php foreach ($aspects as $aspect): 
                        $avg_score = $result['scores_by_aspect'][$aspect['id']] ?? 0;
                        $details = getRtwLevelDetails($avg_score);
                        $progress_percentage = ($avg_score / 3) * 100;
                    ?>
                        <div>
                            <div class="flex justify-between items-center mb-1 text-sm">
                                <p class="font-semibold text-gray-700">
                                    <i class="fas <?= $aspect_icons[$aspect['id']] ?? 'fa-question-circle' ?> mr-2 text-<?= $details['color'] ?>-500"></i>
                                    <?= htmlspecialchars($aspect['aspect_name']) ?>
                                </p>
                                <span class="font-semibold text-<?= $details['color'] ?>-600"><?= $details['level'] ?></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2.5">
                                <div class="bg-<?= $details['color'] ?>-500 h-2.5 rounded-full" style="width: <?= $progress_percentage ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include 'footer.php'; ?>