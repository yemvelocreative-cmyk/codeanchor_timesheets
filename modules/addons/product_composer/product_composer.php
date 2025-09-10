<?php
'Size' => '25',
'Description' => 'Show single parent line to clients & PDFs, but keep children as real items.',
'Default' => 'on',
],
],
];
}


function product_composer_activate()
{
try {
\Illuminate\Database\Capsule\Manager::schema()->create('mod_product_composer_parents', function ($table) {
/** @var \Illuminate\Database\Schema\Blueprint $table */
$table->increments('id');
$table->integer('parent_pid'); // WHMCS product id
$table->string('display_name');
$table->text('invoice_description_template')->nullable();
$table->tinyInteger('collapse_on_client')->default(1);
$table->timestamps();
});


\Illuminate\Database\Capsule\Manager::schema()->create('mod_product_composer_children', function ($table) {
$table->increments('id');
$table->integer('parent_pid');
$table->integer('child_pid');
$table->integer('sort_order')->default(0);
$table->string('qty_formula')->default('1');
$table->enum('unit_price_rule', ['product_price','override_from_cf','fixed'])->default('product_price');
$table->string('unit_price_value')->nullable(); // decimal or token (e.g., cf:2)
$table->decimal('min_amount', 12, 2)->nullable();
$table->decimal('max_amount', 12, 2)->nullable();
$table->text('notes_template')->nullable();
$table->tinyInteger('is_required')->default(1);
$table->timestamps();
});


return ['status' => 'success', 'description' => 'Product Composer tables created.'];
} catch (\Throwable $e) {
return ['status' => 'error', 'description' => 'Activation failed: ' . $e->getMessage()];
}
}


function product_composer_deactivate()
{
// Do not drop data by default; keep for safety.
return ['status' => 'success', 'description' => 'Product Composer deactivated. Tables preserved.'];
}


function product_composer_upgrade($vars)
{
$version = $vars['version'];
// Example of future migrations
// if (version_compare($version, '0.2.0', '<')) { ... }
}


function product_composer_output($vars)
{
$action = isset($_GET['action']) ? $_GET['action'] : 'index';


$smarty = new \WHMCS\Smarty();
$smarty->assign('modulelink', $vars['modulelink']);


switch ($action) {
case 'parents':
$parents = \E2A\Composer\Database::parents();
$smarty->assign('parents', $parents);
echo $smarty->fetch(__DIR__ . '/templates/admin/parents.tpl');
break;
case 'children':
$parentPid = (int)($_GET['parent_pid'] ?? 0);
$children = \E2A\Composer\Database::children($parentPid);
$smarty->assign('parent_pid', $parentPid);
$smarty->assign('children', $children);
echo $smarty->fetch(__DIR__ . '/templates/admin/children.tpl');
break;
case 'index':
default:
echo $smarty->fetch(__DIR__ . '/templates/admin/index.tpl');
break;
}
}