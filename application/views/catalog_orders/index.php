<?php $this->load->view('partial/header'); ?>

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title">Pedidos del catálogo</h3></div>
	<div class="panel-body">
		<p>
			<?php echo anchor('catalog_orders?status=pending', 'Pendientes', array('class' => 'btn btn-sm '.($status === 'pending' ? 'btn-primary' : 'btn-default'))); ?>
			<?php echo anchor('catalog_orders?status=completed', 'Completados', array('class' => 'btn btn-sm '.($status === 'completed' ? 'btn-primary' : 'btn-default'))); ?>
			<?php echo anchor('catalog_orders?status=cancelled', 'Cancelados', array('class' => 'btn btn-sm '.($status === 'cancelled' ? 'btn-primary' : 'btn-default'))); ?>
			<?php echo anchor('catalog_orders?status=all', 'Todos', array('class' => 'btn btn-sm '.($status === 'all' ? 'btn-primary' : 'btn-default'))); ?>
			<?php echo anchor('catalog', 'Ver catálogo público', array('class' => 'btn btn-sm btn-default pull-right', 'target' => '_blank')); ?>
		</p>
		<div class="table-responsive">
		<table class="table table-striped table-bordered">
			<thead><tr><th>#</th><th>Fecha</th><th>Cliente</th><th>Teléfono</th><th class="text-right">Total</th><th>Estado</th><th>Acciones</th></tr></thead>
			<tbody>
			<?php foreach ($orders as $order) { ?>
			<tr>
				<td><?php echo (int)$order->catalog_order_id; ?></td>
				<td><?php echo H(date(get_date_format().' '.get_time_format(), strtotime($order->created_at))); ?></td>
				<td><?php echo H($order->customer_name); ?></td>
				<td><?php echo H($order->customer_phone); ?></td>
				<td class="text-right"><?php echo to_currency($order->total); ?></td>
				<td>
					<?php
					$labels = array('pending' => 'label-warning', 'completed' => 'label-success', 'cancelled' => 'label-default');
					$names = array('pending' => 'Pendiente', 'completed' => 'Completado', 'cancelled' => 'Cancelado');
					?>
					<span class="label <?php echo $labels[$order->status]; ?>"><?php echo $names[$order->status]; ?></span>
				</td>
				<td><?php echo anchor('catalog_orders/view/'.$order->catalog_order_id, 'Ver', array('class' => 'btn btn-primary btn-sm')); ?></td>
			</tr>
			<?php } ?>
			<?php if (!$orders) { ?><tr><td colspan="7" class="text-center">No hay pedidos en este estado.</td></tr><?php } ?>
			</tbody>
		</table>
		</div>
	</div>
</div>

<?php $this->load->view('partial/footer'); ?>
