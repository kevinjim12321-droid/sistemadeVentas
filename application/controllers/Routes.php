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
		$opening_cash = (float)$this->input->post('opening_cash');
		if ($opening_cash > 0)
		{
			$this->Route->add_cash_event($route_id, 'opening', $opening_cash, lang('routes_cash_opening'), $this->Employee->get_logged_in_employee_info()->person_id);
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
		$data['cash_events'] = $this->Route->get_cash_events($route_id);
		$data['route_expenses'] = $this->Route->get_expenses($route_id);
		$data['reconciliation'] = $this->Route->get_cash_reconciliation($route_id);
		$this->load->view('routes/view', $data);
	}

	public function add_cash($route_id)
	{
		$route = $this->Route->get_info($route_id);
		if (!$route || $route->status !== 'open' || (int)$route->location_id !== (int)$this->Employee->get_logged_in_employee_current_location_id())
		{
			show_404();
		}
		$result = $this->Route->add_cash_event($route_id, 'add', (float)$this->input->post('amount'), $this->input->post('notes'), $this->Employee->get_logged_in_employee_info()->person_id);
		$this->session->set_flashdata($result ? 'route_success' : 'route_error', $result ? lang('routes_cash_added') : lang('routes_cash_error'));
		redirect('routes/view/'.$route_id);
	}

	public function add_expense($route_id)
	{
		$route = $this->Route->get_info($route_id);
		if (!$route || $route->status !== 'open' || (int)$route->location_id !== (int)$this->Employee->get_logged_in_employee_current_location_id())
		{
			show_404();
		}
		$result = $this->Route->add_expense($route_id, (float)$this->input->post('amount'), $this->input->post('description'), $this->Employee->get_logged_in_employee_info()->person_id);
		$this->session->set_flashdata($result ? 'route_success' : 'route_error', $result ? lang('routes_expense_added') : lang('routes_expense_error'));
		redirect('routes/view/'.$route_id);
	}

	public function return_all($route_id)
	{
		$route = $this->Route->get_info($route_id);
		if (!$route || $route->status !== 'open' || (int)$route->location_id !== (int)$this->Employee->get_logged_in_employee_current_location_id())
		{
			show_404();
		}
		$result = $this->Route->return_all_inventory($route_id, $this->Employee->get_logged_in_employee_info()->person_id, lang('routes_return_all_note'));
		$this->session->set_flashdata($result ? 'route_success' : 'route_error', $result ? lang('routes_return_all_success') : lang('routes_return_all_error'));
		redirect('routes/view/'.$route_id);
	}

	public function close_form($route_id)
	{
		$route = $this->Route->get_info($route_id);
		if (!$route || $route->status !== 'open' || (int)$route->location_id !== (int)$this->Employee->get_logged_in_employee_current_location_id())
		{
			show_404();
		}
		$data['route'] = $route;
		$data['reconciliation'] = $this->Route->get_cash_reconciliation($route_id);
		$data['remaining_inventory'] = $this->Route->get_remaining_inventory_quantity($route_id);
		$this->load->view('routes/close', $data);
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
		redirect('receivings');
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
		$result = $this->Route->close(
			$route_id,
			$this->Employee->get_logged_in_employee_info()->person_id,
			$this->input->post('counted_cash'),
			(string)$this->input->post('cash_note')
		);
		$this->session->set_flashdata($result ? 'route_success' : 'route_error', $result ? lang('routes_close_success') : lang('routes_close_error'));
		redirect('routes/view/'.$route_id);
	}
}
