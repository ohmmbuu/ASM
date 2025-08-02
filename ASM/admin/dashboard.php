<?php
// === ส่วน Logic: ดึงข้อมูลสำหรับ Dashboard ===
require_once '../config/db.php';
// -- เราจะ include header ทีหลัง เพื่อให้แน่ใจว่าส่วน logic ทำงานก่อน --

$teacher_id = $_SESSION['user_id'];

// 1. ข้อมูลภาพรวม
$subject_count = $conn->query("SELECT COUNT(*) FROM subjects WHERE teacher_id = $teacher_id")->fetchColumn();
$student_count = $conn->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_evals = $conn->query("SELECT COUNT(DISTINCT student_id) FROM char_evaluations")->fetchColumn();

// 2. ข้อมูลสำหรับกราฟเส้น: สถิติการเข้าชมเว็บ 7 วันย้อนหลัง
$visits_stmt = $conn->query("
    SELECT DATE_FORMAT(visit_date, '%Y-%m-%d') AS day, visit_count
    FROM site_visits 
    WHERE visit_date >= CURDATE() - INTERVAL 7 DAY 
    ORDER BY day ASC
");
$visits_data = $visits_stmt->fetchAll(PDO::FETCH_ASSOC);
$visit_labels = array_column($visits_data, 'day');
$visit_counts = array_column($visits_data, 'visit_count');

// 3. ข้อมูลสำหรับกราฟวงกลม: สรุปผลการประเมินการอ่านฯ
$rtw_summary_stmt = $conn->query("
    SELECT 
        CASE 
            WHEN avg_score >= 2.5 THEN 'ดีเยี่ยม'
            WHEN avg_score >= 1.5 THEN 'ดี'
            WHEN avg_score >= 1.0 THEN 'ผ่าน'
            ELSE 'ต้องปรับปรุง'
        END AS level,
        COUNT(*) as count
    FROM (
        SELECT AVG(s.score) AS avg_score
        FROM rtw_evaluations_main e
        JOIN rtw_evaluation_scores s ON e.id = s.evaluation_id
        GROUP BY e.id
    ) AS student_averages
    GROUP BY level
");
$rtw_summary = $rtw_summary_stmt->fetchAll(PDO::FETCH_ASSOC);
$rtw_labels = array_column($rtw_summary, 'level');
$rtw_data = array_column($rtw_summary, 'count');

// 4. ข้อมูลสำหรับกราฟแท่ง: ความคืบหน้าการประเมิน
$char_evaluated_count = $conn->query("SELECT COUNT(DISTINCT student_id) FROM char_evaluations")->fetchColumn();
$rtw_evaluated_count = $conn->query("SELECT COUNT(DISTINCT student_id) FROM rtw_evaluations_main")->fetchColumn();
$progress_data = [
    'labels' => ['คุณลักษณะฯ', 'การอ่านฯ'],
    'evaluated' => [$char_evaluated_count, $rtw_evaluated_count],
    'not_evaluated' => [$student_count - $char_evaluated_count, $student_count - $rtw_evaluated_count]
];

// 5. ตารางข้อมูล: 5 นักเรียนที่ประเมินล่าสุด
$recent_evals = $conn->query("
    (SELECT s.first_name, s.last_name, 'คุณลักษณะฯ' as type, e.evaluation_date as date FROM char_evaluations e JOIN students s ON e.student_id = s.id ORDER BY e.evaluation_date DESC LIMIT 5)
    UNION
    (SELECT s.first_name, s.last_name, 'การอ่านฯ' as type, e.evaluation_date as date FROM rtw_evaluations_main e JOIN students s ON e.student_id = s.id ORDER BY e.evaluation_date DESC LIMIT 5)
    ORDER BY date DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);


// === ส่วน View: แสดงผล ===
include 'header.php';
?>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-lg p-6 flex items-center justify-between transform hover:scale-105 transition-transform duration-300">
        <div>
            <p class="text-3xl font-bold text-blue-600"><?= $subject_count ?></p>
            <p class="text-gray-500">รายวิชาที่สอน</p>
        </div>
        <div class="bg-blue-100 p-4 rounded-full"><i class="fas fa-book text-3xl text-blue-600"></i></div>
    </div>
    <div class="bg-white rounded-lg shadow-lg p-6 flex items-center justify-between transform hover:scale-105 transition-transform duration-300">
        <div>
            <p class="text-3xl font-bold text-green-600"><?= $student_count ?></p>
            <p class="text-gray-500">นักเรียนทั้งหมด</p>
        </div>
        <div class="bg-green-100 p-4 rounded-full"><i class="fas fa-users text-3xl text-green-600"></i></div>
    </div>
    <div class="bg-white rounded-lg shadow-lg p-6 flex items-center justify-between transform hover:scale-105 transition-transform duration-300">
        <div>
            <p class="text-3xl font-bold text-purple-600"><?= $total_evals ?></p>
            <p class="text-gray-500">ประเมินคุณลักษณะแล้ว (คน)</p>
        </div>
        <div class="bg-purple-100 p-4 rounded-full"><i class="fas fa-award text-3xl text-purple-600"></i></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
    <div class="lg:col-span-3 space-y-8">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h3 class="text-lg font-semibold mb-4">สถิติการเข้าใช้งานระบบ (7 วันล่าสุด)</h3>
            <div class="relative h-80">
                <canvas id="visitsLineChart"></canvas>
            </div>
        </div>
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h3 class="text-lg font-semibold mb-4">การประเมินล่าสุด</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody>
                        <?php if(empty($recent_evals)): ?>
                            <tr><td class="text-center text-gray-500 py-4">ยังไม่มีข้อมูล</td></tr>
                        <?php else: ?>
                            <?php foreach($recent_evals as $eval): ?>
                            <tr class="border-b last:border-b-0">
                                <td class="py-3 px-2"><i class="fas fa-user text-gray-400 mr-2"></i><?= htmlspecialchars($eval['first_name'].' '.$eval['last_name']) ?></td>
                                <td class="py-3 px-2 text-gray-600"><?= htmlspecialchars($eval['type']) ?></td>
                                <td class="py-3 px-2 text-right text-gray-500"><?= htmlspecialchars($eval['date']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="lg:col-span-2 space-y-8">
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h3 class="text-lg font-semibold mb-4">ภาพรวมผลการประเมินการอ่านฯ</h3>
             <div class="relative h-64">
                <canvas id="rtwDoughnutChart"></canvas>
            </div>
        </div>
         <div class="bg-white p-6 rounded-lg shadow-lg">
            <h3 class="text-lg font-semibold mb-4">ความคืบหน้าการประเมิน (คน)</h3>
             <div class="relative h-64">
                <canvas id="progressChart"></canvas>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // 1. กราฟเส้น: สถิติการเข้าใช้งาน
    new Chart(document.getElementById('visitsLineChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($visit_labels) ?>,
            datasets: [{
                label: 'จำนวนการเข้าชม',
                data: <?= json_encode($visit_counts) ?>,
                borderColor: 'rgba(59, 130, 246, 1)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
                tension: 0.3
            }]
        },
        options: { responsive: true, maintainAspectRatio: false }
    });

    // 2. กราฟวงกลม: สรุปผลการประเมินการอ่านฯ
    new Chart(document.getElementById('rtwDoughnutChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($rtw_labels) ?>,
            datasets: [{
                data: <?= json_encode($rtw_data) ?>,
                backgroundColor: ['#22c55e', '#3b82f6', '#f97316', '#ef4444'],
                hoverOffset: 4
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    // 3. กราฟแท่ง: ความคืบหน้าการประเมิน
    new Chart(document.getElementById('progressChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($progress_data['labels']) ?>,
            datasets: [
                {
                    label: 'ประเมินแล้ว',
                    data: <?= json_encode($progress_data['evaluated']) ?>,
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                },
                {
                    label: 'ยังไม่ได้ประเมิน',
                    data: <?= json_encode($progress_data['not_evaluated']) ?>,
                    backgroundColor: 'rgba(226, 232, 240, 0.8)',
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true } },
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
</script>


<?php include 'footer.php'; ?>