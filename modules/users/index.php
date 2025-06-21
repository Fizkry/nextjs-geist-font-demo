<?php
$db = Database::getInstance();
$action = $_GET['action'] ?? 'list';

// Only admin can access this module
if ($_SESSION['role'] !== 'admin') {
    flashMessage(translate('access_denied'), 'danger');
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'add' || $action === 'edit') {
        $user_id = $_POST['user_id'] ?? null;
        $username = $_POST['username'];
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'];
        $menu_access = $_POST['menu_access'] ?? [];
        
        try {
            $db->getConnection()->beginTransaction();
            
            if ($action === 'add') {
                // Check if username exists
                $existing = $db->query("SELECT id FROM users WHERE username = ?", [$username])->fetch();
                if ($existing) {
                    throw new Exception(translate('username_exists'));
                }
                
                // Create new user
                $user_id = $db->insert('users', [
                    'username' => $username,
                    'password' => password_hash($password, PASSWORD_DEFAULT),
                    'role' => $role
                ]);
            } else {
                // Update existing user
                $data = ['role' => $role];
                if (!empty($password)) {
                    $data['password'] = password_hash($password, PASSWORD_DEFAULT);
                }
                $db->update('users', $data, ['id' => $user_id]);
            }
            
            $db->getConnection()->commit();
            flashMessage(
                $action === 'add' ? translate('user_created') : translate('user_updated'), 
                'success'
            );
            header('Location: index.php?page=users');
            exit();
        } catch (Exception $e) {
            $db->getConnection()->rollBack();
            flashMessage($e->getMessage(), 'danger');
        }
    } elseif ($action === 'delete') {
        $user_id = $_POST['user_id'];
        
        // Prevent deleting own account
        if ($user_id == $_SESSION['user_id']) {
            flashMessage(translate('cannot_delete_own_account'), 'danger');
        } else {
            if ($db->delete('users', ['id' => $user_id])) {
                flashMessage(translate('user_deleted'), 'success');
            } else {
                flashMessage(translate('error_deleting_user'), 'danger');
            }
        }
        header('Location: index.php?page=users');
        exit();
    }
}

// Get user data for editing
$edit_user = null;
if ($action === 'edit') {
    $user_id = $_GET['id'] ?? '';
    if ($user_id) {
        $edit_user = $db->query("SELECT * FROM users WHERE id = ?", [$user_id])->fetch();
    }
}
?>

<!-- User List -->
<?php if ($action === 'list'): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="fas fa-users"></i> <?php echo translate('user_management'); ?>
        </h5>
        <a href="index.php?page=users&action=add" class="btn btn-primary btn-sm">
            <i class="fas fa-user-plus"></i> <?php echo translate('add_new_user'); ?>
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th><?php echo translate('username'); ?></th>
                        <th><?php echo translate('role'); ?></th>
                        <th><?php echo translate('last_login'); ?></th>
                        <th><?php echo translate('created_at'); ?></th>
                        <th><?php echo translate('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = $db->query("SELECT * FROM users ORDER BY username")->fetchAll();
                    foreach ($users as $user):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td>
                            <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                <?php echo translate($user['role']); ?>
                            </span>
                        </td>
                        <td>
                            <?php echo $user['last_login'] ? formatDate($user['last_login']) : '-'; ?>
                        </td>
                        <td><?php echo formatDate($user['created_at']); ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="index.php?page=users&action=edit&id=<?php echo $user['id']; ?>" 
                                   class="btn btn-warning" 
                                   title="<?php echo translate('edit_user'); ?>">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                <button type="button" 
                                        class="btn btn-danger" 
                                        onclick="confirmDelete(<?php echo $user['id']; ?>)"
                                        title="<?php echo translate('delete_user'); ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
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
                <?php echo translate('confirm_delete_user'); ?>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="user_id" id="deleteUserId">
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
function confirmDelete(userId) {
    document.getElementById('deleteUserId').value = userId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php else: ?>
<!-- Add/Edit User Form -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-user-edit"></i> 
            <?php echo $action === 'add' ? translate('add_new_user') : translate('edit_user'); ?>
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="action" value="<?php echo $action; ?>">
            <?php if ($action === 'edit'): ?>
            <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
            <?php endif; ?>
            
            <div class="mb-3">
                <label for="username" class="form-label"><?php echo translate('username'); ?></label>
                <input type="text" 
                       class="form-control" 
                       id="username" 
                       name="username" 
                       value="<?php echo $edit_user['username'] ?? ''; ?>"
                       <?php echo $action === 'edit' ? 'readonly' : 'required'; ?>>
                <div class="invalid-feedback">
                    <?php echo translate('please_enter_username'); ?>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">
                    <?php echo translate('password'); ?>
                    <?php if ($action === 'edit'): ?>
                    <small class="text-muted">
                        (<?php echo translate('leave_blank_to_keep_current'); ?>)
                    </small>
                    <?php endif; ?>
                </label>
                <input type="password" 
                       class="form-control" 
                       id="password" 
                       name="password"
                       <?php echo $action === 'add' ? 'required' : ''; ?>>
                <div class="invalid-feedback">
                    <?php echo translate('please_enter_password'); ?>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="role" class="form-label"><?php echo translate('role'); ?></label>
                <select class="form-select" id="role" name="role" required>
                    <option value=""><?php echo translate('select_role'); ?></option>
                    <?php foreach (USER_ROLES as $role => $label): ?>
                    <option value="<?php echo $role; ?>"
                            <?php echo (isset($edit_user) && $edit_user['role'] === $role) ? 'selected' : ''; ?>>
                        <?php echo translate($role); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">
                    <?php echo translate('please_select_role'); ?>
                </div>
            </div>

            <div class="text-end">
                <a href="index.php?page=users" class="btn btn-secondary">
                    <?php echo translate('cancel'); ?>
                </a>
                <button type="submit" class="btn btn-primary">
                    <?php echo translate('save'); ?>
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
