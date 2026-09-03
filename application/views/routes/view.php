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

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title"><?php echo lang('routes_cash_control'); ?></h3></div>
	<div class="panel-body">
		<div class="table-responsive">
		<table class="table table-bordered" style="max-width:520px">
			<tbody>
				<tr><td><?php echo lang('routes_cash_opening'); ?> / agregado</td><td class="text-right"><strong><?php echo to_currency($reconciliation->fund); ?></strong></td></tr>
				<tr><td>+ Ventas en efectivo</td><td class="text-right"><?php echo to_currency($reconciliation->cash_sales); ?></td></tr>
				<tr><td>+ Abonos a créditos cobrados</td><td class="text-right"><?php echo to_currency($reconciliation->credit_collected); ?></td></tr>
				<tr><td>− Compras pagadas en efectivo</td><td class="text-right"><?php echo to_currency($reconciliation->cash_purchases); ?></td></tr>
				<tr><td>− Gastos de la ruta</td><td class="text-right"><?php echo to_currency($reconciliation->expenses); ?></td></tr>
				<tr class="active"><td><strong><?php echo lang('routes_expected_cash'); ?></strong></td><td class="text-right"><strong><?php echo to_currency($reconciliation->expected); ?></strong></td></tr>
				<?php if ($route->status !== 'open') { ?>
				<tr><td><?php echo lang('routes_counted_cash'); ?></td><td class="text-right"><?php echo $route->counted_cash !== NULL ? to_currency($route->counted_cash) : '-'; ?></td></tr>
				<tr><td><?php echo lang('routes_cash_difference'); ?></td><td class="text-right"><?php
					if ($route->cash_difference === NULL) { echo '-'; }
					else {
						$diff = (float)$route->cash_difference;
						$label = $diff < 0 ? lang('routes_cash_shortage') : ($diff > 0 ? lang('routes_cash_overage') : '');
						echo '<span class="'.($diff < 0 ? 'text-danger' : ($diff > 0 ? 'text-warning' : '')).'"><strong>'.to_currency($diff).'</strong>'.($label ? ' ('.$label.')' : '').'</span>';
					}
				?></td></tr>
				<?php if ($route->cash_note) { ?><tr><td><?php echo lang('routes_cash_note'); ?></td><td><?php echo nl2br(H($route->cash_note)); ?></td></tr><?php } ?>
				<?php } ?>
			</tbody>
		</table>
		</div>

		<?php if ($route->status === 'open') { ?>
		<div class="row">
			<div class="col-sm-6">
				<h4><?php echo lang('routes_cash_add'); ?></h4>
				<?php echo form_open('routes/add_cash/'.$route->route_id, array('class'=>'form-inline')); ?>
					<input class="form-control" style="width:120px" type="number" min="0.0000000001" step="any" name="amount" placeholder="<?php echo lang('common_amount'); ?>" required>
					<input class="form-control" type="text" name="notes" placeholder="<?php echo lang('common_comments'); ?>">
					<button class="btn btn-default" type="submit"><?php echo lang('common_add'); ?></button>
				<?php echo form_close(); ?>
			</div>
			<div class="col-sm-6">
				<h4><?php echo lang('routes_expense_add'); ?></h4>
				<?php echo form_open('routes/add_expense/'.$route->route_id, array('class'=>'form-inline')); ?>
					<input class="form-control" style="width:120px" type="number" min="0.0000000001" step="any" name="amount" placeholder="<?php echo lang('common_amount'); ?>" required>
					<input class="form-control" type="text" name="description" placeholder="<?php echo lang('common_description'); ?>" required>
					<button class="btn btn-default" type="submit"><?php echo lang('common_add'); ?></button>
				<?php echo form_close(); ?>
			</div>
		</div>
		<?php } ?>

		<h4 style="margin-top:20px"><?php echo lang('routes_expenses_title'); ?></h4>
		<div class="table-responsive">
		<table class="table table-striped table-bordered">
			<thead><tr><th><?php echo lang('common_date'); ?></th><th><?php echo lang('common_description'); ?></th><th><?php echo lang('routes_seller'); ?></th><th class="text-right"><?php echo lang('common_amount'); ?></th></tr></thead>
			<tbody>
			<?php foreach ($route_expenses as $expense) { ?>
			<tr>
				<td><?php echo H(date(get_date_format().' '.get_time_format(), strtotime($expense->occurred_at))); ?></td>
				<td><?php echo H($expense->description); ?></td>
				<td><?php echo H(trim($expense->first_name.' '.$expense->last_name)); ?></td>
				<td class="text-right"><?php echo to_currency($expense->amount); ?></td>
			</tr>
			<?php } ?>
			<?php if (!$route_expenses) { ?><tr><td colspan="4" class="text-center"><?php echo lang('routes_no_expenses'); ?></td></tr><?php } ?>
			</tbody>
		</table>
		</div>
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

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title"><?php echo lang('routes_damage_title'); ?></h3></div>
	<div class="panel-body">
		<?php echo form_open('routes/classify_damage/'.$route->route_id, array('class'=>'form-horizontal')); ?>
			<div class="form-group">
				<?php echo form_label(lang('routes_damage_lot').':', 'route_inventory_lot_id', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><select class="form-control" name="route_inventory_lot_id" required>
					<option value=""><?php echo lang('common_select'); ?></option>
					<?php foreach ($route_inventory as $lot) { if ($lot->condition_type !== 'good' || (float)$lot->quantity_remaining <= 0) continue; ?>
					<option value="<?php echo (int)$lot->route_inventory_lot_id; ?>"><?php echo H($lot->item_name.($lot->variation_name ? ' — '.$lot->variation_name : '').' | '.$lot->lot_code.' | '.lang('routes_remaining').': '.to_quantity($lot->quantity_remaining)); ?></option>
					<?php } ?>
				</select></div>
			</div>
			<div class="form-group">
				<?php echo form_label(lang('common_quantity').':', 'quantity', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><input class="form-control" type="number" min="0.0000000001" step="any" name="quantity" required></div>
			</div>
			<div class="form-group">
				<?php echo form_label(lang('routes_damage_classification').':', 'classification', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><select class="form-control" name="classification" id="damage_classification" required>
					<option value="broken"><?php echo lang('routes_damage_broken'); ?></option>
					<option value="loss"><?php echo lang('routes_damage_loss'); ?></option>
				</select></div>
			</div>
			<div class="form-group" id="damage_price_group">
				<?php echo form_label(lang('routes_damage_price').':', 'unit_price', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><input class="form-control" type="number" min="0" step="any" name="unit_price" placeholder="<?php echo lang('common_unit_price'); ?>"></div>
			</div>
			<div class="form-group">
				<?php echo form_label(lang('common_comments').':', 'notes', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><input class="form-control" type="text" name="notes"></div>
			</div>
			<div class="text-right"><button class="btn btn-warning" type="submit"><?php echo lang('routes_damage_submit'); ?></button></div>
		<?php echo form_close(); ?>
		<script>
		(function () {
			var sel = document.getElementById('damage_classification');
			var priceGroup = document.getElementById('damage_price_group');
			function sync() { priceGroup.style.display = sel.value === 'broken' ? '' : 'none'; }
			sel.addEventListener('change', sync); sync();
		})();
		</script>
	</div>
</div>
<?php } ?>

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title"><?php echo lang('routes_inventory'); ?></h3></div>
	<div class="panel-body table-responsive">
		<?php if ($damage_summary->broken > 0 || $damage_summary->loss > 0) { ?>
		<p>
			<?php if ($damage_summary->broken > 0) { ?><span class="label label-warning"><?php echo lang('routes_damage_broken_total').': '.to_quantity($damage_summary->broken); ?></span> <?php } ?>
			<?php if ($damage_summary->loss > 0) { ?><span class="label label-danger"><?php echo lang('routes_damage_loss_total').': '.to_quantity($damage_summary->loss); ?></span><?php } ?>
		</p>
		<?php } ?>
		<table class="table table-striped table-bordered">
			<thead><tr><th><?php echo lang('common_item'); ?></th><th><?php echo lang('items_lot_code'); ?></th><th><?php echo lang('routes_condition'); ?></th><th><?php echo lang('routes_loaded'); ?></th><th><?php echo lang('routes_remaining'); ?></th><th><?php echo lang('common_cost_price'); ?></th><th><?php echo lang('common_unit_price'); ?></th><th><?php echo lang('items_expire_date'); ?></th><?php if ($route->status === 'open') { ?><th><?php echo lang('common_actions'); ?></th><?php } ?></tr></thead>
			<tbody>
			<?php foreach ($route_inventory as $lot) { ?><tr>
				<td><?php echo H($lot->item_name.($lot->variation_name ? ' — '.$lot->variation_name : '')); ?></td>
				<td><?php echo H($lot->lot_code); ?></td>
				<td><?php
					if ($lot->condition_type === 'good') { echo lang('routes_condition_good'); }
					elseif ($lot->condition_type === 'broken') { echo '<span class="text-warning">'.lang('routes_condition_broken').'</span>'; }
					else { echo H($lot->condition_type); }
				?></td>
				<td><?php echo to_quantity($lot->quantity_loaded); ?></td>
				<td><strong><?php echo to_quantity($lot->quantity_remaining); ?></strong></td>
				<td><?php echo to_currency($lot->unit_cost); ?></td>
				<td><?php echo $lot->unit_price !== NULL ? to_currency($lot->unit_price) : '-'; ?></td>
				<td><?php echo H($lot->expire_date ? $lot->expire_date : '-'); ?></td>
				<?php if ($route->status === 'open') { ?><td>
					<?php if ((float)$lot->quantity_remaining > 0 && $lot->condition_type === 'good') { ?>
					<?php echo form_open('routes/return_lot/'.$route->route_id, array('class'=>'form-inline')); ?>
						<input type="hidden" name="route_inventory_lot_id" value="<?php echo (int)$lot->route_inventory_lot_id; ?>">
						<input class="form-control input-sm" style="width:90px" type="number" min="0.0000000001" max="<?php echo H($lot->quantity_remaining); ?>" step="any" name="quantity" placeholder="<?php echo lang('common_quantity'); ?>" required>
						<button class="btn btn-warning btn-sm" type="submit"><?php echo lang('routes_return'); ?></button>
					<?php echo form_close(); ?>
					<?php } elseif ((float)$lot->quantity_remaining > 0 && $lot->condition_type === 'broken') { ?>
					<span class="text-muted"><?php echo lang('routes_condition_broken'); ?></span>
					<?php } ?>
				</td><?php } ?>
			</tr><?php } ?>
			<?php if (!$route_inventory) { ?><tr><td colspan="<?php echo $route->status === 'open' ? '9' : '8'; ?>" class="text-center"><?php echo lang('routes_no_inventory'); ?></td></tr><?php } ?>
			</tbody>
		</table>
		<p>
			<?php echo anchor('routes', lang('common_back'), array('class'=>'btn btn-primary')); ?>
			<?php if ($route->status === 'open') { ?>
				<?php if ($route_inventory) { ?>
				<?php echo form_open('routes/return_all/'.$route->route_id, array('style'=>'display:inline-block; margin-left:5px', 'onsubmit'=>"return confirm('".lang('routes_return_all')." ?');")); ?><button class="btn btn-warning" type="submit"><?php echo lang('routes_return_all'); ?></button><?php echo form_close(); ?>
				<?php } ?>
				<?php echo anchor('routes/close_form/'.$route->route_id, lang('routes_close'), array('class'=>'btn btn-danger', 'style'=>'margin-left:5px')); ?>
			<?php } ?>
		</p>
	</div>
</div>

<?php $this->load->view('partial/footer'); ?>
