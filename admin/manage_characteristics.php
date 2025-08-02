<?php
// === ส่วน Logic: การประมวลผลฟอร์ม (ต้องอยู่บนสุดเสมอ) ===
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

// --- จัดการการเพิ่ม / แก้ไข ข้อมูล (POST Requests) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'add_trait':
            $stmt = $conn->prepare("INSERT INTO char_traits (trait_name, trait_order) VALUES (?, ?)");
            $stmt->execute([$_POST['trait_name'], $_POST['trait_order']]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'เพิ่มหัวข้อหลักสำเร็จ'];
            break;
        case 'edit_trait':
            $stmt = $conn->prepare("UPDATE char_traits SET trait_name = ?, trait_order = ? WHERE id = ?");
            $stmt->execute([$_POST['trait_name'], $_POST['trait_order'], $_POST['trait_id']]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'แก้ไขหัวข้อหลักสำเร็จ'];
            break;
        case 'add_item':
            $stmt = $conn->prepare("INSERT INTO char_items (trait_id, item_text, item_order) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['trait_id'], $_POST['item_text'], $_POST['item_order']]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'เพิ่มรายการย่อยสำเร็จ'];
            break;
        case 'edit_item':
            $stmt = $conn->prepare("UPDATE char_items SET item_text = ?, item_order = ? WHERE id = ?");
            $stmt->execute([$_POST['item_text'], $_POST['item_order'], $_POST['item_id']]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'แก้ไขรายการย่อยสำเร็จ'];
            break;
    }
    header("Location: manage_characteristics.php");
    exit();
}

