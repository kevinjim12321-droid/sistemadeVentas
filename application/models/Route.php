<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Route extends MY_Model
{
	//Set by create() when it returns FALSE, so the controller can show a
	//specific message: 'employee_required' | 'employee_has_open_route' | 'db_error'.
	public $last_error = NULL;

	public function __construct()
	{
		parent::__construct();
		$this->ensure_schema();
	}

	private function ensure_schema()
	{
		$runs = $this->db->dbprefix('route_runs');
		$lots = $this->db->dbprefix('route_inventory_lots');
		$movements = $this->db->dbprefix('route_movements');

		$this->db->query("CREATE TABLE IF NOT EXISTS `{$runs}` (
			`route_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`name` varchar(100) NOT NULL,
			`route_date` date NOT NULL,
			`location_id` int(10) NOT NULL,
			`employee_id` int(10) DEFAULT NULL,
			`status` varchar(20) NOT NULL DEFAULT 'open',
			`notes` text,
			`opened_by` int(10) DEFAULT NULL,
			`opened_at` datetime NOT NULL,
			`closed_by` int(10) DEFAULT NULL,
			`closed_at` datetime DEFAULT NULL,
			`created_at` datetime NOT NULL,
			`updated_at` datetime NOT NULL,
			PRIMARY KEY (`route_id`),
			KEY `route_location_date` (`location_id`,`route_date`),
			KEY `route_status` (`status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `{$lots}` (
			`route_inventory_lot_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`route_id` bigint(20) unsigned NOT NULL,
			`source_lot_id` bigint(20) unsigned NOT NULL,
			`lot_code` varchar(100) NOT NULL,
			`item_id` int(10) NOT NULL,
			`item_variation_id` int(10) DEFAULT NULL,
			`supplier_id` int(10) DEFAULT NULL,
			`receiving_id` int(10) DEFAULT NULL,
			`receiving_line` int(10) DEFAULT NULL,
			`manufactured_date` date DEFAULT NULL,
			`expire_date` date DEFAULT NULL,
			`unit_cost` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			`unit_price` decimal(23,10) DEFAULT NULL,
			`condition_type` varchar(20) NOT NULL DEFAULT 'good',
			`quantity_loaded` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			`quantity_remaining` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			`quantity_sold` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			`quantity_broken` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			`quantity_loss` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			`quantity_returned` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			`created_at` datetime NOT NULL,
			`updated_at` datetime NOT NULL,
			PRIMARY KEY (`route_inventory_lot_id`),
			KEY `route_lot_route` (`route_id`,`condition_type`),
			KEY `route_lot_source` (`source_lot_id`),
			KEY `route_lot_item` (`item_id`,`item_variation_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `{$movements}` (
			`route_movement_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`route_id` bigint(20) unsigned NOT NULL,
			`route_inventory_lot_id` bigint(20) unsigned DEFAULT NULL,
			`movement_type` varchar(30) NOT NULL,
			`quantity_delta` decimal(23,10) NOT NULL,
			`balance_after` decimal(23,10) NOT NULL,
			`employee_id` int(10) DEFAULT NULL,
			`occurred_at` datetime NOT NULL,
			`notes` text,
			PRIMARY KEY (`route_movement_id`),
			KEY `route_movement_route` (`route_id`,`occurred_at`),
			KEY `route_movement_lot` (`route_inventory_lot_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

		if (!$this->db->field_exists('route_id', 'sales'))
		{
			$this->db->query('ALTER TABLE `'.$this->db->dbprefix('sales').'` ADD `route_id` bigint(20) unsigned DEFAULT NULL, ADD KEY `sales_route_id` (`route_id`)');
		}
		if (!$this->db->field_exists('route_id', 'receivings'))
		{
			$this->db->query('ALTER TABLE `'.$this->db->dbprefix('receivings').'` ADD `route_id` bigint(20) unsigned DEFAULT NULL, ADD KEY `receivings_route_id` (`route_id`)');
		}
		if (!$this->db->field_exists('supplier_id', 'route_inventory_lots'))
		{
			$this->db->query('ALTER TABLE `'.$lots.'` ADD `supplier_id` int(10) DEFAULT NULL AFTER `item_variation_id`, ADD `receiving_id` int(10) DEFAULT NULL AFTER `supplier_id`, ADD `receiving_line` int(10) DEFAULT NULL AFTER `receiving_id`');
		}
		if (!$this->db->field_exists('quantity_broken', 'route_inventory_lots'))
		{
			$this->db->query('ALTER TABLE `'.$lots.'`'
				." ADD `quantity_broken` decimal(23,10) NOT NULL DEFAULT '0.0000000000',"
				." ADD `quantity_loss` decimal(23,10) NOT NULL DEFAULT '0.0000000000'");
		}

		//NOTE: the route_employee_status (employee_id, status) index used by
		//create()'s locked duplicate-open-route check is NOT created here on
		//purpose -- it ships as an independent SQL migration instead
		//(database/migrations/2026-09-04_route_employee_status_index.sql), not
		//as runtime DDL. create() works correctly without it (InnoDB still
		//locks correctly via SELECT ... FOR UPDATE), just less efficiently on
		//a large route_runs table -- the index is a performance addition.

		//Cash reconciliation (Fase 1): closing cuadre stored on the run itself.
		if (!$this->db->field_exists('counted_cash', 'route_runs'))
		{
			$this->db->query('ALTER TABLE `'.$runs.'`'
				.' ADD `counted_cash` decimal(23,10) DEFAULT NULL,'
				.' ADD `expected_cash` decimal(23,10) DEFAULT NULL,'
				.' ADD `cash_difference` decimal(23,10) DEFAULT NULL,'
				.' ADD `cash_note` text NULL');
		}

		$cash = $this->db->dbprefix('route_cash_events');
		$this->db->query("CREATE TABLE IF NOT EXISTS `{$cash}` (
			`route_cash_event_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`route_id` bigint(20) unsigned NOT NULL,
			`event_type` varchar(20) NOT NULL,
			`amount` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			`notes` varchar(255) DEFAULT NULL,
			`employee_id` int(10) DEFAULT NULL,
			`occurred_at` datetime NOT NULL,
			PRIMARY KEY (`route_cash_event_id`),
			KEY `route_cash_route` (`route_id`,`event_type`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

		$expenses = $this->db->dbprefix('route_expenses');
		$this->db->query("CREATE TABLE IF NOT EXISTS `{$expenses}` (
			`route_expense_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`route_id` bigint(20) unsigned NOT NULL,
			`amount` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			`description` varchar(255) NOT NULL,
			`employee_id` int(10) DEFAULT NULL,
			`occurred_at` datetime NOT NULL,
			PRIMARY KEY (`route_expense_id`),
			KEY `route_expense_route` (`route_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
	}

	// ---- Cash reconciliation (Fase 1) --------------------------------------

	public function add_cash_event($route_id, $event_type, $amount, $notes, $employee_id)
	{
		$amount = (float)$amount;
		if ($amount <= 0 || !in_array($event_type, array('opening', 'add'), TRUE))
		{
			return FALSE;
		}
		$route = $this->get_info($route_id);
		if (!$route || $route->status !== 'open')
		{
			return FALSE;
		}
		return $this->db->insert('route_cash_events', array(
			'route_id' => (int)$route_id,
			'event_type' => $event_type,
			'amount' => $this->decimal($amount),
			'notes' => $notes !== NULL && $notes !== '' ? mb_substr(trim($notes), 0, 255) : NULL,
			'employee_id' => $employee_id ? (int)$employee_id : NULL,
			'occurred_at' => date('Y-m-d H:i:s'),
		));
	}

	public function get_cash_events($route_id)
	{
		$this->db->where('route_id', (int)$route_id);
		$this->db->order_by('occurred_at', 'ASC');
		$this->db->order_by('route_cash_event_id', 'ASC');
		return $this->db->get('route_cash_events')->result();
	}

	public function get_cash_fund_total($route_id)
	{
		$this->db->select_sum('amount', 'total');
		$this->db->where('route_id', (int)$route_id);
		$row = $this->db->get('route_cash_events')->row();
		return $row ? (float)$row->total : 0;
	}

	public function add_expense($route_id, $amount, $description, $employee_id)
	{
		$amount = (float)$amount;
		$description = trim((string)$description);
		if ($amount <= 0 || $description === '')
		{
			return FALSE;
		}
		$route = $this->get_info($route_id);
		if (!$route || $route->status !== 'open')
		{
			return FALSE;
		}
		return $this->db->insert('route_expenses', array(
			'route_id' => (int)$route_id,
			'amount' => $this->decimal($amount),
			'description' => mb_substr($description, 0, 255),
			'employee_id' => $employee_id ? (int)$employee_id : NULL,
			'occurred_at' => date('Y-m-d H:i:s'),
		));
	}

	public function get_expenses($route_id)
	{
		$this->db->select('route_expenses.*, people.first_name, people.last_name');
		$this->db->from('route_expenses');
		$this->db->join('people', 'people.person_id = route_expenses.employee_id', 'left');
		$this->db->where('route_expenses.route_id', (int)$route_id);
		$this->db->order_by('route_expenses.occurred_at', 'ASC');
		$this->db->order_by('route_expenses.route_expense_id', 'ASC');
		return $this->db->get()->result();
	}

	public function get_expenses_total($route_id)
	{
		$this->db->select_sum('amount', 'total');
		$this->db->where('route_id', (int)$route_id);
		$row = $this->db->get('route_expenses')->row();
		return $row ? (float)$row->total : 0;
	}

	public function get_cash_purchases_total($route_id)
	{
		if (!$this->db->field_exists('route_id', 'receivings'))
		{
			return 0;
		}
		$sql = 'SELECT COALESCE(SUM(rp.payment_amount),0) AS total'
			.' FROM '.$this->db->dbprefix('receivings_payments').' rp'
			.' INNER JOIN '.$this->db->dbprefix('receivings').' r ON r.receiving_id = rp.receiving_id'
			.' WHERE r.route_id = ? AND r.deleted = 0 AND r.suspended = 0'
			.' AND rp.payment_type = ?';
		$row = $this->db->query($sql, array((int)$route_id, lang('common_cash')))->row();
		return $row ? (float)$row->total : 0;
	}

	/**
	 * Full cash picture for the reconciliation screen.
	 * expected = fund + cash sales + credit collections - cash purchases - expenses
	 */
	public function get_cash_reconciliation($route_id)
	{
		$sales = $this->get_sales_history($route_id);
		$payment_summary = $this->get_route_payment_summary($sales);

		$fund = $this->get_cash_fund_total($route_id);
		$cash_sales = (float)$payment_summary['cash'];
		$credit_collected = $this->get_credit_collections_total($route_id);
		$cash_purchases = $this->get_cash_purchases_total($route_id);
		$expenses = $this->get_expenses_total($route_id);

		$expected = $fund + $cash_sales + $credit_collected - $cash_purchases - $expenses;

		return (object)array(
			'fund' => $fund,
			'cash_sales' => $cash_sales,
			'credit_collected' => $credit_collected,
			'cash_purchases' => $cash_purchases,
			'expenses' => $expenses,
			'expected' => $expected,
		);
	}

	public function get_remaining_inventory_quantity($route_id)
	{
		$this->db->select_sum('quantity_remaining', 'remaining');
		$this->db->where('route_id', (int)$route_id);
		$row = $this->db->get('route_inventory_lots')->row();
		return $row ? (float)$row->remaining : 0;
	}

	/**
	 * Classify part of a route lot as damaged.
	 *  - 'broken': still sellable; a QUEBRADO- route lot is created (own price),
	 *              the original quantity moves out of the good lot.
	 *  - 'loss'  : total loss / merma; the quantity just leaves the route.
	 * Warehouse stock is never touched (route inventory is already off the
	 * location books once it is loaded or purchased on the route).
	 */
	public function classify_lot_damage($route_id, $route_inventory_lot_id, $quantity, $classification, $unit_price, $employee_id, $notes = '')
	{
		$quantity = (float)$quantity;
		if ($quantity <= 0 || !in_array($classification, array('broken', 'loss'), TRUE))
		{
			return FALSE;
		}

		$this->db->trans_begin();
		$route = $this->db->query('SELECT * FROM '.$this->db->dbprefix('route_runs').' WHERE route_id = ? FOR UPDATE', array((int)$route_id))->row();
		$lot = $this->db->query('SELECT * FROM '.$this->db->dbprefix('route_inventory_lots').' WHERE route_inventory_lot_id = ? AND route_id = ? FOR UPDATE', array((int)$route_inventory_lot_id, (int)$route_id))->row();
		if (!$route || $route->status !== 'open' || !$lot || $lot->condition_type !== 'good' || (float)$lot->quantity_remaining + 0.0000000001 < $quantity)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$now = date('Y-m-d H:i:s');
		$balance = (float)$lot->quantity_remaining - $quantity;
		$counter = $classification === 'loss' ? 'quantity_loss' : 'quantity_broken';
		$this->db->where('route_inventory_lot_id', (int)$lot->route_inventory_lot_id);
		$this->db->set('quantity_remaining', $this->decimal(max(0, $balance)));
		$this->db->set($counter, $counter.'+'.$this->decimal($quantity), FALSE);
		$this->db->set('updated_at', $now);
		$this->db->update('route_inventory_lots');

		$this->db->insert('route_movements', array(
			'route_id' => (int)$route_id,
			'route_inventory_lot_id' => (int)$lot->route_inventory_lot_id,
			'movement_type' => $classification === 'loss' ? 'damage_loss' : 'damage_broken',
			'quantity_delta' => $this->decimal(-$quantity),
			'balance_after' => $this->decimal(max(0, $balance)),
			'employee_id' => (int)$employee_id,
			'occurred_at' => $now,
			'notes' => trim($notes),
		));

		$this->db->insert('damaged_items_log', array(
			'damaged_date' => $now,
			'damaged_qty' => $this->decimal($quantity),
			'damaged_reason' => $classification === 'loss' ? 'Estallado ruta / pérdida' : 'Quebrado vendible (ruta)',
			'item_id' => (int)$lot->item_id,
			'item_variation_id' => $lot->item_variation_id ? (int)$lot->item_variation_id : NULL,
			'location_id' => (int)$route->location_id,
			'sale_id' => NULL,
			'damaged_reason_comment' => 'Ruta #'.(int)$route_id.' | Lote: '.$lot->lot_code.(trim($notes) !== '' ? ' | '.trim($notes) : ''),
		));

		if ($classification === 'broken')
		{
			$price = ($unit_price !== NULL && $unit_price !== '') ? (float)$unit_price : (float)$lot->unit_price;
			$broken_code = mb_substr('QUEBRADO-'.$lot->lot_code.'-'.date('YmdHis'), 0, 100);
			$this->db->insert('route_inventory_lots', array(
				'route_id' => (int)$route_id,
				'source_lot_id' => (int)$lot->source_lot_id,
				'lot_code' => $broken_code,
				'item_id' => (int)$lot->item_id,
				'item_variation_id' => $lot->item_variation_id ? (int)$lot->item_variation_id : NULL,
				'supplier_id' => $lot->supplier_id ? (int)$lot->supplier_id : NULL,
				'receiving_id' => $lot->receiving_id ? (int)$lot->receiving_id : NULL,
				'receiving_line' => $lot->receiving_line,
				'manufactured_date' => $lot->manufactured_date,
				'expire_date' => $lot->expire_date,
				'unit_cost' => $lot->unit_cost,
				'unit_price' => $this->decimal($price),
				'condition_type' => 'broken',
				'quantity_loaded' => $this->decimal($quantity),
				'quantity_remaining' => $this->decimal($quantity),
				'created_at' => $now,
				'updated_at' => $now,
			));
			$broken_lot_id = (int)$this->db->insert_id();
			$this->db->insert('route_movements', array(
				'route_id' => (int)$route_id,
				'route_inventory_lot_id' => $broken_lot_id,
				'movement_type' => 'damage_broken_in',
				'quantity_delta' => $this->decimal($quantity),
				'balance_after' => $this->decimal($quantity),
				'employee_id' => (int)$employee_id,
				'occurred_at' => $now,
				'notes' => trim($notes),
			));
		}

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return TRUE;
	}

	public function get_damage_summary($route_id)
	{
		$this->db->select_sum('quantity_broken', 'broken');
		$this->db->select_sum('quantity_loss', 'loss');
		$this->db->where('route_id', (int)$route_id);
		$row = $this->db->get('route_inventory_lots')->row();
		return (object)array(
			'broken' => $row ? (float)$row->broken : 0,
			'loss' => $row ? (float)$row->loss : 0,
		);
	}

	public function receive_purchase($route_id, $item, $quantity, $receiving_id, $receiving_line, $supplier_id, $employee_id, $received_at)
	{
		$route = $this->db->query('SELECT * FROM '.$this->db->dbprefix('route_runs').' WHERE route_id = ? FOR UPDATE', array((int)$route_id))->row();
		if (!$route || $route->status !== 'open' || $quantity <= 0) return FALSE;

		$multiplier = $item->quantity_unit_quantity !== NULL ? (float)$item->quantity_unit_quantity : 1;
		$unit_cost = ((float)$item->unit_price * (1 - ((float)$item->discount / 100))) / $multiplier;
		$unit_price = (float)$item->selling_price / $multiplier;
		$lot_code = !empty($item->lot_code) ? trim($item->lot_code) : 'RUTA-'.$route_id.'-REC-'.$receiving_id.'-'.$receiving_line;
		$now = date('Y-m-d H:i:s');

		$this->db->insert('route_inventory_lots', array(
			'route_id'=>(int)$route_id,
			'source_lot_id'=>0,
			'lot_code'=>$lot_code,
			'item_id'=>(int)$item->item_id,
			'item_variation_id'=>$item->variation_id ? (int)$item->variation_id : NULL,
			'supplier_id'=>$supplier_id ? (int)$supplier_id : NULL,
			'receiving_id'=>(int)$receiving_id,
			'receiving_line'=>(int)$receiving_line,
			'manufactured_date'=>!empty($item->manufactured_date) ? $item->manufactured_date : NULL,
			'expire_date'=>!empty($item->expire_date) ? $item->expire_date : NULL,
			'unit_cost'=>$this->decimal($unit_cost),
			'unit_price'=>$this->decimal($unit_price),
			'condition_type'=>'good',
			'quantity_loaded'=>$this->decimal($quantity),
			'quantity_remaining'=>$this->decimal($quantity),
			'created_at'=>$now,
			'updated_at'=>$now,
		));
		$route_lot_id = $this->db->insert_id();
		if (!$route_lot_id) return FALSE;
		$this->db->insert('route_movements', array(
			'route_id'=>(int)$route_id,
			'route_inventory_lot_id'=>(int)$route_lot_id,
			'movement_type'=>'purchase',
			'quantity_delta'=>$this->decimal($quantity),
			'balance_after'=>$this->decimal($quantity),
			'employee_id'=>(int)$employee_id,
			'occurred_at'=>$received_at ?: $now,
			'notes'=>'RECV '.$receiving_id.($supplier_id ? ' | Proveedor '.$supplier_id : ''),
		));
		return TRUE;
	}

	public function get_available_lots_for_item($route_id, $item_id, $variation_id = NULL)
	{
		$this->db->from('route_inventory_lots');
		$this->db->where('route_id', (int)$route_id);
		$this->db->where('item_id', (int)$item_id);
		if ($variation_id)
		{
			$this->db->where('item_variation_id', (int)$variation_id);
		}
		else
		{
			$this->db->where('item_variation_id IS NULL', NULL, FALSE);
		}
		$this->db->where('quantity_remaining >', 0);
		$this->db->order_by('CASE WHEN expire_date IS NULL THEN 1 ELSE 0 END', '', FALSE);
		$this->db->order_by('expire_date', 'ASC');
		$this->db->order_by('route_inventory_lot_id', 'ASC');
		return $this->db->get()->result();
	}

	public function get_available_quantity($route_id, $item_id, $variation_id = NULL)
	{
		$this->db->select_sum('quantity_remaining', 'available');
		$this->db->where('route_id', (int)$route_id);
		$this->db->where('item_id', (int)$item_id);
		if ($variation_id)
		{
			$this->db->where('item_variation_id', (int)$variation_id);
		}
		else
		{
			$this->db->where('item_variation_id IS NULL', NULL, FALSE);
		}
		$row = $this->db->get('route_inventory_lots')->row();
		return $row ? (float)$row->available : 0;
	}

	public function consume_for_sale($route_id, $item_id, $variation_id, $quantity, $sale_id, $sale_line, $employee_id)
	{
		$quantity = (float)$quantity;
		if ($quantity <= 0) return FALSE;

		$route = $this->db->query('SELECT * FROM '.$this->db->dbprefix('route_runs').' WHERE route_id = ? FOR UPDATE', array((int)$route_id))->row();
		if (!$route || $route->status !== 'open') return FALSE;

		$sql = 'SELECT * FROM '.$this->db->dbprefix('route_inventory_lots').' WHERE route_id = ? AND item_id = ? AND '.($variation_id ? 'item_variation_id = ?' : 'item_variation_id IS NULL').' AND quantity_remaining > 0 ORDER BY CASE WHEN expire_date IS NULL THEN 1 ELSE 0 END, expire_date ASC, route_inventory_lot_id ASC FOR UPDATE';
		$params = $variation_id ? array((int)$route_id, (int)$item_id, (int)$variation_id) : array((int)$route_id, (int)$item_id);
		$lots = $this->db->query($sql, $params)->result();
		$available = 0;
		foreach ($lots as $lot) $available += (float)$lot->quantity_remaining;
		if ($available + 0.0000000001 < $quantity) return FALSE;

		$remaining = $quantity;
		$allocations = array();
		$now = date('Y-m-d H:i:s');
		foreach ($lots as $lot)
		{
			if ($remaining <= 0.0000000001) break;
			$taken = min((float)$lot->quantity_remaining, $remaining);
			$balance = (float)$lot->quantity_remaining - $taken;
			$this->db->where('route_inventory_lot_id', (int)$lot->route_inventory_lot_id);
			$this->db->set('quantity_remaining', $this->decimal(max(0, $balance)));
			$this->db->set('quantity_sold', 'quantity_sold+'.$this->decimal($taken), FALSE);
			$this->db->set('updated_at', $now);
			$this->db->update('route_inventory_lots');
			$this->db->insert('route_movements', array(
				'route_id'=>(int)$route_id,
				'route_inventory_lot_id'=>(int)$lot->route_inventory_lot_id,
				'movement_type'=>'sale',
				'quantity_delta'=>$this->decimal(-$taken),
				'balance_after'=>$this->decimal(max(0, $balance)),
				'employee_id'=>(int)$employee_id,
				'occurred_at'=>$now,
				'notes'=>'POS '.$sale_id.' | Línea '.$sale_line,
			));
			$allocations[] = array('quantity'=>$taken, 'unit_cost'=>(float)$lot->unit_cost, 'route_inventory_lot_id'=>(int)$lot->route_inventory_lot_id);
			$remaining -= $taken;
		}
		return $allocations;
	}

	public function get_sales_summary($route_id)
	{
		if (!$this->db->field_exists('route_id', 'sales')) return (object)array('sale_count'=>0, 'total'=>0);
		$this->db->select('COUNT(*) AS sale_count, COALESCE(SUM(total),0) AS total', FALSE);
		$this->db->where('route_id', (int)$route_id);
		$this->db->where('deleted', 0);
		$this->db->where('suspended', 0);
		$this->db->where('store_account_payment', 0);
		return $this->db->get('sales')->row();
	}

	//Cash the seller collected on the route against customers' existing debt
	//(store account payments rung up while in route mode). Only the cash part
	//counts toward the closing cuadre.
	public function get_credit_collections($route_id)
	{
		if (!$this->db->field_exists('route_id', 'sales')) return array();
		$sql = 'SELECT s.sale_id, s.sale_time, s.customer_id, '
			.'p.first_name AS customer_first_name, p.last_name AS customer_last_name, '
			.'COALESCE(SUM(CASE WHEN sp.payment_type = ? THEN sp.payment_amount ELSE 0 END),0) AS cash_amount '
			.'FROM '.$this->db->dbprefix('sales').' s '
			.'LEFT JOIN '.$this->db->dbprefix('people').' p ON p.person_id = s.customer_id '
			.'LEFT JOIN '.$this->db->dbprefix('sales_payments').' sp ON sp.sale_id = s.sale_id '
			.'WHERE s.route_id = ? AND s.deleted = 0 AND s.suspended = 0 AND s.store_account_payment = 1 '
			.'GROUP BY s.sale_id, s.sale_time, s.customer_id, p.first_name, p.last_name '
			.'ORDER BY s.sale_time DESC, s.sale_id DESC';
		return $this->db->query($sql, array(lang('common_cash'), (int)$route_id))->result();
	}

	public function get_credit_collections_total($route_id)
	{
		$total = 0;
		foreach ($this->get_credit_collections($route_id) as $collection)
		{
			$total += (float)$collection->cash_amount;
		}
		return $total;
	}

	public function get_sales_history($route_id)
	{
		if (!$this->db->field_exists('route_id', 'sales')) return array();

		$sql = 'SELECT sales.sale_id, sales.sale_time, sales.total, sales.customer_id, sales.employee_id, '
			.'customer_people.first_name AS customer_first_name, customer_people.last_name AS customer_last_name, '
			.'employee_people.first_name AS employee_first_name, employee_people.last_name AS employee_last_name '
			.'FROM '.$this->db->dbprefix('sales').' sales '
			.'LEFT JOIN '.$this->db->dbprefix('people').' customer_people ON customer_people.person_id = sales.customer_id '
			.'LEFT JOIN '.$this->db->dbprefix('people').' employee_people ON employee_people.person_id = sales.employee_id '
			.'WHERE sales.route_id = ? AND sales.deleted = 0 AND sales.suspended = 0 AND sales.store_account_payment = 0 '
			.'ORDER BY sales.sale_time DESC, sales.sale_id DESC';
		$sales = $this->db->query($sql, array((int)$route_id))->result();
		if (!$sales) return array();

		$sale_ids = array_map(function($sale) { return (int)$sale->sale_id; }, $sales);
		$this->db->select('sale_id, payment_type, payment_amount');
		$this->db->where_in('sale_id', $sale_ids);
		$payment_rows = $this->db->get('sales_payments')->result();
		$payments = array();
		foreach ($payment_rows as $payment)
		{
			if (!isset($payments[$payment->sale_id])) $payments[$payment->sale_id] = array();
			$payments[$payment->sale_id][] = $payment;
		}

		$invoice_ids = array();
		if ($this->db->table_exists('customer_invoice_details'))
		{
			$this->db->select('sale_id, invoice_id');
			$this->db->where_in('sale_id', $sale_ids);
			foreach ($this->db->get('customer_invoice_details')->result() as $invoice_detail)
			{
				$invoice_ids[(int)$invoice_detail->sale_id] = (int)$invoice_detail->invoice_id;
			}
		}

		$paid_credit = array();
		if ($this->db->table_exists('store_accounts_paid_sales'))
		{
			$this->db->select('sale_id, SUM(partial_payment_amount) AS paid_amount', FALSE);
			$this->db->where_in('sale_id', $sale_ids);
			$this->db->group_by('sale_id');
			foreach ($this->db->get('store_accounts_paid_sales')->result() as $paid)
			{
				$paid_credit[(int)$paid->sale_id] = (float)$paid->paid_amount;
			}
		}

		foreach ($sales as $sale)
		{
			$sale->invoice_id = isset($invoice_ids[(int)$sale->sale_id]) ? $invoice_ids[(int)$sale->sale_id] : NULL;
			$sale->payments = isset($payments[$sale->sale_id]) ? $payments[$sale->sale_id] : array();
			$sale->credit_amount = 0;
			$sale->cash_amount = 0;
			foreach ($sale->payments as $payment)
			{
				if ($payment->payment_type === lang('common_store_account')) $sale->credit_amount += (float)$payment->payment_amount;
				if ($payment->payment_type === lang('common_cash')) $sale->cash_amount += min((float)$payment->payment_amount, (float)$sale->total);
			}
			$sale->credit_paid = isset($paid_credit[(int)$sale->sale_id]) ? $paid_credit[(int)$sale->sale_id] : 0;
			$sale->credit_pending = max(0, $sale->credit_amount - $sale->credit_paid);
		}
		return $sales;
	}

	public function get_route_payment_summary($sales)
	{
		$summary = array('cash'=>0, 'credit'=>0, 'credit_pending'=>0, 'other'=>0);
		foreach ($sales as $sale)
		{
			$summary['cash'] += $sale->cash_amount;
			$summary['credit'] += $sale->credit_amount;
			$summary['credit_pending'] += $sale->credit_pending;
			foreach ($sale->payments as $payment)
			{
				if ($payment->payment_type !== lang('common_cash') && $payment->payment_type !== lang('common_store_account'))
				{
					$summary['other'] += (float)$payment->payment_amount;
				}
			}
		}
		return $summary;
	}

	public function get_purchase_history($route_id)
	{
		if (!$this->db->field_exists('route_id', 'receivings')) return array();
		$sql = 'SELECT receivings.receiving_id, receivings.receiving_time, receivings.total, receivings.supplier_id, '
			.'suppliers.company_name, people.first_name, people.last_name '
			.'FROM '.$this->db->dbprefix('receivings').' receivings '
			.'LEFT JOIN '.$this->db->dbprefix('suppliers').' suppliers ON suppliers.person_id = receivings.supplier_id '
			.'LEFT JOIN '.$this->db->dbprefix('people').' people ON people.person_id = receivings.supplier_id '
			.'WHERE receivings.route_id = ? AND receivings.deleted = 0 AND receivings.suspended = 0 '
			.'ORDER BY receivings.receiving_time DESC, receivings.receiving_id DESC';
		$purchases = $this->db->query($sql, array((int)$route_id))->result();
		if (!$purchases) return array();
		$ids = array_map(function($purchase) { return (int)$purchase->receiving_id; }, $purchases);
		$this->db->select('receiving_id, payment_type, payment_amount');
		$this->db->where_in('receiving_id', $ids);
		$payments = array();
		foreach ($this->db->get('receivings_payments')->result() as $payment)
		{
			if (!isset($payments[$payment->receiving_id])) $payments[$payment->receiving_id] = array();
			$payments[$payment->receiving_id][] = $payment;
		}
		foreach ($purchases as $purchase)
		{
			$purchase->payments = isset($payments[$purchase->receiving_id]) ? $payments[$purchase->receiving_id] : array();
		}
		return $purchases;
	}

	public function get_purchases_total($route_id)
	{
		$total = 0;
		foreach ($this->get_purchase_history($route_id) as $purchase)
		{
			$total += (float)$purchase->total;
		}
		return $total;
	}

	/**
	 * Consolidated figures for every route of a location within a date range.
	 * Returns array('rows' => [...], 'totals' => object).
	 */
	public function get_range_summary($location_id, $start_date, $end_date)
	{
		$this->db->select('route_runs.*, people.first_name, people.last_name');
		$this->db->from('route_runs');
		$this->db->join('people', 'people.person_id = route_runs.employee_id', 'left');
		$this->db->where('route_runs.location_id', (int)$location_id);
		$this->db->where('route_runs.route_date >=', $start_date);
		$this->db->where('route_runs.route_date <=', $end_date);
		$this->db->order_by('route_runs.route_date', 'DESC');
		$this->db->order_by('route_runs.route_id', 'DESC');
		$routes = $this->db->get()->result();

		$rows = array();
		$keys = array('sold','cash','credit','credit_pending','collections','purchases','cash_purchases','expenses','fund','expected','counted','difference');
		$totals = array_fill_keys($keys, 0);

		foreach ($routes as $route)
		{
			$sales = $this->get_sales_history($route->route_id);
			$ps = $this->get_route_payment_summary($sales);
			$sold = 0;
			foreach ($sales as $sale)
			{
				$sold += (float)$sale->total;
			}
			$rec = $this->get_cash_reconciliation($route->route_id);
			$purchases_total = $this->get_purchases_total($route->route_id);
			$counted = $route->counted_cash === NULL ? NULL : (float)$route->counted_cash;
			$difference = $route->cash_difference === NULL ? NULL : (float)$route->cash_difference;

			$rows[] = (object)array(
				'route' => $route,
				'sold' => $sold,
				'cash' => (float)$ps['cash'],
				'credit' => (float)$ps['credit'],
				'credit_pending' => (float)$ps['credit_pending'],
				'collections' => (float)$rec->credit_collected,
				'purchases' => $purchases_total,
				'cash_purchases' => (float)$rec->cash_purchases,
				'expenses' => (float)$rec->expenses,
				'fund' => (float)$rec->fund,
				'expected' => (float)$rec->expected,
				'counted' => $counted,
				'difference' => $difference,
			);

			$totals['sold'] += $sold;
			$totals['cash'] += (float)$ps['cash'];
			$totals['credit'] += (float)$ps['credit'];
			$totals['credit_pending'] += (float)$ps['credit_pending'];
			$totals['collections'] += (float)$rec->credit_collected;
			$totals['purchases'] += $purchases_total;
			$totals['cash_purchases'] += (float)$rec->cash_purchases;
			$totals['expenses'] += (float)$rec->expenses;
			$totals['fund'] += (float)$rec->fund;
			$totals['expected'] += (float)$rec->expected;
			if ($counted !== NULL) $totals['counted'] += $counted;
			if ($difference !== NULL) $totals['difference'] += $difference;
		}

		return array('rows' => $rows, 'totals' => (object)$totals);
	}

	public function get_all_for_location($location_id)
	{
		$this->db->select('route_runs.*, people.first_name, people.last_name');
		$this->db->from('route_runs');
		$this->db->join('people', 'people.person_id = route_runs.employee_id', 'left');
		$this->db->where('route_runs.location_id', (int)$location_id);
		$this->db->order_by('route_runs.route_date', 'DESC');
		$this->db->order_by('route_runs.route_id', 'DESC');
		return $this->db->get()->result();
	}

	public function get_info($route_id)
	{
		$this->db->select('route_runs.*, people.first_name, people.last_name');
		$this->db->from('route_runs');
		$this->db->join('people', 'people.person_id = route_runs.employee_id', 'left');
		$this->db->where('route_runs.route_id', (int)$route_id);
		return $this->db->get()->row();
	}

	/**
	 * The one open route (if any) assigned to this employee. Pass $location_id
	 * to scope it to a single location (used by the automatic route-sale
	 * detection in Sales); omit it to check across every location (used by
	 * create() to enforce "one open route per employee").
	 */
	public function get_open_route_for_employee($employee_id, $location_id = NULL)
	{
		$this->db->select('route_runs.*, people.first_name, people.last_name');
		$this->db->from('route_runs');
		$this->db->join('people', 'people.person_id = route_runs.employee_id', 'left');
		$this->db->where('route_runs.employee_id', (int)$employee_id);
		$this->db->where('route_runs.status', 'open');
		if ($location_id !== NULL)
		{
			$this->db->where('route_runs.location_id', (int)$location_id);
		}
		$this->db->order_by('route_runs.route_id', 'desc');
		$this->db->limit(1);
		return $this->db->get()->row();
	}

	public function create($data)
	{
		$this->last_error = NULL;

		$employee_id = !empty($data['employee_id']) ? (int)$data['employee_id'] : NULL;
		if (!$employee_id)
		{
			$this->last_error = 'employee_required';
			return FALSE;
		}

		$this->db->trans_begin();

		//Lock any existing open route rows for this employee so two concurrent
		//"open route" requests for the same person can't both succeed.
		$existing = $this->db->query(
			'SELECT route_id FROM '.$this->db->dbprefix('route_runs')." WHERE employee_id = ? AND status = 'open' FOR UPDATE",
			array($employee_id)
		)->row();
		if ($existing)
		{
			$this->db->trans_rollback();
			$this->last_error = 'employee_has_open_route';
			return FALSE;
		}

		$now = date('Y-m-d H:i:s');
		$insert = array(
			'name' => trim($data['name']),
			'route_date' => $data['route_date'],
			'location_id' => (int)$data['location_id'],
			'employee_id' => $employee_id,
			'status' => 'open',
			'notes' => isset($data['notes']) ? trim($data['notes']) : NULL,
			'opened_by' => !empty($data['opened_by']) ? (int)$data['opened_by'] : NULL,
			'opened_at' => $now,
			'created_at' => $now,
			'updated_at' => $now,
		);
		$this->db->insert('route_runs', $insert);
		$route_id = $this->db->insert_id();

		if (!$route_id || $this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			$this->last_error = 'db_error';
			return FALSE;
		}
		$this->db->trans_commit();
		return $route_id;
	}

	public function get_available_warehouse_lots($location_id)
	{
		$sql = 'SELECT lots.*, items.name AS item_name, variations.name AS variation_name
			FROM '.$this->db->dbprefix('inventory_lots').' lots
			INNER JOIN '.$this->db->dbprefix('items').' items ON items.item_id = lots.item_id
			LEFT JOIN '.$this->db->dbprefix('item_variations').' variations ON variations.id = lots.item_variation_id
			WHERE lots.location_id = ?
			AND lots.status = ?
			AND lots.quantity_remaining > 0
			AND lots.lot_code NOT LIKE ?
			AND (lots.expire_date IS NULL OR lots.expire_date >= CURDATE())
			ORDER BY items.name ASC, CASE WHEN lots.expire_date IS NULL THEN 1 ELSE 0 END ASC, lots.expire_date ASC, lots.received_at ASC';
		$query = $this->db->query($sql, array((int)$location_id, 'active', 'QUEBRADO-%'));
		return $query ? $query->result() : array();
	}

	public function get_inventory($route_id)
	{
		$sql = 'SELECT route_lots.*, items.name AS item_name, variations.name AS variation_name
			FROM '.$this->db->dbprefix('route_inventory_lots').' route_lots
			INNER JOIN '.$this->db->dbprefix('items').' items ON items.item_id = route_lots.item_id
			LEFT JOIN '.$this->db->dbprefix('item_variations').' variations ON variations.id = route_lots.item_variation_id
			WHERE route_lots.route_id = ?
			ORDER BY items.name ASC, route_lots.route_inventory_lot_id ASC';
		$query = $this->db->query($sql, array((int)$route_id));
		return $query ? $query->result() : array();
	}

	public function load_from_warehouse($route_id, $source_lot_id, $quantity, $employee_id, $notes = '')
	{
		$quantity = (float)$quantity;
		if ($quantity <= 0)
		{
			return FALSE;
		}

		$this->db->trans_begin();
		$route = $this->db->query('SELECT * FROM '.$this->db->dbprefix('route_runs').' WHERE route_id = ? FOR UPDATE', array((int)$route_id))->row();
		$lot = $this->db->query('SELECT * FROM '.$this->db->dbprefix('inventory_lots').' WHERE lot_id = ? FOR UPDATE', array((int)$source_lot_id))->row();
		if (!$route || $route->status !== 'open' || !$lot || (int)$lot->location_id !== (int)$route->location_id || strpos($lot->lot_code, 'QUEBRADO-') === 0 || (float)$lot->quantity_remaining + 0.0000000001 < $quantity)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$now = date('Y-m-d H:i:s');
		$source_balance = (float)$lot->quantity_remaining - $quantity;
		$this->db->where('lot_id', (int)$lot->lot_id);
		$this->db->update('inventory_lots', array(
			'quantity_remaining' => $this->decimal(max(0, $source_balance)),
			'status' => $source_balance <= 0.0000000001 ? 'depleted' : 'active',
			'updated_at' => $now,
		));

		if ($lot->item_variation_id)
		{
			$this->db->set('quantity', 'quantity-'.$this->decimal($quantity), FALSE);
			$this->db->where('item_variation_id', (int)$lot->item_variation_id);
			$this->db->where('location_id', (int)$lot->location_id);
			$this->db->where('quantity >=', $quantity);
			$this->db->update('location_item_variations');
		}
		else
		{
			$this->db->set('quantity', 'quantity-'.$this->decimal($quantity), FALSE);
			$this->db->where('item_id', (int)$lot->item_id);
			$this->db->where('location_id', (int)$lot->location_id);
			$this->db->where('quantity >=', $quantity);
			$this->db->update('location_items');
		}
		if ($this->db->affected_rows() !== 1)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->where('route_id', (int)$route_id);
		$this->db->where('source_lot_id', (int)$source_lot_id);
		$this->db->where('condition_type', 'good');
		$route_lot = $this->db->get('route_inventory_lots')->row();
		if ($route_lot)
		{
			$route_balance = (float)$route_lot->quantity_remaining + $quantity;
			$this->db->where('route_inventory_lot_id', (int)$route_lot->route_inventory_lot_id);
			$this->db->set('quantity_loaded', 'quantity_loaded+'.$this->decimal($quantity), FALSE);
			$this->db->set('quantity_remaining', 'quantity_remaining+'.$this->decimal($quantity), FALSE);
			$this->db->set('updated_at', $now);
			$this->db->update('route_inventory_lots');
			$route_inventory_lot_id = (int)$route_lot->route_inventory_lot_id;
		}
		else
		{
			$route_balance = $quantity;
			$this->db->insert('route_inventory_lots', array(
				'route_id' => (int)$route_id,
				'source_lot_id' => (int)$lot->lot_id,
				'lot_code' => $lot->lot_code,
				'item_id' => (int)$lot->item_id,
				'item_variation_id' => $lot->item_variation_id ? (int)$lot->item_variation_id : NULL,
				'manufactured_date' => $lot->manufactured_date,
				'expire_date' => $lot->expire_date,
				'unit_cost' => $lot->unit_cost,
				'unit_price' => $lot->unit_price,
				'condition_type' => 'good',
				'quantity_loaded' => $this->decimal($quantity),
				'quantity_remaining' => $this->decimal($quantity),
				'created_at' => $now,
				'updated_at' => $now,
			));
			$route_inventory_lot_id = $this->db->insert_id();
		}

		$this->db->insert('route_movements', array(
			'route_id' => (int)$route_id,
			'route_inventory_lot_id' => $route_inventory_lot_id,
			'movement_type' => 'load',
			'quantity_delta' => $this->decimal($quantity),
			'balance_after' => $this->decimal($route_balance),
			'employee_id' => (int)$employee_id,
			'occurred_at' => $now,
			'notes' => trim($notes),
		));
		$this->db->insert('inventory_lot_movements', array(
			'lot_id' => (int)$lot->lot_id,
			'movement_type' => 'route_load',
			'quantity_delta' => $this->decimal(-$quantity),
			'balance_after' => $this->decimal(max(0, $source_balance)),
			'reference_type' => 'route',
			'reference_id' => (string)(int)$route_id,
			'employee_id' => (int)$employee_id,
			'occurred_at' => $now,
			'notes' => trim($notes),
		));

		$current_quantity = $lot->item_variation_id
			? $this->db->get_where('location_item_variations', array('item_variation_id' => (int)$lot->item_variation_id, 'location_id' => (int)$lot->location_id))->row()->quantity
			: $this->db->get_where('location_items', array('item_id' => (int)$lot->item_id, 'location_id' => (int)$lot->location_id))->row()->quantity;
		$this->db->insert('inventory', array(
			'trans_date' => $now,
			'trans_items' => (int)$lot->item_id,
			'item_variation_id' => $lot->item_variation_id ? (int)$lot->item_variation_id : NULL,
			'trans_user' => (int)$employee_id,
			'trans_comment' => 'CARGA RUTA #'.(int)$route_id.' | Lote: '.$lot->lot_code,
			'trans_inventory' => $this->decimal(-$quantity),
			'location_id' => (int)$lot->location_id,
			'trans_current_quantity' => $this->decimal($current_quantity),
		));

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return TRUE;
	}

	public function return_to_warehouse($route_id, $route_inventory_lot_id, $quantity, $employee_id, $notes = '')
	{
		$quantity = (float)$quantity;
		if ($quantity <= 0)
		{
			return FALSE;
		}

		$this->db->trans_begin();
		$route = $this->db->query('SELECT * FROM '.$this->db->dbprefix('route_runs').' WHERE route_id = ? FOR UPDATE', array((int)$route_id))->row();
		$route_lot = $this->db->query('SELECT * FROM '.$this->db->dbprefix('route_inventory_lots').' WHERE route_inventory_lot_id = ? AND route_id = ? FOR UPDATE', array((int)$route_inventory_lot_id, (int)$route_id))->row();
		if (!$route || $route->status !== 'open' || !$route_lot || (float)$route_lot->quantity_remaining + 0.0000000001 < $quantity)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$created_source_lot = FALSE;
		$source_lot = $this->db->query('SELECT * FROM '.$this->db->dbprefix('inventory_lots').' WHERE lot_id = ? FOR UPDATE', array((int)$route_lot->source_lot_id))->row();
		if (!$source_lot && empty($route_lot->source_lot_id))
		{
			$this->load->model('Inventory_lot');
			$new_lot_id = $this->Inventory_lot->create_lot(array(
				'lot_code'=>$route_lot->lot_code,
				'item_id'=>(int)$route_lot->item_id,
				'item_variation_id'=>$route_lot->item_variation_id ? (int)$route_lot->item_variation_id : NULL,
				'location_id'=>(int)$route->location_id,
				'supplier_id'=>$route_lot->supplier_id ? (int)$route_lot->supplier_id : NULL,
				'receiving_id'=>$route_lot->receiving_id ? (int)$route_lot->receiving_id : NULL,
				'receiving_line'=>$route_lot->receiving_line,
				'manufactured_date'=>$route_lot->manufactured_date,
				'expire_date'=>$route_lot->expire_date,
				'quantity_initial'=>$quantity,
				'unit_cost'=>$route_lot->unit_cost,
				'unit_price'=>$route_lot->unit_price,
				'employee_id'=>$employee_id,
				'movement_type'=>'route_return',
				'reference_type'=>'route',
				'reference_id'=>(string)(int)$route_id,
				'notes'=>trim($notes),
			));
			if (!$new_lot_id)
			{
				$this->db->trans_rollback();
				return FALSE;
			}
			$this->db->where('route_inventory_lot_id', (int)$route_lot->route_inventory_lot_id);
			$this->db->update('route_inventory_lots', array('source_lot_id'=>(int)$new_lot_id));
			$source_lot = $this->db->get_where('inventory_lots', array('lot_id'=>(int)$new_lot_id))->row();
			$created_source_lot = TRUE;
			// create_lot already placed this returned quantity in the warehouse lot;
			// neutralize the generic increment performed below.
			$source_lot->quantity_remaining = 0;
		}
		if (!$source_lot || (int)$source_lot->location_id !== (int)$route->location_id)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$now = date('Y-m-d H:i:s');
		$route_balance = (float)$route_lot->quantity_remaining - $quantity;
		$warehouse_lot_balance = (float)$source_lot->quantity_remaining + $quantity;
		$this->db->where('route_inventory_lot_id', (int)$route_lot->route_inventory_lot_id);
		$this->db->set('quantity_remaining', $this->decimal(max(0, $route_balance)));
		$this->db->set('quantity_returned', 'quantity_returned+'.$this->decimal($quantity), FALSE);
		$this->db->set('updated_at', $now);
		$this->db->update('route_inventory_lots');

		$this->db->where('lot_id', (int)$source_lot->lot_id);
		$this->db->update('inventory_lots', array(
			'quantity_remaining' => $this->decimal($warehouse_lot_balance),
			'status' => 'active',
			'updated_at' => $now,
		));

		if ($source_lot->item_variation_id)
		{
			$this->db->set('quantity', 'quantity+'.$this->decimal($quantity), FALSE);
			$this->db->where('item_variation_id', (int)$source_lot->item_variation_id);
			$this->db->where('location_id', (int)$source_lot->location_id);
			$this->db->update('location_item_variations');
		}
		else
		{
			$this->db->set('quantity', 'quantity+'.$this->decimal($quantity), FALSE);
			$this->db->where('item_id', (int)$source_lot->item_id);
			$this->db->where('location_id', (int)$source_lot->location_id);
			$this->db->update('location_items');
		}
		if ($this->db->affected_rows() !== 1)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->insert('route_movements', array(
			'route_id' => (int)$route_id,
			'route_inventory_lot_id' => (int)$route_lot->route_inventory_lot_id,
			'movement_type' => 'return_to_warehouse',
			'quantity_delta' => $this->decimal(-$quantity),
			'balance_after' => $this->decimal(max(0, $route_balance)),
			'employee_id' => (int)$employee_id,
			'occurred_at' => $now,
			'notes' => trim($notes),
		));
		if (!$created_source_lot)
		{
			$this->db->insert('inventory_lot_movements', array(
				'lot_id' => (int)$source_lot->lot_id,
				'movement_type' => 'route_return',
				'quantity_delta' => $this->decimal($quantity),
				'balance_after' => $this->decimal($warehouse_lot_balance),
				'reference_type' => 'route',
				'reference_id' => (string)(int)$route_id,
				'employee_id' => (int)$employee_id,
				'occurred_at' => $now,
				'notes' => trim($notes),
			));
		}

		$current_row = $source_lot->item_variation_id
			? $this->db->get_where('location_item_variations', array('item_variation_id' => (int)$source_lot->item_variation_id, 'location_id' => (int)$source_lot->location_id))->row()
			: $this->db->get_where('location_items', array('item_id' => (int)$source_lot->item_id, 'location_id' => (int)$source_lot->location_id))->row();
		$this->db->insert('inventory', array(
			'trans_date' => $now,
			'trans_items' => (int)$source_lot->item_id,
			'item_variation_id' => $source_lot->item_variation_id ? (int)$source_lot->item_variation_id : NULL,
			'trans_user' => (int)$employee_id,
			'trans_comment' => 'DEVOLUCION RUTA #'.(int)$route_id.' | Lote: '.$source_lot->lot_code,
			'trans_inventory' => $this->decimal($quantity),
			'location_id' => (int)$source_lot->location_id,
			'trans_current_quantity' => $this->decimal($current_row->quantity),
		));

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return TRUE;
	}

	public function close($route_id, $employee_id, $counted_cash = NULL, $cash_note = '')
	{
		$route = $this->get_info($route_id);
		if (!$route || $route->status !== 'open')
		{
			return FALSE;
		}

		$reconciliation = $this->get_cash_reconciliation($route_id);
		$counted = $counted_cash === NULL || $counted_cash === '' ? NULL : (float)$counted_cash;
		$difference = $counted === NULL ? NULL : $counted - (float)$reconciliation->expected;

		$now = date('Y-m-d H:i:s');
		$this->db->where('route_id', (int)$route_id);
		$this->db->where('status', 'open');
		return $this->db->update('route_runs', array(
			'status' => 'closed',
			'closed_by' => (int)$employee_id,
			'closed_at' => $now,
			'updated_at' => $now,
			'expected_cash' => $this->decimal($reconciliation->expected),
			'counted_cash' => $counted === NULL ? NULL : $this->decimal($counted),
			'cash_difference' => $difference === NULL ? NULL : $this->decimal($difference),
			'cash_note' => $cash_note !== NULL && trim($cash_note) !== '' ? trim($cash_note) : NULL,
		));
	}

	public function return_all_inventory($route_id, $employee_id, $notes = '')
	{
		$route = $this->get_info($route_id);
		if (!$route || $route->status !== 'open')
		{
			return FALSE;
		}
		$this->db->where('route_id', (int)$route_id);
		$this->db->where('quantity_remaining >', 0);
		$this->db->where('condition_type', 'good');
		$lots = $this->db->get('route_inventory_lots')->result();
		$all_ok = TRUE;
		foreach ($lots as $lot)
		{
			if (!$this->return_to_warehouse($route_id, (int)$lot->route_inventory_lot_id, (float)$lot->quantity_remaining, $employee_id, $notes))
			{
				$all_ok = FALSE;
			}
		}
		return $all_ok;
	}

	private function decimal($value)
	{
		return number_format((float)$value, 10, '.', '');
	}
}
