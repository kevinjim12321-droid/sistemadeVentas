<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Inventory_lot extends MY_Model
{
	const POLICY_FIFO = 'FIFO';
	const POLICY_FEFO = 'FEFO';

	const STATUS_ACTIVE = 'active';
	const STATUS_DEPLETED = 'depleted';
	const STATUS_BLOCKED = 'blocked';

	public function create_lot($data)
	{
		$required = array('item_id', 'location_id', 'quantity_initial', 'unit_cost');
		foreach ($required as $field)
		{
			if (!isset($data[$field]) || $data[$field] === '')
			{
				return FALSE;
			}
		}

		$quantity = (float)$data['quantity_initial'];
		if ($quantity <= 0)
		{
			return FALSE;
		}

		$now = date('Y-m-d H:i:s');
		$lot_data = array(
			'lot_code' => !empty($data['lot_code']) ? trim($data['lot_code']) : $this->generate_lot_code(),
			'item_id' => (int)$data['item_id'],
			'item_variation_id' => !empty($data['item_variation_id']) ? (int)$data['item_variation_id'] : NULL,
			'location_id' => (int)$data['location_id'],
			'supplier_id' => !empty($data['supplier_id']) ? (int)$data['supplier_id'] : NULL,
			'receiving_id' => !empty($data['receiving_id']) ? (int)$data['receiving_id'] : NULL,
			'receiving_line' => isset($data['receiving_line']) ? (int)$data['receiving_line'] : NULL,
			'manufactured_date' => !empty($data['manufactured_date']) ? $data['manufactured_date'] : NULL,
			'expire_date' => !empty($data['expire_date']) ? $data['expire_date'] : NULL,
			'received_at' => !empty($data['received_at']) ? $data['received_at'] : $now,
			'quantity_initial' => $this->decimal($quantity),
			'quantity_remaining' => $this->decimal($quantity),
			'unit_cost' => $this->decimal($data['unit_cost']),
			'unit_price' => isset($data['unit_price']) && $data['unit_price'] !== '' ? $this->decimal($data['unit_price']) : NULL,
			'status' => self::STATUS_ACTIVE,
			'notes' => isset($data['notes']) ? $data['notes'] : NULL,
			'created_by' => !empty($data['employee_id']) ? (int)$data['employee_id'] : NULL,
			'created_at' => $now,
			'updated_at' => $now,
		);

		$this->db->trans_begin();
		$this->db->insert('inventory_lots', $lot_data);
		$lot_id = $this->db->insert_id();

		$this->record_movement($lot_id, !empty($data['movement_type']) ? $data['movement_type'] : 'receiving', $quantity, $quantity, array(
			'receiving_id' => $lot_data['receiving_id'],
			'receiving_line' => $lot_data['receiving_line'],
			'employee_id' => $lot_data['created_by'],
			'reference_type' => isset($data['reference_type']) ? $data['reference_type'] : NULL,
			'reference_id' => isset($data['reference_id']) ? $data['reference_id'] : NULL,
			'notes' => $lot_data['notes'],
		));
		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return $lot_id;
	}

	public function get_available_lots($item_id, $variation_id, $location_id, $policy = self::POLICY_FEFO, $include_expired = FALSE)
	{
		$this->db->from('inventory_lots');
		$this->db->where('item_id', (int)$item_id);
		$this->where_variation($variation_id);
		$this->db->where('location_id', (int)$location_id);
		$this->db->where('status', self::STATUS_ACTIVE);
		$this->db->where('quantity_remaining >', 0);

		if (!$include_expired)
		{
			$this->db->group_start();
			$this->db->where('expire_date IS NULL', NULL, FALSE);
			$this->db->or_where('expire_date >=', date('Y-m-d'));
			$this->db->group_end();
		}

		$this->apply_policy_order($policy);
		return $this->db->get()->result();
	}

	/**
	 * Available stock while editing a completed sale. The original movements
	 * will be reversed when the edit is saved, so their quantities are added
	 * back only for this preview/validation query.
	 */
	public function get_available_lots_for_sale_edit($item_id, $variation_id, $location_id, $sale_id, $policy = self::POLICY_FEFO)
	{
		$this->db->from('inventory_lots');
		$this->db->where('inventory_lots.item_id', (int)$item_id);
		if ($variation_id)
		{
			$this->db->where('inventory_lots.item_variation_id', (int)$variation_id);
		}
		else
		{
			$this->db->where('inventory_lots.item_variation_id IS NULL', NULL, FALSE);
		}
		$this->db->where('inventory_lots.location_id', (int)$location_id);
		$this->db->where('inventory_lots.status !=', self::STATUS_BLOCKED);
		if (strtoupper($policy) === self::POLICY_FIFO)
		{
			$this->db->order_by('inventory_lots.received_at', 'ASC');
		}
		else
		{
			$this->db->order_by('(inventory_lots.expire_date IS NULL)', 'ASC', FALSE);
			$this->db->order_by('inventory_lots.expire_date', 'ASC');
			$this->db->order_by('inventory_lots.received_at', 'ASC');
		}
		$this->db->order_by('inventory_lots.lot_id', 'ASC');
		$lots = $this->db->get()->result();

		$this->db->select('lot_id, SUM(quantity_delta) AS quantity_delta', FALSE);
		$this->db->from('inventory_lot_movements');
		$this->db->where('sale_id', (int)$sale_id);
		$this->db->where_in('movement_type', array('sale', 'return'));
		$this->db->group_by('lot_id');
		$movement_totals = array();
		foreach ($this->db->get()->result() as $movement)
		{
			$movement_totals[(int)$movement->lot_id] = (float)$movement->quantity_delta;
		}

		$available_lots = array();
		foreach ($lots as $lot)
		{
			$movement_delta = isset($movement_totals[(int)$lot->lot_id]) ? $movement_totals[(int)$lot->lot_id] : 0;
			$lot->quantity_remaining = (float)$lot->quantity_remaining - $movement_delta;
			if ($lot->quantity_remaining > 0.0000000001)
			{
				$available_lots[] = $lot;
			}
		}
		return $available_lots;
	}

	public function get_sale_line_lot($sale_id, $sale_line)
	{
		$this->db->select('inventory_lots.*');
		$this->db->select('inventory_lot_movements.quantity_delta');
		$this->db->from('inventory_lot_movements');
		$this->db->join('inventory_lots', 'inventory_lots.lot_id = inventory_lot_movements.lot_id');
		$this->db->where('inventory_lot_movements.sale_id', (int)$sale_id);
		$this->db->where('inventory_lot_movements.sale_line', (int)$sale_line);
		$this->db->where('inventory_lot_movements.movement_type', 'sale');
		$lots = $this->db->get()->result();
		if (!$lots)
		{
			return FALSE;
		}

		$lot = $lots[0];
		$sale_quantity = 0;
		foreach ($lots as $movement_lot)
		{
			if ((int)$movement_lot->lot_id !== (int)$lot->lot_id)
			{
				return FALSE;
			}
			$sale_quantity += abs((float)$movement_lot->quantity_delta);
		}
		$lot->sale_quantity = $sale_quantity;
		return $lot;
	}

	public function get_item_lots($item_id, $location_id)
	{
		$this->db->select('inventory_lots.*, item_variations.name AS variation_name');
		$this->db->from('inventory_lots');
		$this->db->join('item_variations', 'item_variations.id = inventory_lots.item_variation_id', 'left');
		$this->db->where('inventory_lots.item_id', (int)$item_id);
		$this->db->where('inventory_lots.location_id', (int)$location_id);
		$this->db->order_by('inventory_lots.received_at', 'ASC');
		$this->db->order_by('inventory_lots.lot_id', 'ASC');
		return $this->db->get()->result();
	}

	public function get_lot($lot_id)
	{
		$this->db->select('inventory_lots.*, items.name AS item_name, item_variations.name AS variation_name, locations.name AS location_name, suppliers.company_name AS supplier_name');
		$this->db->from('inventory_lots');
		$this->db->join('items', 'items.item_id = inventory_lots.item_id');
		$this->db->join('item_variations', 'item_variations.id = inventory_lots.item_variation_id', 'left');
		$this->db->join('locations', 'locations.location_id = inventory_lots.location_id', 'left');
		$this->db->join('suppliers', 'suppliers.person_id = inventory_lots.supplier_id', 'left');
		$this->db->where('inventory_lots.lot_id', (int)$lot_id);
		return $this->db->get()->row();
	}

	public function get_lot_movements($lot_id)
	{
		$this->db->select('inventory_lot_movements.*, people.first_name, people.last_name');
		$this->db->from('inventory_lot_movements');
		$this->db->join('people', 'people.person_id = inventory_lot_movements.employee_id', 'left');
		$this->db->where('inventory_lot_movements.lot_id', (int)$lot_id);
		$this->db->order_by('inventory_lot_movements.occurred_at', 'DESC');
		$this->db->order_by('inventory_lot_movements.movement_id', 'DESC');
		return $this->db->get()->result();
	}

	public function get_lot_total($item_id, $variation_id, $location_id)
	{
		$this->db->select_sum('quantity_remaining', 'total');
		$this->db->from('inventory_lots');
		$this->db->where('item_id', (int)$item_id);
		$this->where_variation($variation_id);
		$this->db->where('location_id', (int)$location_id);
		$row = $this->db->get()->row();
		return $row && $row->total !== NULL ? (float)$row->total : 0;
	}

	/**
	 * Preview the lots that would be consumed without changing inventory.
	 * The ordering intentionally matches allocate() so cart pricing and the
	 * final inventory movement use the same FIFO/FEFO decision.
	 */
	public function preview_allocation($item_id, $variation_id, $location_id, $quantity, $policy = self::POLICY_FEFO, $skip_quantity = 0)
	{
		$quantity = (float)$quantity;
		$skip_quantity = max(0, (float)$skip_quantity);
		if ($quantity <= 0)
		{
			return array();
		}

		$this->db->from('inventory_lots');
		$this->db->where('item_id', (int)$item_id);
		$this->where_variation($variation_id);
		$this->db->where('location_id', (int)$location_id);
		$this->db->where('status', self::STATUS_ACTIVE);
		$this->db->where('quantity_remaining >', 0);
		$this->db->group_start();
		$this->db->where('expire_date IS NULL', NULL, FALSE);
		$this->db->or_where('expire_date >=', date('Y-m-d'));
		$this->db->group_end();
		$this->apply_policy_order($policy);
		$lots = $this->db->get()->result();

		$remaining = $quantity;
		$preview = array();
		foreach ($lots as $lot)
		{
			$available = (float)$lot->quantity_remaining;
			if ($skip_quantity >= $available)
			{
				$skip_quantity -= $available;
				continue;
			}

			$available -= $skip_quantity;
			$skip_quantity = 0;
			$taken = min($available, $remaining);
			$preview[] = array(
				'lot_id' => (int)$lot->lot_id,
				'lot_code' => $lot->lot_code,
				'quantity' => $taken,
				'unit_price' => $lot->unit_price,
			);
			$remaining -= $taken;
			if ($remaining <= 0.0000000001)
			{
				break;
			}
		}

		return $preview;
	}

	public function adjust_lot($lot_id, $quantity_delta, $context = array())
	{
		$quantity_delta = (float)$quantity_delta;
		if (abs($quantity_delta) <= 0.0000000001)
		{
			return TRUE;
		}

		$this->db->trans_begin();
		$sql = "SELECT * FROM {$this->db->dbprefix('inventory_lots')} WHERE lot_id = ? FOR UPDATE";
		$lot = $this->db->query($sql, array((int)$lot_id))->row();
		if (!$lot)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$new_balance = (float)$lot->quantity_remaining + $quantity_delta;
		if ($new_balance < -0.0000000001)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->where('lot_id', (int)$lot_id);
		$this->db->update('inventory_lots', array(
			'quantity_remaining' => $this->decimal(max(0, $new_balance)),
			'status' => $new_balance <= 0.0000000001 ? self::STATUS_DEPLETED : self::STATUS_ACTIVE,
			'updated_at' => date('Y-m-d H:i:s'),
		));
		$this->record_movement($lot_id, 'adjustment', $quantity_delta, max(0, $new_balance), $context);

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return TRUE;
	}

	public function allocate($item_id, $variation_id, $location_id, $quantity, $context = array(), $policy = self::POLICY_FEFO)
	{
		$quantity = (float)$quantity;
		if ($quantity <= 0)
		{
			return array();
		}

		$this->db->trans_begin();
		$lots = $this->get_locked_lots($item_id, $variation_id, $location_id, $policy);
		$remaining = $quantity;
		$allocations = array();

		foreach ($lots as $lot)
		{
			if ($remaining <= 0.0000000001)
			{
				break;
			}

			$available = (float)$lot->quantity_remaining;
			$taken = min($available, $remaining);
			$new_balance = $available - $taken;
			$status = $new_balance <= 0.0000000001 ? self::STATUS_DEPLETED : self::STATUS_ACTIVE;

			$this->db->where('lot_id', $lot->lot_id);
			$this->db->update('inventory_lots', array(
				'quantity_remaining' => $this->decimal(max(0, $new_balance)),
				'status' => $status,
				'updated_at' => date('Y-m-d H:i:s'),
			));

			$this->record_movement($lot->lot_id, isset($context['movement_type']) ? $context['movement_type'] : 'sale', -$taken, max(0, $new_balance), $context);
			$allocations[] = array(
				'lot_id' => (int)$lot->lot_id,
				'lot_code' => $lot->lot_code,
				'quantity' => $this->decimal($taken),
				'unit_cost' => $lot->unit_cost,
				'expire_date' => $lot->expire_date,
			);
			$remaining -= $taken;
		}

		if ($remaining > 0.0000000001)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return $allocations;
	}

	public function allocate_from_lot($lot_id, $item_id, $variation_id, $location_id, $quantity, $context = array())
	{
		$quantity = (float)$quantity;
		if ($quantity <= 0)
		{
			return array();
		}

		$this->db->trans_begin();
		$sql = "SELECT * FROM {$this->db->dbprefix('inventory_lots')} WHERE lot_id = ? FOR UPDATE";
		$lot = $this->db->query($sql, array((int)$lot_id))->row();
		$expected_variation = $variation_id ? (int)$variation_id : NULL;
		$lot_variation = $lot && $lot->item_variation_id ? (int)$lot->item_variation_id : NULL;

		if (!$lot || (int)$lot->item_id !== (int)$item_id || $lot_variation !== $expected_variation ||
			(int)$lot->location_id !== (int)$location_id || $lot->status !== self::STATUS_ACTIVE ||
			($lot->expire_date && $lot->expire_date < date('Y-m-d')) ||
			(float)$lot->quantity_remaining + 0.0000000001 < $quantity)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$new_balance = (float)$lot->quantity_remaining - $quantity;
		$this->db->where('lot_id', (int)$lot->lot_id);
		$this->db->update('inventory_lots', array(
			'quantity_remaining' => $this->decimal(max(0, $new_balance)),
			'status' => $new_balance <= 0.0000000001 ? self::STATUS_DEPLETED : self::STATUS_ACTIVE,
			'updated_at' => date('Y-m-d H:i:s'),
		));

		$this->record_movement($lot->lot_id, isset($context['movement_type']) ? $context['movement_type'] : 'sale', -$quantity, max(0, $new_balance), $context);
		$allocation = array(array(
			'lot_id' => (int)$lot->lot_id,
			'lot_code' => $lot->lot_code,
			'quantity' => $this->decimal($quantity),
			'unit_cost' => $lot->unit_cost,
			'unit_price' => $lot->unit_price,
			'expire_date' => $lot->expire_date,
		));

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->trans_commit();
		return $allocation;
	}

	public function can_rebuild_receiving($receiving_id)
	{
		$this->db->select('inventory_lot_movements.movement_id');
		$this->db->from('inventory_lot_movements');
		$this->db->join('inventory_lots', 'inventory_lots.lot_id = inventory_lot_movements.lot_id');
		$this->db->where('inventory_lots.receiving_id', (int)$receiving_id);
		$this->db->where_not_in('inventory_lot_movements.movement_type', array('receiving', 'transfer_in'));
		$this->db->limit(1);
		return $this->db->get()->num_rows() === 0;
	}

	public function delete_receiving_lots($receiving_id)
	{
		if (!$this->can_rebuild_receiving($receiving_id))
		{
			return FALSE;
		}

		// Restore source lots used by a transfer before deleting its destination lots.
		$sql = "SELECT m.movement_id, m.lot_id, m.quantity_delta, l.quantity_remaining
			FROM {$this->db->dbprefix('inventory_lot_movements')} m
			INNER JOIN {$this->db->dbprefix('inventory_lots')} l ON l.lot_id = m.lot_id
			WHERE m.receiving_id = ? AND m.movement_type = 'transfer_out'
			FOR UPDATE";
		$transfer_out = $this->db->query($sql, array((int)$receiving_id))->result();
		foreach ($transfer_out as $movement)
		{
			$restored_balance = (float)$movement->quantity_remaining - (float)$movement->quantity_delta;
			$this->db->where('lot_id', $movement->lot_id);
			$this->db->update('inventory_lots', array(
				'quantity_remaining' => $this->decimal($restored_balance),
				'status' => self::STATUS_ACTIVE,
				'updated_at' => date('Y-m-d H:i:s'),
			));
		}
		if ($transfer_out)
		{
			$this->db->where('receiving_id', (int)$receiving_id);
			$this->db->where('movement_type', 'transfer_out');
			$this->db->delete('inventory_lot_movements');
		}

		$this->db->select('lot_id');
		$this->db->from('inventory_lots');
		$this->db->where('receiving_id', (int)$receiving_id);
		$lot_ids = array_column($this->db->get()->result_array(), 'lot_id');

		if (!$lot_ids)
		{
			return TRUE;
		}

		$this->db->where_in('lot_id', $lot_ids);
		$this->db->delete('inventory_lot_movements');
		$this->db->where_in('lot_id', $lot_ids);
		return $this->db->delete('inventory_lots');
	}

	public function restore_sale_allocations($sale_id)
	{
		$sql = "SELECT m.movement_id, m.lot_id, m.quantity_delta, l.quantity_remaining
			FROM {$this->db->dbprefix('inventory_lot_movements')} m
			INNER JOIN {$this->db->dbprefix('inventory_lots')} l ON l.lot_id = m.lot_id
			WHERE m.sale_id = ? AND m.movement_type IN ('sale', 'return')
			FOR UPDATE";
		$movements = $this->db->query($sql, array((int)$sale_id))->result();

		foreach ($movements as $movement)
		{
			// Reverse the original delta: sales are negative and returns are positive.
			$restored_balance = (float)$movement->quantity_remaining - (float)$movement->quantity_delta;
			if ($restored_balance < -0.0000000001)
			{
				return FALSE;
			}

			$this->db->where('lot_id', $movement->lot_id);
			$this->db->update('inventory_lots', array(
				'quantity_remaining' => $this->decimal(max(0, $restored_balance)),
				'status' => $restored_balance <= 0.0000000001 ? self::STATUS_DEPLETED : self::STATUS_ACTIVE,
				'updated_at' => date('Y-m-d H:i:s'),
			));
		}

		if ($movements)
		{
			$this->db->where('sale_id', (int)$sale_id);
			$this->db->where_in('movement_type', array('sale', 'return'));
			$this->db->delete('inventory_lot_movements');
		}

		return $this->db->trans_status();
	}

	public function return_to_sale($original_sale_id, $item_id, $variation_id, $location_id, $quantity, $context = array())
	{
		$quantity = (float)$quantity;
		if ($quantity <= 0)
		{
			return array();
		}

		$variation_sql = $variation_id ? 'l.item_variation_id = '.(int)$variation_id : 'l.item_variation_id IS NULL';
		$sql = "SELECT l.lot_id, l.lot_code, l.unit_cost, l.expire_date, l.quantity_remaining,
			ABS(SUM(CASE WHEN m.movement_type = 'sale' THEN m.quantity_delta ELSE 0 END)) AS sold_quantity,
			(SELECT COALESCE(SUM(r.quantity_delta), 0)
			 FROM {$this->db->dbprefix('inventory_lot_movements')} r
			 WHERE r.lot_id = l.lot_id AND r.movement_type = 'return'
			 AND r.reference_type = 'sale' AND r.reference_id = ?) AS returned_quantity
			FROM {$this->db->dbprefix('inventory_lot_movements')} m
			INNER JOIN {$this->db->dbprefix('inventory_lots')} l ON l.lot_id = m.lot_id
			WHERE m.sale_id = ? AND m.movement_type = 'sale'
			AND l.item_id = ? AND {$variation_sql} AND l.location_id = ?
			GROUP BY l.lot_id, l.lot_code, l.unit_cost, l.expire_date, l.quantity_remaining
			ORDER BY MIN(m.movement_id) ASC
			FOR UPDATE";
		$lots = $this->db->query($sql, array((string)(int)$original_sale_id, (int)$original_sale_id, (int)$item_id, (int)$location_id))->result();
		$remaining = $quantity;
		$returned = array();

		foreach ($lots as $lot)
		{
			if ($remaining <= 0.0000000001)
			{
				break;
			}

			$return_capacity = max(0, (float)$lot->sold_quantity - (float)$lot->returned_quantity);
			$restored = min($return_capacity, $remaining);
			if ($restored <= 0)
			{
				continue;
			}

			$new_balance = (float)$lot->quantity_remaining + $restored;
			$this->db->where('lot_id', $lot->lot_id);
			$this->db->update('inventory_lots', array(
				'quantity_remaining' => $this->decimal($new_balance),
				'status' => self::STATUS_ACTIVE,
				'updated_at' => date('Y-m-d H:i:s'),
			));

			$movement_context = $context;
			$movement_context['reference_type'] = 'sale';
			$movement_context['reference_id'] = (string)(int)$original_sale_id;
			$this->record_movement($lot->lot_id, 'return', $restored, $new_balance, $movement_context);
			$returned[] = array(
				'lot_id' => (int)$lot->lot_id,
				'lot_code' => $lot->lot_code,
				'quantity' => $this->decimal($restored),
				'unit_cost' => $lot->unit_cost,
				'expire_date' => $lot->expire_date,
			);
			$remaining -= $restored;
		}

		return $remaining > 0.0000000001 || $this->db->trans_status() === FALSE ? FALSE : $returned;
	}

	public function transfer($item_id, $variation_id, $from_location_id, $to_location_id, $quantity, $context = array(), $policy = self::POLICY_FEFO)
	{
		$quantity = (float)$quantity;
		if ($quantity <= 0 || (int)$from_location_id === (int)$to_location_id)
		{
			return FALSE;
		}

		$lots = $this->get_locked_lots($item_id, $variation_id, $from_location_id, $policy);
		$remaining = $quantity;
		$transferred = array();
		$now = date('Y-m-d H:i:s');

		foreach ($lots as $lot)
		{
			if ($remaining <= 0.0000000001)
			{
				break;
			}

			$taken = min((float)$lot->quantity_remaining, $remaining);
			$source_balance = (float)$lot->quantity_remaining - $taken;
			$this->db->where('lot_id', $lot->lot_id);
			$this->db->update('inventory_lots', array(
				'quantity_remaining' => $this->decimal(max(0, $source_balance)),
				'status' => $source_balance <= 0.0000000001 ? self::STATUS_DEPLETED : self::STATUS_ACTIVE,
				'updated_at' => $now,
			));

			$movement_context = $context;
			$movement_context['reference_type'] = 'receiving';
			$movement_context['reference_id'] = isset($context['receiving_id']) ? (string)(int)$context['receiving_id'] : NULL;
			$this->record_movement($lot->lot_id, 'transfer_out', -$taken, max(0, $source_balance), $movement_context);

			$this->db->insert('inventory_lots', array(
				'lot_code' => $lot->lot_code,
				'item_id' => (int)$lot->item_id,
				'item_variation_id' => $lot->item_variation_id ? (int)$lot->item_variation_id : NULL,
				'location_id' => (int)$to_location_id,
				'supplier_id' => $lot->supplier_id ? (int)$lot->supplier_id : NULL,
				'receiving_id' => !empty($context['receiving_id']) ? (int)$context['receiving_id'] : NULL,
				'receiving_line' => isset($context['receiving_line']) ? (int)$context['receiving_line'] : NULL,
				'manufactured_date' => $lot->manufactured_date,
				'expire_date' => $lot->expire_date,
				'received_at' => $lot->received_at,
				'quantity_initial' => $this->decimal($taken),
				'quantity_remaining' => $this->decimal($taken),
				'unit_cost' => $lot->unit_cost,
				'unit_price' => $lot->unit_price,
				'status' => self::STATUS_ACTIVE,
				'notes' => isset($context['notes']) ? $context['notes'] : NULL,
				'created_by' => !empty($context['employee_id']) ? (int)$context['employee_id'] : NULL,
				'created_at' => $now,
				'updated_at' => $now,
			));
			$destination_lot_id = $this->db->insert_id();
			$this->record_movement($destination_lot_id, 'transfer_in', $taken, $taken, $movement_context);

			$transferred[] = array('source_lot_id' => (int)$lot->lot_id, 'destination_lot_id' => (int)$destination_lot_id, 'lot_code' => $lot->lot_code, 'quantity' => $this->decimal($taken));
			$remaining -= $taken;
		}

		return $remaining > 0.0000000001 || $this->db->trans_status() === FALSE ? FALSE : $transferred;
	}

	private function get_locked_lots($item_id, $variation_id, $location_id, $policy)
	{
		$item_id = (int)$item_id;
		$location_id = (int)$location_id;
		$variation_sql = $variation_id ? 'item_variation_id = '.(int)$variation_id : 'item_variation_id IS NULL';
		$order_sql = strtoupper($policy) === self::POLICY_FIFO
			? 'received_at ASC, lot_id ASC'
			: '(expire_date IS NULL) ASC, expire_date ASC, received_at ASC, lot_id ASC';

		$sql = "SELECT * FROM {$this->db->dbprefix('inventory_lots')}
			WHERE item_id = {$item_id}
			AND {$variation_sql}
			AND location_id = {$location_id}
			AND status = 'active'
			AND quantity_remaining > 0
			AND (expire_date IS NULL OR expire_date >= CURDATE())
			ORDER BY {$order_sql}
			FOR UPDATE";

		return $this->db->query($sql)->result();
	}

	private function record_movement($lot_id, $type, $delta, $balance, $context)
	{
		return $this->db->insert('inventory_lot_movements', array(
			'lot_id' => (int)$lot_id,
			'movement_type' => $type,
			'quantity_delta' => $this->decimal($delta),
			'balance_after' => $this->decimal($balance),
			'sale_id' => !empty($context['sale_id']) ? (int)$context['sale_id'] : NULL,
			'sale_line' => isset($context['sale_line']) ? (int)$context['sale_line'] : NULL,
			'receiving_id' => !empty($context['receiving_id']) ? (int)$context['receiving_id'] : NULL,
			'receiving_line' => isset($context['receiving_line']) ? (int)$context['receiving_line'] : NULL,
			'reference_type' => isset($context['reference_type']) ? $context['reference_type'] : NULL,
			'reference_id' => isset($context['reference_id']) ? (string)$context['reference_id'] : NULL,
			'employee_id' => !empty($context['employee_id']) ? (int)$context['employee_id'] : NULL,
			'occurred_at' => date('Y-m-d H:i:s'),
			'notes' => isset($context['notes']) ? $context['notes'] : NULL,
		));
	}

	private function apply_policy_order($policy)
	{
		if (strtoupper($policy) === self::POLICY_FIFO)
		{
			$this->db->order_by('received_at', 'ASC');
		}
		else
		{
			$this->db->order_by('(expire_date IS NULL)', 'ASC', FALSE);
			$this->db->order_by('expire_date', 'ASC');
			$this->db->order_by('received_at', 'ASC');
		}
		$this->db->order_by('lot_id', 'ASC');
	}

	private function where_variation($variation_id)
	{
		if ($variation_id)
		{
			$this->db->where('item_variation_id', (int)$variation_id);
		}
		else
		{
			$this->db->where('item_variation_id IS NULL', NULL, FALSE);
		}
	}

	private function generate_lot_code()
	{
		return 'LOT-'.date('Ymd-His').'-'.random_int(1000, 9999);
	}

	private function decimal($value)
	{
		return number_format((float)$value, 10, '.', '');
	}
}
