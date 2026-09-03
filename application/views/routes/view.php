<?php $this->load->view('partial/header'); ?>

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title"><?php echo H($route->name); ?> — <?php echo H($route->route_date); ?></h3></div>
	<div class="panel-body">
		<p><strong><?php echo lang('routes_seller'); ?>:</strong> <?php echo H(trim($route->first_name.' '.$route->last_name)); ?> &nbsp; <strong><?php echo lang('common_status'); ?>:</strong> <?php echo $route->status === 'open' ? lang('routes_status_open') : lang('routes_status_closed'); ?></p>
		<p><strong>Ventas realizadas:</strong> <?php echo (int)$sales_summary->sale_count; ?> &nbsp; <strong>Total vendido:</strong> <?php echo to_currency($sales_summary->total); ?></p>
		<?php if ($route->status === 'open') { ?><p><?php echo anchor('routes/sell/'.$route->route_id, '<span class="ion-cash"></span> Vender desde esta ruta', array('class'=>'btn btn-success')); ?></p><?php } ?>
		<?php if ($route->notes) { ?><p><strong><?php echo lang('common_comments'); ?>:</strong> <?php echo nl2br(H($route->notes)); ?></p><?php } ?>
		<?php if ($this->session->flashdata('route_success')) { ?><div class="alert alert-success"><?php echo H($this->session->flashdata('route_success')); ?></div><?php } ?>
		<?php if ($this->session->flashdata('route_error')) { ?><div class="alert alert-danger"><?php echo H($this->session->flashdata('route_error')); ?></div><?php } ?>
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