// --- จัดการการลบข้อมูล (GET Requests) ---
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'] ?? null;

    if ($action === 'delete_trait' && $id) {
        $stmt = $conn->prepare("DELETE FROM char_traits WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['toast'] = ['type' => 'warning', 'message' => 'ลบหัวข้อหลักและรายการย่อยทั้งหมดแล้ว'];
    } elseif ($action === 'delete_item' && $id) {
        $stmt = $conn->prepare("DELETE FROM char_items WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['toast'] = ['type' => 'warning', 'message' => 'ลบรายการย่อยสำเร็จ'];
    }
    header("Location: manage_characteristics.php");
    exit();
}

// --- เตรียมข้อมูลสำหรับ Modal แก้ไข ---
$modal_info = ['type' => 'none', 'data' => null, 'trait_id' => null];
if (isset($_GET['modal'])) {
    $modal_type = $_GET['modal'];
    $id = $_GET['id'] ?? null;

    if ($modal_type === 'edit_trait' && $id) {
        $stmt = $conn->prepare("SELECT * FROM char_traits WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) $modal_info = ['type' => 'edit_trait', 'data' => $data];
    } elseif ($modal_type === 'add_item' && isset($_GET['trait_id'])) {
        $modal_info = ['type' => 'add_item', 'data' => null, 'trait_id' => $_GET['trait_id']];
    } elseif ($modal_type === 'edit_item' && $id) {
        $stmt = $conn->prepare("SELECT * FROM char_items WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) $modal_info = ['type' => 'edit_item', 'data' => $data];
    }
}

// === ส่วน View: การแสดงผล HTML ===
include 'header.php';

// Fetch all traits and their items
$traits = $conn->query("SELECT * FROM char_traits ORDER BY trait_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$items_stmt = $conn->prepare("SELECT * FROM char_items WHERE trait_id = ? ORDER BY item_order ASC");
?>

<div class="bg-white p-6 rounded-lg shadow-lg">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-gray-800">จัดการรายการประเมินคุณลักษณะอันพึงประสงค์</h3>
        <a href="?modal=add_trait" class="btn-gradient text-white font-bold py-2 px-4 rounded-lg shadow-md">
            <i class="fas fa-plus mr-2"></i>เพิ่มหัวข้อหลักใหม่
        </a>
    </div>

    <div class="space-y-6">
        <?php foreach($traits as $trait): ?>
            <div class="border rounded-lg p-4 bg-gray-50">
                <div class="flex justify-between items-center border-b pb-3 mb-3">
                    <h4 class="text-lg font-bold text-blue-800"><?= htmlspecialchars($trait['trait_order']) ?>. <?= htmlspecialchars($trait['trait_name']) ?></h4>
                    <div class="flex space-x-2">
                        <a href="?modal=edit_trait&id=<?= $trait['id'] ?>" class="text-yellow-500 hover:text-yellow-700" title="แก้ไขหัวข้อหลัก"><i class="fas fa-pencil-alt"></i></a>
                        <a href="#" onclick="confirmDelete('manage_characteristics.php?action=delete_trait&id=<?= $trait['id'] ?>')" class="text-red-500 hover:text-red-700" title="ลบหัวข้อหลัก"><i class="fas fa-trash-alt"></i></a>
                    </div>
                </div>
                <div class="mt-2 pl-4">
                    <table class="min-w-full text-sm">
                        <?php 
                            $items_stmt->execute([$trait['id']]);
                            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                            if (empty($items)) {
                                echo '<tr><td class="text-gray-500 py-2">ยังไม่มีรายการประเมินย่อย</td></tr>';
                            } else {
                                foreach($items as $item):
                        ?>
                            <tr class="border-b">
                                <td class="py-2 pr-4 text-gray-600 w-8"><?= htmlspecialchars($item['item_order']) ?>.</td>
                                <td class="py-2 w-full text-gray-800"><?= htmlspecialchars($item['item_text']) ?></td>
                                <td class="py-2 whitespace-nowrap">
                                    <a href="?modal=edit_item&id=<?= $item['id'] ?>" class="text-yellow-500 hover:text-yellow-700 mr-3" title="แก้ไขรายการย่อย"><i class="fas fa-pencil-alt"></i></a>
                                    <a href="#" onclick="confirmDelete('manage_characteristics.php?action=delete_item&id=<?= $item['id'] ?>')" class="text-red-500 hover:text-red-700" title="ลบรายการย่อย"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php 
                                endforeach; 
                            }
                        ?>
                    </table>
                     <a href="?modal=add_item&trait_id=<?= $trait['id'] ?>" class="text-sm mt-3 inline-block bg-green-100 text-green-800 hover:bg-green-200 py-1 px-3 rounded-full">
                        <i class="fas fa-plus"></i> เพิ่มรายการย่อย
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if($modal_info['type'] === 'add_trait' || $modal_info['type'] === 'edit_trait'): 
    $is_edit = $modal_info['type'] === 'edit_trait';
    $data = $modal_info['data'];
?>
<div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white">
        <div class="card-header-gradient text-white p-4 rounded-t-md flex justify-between items-center">
             <h3 class="text-lg font-semibold"><?= $is_edit ? 'แก้ไขหัวข้อหลัก' : 'เพิ่มหัวข้อหลักใหม่' ?></h3>
            <a href="manage_characteristics.php" class="text-white hover:text-gray-200 text-2xl">&times;</a>
        </div>
        <form action="manage_characteristics.php" method="POST" class="p-4">
            <input type="hidden" name="action" value="<?= $is_edit ? 'edit_trait' : 'add_trait' ?>">
            <input type="hidden" name="trait_id" value="<?= $data['id'] ?? '' ?>">
            <div class="mb-4">
                <label class="block text-gray-700">ชื่อหัวข้อหลัก</label>
                <input type="text" name="trait_name" value="<?= htmlspecialchars($data['trait_name'] ?? '') ?>" class="w-full px-3 py-2 border rounded" required>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">ลำดับการแสดงผล</label>
                <input type="number" name="trait_order" value="<?= htmlspecialchars($data['trait_order'] ?? '0') ?>" class="w-full px-3 py-2 border rounded" required>
            </div>
            <div class="flex justify-end pt-4 border-t"><button type="submit" class="btn-gradient text-white font-bold py-2 px-4 rounded-lg">บันทึก</button></div>
        </form>
    </div>
</div>
<?php endif; ?>


<?php if($modal_info['type'] === 'add_item' || $modal_info['type'] === 'edit_item'): 
    $is_edit = $modal_info['type'] === 'edit_item';
    $data = $modal_info['data'];
    $trait_id = $is_edit ? $data['trait_id'] : $modal_info['trait_id'];
?>
<div class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white">
        <div class="card-header-gradient text-white p-4 rounded-t-md flex justify-between items-center">
             <h3 class="text-lg font-semibold"><?= $is_edit ? 'แก้ไขรายการย่อย' : 'เพิ่มรายการย่อยใหม่' ?></h3>
            <a href="manage_characteristics.php" class="text-white hover:text-gray-200 text-2xl">&times;</a>
        </div>
        <form action="manage_characteristics.php" method="POST" class="p-4">
            <input type="hidden" name="action" value="<?= $is_edit ? 'edit_item' : 'add_item' ?>">
            <input type="hidden" name="item_id" value="<?= $data['id'] ?? '' ?>">
            <input type="hidden" name="trait_id" value="<?= $trait_id ?>">
            <div class="mb-4">
                <label class="block text-gray-700">เนื้อหารายการประเมิน</label>
                <textarea name="item_text" class="w-full px-3 py-2 border rounded" rows="3" required><?= htmlspecialchars($data['item_text'] ?? '') ?></textarea>
            </div>
            <div class="mb-4">
                <label class="block text-gray-700">ลำดับการแสดงผล</label>
                <input type="number" name="item_order" value="<?= htmlspecialchars($data['item_order'] ?? '0') ?>" class="w-full px-3 py-2 border rounded" required>
            </div>
            <div class="flex justify-end pt-4 border-t"><button type="submit" class="btn-gradient text-white font-bold py-2 px-4 rounded-lg">บันทึก</button></div>
        </form>
    </div>
</div>
<?php endif; ?>


<?php include 'footer.php'; ?>