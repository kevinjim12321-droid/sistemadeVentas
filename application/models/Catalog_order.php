<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Catalog_order extends MY_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->ensure_schema();
	}

	private function ensure_schema()
	{
		$orders = $this->db->dbprefix('catalog_orders');
		$this->db->query("CREATE TABLE IF NOT EXISTS `{$orders}` (
			`catalog_order_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`customer_name` varchar(150) NOT NULL,
			`customer_phone` varchar(50) NOT NULL,
			`notes` text,
			`status` varchar(20) NOT NULL DEFAULT 'pending',
			`total` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			`created_at` datetime NOT NULL,
			`completed_by` int(10) DEFAULT NULL,
			`completed_at` datetime DEFAULT NULL,
			PRIMARY KEY (`catalog_order_id`),
			KEY `catalog_orders_status` (`status`,`created_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

		$items = $this->db->dbprefix('catalog_order_items');
		$this->db->query("CREATE TABLE IF NOT EXISTS `{$items}` (
			`catalog_order_item_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`catalog_order_id` bigint(20) unsigned NOT NULL,
			`item_id` int(10) NOT NULL,
			`item_name` varchar(255) NOT NULL,
			`quantity` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			`unit_price` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			`subtotal` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
			PRIMARY KEY (`catalog_order_item_id`),
			KEY `catalog_order_items_order` (`catalog_order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");
	}

	private function decimal($value)
	{
		return number_format((float)$value, 10, '.', '');
	}

	/**
	 * $cart is array(item_id => quantity). Prices/names are looked up fresh
	 * from the items table so the order always reflects what is really being
	 * sold, not a possibly stale price the visitor had cached in their cart.
	 */
	public function create_order($customer_name, $customer_phone, $notes, array $cart)
	{
		$customer_name = trim((string)$customer_name);
		$customer_phone = trim((string)$customer_phone);
		$cart = array_filter($cart, function ($qty) { return (float)$qty > 0; });

		if ($customer_name === '' || $customer_phone === '' || !$cart)
		{
			return FALSE;
		}

		$item_ids = array_map('intval', array_keys($cart));
		$this->db->where('deleted', 0);
		$this->db->where('is_service', 0);
		$this->db->where('show_in_catalog', 1);
		$this->db->where_in('item_id', $item_ids);
		$items = $this->db->get('items')->result();
		if (!$items)
		{
			return FALSE;
		}

		$this->db->trans_begin();
		$now = date('Y-m-d H:i:s');
		$total = 0;
		$rows = array();
		foreach ($items as $item)
		{
			$quantity = (float)$cart[(string)$item->item_id];
			if ($quantity <= 0)
			{
				continue;
			}
			$subtotal = $quantity * (float)$item->unit_price;
			$total += $subtotal;
			$rows[] = array(
				'item_id' => (int)$item->item_id,
				'item_name' => $item->name,
				'quantity' => $this->decimal($quantity),
				'unit_price' => $this->decimal($item->unit_price),
				'subtotal' => $this->decimal($subtotal),
			);
		}

		if (!$rows)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		$this->db->insert('catalog_orders', array(
			'customer_name' => mb_substr($customer_name, 0, 150),
			'customer_phone' => mb_substr($customer_phone, 0, 50),
			'notes' => $notes !== NULL && trim($notes) !== '' ? trim($notes) : NULL,
			'status' => 'pending',
			'total' => $this->decimal($total),
			'created_at' => $now,
		));
		$order_id = $this->db->insert_id();
		if (!$order_id)
		{
			$this->db->trans_rollback();
			return FALSE;
		}

		foreach ($rows as $row)
		{
			$row['catalog_order_id'] = $order_id;
			$this->db->insert('catalog_order_items', $row);
		}

		if ($this->db->trans_status() === FALSE)
		{
			$this->db->trans_rollback();
			return FALSE;
		}
		$this->db->trans_commit();
		return (int)$order_id;
	}

	public function get_orders($status = NULL)
	{
		$this->db->from('catalog_orders');
		if ($status)
		{
			$this->db->where('status', $status);
		}
		$this->db->order_by('created_at', 'desc');
		return $this->db->get()->result();
	}

	public function get_order($order_id)
	{
		return $this->db->get_where('catalog_orders', array('catalog_order_id' => (int)$order_id))->row();
	}

	public function get_order_items($order_id)
	{
		$this->db->where('catalog_order_id', (int)$order_id);
		$this->db->order_by('catalog_order_item_id', 'asc');
		return $this->db->get('catalog_order_items')->result();
	}

	public function update_status($order_id, $status, $employee_id)
	{
		if (!in_array($status, array('pending', 'completed', 'cancelled'), TRUE))
		{
			return FALSE;
		}
		$data = array('status' => $status);
		if ($status !== 'pending')
		{
			$data['completed_by'] = (int)$employee_id;
			$data['completed_at'] = date('Y-m-d H:i:s');
		}
		else
		{
			$data['completed_by'] = NULL;
			$data['completed_at'] = NULL;
		}
		$this->db->where('catalog_order_id', (int)$order_id);
		return $this->db->update('catalog_orders', $data);
	}

	public function count_pending()
	{
		$this->db->where('status', 'pending');
		return (int)$this->db->count_all_results('catalog_orders');
	}
}
