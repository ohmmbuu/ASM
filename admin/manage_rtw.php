<?php
// === ส่วน Logic ===
require_once '../config/db.php';
if (!isset($_SESSION['user_id'])) { header("Location: ../login.php"); exit(); }

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    switch ($action) {
        case 'add_aspect':
            $stmt = $conn->prepare("INSERT INTO rtw_aspects (aspect_name, aspect_order) VALUES (?, ?)");
            $stmt->execute([$_POST['aspect_name'], $_POST['aspect_order']]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'เพิ่มด้านหลักสำเร็จ'];
            break;
        case 'edit_aspect':
            $stmt = $conn->prepare("UPDATE rtw_aspects SET aspect_name = ?, aspect_order = ? WHERE id = ?");
            $stmt->execute([$_POST['aspect_name'], $_POST['aspect_order'], $_POST['aspect_id']]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'แก้ไขด้านหลักสำเร็จ'];
            break;
        case 'add_item':
            $stmt = $conn->prepare("INSERT INTO rtw_items (aspect_id, item_text, item_order) VALUES (?, ?, ?)");
            $stmt->execute([$_POST['aspect_id'], $_POST['item_text'], $_POST['item_order']]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'เพิ่มรายการย่อยสำเร็จ'];
            break;
        case 'edit_item':
            $stmt = $conn->prepare("UPDATE rtw_items SET item_text = ?, item_order = ? WHERE id = ?");
            $stmt->execute([$_POST['item_text'], $_POST['item_order'], $_POST['item_id']]);
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'แก้ไขรายการย่อยสำเร็จ'];
            break;
    }
    header("Location: manage_rtw.php");
    exit();
}

// Handle GET Requests (Delete)
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    $id = $_GET['id'] ?? null;
    if ($action === 'delete_aspect' && $id) {
        $stmt = $conn->prepare("DELETE FROM rtw_aspects WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['toast'] = ['type' => 'warning', 'message' => 'ลบด้านหลักและรายการย่อยทั้งหมดแล้ว'];
    } elseif ($action === 'delete_item' && $id) {
        $stmt = $conn->prepare("DELETE FROM rtw_items WHERE id = ?");
        $stmt->execute([$id]);
        $_SESSION['toast'] = ['type' => 'warning', 'message' => 'ลบรายการย่อยสำเร็จ'];
    }
    header("Location: manage_rtw.php");
    exit();
}

// Prepare data for Modals
$modal_info = ['type' => 'none', 'data' => null, 'aspect_id' => null];
if (isset($_GET['modal'])) {
    $modal_type = $_GET['modal'];
    $id = $_GET['id'] ?? null;
    if ($modal_type === 'edit_aspect' && $id) {
        $stmt = $conn->prepare("SELECT * FROM rtw_aspects WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) $modal_info = ['type' => 'edit_aspect', 'data' => $data];
    } elseif ($modal_type === 'add_item' && isset($_GET['aspect_id'])) {
        $modal_info = ['type' => 'add_item', 'data' => null, 'aspect_id' => $_GET['aspect_id']];
    } elseif ($modal_type === 'edit_item' && $id) {
        $stmt = $conn->prepare("SELECT * FROM rtw_items WHERE id = ?");
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) $modal_info = ['type' => 'edit_item', 'data' => $data];
    }
}

// === ส่วน View ===
include 'header.php';

