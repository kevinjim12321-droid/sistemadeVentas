<?php $this->load->view('partial/header'); ?>

<div class="panel panel-piluku">
	<div class="panel-heading"><h3 class="panel-title"><?php echo lang('routes_close'); ?> — <?php echo H($route->name); ?> (<?php echo H($route->route_date); ?>)</h3></div>
	<div class="panel-body">

		<?php if ($remaining_inventory > 0.0000000001) { ?>
		<div class="alert alert-warning">
			La ruta todavía tiene <strong><?php echo to_quantity($remaining_inventory); ?></strong> unidad(es) cargadas.
			Puede cerrarla igual; el producto seguirá registrado en la ruta.
			<?php echo anchor('routes/view/'.$route->route_id, 'Volver y devolver a bodega', array('class'=>'alert-link')); ?>.
		</div>
		<?php } ?>

		<div class="table-responsive">
		<table class="table table-bordered" style="max-width:520px">
			<tbody>
				<tr><td><?php echo lang('routes_cash_opening'); ?> / agregado</td><td class="text-right"><?php echo to_currency($reconciliation->fund); ?></td></tr>
				<tr><td>+ Ventas en efectivo</td><td class="text-right"><?php echo to_currency($reconciliation->cash_sales); ?></td></tr>
				<tr><td>+ Abonos a créditos cobrados</td><td class="text-right"><?php echo to_currency($reconciliation->credit_collected); ?></td></tr>
				<tr><td>− Compras pagadas en efectivo</td><td class="text-right"><?php echo to_currency($reconciliation->cash_purchases); ?></td></tr>
				<tr><td>− Gastos de la ruta</td><td class="text-right"><?php echo to_currency($reconciliation->expenses); ?></td></tr>
				<tr class="active"><td><strong><?php echo lang('routes_expected_cash'); ?></strong></td><td class="text-right"><strong id="expected_cash" data-value="<?php echo H($reconciliation->expected); ?>"><?php echo to_currency($reconciliation->expected); ?></strong></td></tr>
			</tbody>
		</table>
		</div>

		<?php echo form_open('routes/close/'.$route->route_id, array('class'=>'form-horizontal', 'onsubmit'=>"return confirm('¿Cerrar la ruta con este cuadre?');")); ?>
			<div class="form-group">
				<?php echo form_label(lang('routes_counted_cash').':', 'counted_cash', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><input class="form-control" type="number" min="0" step="any" name="counted_cash" id="counted_cash" placeholder="0.00"></div>
			</div>
			<div class="form-group">
				<label class="col-sm-3 control-label"><?php echo lang('routes_cash_difference'); ?></label>
				<div class="col-sm-9"><p class="form-control-static" id="difference_display">—</p></div>
			</div>
			<div class="form-group">
				<?php echo form_label(lang('routes_cash_note').':', 'cash_note', array('class'=>'col-sm-3 control-label')); ?>
				<div class="col-sm-9"><textarea class="form-control" name="cash_note" id="cash_note" rows="2" placeholder="Motivo del faltante/sobrante, entrega de efectivo, etc."></textarea></div>
			</div>
			<div class="text-right">
				<?php echo anchor('routes/view/'.$route->route_id, lang('common_cancel'), array('class'=>'btn btn-default')); ?>
				<button class="btn btn-danger" type="submit"><?php echo lang('routes_close'); ?></button>
			</div>
		<?php echo form_close(); ?>
	</div>
</div>

<script>
(function () {
	var expected = parseFloat(document.getElementById('expected_cash').getAttribute('data-value')) || 0;
	var counted = document.getElementById('counted_cash');
	var display = document.getElementById('difference_display');
	function render() {
		if (counted.value === '') { display.textContent = '—'; display.className = 'form-control-static'; return; }
		var diff = (parseFloat(counted.value) || 0) - expected;
		var rounded = Math.round(diff * 100) / 100;
		var text = rounded.toFixed(2);
		if (rounded < 0) { display.textContent = text + ' (<?php echo lang('routes_cash_shortage'); ?>)'; display.className = 'form-control-static text-danger'; }
		else if (rounded > 0) { display.textContent = '+' + text + ' (<?php echo lang('routes_cash_overage'); ?>)'; display.className = 'form-control-static text-warning'; }
		else { display.textContent = text; display.className = 'form-control-static text-success'; }
	}
	counted.addEventListener('input', render);
})();
</script>

<?php $this->load->view('partial/footer'); ?>
