<?php
// Get all materials with their stock levels
$db = Database::getInstance();
$materials = $db->query("
    SELECT 
        id,
        code,
        name,
        type,
        current_stock,
        min_stock,
        max_stock,
        CASE
            WHEN current_stock <= min_stock THEN 'danger'
            WHEN current_stock <= (min_stock + ((max_stock - min_stock) * 0.3)) THEN 'warning'
            ELSE 'success'
        END as stock_status
    FROM materials
    ORDER BY type, name
")->fetchAll();

// Group materials by type for the chart
$stockData = [
    'raw' => ['label' => translate('raw_materials'), 'data' => []],
    'semi_finished' => ['label' => translate('semi_finished'), 'data' => []],
    'finished' => ['label' => translate('finished_products'), 'data' => []]
];

foreach ($materials as $material) {
    $stockData[$material['type']]['data'][] = [
        'code' => $material['code'],
        'current' => $material['current_stock'],
        'min' => $material['min_stock'],
        'max' => $material['max_stock']
    ];
}
?>

<div class="row mb-4">
    <!-- Stock Status Cards -->
    <div class="col-md-4">
        <div class="card bg-success text-white h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-check-circle"></i> <?php echo translate('normal_stock'); ?>
                </h5>
                <h3 class="card-text">
                    <?php 
                    echo count(array_filter($materials, function($m) { 
                        return $m['stock_status'] === 'success'; 
                    })); 
                    ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-white h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo translate('low_stock'); ?>
                </h5>
                <h3 class="card-text">
                    <?php 
                    echo count(array_filter($materials, function($m) { 
                        return $m['stock_status'] === 'warning'; 
                    })); 
                    ?>
                </h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white h-100">
            <div class="card-body">
                <h5 class="card-title">
                    <i class="fas fa-times-circle"></i> <?php echo translate('critical_stock'); ?>
                </h5>
                <h3 class="card-text">
                    <?php 
                    echo count(array_filter($materials, function($m) { 
                        return $m['stock_status'] === 'danger'; 
                    })); 
                    ?>
                </h3>
            </div>
        </div>
    </div>
</div>

<!-- Stock Overview Chart -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-chart-bar"></i> <?php echo translate('stock_overview'); ?>
        </h5>
    </div>
    <div class="card-body">
        <ul class="nav nav-tabs" id="stockTabs" role="tablist">
            <?php foreach ($stockData as $type => $data): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo $type === 'raw' ? 'active' : ''; ?>" 
                        id="<?php echo $type; ?>-tab" 
                        data-bs-toggle="tab" 
                        data-bs-target="#<?php echo $type; ?>-content" 
                        type="button" 
                        role="tab">
                    <?php echo $data['label']; ?>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="tab-content mt-3" id="stockTabsContent">
            <?php foreach ($stockData as $type => $data): ?>
            <div class="tab-pane fade <?php echo $type === 'raw' ? 'show active' : ''; ?>" 
                 id="<?php echo $type; ?>-content" 
                 role="tabpanel">
                <canvas id="<?php echo $type; ?>Chart" height="300"></canvas>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Stock Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="fas fa-table"></i> <?php echo translate('stock_details'); ?>
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover datatable">
                <thead>
                    <tr>
                        <th><?php echo translate('code'); ?></th>
                        <th><?php echo translate('name'); ?></th>
                        <th><?php echo translate('type'); ?></th>
                        <th><?php echo translate('current_stock'); ?></th>
                        <th><?php echo translate('min_stock'); ?></th>
                        <th><?php echo translate('max_stock'); ?></th>
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
                            <?php echo number_format($material['current_stock']); ?>
                        </td>
                        <td class="text-end">
                            <?php echo number_format($material['min_stock']); ?>
                        </td>
                        <td class="text-end">
                            <?php echo number_format($material['max_stock']); ?>
                        </td>
                        <td>
                            <span class="badge bg-<?php echo $material['stock_status']; ?>">
                                <?php echo translate('stock_' . $material['stock_status']); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Chart Initialization -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php foreach ($stockData as $type => $data): ?>
    new Chart(document.getElementById('<?php echo $type; ?>Chart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($data['data'], 'code')); ?>,
            datasets: [
                {
                    label: '<?php echo translate('current_stock'); ?>',
                    data: <?php echo json_encode(array_column($data['data'], 'current')); ?>,
                    backgroundColor: 'rgba(54, 162, 235, 0.5)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                },
                {
                    label: '<?php echo translate('min_stock'); ?>',
                    data: <?php echo json_encode(array_column($data['data'], 'min')); ?>,
                    type: 'line',
                    borderColor: 'rgba(255, 99, 132, 1)',
                    borderWidth: 2,
                    fill: false
                },
                {
                    label: '<?php echo translate('max_stock'); ?>',
                    data: <?php echo json_encode(array_column($data['data'], 'max')); ?>,
                    type: 'line',
                    borderColor: 'rgba(75, 192, 192, 1)',
                    borderWidth: 2,
                    fill: false
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                legend: {
                    position: 'top'
                }
            }
        }
    });
    <?php endforeach; ?>
});
</script>
