<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Tu pedido — Catálogo</title>
<style>
	* { box-sizing: border-box; }
	body { margin:0; font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background:#f4f5f7; color:#222; }
	a { color:#2f80ed; }
	.topbar { background:#2f80ed; color:#fff; padding:14px 16px; }
	.topbar-inner { max-width:700px; margin:0 auto; display:flex; align-items:center; justify-content:space-between; }
	.topbar h1 { font-size:18px; margin:0; }
	.topbar a { color:#fff; text-decoration:underline; font-size:13px; }
	.wrap { max-width:700px; margin:16px auto; padding:0 16px; }
	.empty { text-align:center; color:#888; margin:60px 0; }
	table { width:100%; border-collapse:collapse; background:#fff; border-radius:10px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.08); }
	th, td { padding:10px 12px; text-align:left; font-size:13px; border-bottom:1px solid #eee; }
	th { background:#f7f8fa; font-size:11px; text-transform:uppercase; color:#888; }
	.qty-input { width:56px; padding:5px; border:1px solid #ddd; border-radius:6px; }
	.row-actions { display:flex; gap:6px; }
	.row-actions button { padding:5px 10px; border:none; border-radius:6px; font-size:12px; cursor:pointer; }
	.btn-update { background:#2f80ed; color:#fff; }
	.btn-remove { background:#fdecec; color:#b02a2a; }
	.total-row td { font-weight:700; font-size:15px; border-bottom:none; }
	.checkout { margin-top:20px; background:#fff; border-radius:10px; padding:16px; box-shadow:0 1px 3px rgba(0,0,0,0.08); }
	.checkout h2 { font-size:15px; margin:0 0 12px; }
	.checkout label { display:block; font-size:12px; color:#666; margin-bottom:4px; margin-top:10px; }
	.checkout input, .checkout textarea { width:100%; padding:9px 10px; border:1px solid #ddd; border-radius:8px; font-size:14px; }
	.checkout button { margin-top:16px; width:100%; padding:12px; border:none; border-radius:8px; background:#2f80ed; color:#fff; font-size:15px; font-weight:600; cursor:pointer; }
	.flash-box { background:#fdecec; color:#b02a2a; border-radius:8px; padding:10px 14px; font-size:13px; margin-bottom:14px; }
</style>
</head>
<body>

<div class="topbar">
	<div class="topbar-inner">
		<h1>Tu pedido</h1>
		<?php echo anchor('catalog', '← Seguir viendo productos'); ?>
	</div>
</div>

<div class="wrap">
	<?php if ($this->session->flashdata('catalog_error')) { ?>
	<div class="flash-box"><?php echo H($this->session->flashdata('catalog_error')); ?></div>
	<?php } ?>

	<?php if (!$lines) { ?>
	<div class="empty">Tu pedido está vacío. <?php echo anchor('catalog', 'Ver productos'); ?></div>
	<?php } else { ?>
	<table>
		<thead><tr><th>Producto</th><th>Precio</th><th>Cantidad</th><th>Subtotal</th><th></th></tr></thead>
		<tbody>
		<?php foreach ($lines as $line) { ?>
		<tr>
			<td><?php echo H($line->name); ?></td>
			<td><?php echo to_currency($line->unit_price); ?></td>
			<td>
				<?php echo form_open('catalog/update_cart_item/'.$line->item_id, array('style' => 'display:inline-flex; gap:6px;')); ?>
					<input class="qty-input" type="number" name="quantity" value="<?php echo H($line->quantity); ?>" min="0" step="1">
					<button class="btn-update" type="submit">Actualizar</button>
				<?php echo form_close(); ?>
			</td>
			<td><?php echo to_currency($line->subtotal); ?></td>
			<td>
				<?php echo form_open('catalog/update_cart_item/'.$line->item_id); ?>
					<input type="hidden" name="quantity" value="0">
					<button class="btn-remove" type="submit">Quitar</button>
				<?php echo form_close(); ?>
			</td>
		</tr>
		<?php } ?>
		<tr class="total-row"><td colspan="3">Total</td><td colspan="2"><?php echo to_currency($total); ?></td></tr>
		</tbody>
	</table>

	<div class="checkout">
		<h2>Enviar pedido</h2>
		<?php echo form_open('catalog/checkout'); ?>
			<label>Nombre</label>
			<input type="text" name="customer_name" required>
			<label>Teléfono</label>
			<input type="text" name="customer_phone" required>
			<label>Comentarios (opcional)</label>
			<textarea name="notes" rows="2"></textarea>
			<button type="submit">Enviar pedido</button>
		<?php echo form_close(); ?>
	</div>
	<?php } ?>
</div>

</body>
</html>
