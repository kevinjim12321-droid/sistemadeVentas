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
				<div class="col-sm-9"><select class="form-control" name="employee_id" id="employee_id">
					<option value=""><?php echo lang('common_none'); ?></option>
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

<?php $this->load->view('partial/footer'); ?>
