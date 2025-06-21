<?php
$db = Database::getInstance();
$plan = $_GET['plan'] ?? '1';
$action = $_GET['action'] ?? 'list';

// Get plan codes based on selected plan
$plan_codes = PLAN_TYPES["plan{$plan}"];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'create' || $action === 'update') {
        $production_id = $_POST['production_id'] ?? null;
        $plan_type = $_POST['plan_type'];
        $code = $_POST['code'];
        $status = $_POST['status'];
        
        try {
            if ($action === 'create') {
                $db->insert('production_plans', [
                    'plan_type' => $plan_type,
                    'code' => $code,
                    'status' => $status
                ]);
                flashMessage(translate('production_plan_created'), 'success');
            } else {
                $db->update('production_plans', 
                    ['status' => $status],
                    ['id' => $production_id]
                );
                flashMessage(translate('production_plan_updated'), 'success');
            }
        } catch (Exception $e) {
            flashMessage(translate('error_saving_production_plan'), 'danger');
        }
        
        header("Location: index.php?page=production&plan={$plan}");
        exit();
    }
}

// Get production plans for the selected plan type
$production_plans = $db->query("
    SELECT p.*, 
           COALESCE(COUNT(DISTINCT m.id), 0) as materials_count,
           COALESCE(SUM(CASE WHEN m.current_stock < (b.quantity) THEN 1 ELSE 0 END), 0) as shortage_count
    FROM production_plans p
    LEFT JOIN materials f ON p.code = f.code
    LEFT JOIN bom b ON f.id = b.product_id
    LEFT JOIN materials m ON b.material_id = m.id
    WHERE p.plan_type = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
", ["plan{$plan}"])->fetchAll();
?>

<div class="card">
    <div class="card-header">
        <ul class="nav nav-tabs card-header-tabs">
            <?php for ($i = 1; $i <= 3; $i++): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo $plan == $i ? 'active' : ''; ?>" 
                   href="index.php?page=production&plan=<?php echo $i; ?>">
                    Plan <?php echo $i; ?>
                    <?php
                    $codes = implode(', ', PLAN_TYPES["plan{$i}"]);
                    echo "({$codes})";
                    ?>
                </a>
            </li>
            <?php endfor; ?>
        </ul>
    </div>
    <div class="card-body">
        <!-- Action Buttons -->
        <div class="mb-3">
            <button type="button" 
                    class="btn btn-primary" 
                    data-bs-toggle="modal" 
                    data-bs-target="#productionModal">
                <i class="fas fa-plus"></i> <?php echo translate('create_production_plan'); ?>
            </button>
        </div>

        <!-- Production Plans Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th><?php echo translate('code'); ?></th>
                        <th><?php echo translate('materials'); ?></th>
                        <th><?php echo translate('shortages'); ?></th>
                        <th><?php echo translate('status'); ?></th>
                        <th><?php echo translate('created_at'); ?></th>
                        <th><?php echo translate('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($production_plans as $prod): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($prod['code']); ?></td>
                        <td><?php echo $prod['materials_count']; ?></td>
                        <td>
                            <?php if ($prod['shortage_count'] > 0): ?>
                            <span class="badge bg-danger">
                                <?php echo $prod['shortage_count']; ?> <?php echo translate('items_short'); ?>
                            </span>
                            <?php else: ?>
                            <span class="badge bg-success">
                                <?php echo translate('all_materials_available'); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php
                            $status_colors = [
                                'pending' => 'secondary',
                                'in_progress' => 'primary',
                                'completed' => 'success'
                            ];
                            ?>
                            <span class="badge bg-<?php echo $status_colors[$prod['status']]; ?>">
                                <?php echo translate($prod['status']); ?>
                            </span>
                        </td>
                        <td><?php echo formatDate($prod['created_at']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="index.php?page=production&plan=<?php echo $plan; ?>&action=view&id=<?php echo $prod['id']; ?>" 
                                   class="btn btn-info" 
                                   title="<?php echo translate('view_details'); ?>">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" 
                                        class="btn btn-warning"
                                        onclick="editStatus(<?php echo $prod['id']; ?>, '<?php echo $prod['status']; ?>')"
                                        title="<?php echo translate('update_status'); ?>">
                                    <i class="fas fa-edit"></i>
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

<!-- Create Production Plan Modal -->
<div class="modal fade" id="productionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo translate('create_production_plan'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="plan_type" value="plan<?php echo $plan; ?>">
                    
                    <div class="mb-3">
                        <label for="code" class="form-label"><?php echo translate('product_code'); ?></label>
                        <select class="form-select" id="code" name="code" required>
                            <option value=""><?php echo translate('select_product'); ?></option>
                            <?php foreach ($plan_codes as $code): ?>
                            <option value="<?php echo $code; ?>"><?php echo $code; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            <?php echo translate('please_select_product'); ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label"><?php echo translate('status'); ?></label>
                        <select class="form-select" id="status" name="status" required>
                            <option value="pending"><?php echo translate('pending'); ?></option>
                            <option value="in_progress"><?php echo translate('in_progress'); ?></option>
                            <option value="completed"><?php echo translate('completed'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo translate('cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo translate('create'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo translate('update_status'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-body">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="production_id" id="editProductionId">
                    <input type="hidden" name="plan_type" value="plan<?php echo $plan; ?>">
                    
                    <div class="mb-3">
                        <label for="editStatus" class="form-label"><?php echo translate('status'); ?></label>
                        <select class="form-select" id="editStatus" name="status" required>
                            <option value="pending"><?php echo translate('pending'); ?></option>
                            <option value="in_progress"><?php echo translate('in_progress'); ?></option>
                            <option value="completed"><?php echo translate('completed'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <?php echo translate('cancel'); ?>
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <?php echo translate('update'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($action === 'view'): ?>
<!-- View Production Plan Details -->
<?php
$production_id = $_GET['id'] ?? '';
if ($production_id) {
    $production = $db->query("
        SELECT p.*, 
               f.name as product_name,
               f.current_stock as product_stock
        FROM production_plans p
        LEFT JOIN materials f ON p.code = f.code
        WHERE p.id = ?
    ", [$production_id])->fetch();
    
    if ($production) {
        $materials = $db->query("
            SELECT 
                m.*,
                b.quantity as required_qty,
                m.current_stock as available_qty,
                GREATEST(0, b.quantity - m.current_stock) as shortage_qty
            FROM materials f
            JOIN bom b ON f.id = b.product_id
            JOIN materials m ON b.material_id = m.id
            WHERE f.code = ?
            ORDER BY m.type, m.name
        ", [$production['code']])->fetchAll();
    }
}
?>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <?php echo translate('production_details'); ?>: 
            <?php echo htmlspecialchars($production['code']); ?>
            (<?php echo htmlspecialchars($production['product_name']); ?>)
        </h5>
    </div>
    <div class="card-body">
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">
                            <?php echo translate('status'); ?>
                        </h6>
                        <span class="badge bg-<?php echo $status_colors[$production['status']]; ?>">
                            <?php echo translate($production['status']); ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">
                            <?php echo translate('created_at'); ?>
                        </h6>
                        <?php echo formatDate($production['created_at']); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">
                            <?php echo translate('product_stock'); ?>
                        </h6>
                        <?php echo number_format($production['product_stock']); ?>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-light">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">
                            <?php echo translate('materials_count'); ?>
                        </h6>
                        <?php echo count($materials); ?>
                    </div>
                </div>
            </div>
        </div>

        <h6 class="mb-3"><?php echo translate('required_materials'); ?></h6>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><?php echo translate('material_code'); ?></th>
                        <th><?php echo translate('material_name'); ?></th>
                        <th><?php echo translate('type'); ?></th>
                        <th><?php echo translate('required_qty'); ?></th>
                        <th><?php echo translate('available_qty'); ?></th>
                        <th><?php echo translate('shortage_qty'); ?></th>
                        <th><?php echo translate('status'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($materials as $material): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($material['code']); ?></td>
                        <td><?php echo htmlspecialchars($material['name']); ?></td>
                        <td><?php echo translate($material['type']); ?></td>
                        <td class="text-end">
                            <?php echo number_format($material['required_qty'], 2); ?>
                        </td>
                        <td class="text-end">
                            <?php echo number_format($material['available_qty']); ?>
                        </td>
                        <td class="text-end">
                            <?php echo number_format($material['shortage_qty'], 2); ?>
                        </td>
                        <td>
                            <?php if ($material['shortage_qty'] > 0): ?>
                            <span class="badge bg-danger">
                                <?php echo translate('insufficient'); ?>
                            </span>
                            <?php else: ?>
                            <span class="badge bg-success">
                                <?php echo translate('sufficient'); ?>
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="text-end mt-3">
            <a href="index.php?page=production&plan=<?php echo $plan; ?>" class="btn btn-secondary">
                <?php echo translate('back_to_list'); ?>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function editStatus(id, currentStatus) {
    document.getElementById('editProductionId').value = id;
    document.getElementById('editStatus').value = currentStatus;
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}
</script>
