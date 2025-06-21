<?php
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';

// Get all finished products
$finished_products = $db->query("
    SELECT id, code, name 
    FROM materials 
    WHERE type = 'finished' 
    ORDER BY name
")->fetchAll();

// Get all materials (raw and semi-finished)
$available_materials = $db->query("
    SELECT id, code, name, type 
    FROM materials 
    WHERE type IN ('raw', 'semi_finished') 
    ORDER BY type, name
")->fetchAll();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $product_id = $_POST['product_id'] ?? '';
        $materials = $_POST['materials'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        
        if ($product_id && !empty($materials)) {
            // Begin transaction
            $db->getConnection()->beginTransaction();
            
            try {
                // Delete existing BOM entries if editing
                if ($action === 'edit') {
                    $db->query("DELETE FROM bom WHERE product_id = ?", [$product_id]);
                }
                
                // Insert new BOM entries
                foreach ($materials as $key => $material_id) {
                    if (!empty($material_id) && !empty($quantities[$key])) {
                        $db->insert('bom', [
                            'product_id' => $product_id,
                            'material_id' => $material_id,
                            'quantity' => $quantities[$key]
                        ]);
                    }
                }
                
                $db->getConnection()->commit();
                flashMessage(translate('bom_saved_successfully'), 'success');
            } catch (Exception $e) {
                $db->getConnection()->rollBack();
                flashMessage(translate('error_saving_bom'), 'danger');
            }
        }
    } elseif ($action === 'delete') {
        $product_id = $_POST['product_id'] ?? '';
        
        if ($product_id) {
            if ($db->delete('bom', ['product_id' => $product_id])) {
                flashMessage(translate('bom_deleted_successfully'), 'success');
            } else {
                flashMessage(translate('error_deleting_bom'), 'danger');
            }
        }
    }
    
    // Redirect back to BOM list
    header('Location: index.php?page=bom');
    exit();
}

// Get BOM data for editing
$edit_data = null;
if ($action === 'edit') {
    $product_id = $_GET['product_id'] ?? '';
    if ($product_id) {
        $edit_data = $db->query("
            SELECT b.*, m.code as material_code, m.name as material_name
            FROM bom b
            JOIN materials m ON b.material_id = m.id
            WHERE b.product_id = ?
            ORDER BY m.type, m.name
        ", [$product_id])->fetchAll();
    }
}
?>

<!-- BOM List -->
<?php if ($action === 'list'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-boxes"></i> <?php echo translate('bill_of_materials'); ?>
        </h5>
        <a href="index.php?page=bom&action=add" class="btn btn-primary btn-sm">
            <i class="fas fa-plus"></i> <?php echo translate('add_new_bom'); ?>
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th><?php echo translate('product'); ?></th>
                        <th><?php echo translate('materials_count'); ?></th>
                        <th><?php echo translate('last_updated'); ?></th>
                        <th><?php echo translate('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $bom_list = $db->query("
                        SELECT 
                            m.id,
                            m.code,
                            m.name,
                            COUNT(b.material_id) as materials_count,
                            MAX(b.created_at) as last_updated
                        FROM materials m
                        LEFT JOIN bom b ON m.id = b.product_id
                        WHERE m.type = 'finished'
                        GROUP BY m.id, m.code, m.name
                        ORDER BY m.name
                    ")->fetchAll();
                    
                    foreach ($bom_list as $item):
                    ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($item['code']); ?> - 
                            <?php echo htmlspecialchars($item['name']); ?>
                        </td>
                        <td><?php echo $item['materials_count']; ?></td>
                        <td>
                            <?php echo $item['last_updated'] ? formatDate($item['last_updated']) : '-'; ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="index.php?page=bom&action=view&product_id=<?php echo $item['id']; ?>" 
                                   class="btn btn-info" 
                                   title="<?php echo translate('view_details'); ?>">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="index.php?page=bom&action=edit&product_id=<?php echo $item['id']; ?>" 
                                   class="btn btn-warning" 
                                   title="<?php echo translate('edit_bom'); ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" 
                                        class="btn btn-danger" 
                                        title="<?php echo translate('delete_bom'); ?>"
                                        onclick="confirmDelete(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo translate('confirm_delete'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php echo translate('confirm_delete_bom'); ?>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="product_id" id="deleteProductId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo translate('cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-danger">
                        <?php echo translate('delete'); ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(productId) {
    document.getElementById('deleteProductId').value = productId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit BOM Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-edit"></i> 
            <?php echo $action === 'add' ? translate('add_new_bom') : translate('edit_bom'); ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="<?php echo $action; ?>">
            
            <div class="mb-3">
                <label for="product_id" class="form-label"><?php echo translate('product'); ?></label>
                <select class="form-select" id="product_id" name="product_id" required 
                        <?php echo $action === 'edit' ? 'disabled' : ''; ?>>
                    <option value=""><?php echo translate('select_product'); ?></option>
                    <?php foreach ($finished_products as $product): ?>
                    <option value="<?php echo $product['id']; ?>"
                            <?php echo ($action === 'edit' && $edit_data && $edit_data[0]['product_id'] == $product['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($product['code'] . ' - ' . $product['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">
                    <?php echo translate('please_select_product'); ?>
                </div>
            </div>

            <div id="materialsContainer">
                <?php 
                if ($action === 'edit' && $edit_data): 
                    foreach ($edit_data as $index => $item):
                ?>
                <div class="row mb-3 material-row">
                    <div class="col-md-6">
                        <select class="form-select" name="materials[]" required>
                            <option value=""><?php echo translate('select_material'); ?></option>
                            <?php foreach ($available_materials as $material): ?>
                            <option value="<?php echo $material['id']; ?>"
                                    <?php echo $item['material_id'] == $material['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($material['code'] . ' - ' . $material['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="number" class="form-control" name="quantities[]" 
                               value="<?php echo $item['quantity']; ?>"
                               min="0.01" step="0.01" required>
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm remove-material">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <?php 
                    endforeach;
                else:
                ?>
                <div class="row mb-3 material-row">
                    <div class="col-md-6">
                        <select class="form-select" name="materials[]" required>
                            <option value=""><?php echo translate('select_material'); ?></option>
                            <?php foreach ($available_materials as $material): ?>
                            <option value="<?php echo $material['id']; ?>">
                                <?php echo htmlspecialchars($material['code'] . ' - ' . $material['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <input type="number" class="form-control" name="quantities[]" 
                               min="0.01" step="0.01" required
                               placeholder="<?php echo translate('quantity'); ?>">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm remove-material">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <button type="button" class="btn btn-secondary" id="addMaterial">
                    <i class="fas fa-plus"></i> <?php echo translate('add_material'); ?>
                </button>
            </div>

            <div class="text-end">
                <a href="index.php?page=bom" class="btn btn-secondary">
                    <?php echo translate('cancel'); ?>
                </a>
                <button type="submit" class="btn btn-primary">
                    <?php echo translate('save_bom'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const materialsContainer = document.getElementById('materialsContainer');
    const addMaterialBtn = document.getElementById('addMaterial');
    
    // Add new material row
    addMaterialBtn.addEventListener('click', function() {
        const newRow = materialsContainer.querySelector('.material-row').cloneNode(true);
        newRow.querySelectorAll('select, input').forEach(input => input.value = '');
        materialsContainer.appendChild(newRow);
        initializeRemoveButtons();
    });
    
    // Initialize remove buttons
    function initializeRemoveButtons() {
        document.querySelectorAll('.remove-material').forEach(btn => {
            btn.onclick = function() {
                if (document.querySelectorAll('.material-row').length > 1) {
                    this.closest('.material-row').remove();
                }
            };
        });
    }
    
    initializeRemoveButtons();
});
</script>

<?php elseif ($action === 'view'): ?>
<!-- View BOM Details -->
<?php
$product_id = $_GET['product_id'] ?? '';
if ($product_id) {
    $product = $db->query("
        SELECT * FROM materials WHERE id = ?
    ", [$product_id])->fetch();
    
    $bom_details = $db->query("
        SELECT 
            b.*,
            m.code as material_code,
            m.name as material_name,
            m.type as material_type,
            m.current_stock
        FROM bom b
        JOIN materials m ON b.material_id = m.id
        WHERE b.product_id = ?
        ORDER BY m.type, m.name
    ", [$product_id])->fetchAll();
}
?>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-box"></i> 
            <?php echo translate('bom_details'); ?>: 
            <?php echo htmlspecialchars($product['code'] . ' - ' . $product['name']); ?>
        </h5>
        <a href="index.php?page=bom" class="btn btn-secondary btn-sm">
            <?php echo translate('back_to_list'); ?>
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?php echo translate('material_code'); ?></th>
                        <th><?php echo translate('material_name'); ?></th>
                        <th><?php echo translate('type'); ?></th>
                        <th><?php echo translate('quantity_needed'); ?></th>
                        <th><?php echo translate('current_stock'); ?></th>
                        <th><?php echo translate('status'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bom_details as $detail): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($detail['material_code']); ?></td>
                        <td><?php echo htmlspecialchars($detail['material_name']); ?></td>
                        <td><?php echo translate($detail['material_type']); ?></td>
                        <td class="text-end">
                            <?php echo number_format($detail['quantity'], 2); ?>
                        </td>
                        <td class="text-end">
                            <?php echo number_format($detail['current_stock']); ?>
                        </td>
                        <td>
                            <?php
                            $status = $detail['current_stock'] >= $detail['quantity'] ? 'success' : 'danger';
                            $status_text = $status === 'success' ? 'sufficient' : 'insufficient';
                            ?>
                            <span class="badge bg-<?php echo $status; ?>">
                                <?php echo translate('stock_' . $status_text); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>
