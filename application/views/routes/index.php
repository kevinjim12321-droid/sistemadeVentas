<?php $this->load->view('partial/header'); ?>

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title"><?php echo lang('routes_title'); ?></h3></div>
	<div class="panel-body">
		<?php if ($this->session->flashdata('route_error')) { ?><div class="alert alert-danger"><?php echo H($this->session->flashdata('route_error')); ?></div><?php } ?>
		<p><?php echo lang('routes_intro'); ?></p>
		<?php echo form_open('routes/create', array('class' => 'form-horizontal')); ?>
			<div class="form-group">
				<?php echo form_label(lang('routes_name').':', 'name', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><input class="form-control" type="text" name="name" id="name" placeholder="Ruta 1" required></div>
			</div>
			<div class="form-group">
				<?php echo form_label(lang('common_date').':', 'route_date', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><input class="form-control" type="date" name="route_date" id="route_date" value="<?php echo date('Y-m-d'); ?>" required></div>
			</div>
			<div class="form-group">
				<?php echo form_label(lang('routes_seller').':', 'employee_id', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><select class="form-control" name="employee_id" id="employee_id" required>
					<option value=""><?php echo lang('routes_select_seller'); ?></option>
					<?php foreach ($employees as $employee) { ?><option value="<?php echo (int)$employee->person_id; ?>"><?php echo H($employee->first_name.' '.$employee->last_name); ?></option><?php } ?>
				</select></div>
			</div>
			<div class="form-group">
				<?php echo form_label(lang('routes_cash_opening_field').':', 'opening_cash', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><input class="form-control" type="number" min="0" step="any" name="opening_cash" id="opening_cash" placeholder="0.00"></div>
			</div>
			<div class="form-group">
				<?php echo form_label(lang('common_comments').':', 'notes', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><textarea class="form-control" name="notes" id="notes" rows="2"></textarea></div>
			</div>
			<div class="text-right"><button class="btn btn-primary" type="submit"><?php echo lang('routes_open'); ?></button></div>
		<?php echo form_close(); ?>
	</div>
</div>

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title"><?php echo lang('routes_history'); ?></h3></div>
	<div class="panel-body table-responsive">
		<table class="table table-striped table-bordered">
			<thead><tr><th>#</th><th><?php echo lang('routes_name'); ?></th><th><?php echo lang('common_date'); ?></th><th><?php echo lang('routes_seller'); ?></th><th><?php echo lang('common_status'); ?></th><th><?php echo lang('common_actions'); ?></th></tr></thead>
			<tbody>
			<?php foreach ($routes as $route) { ?><tr>
				<td><?php echo (int)$route->route_id; ?></td>
				<td><?php echo H($route->name); ?></td>
				<td><?php echo H($route->route_date); ?></td>
				<td><?php echo H(trim($route->first_name.' '.$route->last_name)); ?></td>
				<td><?php echo $route->status === 'open' ? lang('routes_status_open') : lang('routes_status_closed'); ?></td>
				<td><?php echo anchor('routes/view/'.$route->route_id, lang('common_view'), array('class'=>'btn btn-primary btn-xs')); ?></td>
			</tr><?php } ?>
			<?php if (!$routes) { ?><tr><td colspan="6" class="text-center"><?php echo lang('routes_empty'); ?></td></tr><?php } ?>
			</tbody>
		</table>
	</div>
</div>

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title"><?php echo lang('routes_range_summary'); ?></h3></div>
	<div class="panel-body">
		<form method="get" action="<?php echo site_url('routes'); ?>" class="form-inline" style="margin-bottom:15px">
			<label><?php echo lang('routes_date_range'); ?>:</label>
			<input class="form-control" type="date" name="start_date" value="<?php echo H($summary_start); ?>">
			<input class="form-control" type="date" name="end_date" value="<?php echo H($summary_end); ?>">
			<button class="btn btn-primary" type="submit"><?php echo lang('common_submit'); ?></button>
		</form>

		<div class="table-responsive">
		<table class="table table-striped table-bordered">
			<thead><tr>
				<th><?php echo lang('routes_name'); ?></th>
				<th><?php echo lang('common_date'); ?></th>
				<th><?php echo lang('routes_seller'); ?></th>
				<th><?php echo lang('common_status'); ?></th>
				<th class="text-right"><?php echo lang('routes_col_sold'); ?></th>
				<th class="text-right"><?php echo lang('routes_col_cash'); ?></th>
				<th class="text-right"><?php echo lang('routes_col_credit'); ?></th>
				<th class="text-right"><?php echo lang('routes_col_credit_pending'); ?></th>
				<th class="text-right"><?php echo lang('routes_col_collections'); ?></th>
				<th class="text-right"><?php echo lang('routes_col_purchases'); ?></th>
				<th class="text-right"><?php echo lang('routes_col_expenses'); ?></th>
				<th class="text-right"><?php echo lang('routes_expected_cash'); ?></th>
				<th class="text-right"><?php echo lang('routes_counted_cash'); ?></th>
				<th class="text-right"><?php echo lang('routes_cash_difference'); ?></th>
			</tr></thead>
			<tbody>
			<?php foreach ($range_summary['rows'] as $r) { ?>
			<tr>
				<td><?php echo anchor('routes/view/'.$r->route->route_id, H($r->route->name)); ?></td>
				<td><?php echo H($r->route->route_date); ?></td>
				<td><?php echo H(trim($r->route->first_name.' '.$r->route->last_name)); ?></td>
				<td><?php echo $r->route->status === 'open' ? lang('routes_status_open') : lang('routes_status_closed'); ?></td>
				<td class="text-right"><?php echo to_currency($r->sold); ?></td>
				<td class="text-right"><?php echo to_currency($r->cash); ?></td>
				<td class="text-right"><?php echo to_currency($r->credit); ?></td>
				<td class="text-right"><?php echo $r->credit_pending > 0 ? '<span class="text-danger">'.to_currency($r->credit_pending).'</span>' : to_currency(0); ?></td>
				<td class="text-right"><?php echo to_currency($r->collections); ?></td>
				<td class="text-right"><?php echo to_currency($r->purchases); ?></td>
				<td class="text-right"><?php echo to_currency($r->expenses); ?></td>
				<td class="text-right"><?php echo to_currency($r->expected); ?></td>
				<td class="text-right"><?php echo $r->counted === NULL ? '-' : to_currency($r->counted); ?></td>
				<td class="text-right"><?php
					if ($r->difference === NULL) { echo '-'; }
					else { echo '<span class="'.($r->difference < 0 ? 'text-danger' : ($r->difference > 0 ? 'text-warning' : '')).'">'.to_currency($r->difference).'</span>'; }
				?></td>
			</tr>
			<?php } ?>
			<?php if (!$range_summary['rows']) { ?><tr><td colspan="14" class="text-center"><?php echo lang('routes_empty'); ?></td></tr><?php } ?>
			</tbody>
			<?php if ($range_summary['rows']) { $t = $range_summary['totals']; ?>
			<tfoot><tr class="active">
				<th colspan="4"><?php echo lang('common_total'); ?></th>
				<th class="text-right"><?php echo to_currency($t->sold); ?></th>
				<th class="text-right"><?php echo to_currency($t->cash); ?></th>
				<th class="text-right"><?php echo to_currency($t->credit); ?></th>
				<th class="text-right"><?php echo to_currency($t->credit_pending); ?></th>
				<th class="text-right"><?php echo to_currency($t->collections); ?></th>
				<th class="text-right"><?php echo to_currency($t->purchases); ?></th>
				<th class="text-right"><?php echo to_currency($t->expenses); ?></th>
				<th class="text-right"><?php echo to_currency($t->expected); ?></th>
				<th class="text-right"><?php echo to_currency($t->counted); ?></th>
				<th class="text-right"><?php echo to_currency($t->difference); ?></th>
			</tr></tfoot>
			<?php } ?>
		</table>
		</div>
	</div>
</div>

<?php $this->load->view('partial/footer'); ?>
