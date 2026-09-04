<?php
defined('BASEPATH') OR exit('No direct script access allowed');

//Public product catalog. Deliberately does NOT extend Secure_area: it must be
//reachable without a session (to share the link with customers). When the
//visitor does have a staff session, the view adds cost price and exact stock.
//The pre-checkout cart lives in the visitor's own PHP session (catalog_cart),
//never in a database table -- only a submitted order is persisted.
class Catalog extends MY_Controller
{
	function __construct()
	{
		parent::__construct();
		$this->load->model('Catalog_products');
		$this->load->model('Catalog_order');
	}

	private function get_cart()
	{
		$cart = $this->session->userdata('catalog_cart');
		return is_array($cart) ? $cart : array();
	}

	private function save_cart($cart)
	{
		$cart = array_filter($cart, function ($qty) { return (float)$qty > 0; });
		$this->session->set_userdata('catalog_cart', $cart);
	}

	private function cart_count($cart)
	{
		$count = 0;
		foreach ($cart as $qty) { $count += (float)$qty; }
		return $count;
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
		$data['cart_count'] = $this->cart_count($this->get_cart());
		$data['query_string'] = $this->input->server('QUERY_STRING');

		$this->load->view('catalog/index', $data);
	}

	function add_to_cart($item_id)
	{
		$item = $this->Catalog_products->get_item($item_id);
		$quantity = (float)$this->input->post('quantity');
		if ($item && $quantity > 0)
		{
			$cart = $this->get_cart();
			$key = (string)(int)$item_id;
			$cart[$key] = (isset($cart[$key]) ? (float)$cart[$key] : 0) + $quantity;
			$this->save_cart($cart);
			$this->session->set_flashdata('catalog_success', 'Se agregó '.H($item->name).' al pedido.');
		}
		$back = $this->input->post('redirect_qs');
		redirect('catalog'.($back ? '?'.$back : ''));
	}

	function cart()
	{
		$cart = $this->get_cart();
		$data['lines'] = array();
		$data['total'] = 0;
		if ($cart)
		{
			$this->db->where_in('item_id', array_map('intval', array_keys($cart)));
			$this->db->where('deleted', 0);
			$items = $this->db->get('items')->result();
			foreach ($items as $item)
			{
				$key = (string)(int)$item->item_id;
				if (!isset($cart[$key])) continue;
				$quantity = (float)$cart[$key];
				$subtotal = $quantity * (float)$item->unit_price;
				$data['lines'][] = (object)array(
					'item_id' => (int)$item->item_id,
					'name' => $item->name,
					'unit_price' => (float)$item->unit_price,
					'quantity' => $quantity,
					'subtotal' => $subtotal,
				);
				$data['total'] += $subtotal;
			}
		}
		$this->load->view('catalog/cart', $data);
	}

	function update_cart_item($item_id)
	{
		$cart = $this->get_cart();
		$quantity = (float)$this->input->post('quantity');
		$key = (string)(int)$item_id;
		if ($quantity > 0)
		{
			$cart[$key] = $quantity;
		}
		else
		{
			unset($cart[$key]);
		}
		$this->save_cart($cart);
		redirect('catalog/cart');
	}

	function checkout()
	{
		$cart = $this->get_cart();
		$order_id = $this->Catalog_order->create_order(
			$this->input->post('customer_name'),
			$this->input->post('customer_phone'),
			$this->input->post('notes'),
			$cart
		);
		if (!$order_id)
		{
			$this->session->set_flashdata('catalog_error', 'No se pudo enviar el pedido. Verifique su nombre, teléfono y que el pedido tenga al menos un producto.');
			redirect('catalog/cart');
		}
		$this->save_cart(array());
		redirect('catalog/order_confirmation/'.$order_id);
	}

	function order_confirmation($order_id)
	{
		$order = $this->Catalog_order->get_order($order_id);
		if (!$order)
		{
			show_404();
		}
		$data['order'] = $order;
		$data['items'] = $this->Catalog_order->get_order_items($order_id);
		$this->load->view('catalog/order_confirmation', $data);
	}
}
