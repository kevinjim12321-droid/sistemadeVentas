<?php $this->load->view('partial/header'); ?>

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title"><?php echo H($route->name); ?> — <?php echo H($route->route_date); ?></h3></div>
	<div class="panel-body">
		<p><strong><?php echo lang('routes_seller'); ?>:</strong> <?php echo H(trim($route->first_name.' '.$route->last_name)); ?> &nbsp; <strong><?php echo lang('common_status'); ?>:</strong> <?php echo $route->status === 'open' ? lang('routes_status_open') : lang('routes_status_closed'); ?></p>
		<p><strong>Ventas realizadas:</strong> <?php echo (int)$sales_summary->sale_count; ?> &nbsp; <strong>Total vendido:</strong> <?php echo to_currency($sales_summary->total); ?></p>
		<?php if ($route->status === 'open') { ?><p><?php echo anchor('routes/sell/'.$route->route_id, '<span class="ion-cash"></span> Vender desde esta ruta', array('class'=>'btn btn-success')); ?> <?php echo anchor('routes/purchase/'.$route->route_id, '<span class="ion-plus"></span> Comprar para esta ruta', array('class'=>'btn btn-primary')); ?></p><?php } ?>
		<?php if ($route->notes) { ?><p><strong><?php echo lang('common_comments'); ?>:</strong> <?php echo nl2br(H($route->notes)); ?></p><?php } ?>
		<?php if ($this->session->flashdata('route_success')) { ?><div class="alert alert-success"><?php echo H($this->session->flashdata('route_success')); ?></div><?php } ?>
		<?php if ($this->session->flashdata('route_error')) { ?><div class="alert alert-danger"><?php echo H($this->session->flashdata('route_error')); ?></div><?php } ?>
	</div>
</div>

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title">Historial de ventas de la ruta</h3></div>
	<div class="panel-body">
		<div class="row" style="margin-bottom:15px">
			<div class="col-sm-3"><strong>Total vendido</strong><br><?php echo to_currency($sales_summary->total); ?></div>
			<div class="col-sm-3"><strong>Efectivo</strong><br><?php echo to_currency($payment_summary['cash']); ?></div>
			<div class="col-sm-3"><strong>Crédito otorgado</strong><br><?php echo to_currency($payment_summary['credit']); ?></div>
			<div class="col-sm-3"><strong>Crédito pendiente</strong><br><?php echo to_currency($payment_summary['credit_pending']); ?></div>
		</div>
		<div class="table-responsive">
		<table class="table table-striped table-bordered">
			<thead><tr><th>Recibo</th><th>Fecha y hora</th><th>Cliente</th><th>Vendedor</th><th>Forma de pago</th><th>Total</th><th>Saldo pendiente</th><th>Acciones</th></tr></thead>
			<tbody>
			<?php foreach ($route_sales as $sale) { ?>
			<tr>
				<td><?php echo H(($this->config->item('sale_prefix') ? $this->config->item('sale_prefix') : 'POS').' '.$sale->sale_id); ?></td>
				<td><?php echo H(date(get_date_format().' '.get_time_format(), strtotime($sale->sale_time))); ?></td>
				<td><?php $customer_name = trim($sale->customer_first_name.' '.$sale->customer_last_name); echo H($customer_name !== '' ? $customer_name : 'Consumidor final'); ?></td>
				<td><?php echo H(trim($sale->employee_first_name.' '.$sale->employee_last_name)); ?></td>
				<td><?php $payment_labels = array(); foreach ($sale->payments as $payment) $payment_labels[] = $payment->payment_type.' '.to_currency($payment->payment_amount); echo H(implode(' / ', $payment_labels)); ?></td>
				<td><strong><?php echo to_currency($sale->total); ?></strong></td>
				<td><?php echo $sale->credit_pending > 0 ? '<span class="text-danger"><strong>'.to_currency($sale->credit_pending).'</strong></span>' : to_currency(0); ?></td>
				<td>
					<?php echo anchor('sales/receipt/'.$sale->sale_id, 'Abrir recibo', array('class'=>'btn btn-primary btn-sm', 'target'=>'_blank')); ?>
					<?php if ($sale->invoice_id) { echo anchor('invoices/show/customer/'.$sale->invoice_id, 'Abrir factura', array('class'=>'btn btn-success btn-sm', 'target'=>'_blank')); } ?>
					<?php if ($sale->customer_id) { echo anchor('customers/view/'.$sale->customer_id, 'Ver cliente/deuda', array('class'=>'btn btn-default btn-sm', 'target'=>'_blank')); } ?>
				</td>
			</tr>
			<?php } ?>
			<?php if (!$route_sales) { ?><tr><td colspan="8" class="text-center">Esta ruta todavía no tiene ventas registradas.</td></tr><?php } ?>
			</tbody>
		</table>
		</div>
		<p class="text-muted">Los recibos de ruta utilizan el mismo historial de ventas, clientes y línea de crédito del sistema principal.</p>
	</div>
