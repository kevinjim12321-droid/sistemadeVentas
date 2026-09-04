<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Catalog_products extends MY_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->ensure_schema();
	}

	private function ensure_schema()
	{
		if (!$this->db->field_exists('show_in_catalog', 'items'))
		{
			$this->db->query('ALTER TABLE `'.$this->db->dbprefix('items')."` ADD `show_in_catalog` tinyint(1) NOT NULL DEFAULT 1");
		}
	}

	//This system's licensed location count is 1; use whichever location exists
	//so the public catalog never depends on an employee session.
	public function get_default_location_id()
	{
		$row = $this->db->select('location_id')->order_by('location_id', 'asc')->limit(1)->get('locations')->row();
		return $row ? (int)$row->location_id : 1;
	}

	public function get_categories()
	{
		$this->db->select('categories.id, categories.name, COUNT(items.item_id) AS item_count', FALSE);
		$this->db->from('categories');
		$this->db->join('items', 'items.category_id = categories.id AND items.deleted = 0 AND items.is_service = 0 AND items.show_in_catalog = 1', 'inner');
		$this->db->where('categories.deleted', 0);
		$this->db->group_by('categories.id, categories.name');
		$this->db->having('item_count >', 0);
		$this->db->order_by('categories.name', 'asc');
		return $this->db->get()->result();
	}

	private function apply_filters($category_id, $search)
	{
		$this->db->where('items.deleted', 0);
		$this->db->where('items.is_service', 0);
		$this->db->where('items.show_in_catalog', 1);
		if ($category_id)
		{
			$this->db->where('items.category_id', (int)$category_id);
		}
		if ($search !== '' && $search !== NULL)
		{
			$this->db->group_start();
			$this->db->like('items.name', $search);
			$this->db->or_like('items.description', $search);
			$this->db->or_like('items.item_number', $search);
			$this->db->group_end();
		}
	}

	public function count_items($category_id = NULL, $search = '')
	{
		$this->db->from('items');
		$this->apply_filters($category_id, $search);
		return (int)$this->db->count_all_results();
	}

	public function get_items($category_id = NULL, $search = '', $limit = 24, $offset = 0)
	{
		$location_id = $this->get_default_location_id();

		$this->db->select('items.item_id, items.name, items.description, items.item_number, items.unit_price, items.main_image_id, items.cost_price, categories.name AS category_name, categories.id AS category_id, COALESCE(location_items.quantity, 0) AS quantity');
		$this->db->from('items');
		$this->db->join('categories', 'categories.id = items.category_id', 'left');
		$this->db->join('location_items', 'location_items.item_id = items.item_id AND location_items.location_id = '.(int)$location_id, 'left');
		$this->apply_filters($category_id, $search);
		$this->db->order_by('items.name', 'asc');
		$this->db->limit($limit, $offset);
		return $this->db->get()->result();
	}

	public function get_item($item_id)
	{
		$location_id = $this->get_default_location_id();

		$this->db->select('items.*, categories.name AS category_name, COALESCE(location_items.quantity, 0) AS quantity');
		$this->db->from('items');
		$this->db->join('categories', 'categories.id = items.category_id', 'left');
		$this->db->join('location_items', 'location_items.item_id = items.item_id AND location_items.location_id = '.(int)$location_id, 'left');
		$this->db->where('items.item_id', (int)$item_id);
		$this->db->where('items.deleted', 0);
		$this->db->where('items.show_in_catalog', 1);
		return $this->db->get()->row();
	}
}
