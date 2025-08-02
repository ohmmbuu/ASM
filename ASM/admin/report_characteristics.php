<?php
include 'header.php';

// ฟังก์ชันสำหรับแปลงค่าเฉลี่ยเป็นระดับคุณภาพ
function getCharacteristicLevel($average_score) {
    if ($average_score >= 2.5) return '<span class="text-green-600 font-bold">ดีเยี่ยม</span>';
    if ($average_score >= 1.5) return '<span class="text-blue-600 font-bold">ดี</span>';
    if ($average_score >= 1) return '<span class="text-orange-600 font-bold">ผ่าน</span>';
    // Handle cases where there might be no score yet
    if ($average_score > 0) return '<span class="text-red-600 font-bold">ต้องปรับปรุง</span>';
    return '-';
}

// 1. ดึงหัวข้อหลักทั้งหมดมาเพื่อสร้างคอลัมน์ในตาราง
$traits = $conn->query("SELECT * FROM char_traits ORDER BY trait_order ASC")->fetchAll(PDO::FETCH_ASSOC);

// 2. ดึงข้อมูลการประเมินทั้งหมดที่เคยบันทึกไว้
$evaluations_stmt = $conn->query("
    SELECT 
        e.id AS evaluation_id,
        s.class_level,
        s.class_no,
        s.first_name,
        s.last_name,
        ci.trait_id,
        AVG(ces.score) AS average_score
    FROM char_evaluations e
    JOIN students s ON e.student_id = s.id
    JOIN char_evaluation_scores ces ON e.id = ces.evaluation_id
    JOIN char_items ci ON ces.item_id = ci.id
    GROUP BY e.id, ci.trait_id
    ORDER BY s.class_level, s.class_no
");

// 3. จัดระเบียบข้อมูลใหม่เพื่อให้ง่ายต่อการแสดงผล
$results = [];
while ($row = $evaluations_stmt->fetch(PDO::FETCH_ASSOC)) {
    $eval_id = $row['evaluation_id'];
    if (!isset($results[$eval_id])) {
        $results[$eval_id] = [
            'class_level' => $row['class_level'],
            'class_no' => $row['class_no'],
            'full_name' => $row['first_name'] . ' ' . $row['last_name'],
            'scores_by_trait' => [],
            'total_score' => 0,
            'total_traits' => 0
        ];
    }
    $results[$eval_id]['scores_by_trait'][$row['trait_id']] = $row['average_score'];
    $results[$eval_id]['total_score'] += $row['average_score'];
    $results[$eval_id]['total_traits']++;
}

?>

<div class="mb-4 flex justify-between items-center">
    <div>
        <a href="evaluate_characteristics.php" class="text-blue-600 hover:underline"><i class="fas fa-arrow-left mr-2"></i>กลับไปหน้าประเมิน</a>
        <h2 class="text-2xl font-bold text-gray-800 mt-2">สรุปผลการประเมินคุณลักษณะอันพึงประสงค์</h2>
    </div>
    <button onclick="window.print()" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg">
        <i class="fas fa-print mr-2"></i>พิมพ์รายงาน
    </button>
</div>

<div class="bg-white p-6 rounded-lg shadow-lg overflow-x-auto">
    <table class="min-w-full bg-white text-sm">
        <thead class="bg-blue-100">
            <tr>
                <th class="py-3 px-2 text-center">เลขที่</th>
                <th class="py-3 px-2 text-left">ชื่อ-สกุล</th>
                <?php foreach ($traits as $trait): ?>
                    <th class="py-3 px-2 text-center" style="writing-mode: vertical-rl; transform: rotate(180deg); white-space: nowrap;"><?= htmlspecialchars($trait['trait_name']) ?></th>
                <?php endforeach; ?>
                <th class="py-3 px-2 text-center font-bold" style="writing-mode: vertical-rl; transform: rotate(180deg);">สรุปผลโดยรวม</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($results)): ?>
                <tr><td colspan="<?= count($traits) + 3 ?>" class="text-center py-4">ยังไม่มีข้อมูลการประเมิน</td></tr>
            <?php else: ?>
                <?php foreach ($results as $result): ?>
                    <tr class="border-b hover:bg-gray-50">
                        <td class="py-2 px-2 text-center"><?= htmlspecialchars($result['class_no']) ?></td>
                        <td class="py-2 px-2 font-semibold"><?= htmlspecialchars($result['full_name']) ?></td>
                        
                        <?php foreach ($traits as $trait): 
                            $avg_score = $result['scores_by_trait'][$trait['id']] ?? 0;
                        ?>
                            <td class="py-2 px-2 text-center"><?= getCharacteristicLevel($avg_score) ?></td>
                        <?php endforeach; ?>
                        
                        <?php
                            $overall_average = ($result['total_traits'] > 0) ? $result['total_score'] / $result['total_traits'] : 0;
                        ?>
                        <td class="py-2 px-2 text-center bg-gray-100"><?= getCharacteristicLevel($overall_average) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'footer.php'; ?>