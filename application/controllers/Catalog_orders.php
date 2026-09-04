<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once ('Secure_area.php');

class Catalog_orders extends Secure_area
{
	function __construct()
	{
		parent::__construct('sales');
		$this->load->model('Catalog_order');
	}

	function index()
	{
		$status = $this->input->get('status') ? $this->input->get('status') : 'pending';
		$status = in_array($status, array('pending', 'completed', 'cancelled', 'all'), TRUE) ? $status : 'pending';
		$data['status'] = $status;
		$data['orders'] = $this->Catalog_order->get_orders($status === 'all' ? NULL : $status);
		$this->load->view('catalog_orders/index', $data);
	}

	function view($order_id)
	{
		$order = $this->Catalog_order->get_order($order_id);
		if (!$order)
		{
			show_404();
		}
		$data['order'] = $order;
		$data['items'] = $this->Catalog_order->get_order_items($order_id);
		$this->load->view('catalog_orders/view', $data);
	}

	function mark_status($order_id)
	{
		$status = $this->input->post('status');
		$result = $this->Catalog_order->update_status($order_id, $status, $this->Employee->get_logged_in_employee_info()->person_id);
		$this->session->set_flashdata('catalog_order_success', $result ? 'El estado del pedido se actualizó.' : 'No se pudo actualizar el estado.');
		redirect('catalog_orders/view/'.$order_id);
	}

	//Loads the order's items into the operator's own POS sale cart (current
	//prices, normal item validation) and sends them to Ventas to finish the
	//sale as they normally would. Does not touch the catalog order's status.
	function load_into_pos($order_id)
	{
		$order = $this->Catalog_order->get_order($order_id);
		if (!$order)
		{
			show_404();
		}
		require_once (APPPATH.'models/cart/PHPPOSCartSale.php');
		$cart = PHPPOSCartSale::get_instance('sale');
		$cart->destroy();
		foreach ($this->Catalog_order->get_order_items($order_id) as $line)
		{
			$item_to_add = new PHPPOSCartItemSale(array('cart' => $cart, 'scan' => (int)$line->item_id, 'quantity' => (float)$line->quantity));
			if ($item_to_add->item_id)
			{
				$cart->add_item($item_to_add);
			}
		}
		$cart->save();
		redirect('sales');
	}
}