$aspects = $conn->query("SELECT * FROM rtw_aspects ORDER BY aspect_order ASC")->fetchAll(PDO::FETCH_ASSOC);
$items_stmt = $conn->prepare("SELECT * FROM rtw_items WHERE aspect_id = ? ORDER BY item_order ASC");
?>
<div class="bg-white p-6 rounded-lg shadow-lg">
    <div class="flex justify-between items-center mb-6">
        <h3 class="text-xl font-semibold text-gray-800">จัดการรายการประเมินการอ่าน คิดวิเคราะห์ และเขียน</h3>
        <a href="?modal=add_aspect" class="btn-gradient text-white font-bold py-2 px-4 rounded-lg">เพิ่มด้านหลักใหม่</a>
    </div>
    <div class="space-y-6">
        <?php foreach($aspects as $aspect): ?>
            <div class="border rounded-lg p-4 bg-gray-50">
                <div class="flex justify-between items-center border-b pb-3 mb-3">
                    <h4 class="text-lg font-bold text-blue-800"><?= htmlspecialchars($aspect['aspect_name']) ?></h4>
                    <div class="flex space-x-2">
                        <a href="?modal=edit_aspect&id=<?= $aspect['id'] ?>" class="text-yellow-500"><i class="fas fa-pencil-alt"></i></a>
                        <a href="#" onclick="confirmDelete('manage_rtw.php?action=delete_aspect&id=<?= $aspect['id'] ?>')" class="text-red-500"><i class="fas fa-trash-alt"></i></a>
                    </div>
                </div>
                <div class="mt-2 pl-4">
                    <table class="min-w-full text-sm">
                        <?php 
                            $items_stmt->execute([$aspect['id']]);
                            $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
                            foreach($items as $item):
                        ?>
                            <tr class="border-b">
                                <td class="py-2 pr-4 w-8"><?= htmlspecialchars($item['item_order']) ?>.</td>
                                <td class="py-2 w-full"><?= htmlspecialchars($item['item_text']) ?></td>
                                <td class="py-2 whitespace-nowrap">
                                    <a href="?modal=edit_item&id=<?= $item['id'] ?>" class="text-yellow-500 mr-3"><i class="fas fa-pencil-alt"></i></a>
                                    <a href="#" onclick="confirmDelete('manage_rtw.php?action=delete_item&id=<?= $item['id'] ?>')" class="text-red-500"><i class="fas fa-trash-alt"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                     <a href="?modal=add_item&aspect_id=<?= $aspect['id'] ?>" class="text-sm mt-3 inline-block bg-green-100 text-green-800 py-1 px-3 rounded-full">เพิ่มรายการย่อย</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php if($modal_info['type'] === 'add_aspect' || $modal_info['type'] === 'edit_aspect'): 
    $is_edit = $modal_info['type'] === 'edit_aspect'; $data = $modal_info['data']; ?>
<div class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-lg bg-white rounded-md">
        <form action="manage_rtw.php" method="POST" class="p-4">
            <h3 class="text-lg font-semibold"><?= $is_edit ? 'แก้ไขด้านหลัก' : 'เพิ่มด้านหลักใหม่' ?></h3>
            <input type="hidden" name="action" value="<?= $is_edit ? 'edit_aspect' : 'add_aspect' ?>">
            <input type="hidden" name="aspect_id" value="<?= $data['id'] ?? '' ?>">
            <div class="my-4"><label>ชื่อด้านหลัก</label><input type="text" name="aspect_name" value="<?= htmlspecialchars($data['aspect_name'] ?? '') ?>" class="w-full p-2 border rounded" required></div>
            <div class="my-4"><label>ลำดับ</label><input type="number" name="aspect_order" value="<?= htmlspecialchars($data['aspect_order'] ?? '0') ?>" class="w-full p-2 border rounded" required></div>
            <div class="flex justify-end pt-4"><button type="submit" class="btn-gradient text-white py-2 px-4 rounded-lg">บันทึก</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php if($modal_info['type'] === 'add_item' || $modal_info['type'] === 'edit_item'): 
    $is_edit = $modal_info['type'] === 'edit_item'; $data = $modal_info['data']; $aspect_id = $is_edit ? $data['aspect_id'] : $modal_info['aspect_id']; ?>
<div class="fixed inset-0 bg-gray-600 bg-opacity-50 h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-lg bg-white rounded-md">
        <form action="manage_rtw.php" method="POST" class="p-4">
            <h3 class="text-lg font-semibold"><?= $is_edit ? 'แก้ไขรายการย่อย' : 'เพิ่มรายการย่อยใหม่' ?></h3>
            <input type="hidden" name="action" value="<?= $is_edit ? 'edit_item' : 'add_item' ?>">
            <input type="hidden" name="item_id" value="<?= $data['id'] ?? '' ?>">
            <input type="hidden" name="aspect_id" value="<?= $aspect_id ?>">
            <div class="my-4"><label>เนื้อหารายการ</label><textarea name="item_text" class="w-full p-2 border rounded" required><?= htmlspecialchars($data['item_text'] ?? '') ?></textarea></div>
            <div class="my-4"><label>ลำดับ</label><input type="number" name="item_order" value="<?= htmlspecialchars($data['item_order'] ?? '0') ?>" class="w-full p-2 border rounded" required></div>
            <div class="flex justify-end pt-4"><button type="submit" class="btn-gradient text-white py-2 px-4 rounded-lg">บันทึก</button></div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php include 'footer.php'; ?>