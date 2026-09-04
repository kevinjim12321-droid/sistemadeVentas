<?php
defined('BASEPATH') OR exit('No direct script access allowed');

//Public product catalog. Deliberately does NOT extend Secure_area: it must be
//reachable without a session (to share the link with customers). When the
//visitor does have a staff session, the view adds cost price and exact stock.
class Catalog extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Catalog_products');
	}

	function index()
	{
		$per_page = 24;
		$page = max(1, (int)$this->input->get('page'));
		$category_id = $this->input->get('category') ? (int)$this->input->get('category') : NULL;
		$search = trim((string)$this->input->get('q'));

		$data['categories'] = $this->Catalog_products->get_categories();
		$data['category_id'] = $category_id;
		$data['search'] = $search;
		$data['items'] = $this->Catalog_products->get_items($category_id, $search, $per_page, ($page - 1) * $per_page);
		$data['total_items'] = $this->Catalog_products->count_items($category_id, $search);
		$data['total_pages'] = max(1, (int)ceil($data['total_items'] / $per_page));
		$data['page'] = $page;
		$data['is_staff'] = (bool)$this->Employee->is_logged_in();
		$data['location'] = $this->db->get_where('locations', array('location_id' => $this->Catalog_products->get_default_location_id()))->row();

		$this->load->view('catalog/index', $data);
	}
}
