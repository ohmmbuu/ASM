<?php
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }
$teacher_id = $_SESSION['user_id'];
include 'header.php';

$activities = $conn->query("SELECT * FROM dev_activities WHERE teacher_id = $teacher_id ORDER BY academic_year DESC, activity_name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="bg-white p-6 rounded-lg shadow-lg">
    <div class="flex justify-between items-center mb-6">
        <form action="report_dev_activities.php" method="GET" class="flex items-center gap-4">
            <label for="activity_id" class="font-semibold">เลือกกิจกรรมเพื่อดูรายงาน:</label>
            <select name="activity_id" id="activity_id" onchange="this.form.submit()" class="w-full md:w-1/2 p-2 border rounded-lg">
                <option value="">-- กรุณาเลือกกิจกรรม --</option>
                <?php foreach($activities as $activity): ?>
                    <option value="<?= $activity['id'] ?>" <?= (($_GET['activity_id'] ?? '') == $activity['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($activity['activity_name']) ?> (ปีการศึกษา <?= htmlspecialchars($activity['academic_year']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <button onclick="window.print()" class="bg-gray-600 text-white py-2 px-4 rounded-lg">พิมพ์</button>
    </div>

    <?php if(isset($_GET['activity_id']) && is_numeric($_GET['activity_id'])): 
        $activity_id = $_GET['activity_id'];
        $results_stmt = $conn->prepare("
            SELECT s.class_level, s.class_no, s.first_name, s.last_name, r.result
            FROM dev_activity_results r
            JOIN students s ON r.student_id = s.id
            WHERE r.activity_id = ?
            ORDER BY s.class_level, s.class_no
        ");
        $results_stmt->execute([$activity_id]);
        $results = $results_stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <table class="min-w-full bg-white">
        <thead class="bg-gray-100"><tr><th>ระดับชั้น</th><th>เลขที่</th><th>ชื่อ-สกุล</th><th class="text-center">ผลการประเมิน</th></tr></thead>
        <tbody>
        <?php foreach($results as $result): ?>
            <tr class="border-b">
                <td class="py-2 px-4"><?= htmlspecialchars($result['class_level']) ?></td>
                <td class="py-2 px-4 text-center"><?= htmlspecialchars($result['class_no']) ?></td>
                <td class="py-2 px-4"><?= htmlspecialchars($result['first_name'].' '.$result['last_name']) ?></td>
                <td class="py-2 px-4 text-center">
                    <?php 
                        $res = htmlspecialchars($result['result']);
                        $color = $res == 'ผ่าน' ? 'green' : 'red';
                        echo "<span class='font-bold text-{$color}-600'>{$res}</span>";
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>
<?php include 'footer.php'; ?>