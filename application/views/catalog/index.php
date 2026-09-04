<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo H($location ? $location->name : 'Catálogo'); ?> — Catálogo</title>
<style>
	* { box-sizing: border-box; }
	body { margin:0; font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background:#f4f5f7; color:#222; }
	a { color: inherit; }
	.topbar { background:#2f80ed; color:#fff; padding:14px 16px; }
	.topbar-inner { max-width:1100px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; }
	.topbar h1 { font-size:18px; margin:0; }
	.staff-badge { background:rgba(255,255,255,0.2); border-radius:20px; padding:4px 12px; font-size:12px; }
	.staff-badge a { color:#fff; text-decoration:underline; margin-left:8px; }
	.search-wrap { max-width:1100px; margin:16px auto 0; padding:0 16px; }
	.search-form { display:flex; gap:8px; }
	.search-form input[type=text] { flex:1; padding:10px 12px; border:1px solid #ddd; border-radius:8px; font-size:15px; }
	.search-form button { padding:10px 16px; border:none; border-radius:8px; background:#2f80ed; color:#fff; font-size:14px; cursor:pointer; }
	.chips { max-width:1100px; margin:12px auto 0; padding:0 16px; display:flex; gap:8px; overflow-x:auto; }
	.chip { flex:0 0 auto; padding:6px 14px; border-radius:20px; background:#fff; border:1px solid #ddd; font-size:13px; text-decoration:none; white-space:nowrap; }
	.chip.active { background:#2f80ed; border-color:#2f80ed; color:#fff; }
	.grid { max-width:1100px; margin:16px auto; padding:0 16px; display:grid; grid-template-columns:repeat(auto-fill, minmax(160px,1fr)); gap:14px; }
	.card { background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08); display:flex; flex-direction:column; }
	.card-img { width:100%; aspect-ratio:1/1; background:#eef1f4 url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23c4cad1"><path d="M21 19V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>') center/40% no-repeat; }
	.card-img img { width:100%; height:100%; object-fit:cover; display:block; }
	.card-body { padding:10px 12px 12px; display:flex; flex-direction:column; gap:4px; flex:1; }
	.card-cat { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.04em; }
	.card-name { font-size:14px; font-weight:600; line-height:1.3; }
	.card-desc { font-size:12px; color:#666; line-height:1.35; flex:1; }
	.card-price { font-size:16px; font-weight:700; color:#2f80ed; margin-top:4px; }
	.card-meta { font-size:11px; color:#999; }
	.stock-ok { color:#1a9e5c; font-weight:600; }
	.stock-out { color:#d64545; font-weight:600; }
	.pagination { max-width:1100px; margin:20px auto; padding:0 16px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }
	.pagination a, .pagination span { padding:8px 12px; border-radius:6px; background:#fff; border:1px solid #ddd; font-size:13px; text-decoration:none; }
	.pagination .current { background:#2f80ed; border-color:#2f80ed; color:#fff; }
	.empty { max-width:1100px; margin:60px auto; padding:0 16px; text-align:center; color:#888; }
	.cart-link { background:#fff; color:#2f80ed; border-radius:20px; padding:6px 14px; font-size:13px; font-weight:600; text-decoration:none; }
	.flash { max-width:1100px; margin:12px auto 0; padding:0 16px; }
	.flash-box { background:#e6f7ee; color:#1a7a44; border-radius:8px; padding:10px 14px; font-size:13px; }
	.flash-box.error { background:#fdecec; color:#b02a2a; }
	.add-form { display:flex; gap:6px; margin-top:6px; }
	.add-form input[type=number] { width:56px; padding:6px; border:1px solid #ddd; border-radius:6px; font-size:13px; }
	.add-form button { flex:1; padding:6px 8px; border:none; border-radius:6px; background:#2f80ed; color:#fff; font-size:12px; cursor:pointer; }
	.add-form button[disabled] { background:#ccc; cursor:not-allowed; }
</style>
</head>
<body>

<div class="topbar">
	<div class="topbar-inner">
		<h1><?php echo H($location ? $location->name : 'Catálogo'); ?></h1>
		<div style="display:flex; align-items:center; gap:10px;">
			<?php if ($is_staff) { ?>
			<span class="staff-badge">Vista interna (costo y existencia exacta)<?php echo anchor('home', 'Volver al panel'); ?></span>
			<?php } ?>
			<?php echo anchor('catalog/cart', '🛒 Pedido ('.(int)$cart_count.')', array('class' => 'cart-link')); ?>
		</div>
	</div>
</div>

<?php if ($this->session->flashdata('catalog_success')) { ?>
<div class="flash"><div class="flash-box"><?php echo $this->session->flashdata('catalog_success'); ?></div></div>
<?php } ?>
<?php if ($this->session->flashdata('catalog_error')) { ?>
<div class="flash"><div class="flash-box error"><?php echo H($this->session->flashdata('catalog_error')); ?></div></div>
<?php } ?>

<div class="search-wrap">
	<form class="search-form" method="get" action="<?php echo site_url('catalog'); ?>">
		<?php if ($category_id) { ?><input type="hidden" name="category" value="<?php echo (int)$category_id; ?>"><?php } ?>
		<input type="text" name="q" placeholder="Buscar producto..." value="<?php echo H($search); ?>">
		<button type="submit">Buscar</button>
	</form>
</div>

<div class="chips">
	<?php echo anchor('catalog'.($search !== '' ? '?q='.urlencode($search) : ''), 'Todos', array('class' => 'chip'.(!$category_id ? ' active' : ''))); ?>
	<?php foreach ($categories as $cat) {
		$qs = 'category='.(int)$cat->id.($search !== '' ? '&q='.urlencode($search) : '');
	?>
	<?php echo anchor('catalog?'.$qs, H($cat->name).' ('.(int)$cat->item_count.')', array('class' => 'chip'.((int)$category_id === (int)$cat->id ? ' active' : ''))); ?>
	<?php } ?>
</div>

<?php if (!$items) { ?>
<div class="empty">No se encontraron productos.</div>
<?php } else { ?>
<div class="grid">
	<?php foreach ($items as $item) { ?>
	<div class="card">
		<div class="card-img"><?php if ($item->main_image_id) { ?><img src="<?php echo cacheable_app_file_url($item->main_image_id); ?>" alt="<?php echo H($item->name); ?>" loading="lazy"><?php } ?></div>
		<div class="card-body">
			<?php if ($item->category_name) { ?><div class="card-cat"><?php echo H($item->category_name); ?></div><?php } ?>
			<div class="card-name"><?php echo H($item->name); ?></div>
			<?php if ($item->description) { ?><div class="card-desc"><?php echo H(mb_strimwidth(strip_tags($item->description), 0, 90, '...')); ?></div><?php } ?>
			<div class="card-price"><?php echo to_currency($item->unit_price); ?></div>
			<div class="card-meta">
				<?php if ((float)$item->quantity > 0) { ?>
					<span class="stock-ok"><?php echo $is_staff ? 'Disponible: '.to_quantity($item->quantity) : 'Disponible'; ?></span>
				<?php } else { ?>
					<span class="stock-out">Agotado</span>
				<?php } ?>
				<?php if ($item->item_number) { ?> · Cód. <?php echo H($item->item_number); ?><?php } ?>
			</div>
			<?php if ($is_staff) { ?><div class="card-meta">Costo: <?php echo to_currency($item->cost_price); ?></div><?php } ?>
			<?php echo form_open('catalog/add_to_cart/'.(int)$item->item_id, array('class' => 'add-form')); ?>
				<input type="hidden" name="redirect_qs" value="<?php echo H($query_string); ?>">
				<input type="number" name="quantity" value="1" min="1" step="1" <?php echo (float)$item->quantity <= 0 ? 'disabled' : ''; ?>>
				<button type="submit" <?php echo (float)$item->quantity <= 0 ? 'disabled' : ''; ?>>Agregar</button>
			<?php echo form_close(); ?>
		</div>
	</div>
	<?php } ?>
</div>
<?php } ?>

<?php if ($total_pages > 1) { ?>
<div class="pagination">
	<?php for ($p = 1; $p <= $total_pages; $p++) {
		$qs = ($category_id ? 'category='.(int)$category_id.'&' : '').($search !== '' ? 'q='.urlencode($search).'&' : '').'page='.$p;
		if ($p == $page) { ?>
		<span class="current"><?php echo $p; ?></span>
		<?php } else { ?>
		<?php echo anchor('catalog?'.$qs, (string)$p); ?>
		<?php } ?>
	<?php } ?>
</div>
<?php } ?>

</body>
</html>
