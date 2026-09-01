<?php $this->load->view('partial/header'); ?>

<div class="panel panel-piluku">
	<div class="panel-heading">
		<h3 class="panel-title"><?php echo lang('items_lot_details').' — '.H($lot->lot_code); ?></h3>
	</div>
	<div class="panel-body">
		<div class="row margin-bottom-20">
			<div class="col-sm-4"><strong><?php echo lang('common_item'); ?>:</strong> <?php echo H($lot->item_name); ?></div>
			<div class="col-sm-4"><strong><?php echo lang('common_location'); ?>:</strong> <?php echo H($lot->location_name); ?></div>
			<div class="col-sm-4"><strong><?php echo lang('common_supplier'); ?>:</strong> <?php echo H($lot->supplier_name ? $lot->supplier_name : '-'); ?></div>
			<div class="col-sm-4"><strong><?php echo lang('items_lot_initial_quantity'); ?>:</strong> <?php echo to_quantity($lot->quantity_initial); ?></div>
			<div class="col-sm-4"><strong><?php echo lang('items_lot_remaining_quantity'); ?>:</strong> <?php echo to_quantity($lot->quantity_remaining); ?></div>
			<div class="col-sm-4"><strong><?php echo lang('common_status'); ?>:</strong> <?php echo H($lot->status); ?></div>
		</div>

		<div class="table-responsive">
			<table class="table table-bordered table-striped">
				<thead><tr>
					<th><?php echo lang('common_date'); ?></th>
					<th><?php echo lang('items_lot_movement_type'); ?></th>
					<th><?php echo lang('common_quantity'); ?></th>
					<th><?php echo lang('items_lot_balance_after'); ?></th>
					<th><?php echo lang('common_employee'); ?></th>
					<th><?php echo lang('items_lot_reference'); ?></th>
					<th><?php echo lang('common_comments'); ?></th>
				</tr></thead>
				<tbody>
				<?php foreach ($movements as $movement) { ?>
				<tr>
					<td><?php echo H($movement->occurred_at); ?></td>
					<td><?php echo H($movement->movement_type); ?></td>
					<td><?php echo to_quantity($movement->quantity_delta); ?></td>
					<td><?php echo to_quantity($movement->balance_after); ?></td>
					<td><?php echo H(trim($movement->first_name.' '.$movement->last_name)); ?></td>
					<td><?php echo H($movement->reference_type.' '.$movement->reference_id); ?></td>
					<td><?php echo H($movement->notes); ?></td>
				</tr>
				<?php } ?>
				</tbody>
			</table>
		</div>
		<p><?php echo anchor('items/inventory/'.$lot->item_id, lang('common_back'), array('class'=>'btn btn-primary')); ?></p>
	</div>
</div>

<?php $this->load->view('partial/footer'); ?>
