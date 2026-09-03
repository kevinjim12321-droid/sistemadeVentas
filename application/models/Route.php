<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Route extends MY_Model
{
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

	public function create($data)
	{
		$now = date('Y-m-d H:i:s');
		$insert = array(
			'name' => trim($data['name']),
			'route_date' => $data['route_date'],
			'location_id' => (int)$data['location_id'],
			'employee_id' => !empty($data['employee_id']) ? (int)$data['employee_id'] : NULL,
			'status' => 'open',
			'notes' => isset($data['notes']) ? trim($data['notes']) : NULL,
			'opened_by' => !empty($data['opened_by']) ? (int)$data['opened_by'] : NULL,
			'opened_at' => $now,
			'created_at' => $now,
			'updated_at' => $now,
		);
		return $this->db->insert('route_runs', $insert) ? $this->db->insert_id() : FALSE;
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

		$source_lot = $this->db->query('SELECT * FROM '.$this->db->dbprefix('inventory_lots').' WHERE lot_id = ? FOR UPDATE', array((int)$route_lot->source_lot_id))->row();
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

	public function close($route_id, $employee_id)
	{
		$this->db->select_sum('quantity_remaining', 'remaining');
		$this->db->where('route_id', (int)$route_id);
		$row = $this->db->get('route_inventory_lots')->row();
		if ($row && (float)$row->remaining > 0.0000000001)
		{
			return FALSE;
		}
		$now = date('Y-m-d H:i:s');
		$this->db->where('route_id', (int)$route_id);
		$this->db->where('status', 'open');
		return $this->db->update('route_runs', array('status'=>'closed', 'closed_by'=>(int)$employee_id, 'closed_at'=>$now, 'updated_at'=>$now));
	}

	private function decimal($value)
	{
		return number_format((float)$value, 10, '.', '');
	}
}
