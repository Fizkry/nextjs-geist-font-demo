<?php
$db = Database::getInstance();

// Only admin can access this module
if ($_SESSION['role'] !== 'admin') {
    flashMessage(translate('access_denied'), 'danger');
    header('Location: index.php');
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->getConnection()->beginTransaction();
        
        // Update system title
        setSetting('system_title', $_POST['system_title']);
        
        // Update language
        if (setLanguage($_POST['language'])) {
            setSetting('default_language', $_POST['language']);
        }
        
        // Handle logo upload
        if (isset($_FILES['system_logo']) && $_FILES['system_logo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['system_logo'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            $maxSize = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowedTypes)) {
                throw new Exception(translate('invalid_image_type'));
            }
            
            if ($file['size'] > $maxSize) {
                throw new Exception(translate('file_too_large'));
            }
            
            $uploadDir = 'assets/images/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $filename = 'logo_' . time() . '_' . $file['name'];
            $destination = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                // Delete old logo if exists
                $oldLogo = getSetting('system_logo');
                if ($oldLogo && file_exists($oldLogo)) {
                    unlink($oldLogo);
                }
                
                setSetting('system_logo', $destination);
            } else {
                throw new Exception(translate('error_uploading_file'));
            }
        }
        
        $db->getConnection()->commit();
        flashMessage(translate('settings_updated'), 'success');
    } catch (Exception $e) {
        $db->getConnection()->rollBack();
        flashMessage($e->getMessage(), 'danger');
    }
    
    header('Location: index.php?page=settings');
    exit();
}

// Get current settings
$system_title = getSetting('system_title') ?? SITE_NAME;
$current_language = getSetting('default_language') ?? DEFAULT_LANGUAGE;
$system_logo = getSetting('system_logo');
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-cog"></i> <?php echo translate('system_settings'); ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <!-- System Title -->
                    <div class="mb-4">
                        <label for="system_title" class="form-label">
                            <?php echo translate('system_title'); ?>
                        </label>
                        <input type="text" 
                               class="form-control" 
                               id="system_title" 
                               name="system_title"
                               value="<?php echo htmlspecialchars($system_title); ?>" 
                               required>
                        <div class="invalid-feedback">
                            <?php echo translate('please_enter_system_title'); ?>
                        </div>
                    </div>

                    <!-- Language Selection -->
                    <div class="mb-4">
                        <label for="language" class="form-label">
                            <?php echo translate('system_language'); ?>
                        </label>
                        <select class="form-select" id="language" name="language" required>
                            <?php foreach ($available_languages as $code => $name): ?>
                            <option value="<?php echo $code; ?>"
                                    <?php echo $current_language === $code ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            <?php echo translate('please_select_language'); ?>
                        </div>
                    </div>

                    <!-- System Logo -->
                    <div class="mb-4">
                        <label for="system_logo" class="form-label">
                            <?php echo translate('system_logo'); ?>
                        </label>
                        
                        <?php if ($system_logo && file_exists($system_logo)): ?>
                        <div class="mb-3">
                            <img src="<?php echo $system_logo; ?>" 
                                 alt="<?php echo translate('current_logo'); ?>"
                                 class="img-thumbnail"
                                 style="max-height: 100px;">
                        </div>
                        <?php endif; ?>
                        
                        <input type="file" 
                               class="form-control" 
                               id="system_logo" 
                               name="system_logo"
                               accept="image/jpeg,image/png,image/gif">
                        <div class="form-text">
                            <?php echo translate('allowed_image_types'); ?>: JPG, PNG, GIF
                            <br>
                            <?php echo translate('max_file_size'); ?>: 5MB
                        </div>
                    </div>

                    <!-- Preview Section -->
                    <div class="mb-4">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-3 text-muted">
                                    <?php echo translate('preview'); ?>
                                </h6>
                                <div class="d-flex align-items-center">
                                    <div id="logoPreview" class="me-3">
                                        <?php if ($system_logo && file_exists($system_logo)): ?>
                                        <img src="<?php echo $system_logo; ?>" 
                                             alt="Logo Preview"
                                             style="max-height: 50px;">
                                        <?php endif; ?>
                                    </div>
                                    <div id="titlePreview" class="h5 mb-0">
                                        <?php echo htmlspecialchars($system_title); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <?php echo translate('save_settings'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Preview system title
    const titleInput = document.getElementById('system_title');
    const titlePreview = document.getElementById('titlePreview');
    
    titleInput.addEventListener('input', function() {
        titlePreview.textContent = this.value;
    });
    
    // Preview logo
    const logoInput = document.getElementById('system_logo');
    const logoPreview = document.getElementById('logoPreview');
    
    logoInput.addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                logoPreview.innerHTML = `
                    <img src="${e.target.result}" 
                         alt="Logo Preview" 
                         style="max-height: 50px;">
                `;
            };
            reader.readAsDataURL(file);
        }
    });
});
</script>