</div>

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title">Compras realizadas para la ruta</h3></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-bordered">
			<thead><tr><th>Recibo</th><th>Fecha y hora</th><th>Proveedor</th><th>Forma de pago</th><th>Total comprado</th><th>Acciones</th></tr></thead>
			<tbody>
			<?php foreach ($route_purchases as $purchase) { ?>
			<tr>
				<td>RECV <?php echo (int)$purchase->receiving_id; ?></td>
				<td><?php echo H(date(get_date_format().' '.get_time_format(), strtotime($purchase->receiving_time))); ?></td>
				<td><?php $supplier_name = trim($purchase->company_name ? $purchase->company_name : ($purchase->first_name.' '.$purchase->last_name)); echo H($supplier_name !== '' ? $supplier_name : 'Sin proveedor'); ?></td>
				<td><?php $purchase_payment_labels = array(); foreach ($purchase->payments as $payment) $purchase_payment_labels[] = $payment->payment_type.' '.to_currency($payment->payment_amount); echo H(implode(' / ', $purchase_payment_labels)); ?></td>
				<td><strong><?php echo to_currency($purchase->total); ?></strong></td>
				<td><?php echo anchor('receivings/receipt/'.$purchase->receiving_id, 'Abrir recibo', array('class'=>'btn btn-primary btn-sm', 'target'=>'_blank')); ?></td>
			</tr>
			<?php } ?>
			<?php if (!$route_purchases) { ?><tr><td colspan="6" class="text-center">Esta ruta todavía no tiene compras directas.</td></tr><?php } ?>
			</tbody>
		</table>
	</div>
</div>

<?php if ($route->status === 'open') { ?>
<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title"><?php echo lang('routes_load_inventory'); ?></h3></div>
	<div class="panel-body">
		<?php echo form_open('routes/load_lot/'.$route->route_id, array('class'=>'form-horizontal')); ?>
			<div class="form-group">
				<?php echo form_label(lang('routes_source_lot').':', 'source_lot_id', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><select class="form-control" name="source_lot_id" id="source_lot_id" required>
					<option value=""><?php echo lang('common_select'); ?></option>
					<?php foreach ($warehouse_lots as $lot) { ?><option value="<?php echo (int)$lot->lot_id; ?>"><?php echo H($lot->item_name.($lot->variation_name ? ' — '.$lot->variation_name : '').' | '.$lot->lot_code.' | '.lang('routes_available').': '.to_quantity($lot->quantity_remaining)); ?></option><?php } ?>
				</select></div>
			</div>
			<div class="form-group">
				<?php echo form_label(lang('common_quantity').':', 'quantity', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><input class="form-control" type="number" min="0.0000000001" step="any" name="quantity" id="quantity" required></div>
			</div>
			<div class="form-group">
				<?php echo form_label(lang('common_comments').':', 'notes', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><input class="form-control" type="text" name="notes" id="notes"></div>
			</div>
			<div class="text-right"><button class="btn btn-success" type="submit"><?php echo lang('routes_load'); ?></button></div>
		<?php echo form_close(); ?>
	</div>
</div>
<?php } ?>

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title"><?php echo lang('routes_inventory'); ?></h3></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-bordered">
			<thead><tr><th><?php echo lang('common_item'); ?></th><th><?php echo lang('items_lot_code'); ?></th><th><?php echo lang('routes_condition'); ?></th><th><?php echo lang('routes_loaded'); ?></th><th><?php echo lang('routes_remaining'); ?></th><th><?php echo lang('common_cost_price'); ?></th><th><?php echo lang('common_unit_price'); ?></th><th><?php echo lang('items_expire_date'); ?></th><?php if ($route->status === 'open') { ?><th><?php echo lang('common_actions'); ?></th><?php } ?></tr></thead>
			<tbody>
			<?php foreach ($route_inventory as $lot) { ?><tr>
				<td><?php echo H($lot->item_name.($lot->variation_name ? ' — '.$lot->variation_name : '')); ?></td>
				<td><?php echo H($lot->lot_code); ?></td>
				<td><?php echo $lot->condition_type === 'good' ? lang('routes_condition_good') : H($lot->condition_type); ?></td>
				<td><?php echo to_quantity($lot->quantity_loaded); ?></td>
				<td><strong><?php echo to_quantity($lot->quantity_remaining); ?></strong></td>
				<td><?php echo to_currency($lot->unit_cost); ?></td>
				<td><?php echo $lot->unit_price !== NULL ? to_currency($lot->unit_price) : '-'; ?></td>
				<td><?php echo H($lot->expire_date ? $lot->expire_date : '-'); ?></td>
				<?php if ($route->status === 'open') { ?><td>
					<?php if ((float)$lot->quantity_remaining > 0) { ?>
					<?php echo form_open('routes/return_lot/'.$route->route_id, array('class'=>'form-inline')); ?>
						<input type="hidden" name="route_inventory_lot_id" value="<?php echo (int)$lot->route_inventory_lot_id; ?>">
						<input class="form-control input-sm" style="width:90px" type="number" min="0.0000000001" max="<?php echo H($lot->quantity_remaining); ?>" step="any" name="quantity" placeholder="<?php echo lang('common_quantity'); ?>" required>
						<button class="btn btn-warning btn-sm" type="submit"><?php echo lang('routes_return'); ?></button>
					<?php echo form_close(); ?>
					<?php } ?>
				</td><?php } ?>
			</tr><?php } ?>
			<?php if (!$route_inventory) { ?><tr><td colspan="<?php echo $route->status === 'open' ? '9' : '8'; ?>" class="text-center"><?php echo lang('routes_no_inventory'); ?></td></tr><?php } ?>
			</tbody>
		</table>
		<p>
			<?php echo anchor('routes', lang('common_back'), array('class'=>'btn btn-primary')); ?>
			<?php if ($route->status === 'open') { ?><?php echo form_open('routes/close/'.$route->route_id, array('style'=>'display:inline-block; margin-left:5px')); ?><button class="btn btn-danger" type="submit"><?php echo lang('routes_close'); ?></button><?php echo form_close(); ?><?php } ?>
		</p>
	</div>
</div>

<?php $this->load->view('partial/footer'); ?>
