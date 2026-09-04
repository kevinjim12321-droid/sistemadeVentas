<?php $this->load->view('partial/header'); ?>

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title">Pedido del catálogo #<?php echo (int)$order->catalog_order_id; ?></h3></div>
	<div class="panel-body">
		<?php if ($this->session->flashdata('catalog_order_success')) { ?><div class="alert alert-success"><?php echo H($this->session->flashdata('catalog_order_success')); ?></div><?php } ?>
		<p>
			<strong>Cliente:</strong> <?php echo H($order->customer_name); ?> &nbsp;
			<strong>Teléfono:</strong> <?php echo H($order->customer_phone); ?> &nbsp;
			<strong>Fecha:</strong> <?php echo H(date(get_date_format().' '.get_time_format(), strtotime($order->created_at))); ?>
		</p>
		<?php if ($order->notes) { ?><p><strong>Comentarios:</strong> <?php echo nl2br(H($order->notes)); ?></p><?php } ?>

		<div class="table-responsive">
		<table class="table table-striped table-bordered">
			<thead><tr><th>Producto</th><th class="text-right">Precio</th><th class="text-right">Cantidad</th><th class="text-right">Subtotal</th></tr></thead>
			<tbody>
			<?php foreach ($items as $item) { ?>
			<tr>
				<td><?php echo H($item->item_name); ?></td>
				<td class="text-right"><?php echo to_currency($item->unit_price); ?></td>
				<td class="text-right"><?php echo to_quantity($item->quantity); ?></td>
				<td class="text-right"><?php echo to_currency($item->subtotal); ?></td>
			</tr>
			<?php } ?>
			<tr><td colspan="3" class="text-right"><strong>Total</strong></td><td class="text-right"><strong><?php echo to_currency($order->total); ?></strong></td></tr>
			</tbody>
		</table>
		</div>

		<p>
			<?php echo anchor('catalog_orders', '← Volver a pedidos', array('class' => 'btn btn-default')); ?>

			<?php if ($order->status === 'pending') { ?>
				<?php echo anchor('catalog_orders/load_into_pos/'.$order->catalog_order_id, 'Cargar en Ventas', array('class' => 'btn btn-primary')); ?>
				<?php echo form_open('catalog_orders/mark_status/'.$order->catalog_order_id, array('style' => 'display:inline-block')); ?>
					<input type="hidden" name="status" value="completed">
					<button class="btn btn-success" type="submit">Marcar completado</button>
				<?php echo form_close(); ?>
				<?php echo form_open('catalog_orders/mark_status/'.$order->catalog_order_id, array('style' => 'display:inline-block')); ?>
					<input type="hidden" name="status" value="cancelled">
					<button class="btn btn-danger" type="submit">Cancelar</button>
				<?php echo form_close(); ?>
			<?php } else { ?>
				<?php echo form_open('catalog_orders/mark_status/'.$order->catalog_order_id, array('style' => 'display:inline-block')); ?>
					<input type="hidden" name="status" value="pending">
					<button class="btn btn-default" type="submit">Volver a marcar pendiente</button>
				<?php echo form_close(); ?>
			<?php } ?>
		</p>
	</div>
</div>

<?php $this->load->view('partial/footer'); ?>
