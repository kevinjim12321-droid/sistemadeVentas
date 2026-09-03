<?php
require_once ('Secure_area.php');

class Routes extends Secure_area
{
	public function __construct()
	{
		parent::__construct('receivings');
		$this->load->model('Route');
		$this->lang->load('routes');
		$this->lang->load('items');
	}

	public function index()
	{
		$location_id = $this->Employee->get_logged_in_employee_current_location_id();
		$data['routes'] = $this->Route->get_all_for_location($location_id);
		$data['employees'] = $this->Employee->get_all(0, 10000, 0, 'last_name', 'asc')->result();
		$this->load->view('routes/index', $data);
	}

	public function create()
	{
		$name = trim((string)$this->input->post('name'));
		$route_date = $this->input->post('route_date');
		if ($name === '' || !$route_date)
		{
			$this->session->set_flashdata('route_error', lang('routes_required_fields'));
			redirect('routes');
		}

		$route_id = $this->Route->create(array(
			'name' => $name,
			'route_date' => $route_date,
			'location_id' => $this->Employee->get_logged_in_employee_current_location_id(),
			'employee_id' => (int)$this->input->post('employee_id'),
			'notes' => $this->input->post('notes'),
			'opened_by' => $this->Employee->get_logged_in_employee_info()->person_id,
		));
		if (!$route_id)
		{
			$this->session->set_flashdata('route_error', lang('routes_create_error'));
			redirect('routes');
		}
		redirect('routes/view/'.$route_id);
	}

	public function view($route_id)
	{
		$route = $this->Route->get_info($route_id);
		if (!$route || (int)$route->location_id !== (int)$this->Employee->get_logged_in_employee_current_location_id())
		{
			show_404();
		}
		$data['route'] = $route;
		$data['route_inventory'] = $this->Route->get_inventory($route_id);
		$data['warehouse_lots'] = $route->status === 'open' ? $this->Route->get_available_warehouse_lots($route->location_id) : array();
		$data['sales_summary'] = $this->Route->get_sales_summary($route_id);
		$data['route_sales'] = $this->Route->get_sales_history($route_id);
		$data['payment_summary'] = $this->Route->get_route_payment_summary($data['route_sales']);
		$data['route_purchases'] = $this->Route->get_purchase_history($route_id);
		$this->load->view('routes/view', $data);
	}

	public function sell($route_id)
	{
		$route = $this->Route->get_info($route_id);
		if (!$route || $route->status !== 'open' || (int)$route->location_id !== (int)$this->Employee->get_logged_in_employee_current_location_id())
		{
			show_404();
		}
		require_once (APPPATH.'models/cart/PHPPOSCartSale.php');
		$cart = PHPPOSCartSale::get_instance('sale');
		$cart->destroy();
		$cart->route_id = (int)$route->route_id;
		$cart->route_name = $route->name;
		$cart->save();
		redirect('sales');
	}

	public function purchase($route_id)
	{
		$route = $this->Route->get_info($route_id);
		if (!$route || $route->status !== 'open' || (int)$route->location_id !== (int)$this->Employee->get_logged_in_employee_current_location_id())
		{
			show_404();
		}
		//Receivings loads all cart dependencies. Stage the selected route in the
		//session and let that controller initialize its own cart after redirecting.
		$this->session->set_userdata('route_purchase_id', (int)$route->route_id);
		@file_put_contents(FCPATH.'rp_debug.log', date('Y-m-d H:i:s').' Routes::purchase set route_purchase_id='.(int)$route->route_id."\n", FILE_APPEND);
		redirect('receivings');
	}

	//TEMP: dumps the route purchase diagnostics log. Remove once resolved.
	public function debug_purchase()
	{
		$this->output->set_content_type('text/plain');
		$file = FCPATH.'rp_debug.log';
		$this->output->set_output(is_file($file) ? file_get_contents($file) : 'rp_debug.log does not exist yet');
	}

	public function load_lot($route_id)
	{
		$route = $this->Route->get_info($route_id);
		if (!$route || (int)$route->location_id !== (int)$this->Employee->get_logged_in_employee_current_location_id())
		{
			show_404();
		}
		$result = $this->Route->load_from_warehouse(
			$route_id,
			(int)$this->input->post('source_lot_id'),
			(float)$this->input->post('quantity'),
			$this->Employee->get_logged_in_employee_info()->person_id,
			$this->input->post('notes')
		);
		$this->session->set_flashdata($result ? 'route_success' : 'route_error', $result ? lang('routes_load_success') : lang('routes_load_error'));
		redirect('routes/view/'.$route_id);
	}

	public function return_lot($route_id)
	{
		$route = $this->Route->get_info($route_id);
		if (!$route || (int)$route->location_id !== (int)$this->Employee->get_logged_in_employee_current_location_id())
		{
			show_404();
		}
		$result = $this->Route->return_to_warehouse(
			$route_id,
			(int)$this->input->post('route_inventory_lot_id'),
			(float)$this->input->post('quantity'),
			$this->Employee->get_logged_in_employee_info()->person_id,
			$this->input->post('notes')
		);
		$this->session->set_flashdata($result ? 'route_success' : 'route_error', $result ? lang('routes_return_success') : lang('routes_return_error'));
		redirect('routes/view/'.$route_id);
	}

	public function close($route_id)
	{
		$route = $this->Route->get_info($route_id);
		if (!$route || (int)$route->location_id !== (int)$this->Employee->get_logged_in_employee_current_location_id())
		{
			show_404();
		}
		$result = $this->Route->close($route_id, $this->Employee->get_logged_in_employee_info()->person_id);
		$this->session->set_flashdata($result ? 'route_success' : 'route_error', $result ? lang('routes_close_success') : lang('routes_close_error'));
		redirect('routes/view/'.$route_id);
	}
}
