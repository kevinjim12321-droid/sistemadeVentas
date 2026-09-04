<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Pedido recibido — Catálogo</title>
<style>
	* { box-sizing: border-box; }
	body { margin:0; font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background:#f4f5f7; color:#222; }
	a { color:#2f80ed; }
	.topbar { background:#1a9e5c; color:#fff; padding:14px 16px; }
	.topbar-inner { max-width:700px; margin:0 auto; }
	.topbar h1 { font-size:18px; margin:0; }
	.wrap { max-width:700px; margin:16px auto; padding:0 16px; }
	.card { background:#fff; border-radius:10px; padding:20px; box-shadow:0 1px 3px rgba(0,0,0,0.08); }
	table { width:100%; border-collapse:collapse; margin-top:14px; }
	th, td { padding:8px 6px; text-align:left; font-size:13px; border-bottom:1px solid #eee; }
	th { font-size:11px; text-transform:uppercase; color:#888; }
	.total-row td { font-weight:700; border-bottom:none; }
	.back { display:inline-block; margin-top:20px; }
</style>
</head>
<body>

<div class="topbar"><div class="topbar-inner"><h1>✅ Pedido recibido</h1></div></div>

<div class="wrap">
	<div class="card">
		<p>Gracias, <strong><?php echo H($order->customer_name); ?></strong>. Tu pedido <strong>#<?php echo (int)$order->catalog_order_id; ?></strong> quedó registrado y nos pondremos en contacto al <strong><?php echo H($order->customer_phone); ?></strong>.</p>
		<?php if ($order->notes) { ?><p><em><?php echo H($order->notes); ?></em></p><?php } ?>
		<table>
			<thead><tr><th>Producto</th><th>Cantidad</th><th>Subtotal</th></tr></thead>
			<tbody>
			<?php foreach ($items as $item) { ?>
			<tr>
				<td><?php echo H($item->item_name); ?></td>
				<td><?php echo to_quantity($item->quantity); ?></td>
				<td><?php echo to_currency($item->subtotal); ?></td>
			</tr>
			<?php } ?>
			<tr class="total-row"><td colspan="2">Total</td><td><?php echo to_currency($order->total); ?></td></tr>
			</tbody>
		</table>
	</div>
	<?php echo anchor('catalog', '← Volver al catálogo', array('class' => 'back')); ?>
</div>

</body>
</html>
