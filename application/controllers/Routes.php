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
		$data['employees'] = $this->Employee->get_employees_for_location($location_id);

		$start = $this->input->get('start_date');
		$end = $this->input->get('end_date');
		$data['summary_start'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$start) ? $start : date('Y-m-01');
		$data['summary_end'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$end) ? $end : date('Y-m-d');
		$data['range_summary'] = $this->Route->get_range_summary($location_id, $data['summary_start'], $data['summary_end']);

		$this->load->view('routes/index', $data);
	}

	public function create()
	{
		$name = trim((string)$this->input->post('name'));
		$route_date = $this->input->post('route_date');
		$employee_id = (int)$this->input->post('employee_id');
		$location_id = $this->Employee->get_logged_in_employee_current_location_id();

		if ($name === '' || !$route_date)
		{
			$this->session->set_flashdata('route_error', lang('routes_required_fields'));
			redirect('routes');
		}

		//Vendedor obligatorio: reject before touching the model if nothing was
		//selected, so the message names the real reason instead of a generic
		//"required fields" error.
		if (!$employee_id)
		{
			$this->session->set_flashdata('route_error', lang('routes_employee_required'));
			redirect('routes');
		}

		//A route can only be assigned to an employee authorized for the
		//location it is being opened in. Reuses the existing
		//employees_locations relationship via Employee::is_employee_authenticated() --
		//no new table, no duplicated permission logic.
		if (!$this->Employee->is_employee_authenticated($employee_id, $location_id))
		{
			$this->session->set_flashdata('route_error', lang('routes_employee_no_location_access'));
			redirect('routes');
		}

		$route_id = $this->Route->create(array(
			'name' => $name,
			'route_date' => $route_date,
			'location_id' => $location_id,
			'employee_id' => $employee_id,
			'notes' => $this->input->post('notes'),
			'opened_by' => $this->Employee->get_logged_in_employee_info()->person_id,
		));
		if (!$route_id)
		{
			$messages = array(
				'employee_required' => lang('routes_employee_required'),
				'employee_has_open_route' => lang('routes_employee_has_open_route'),
			);
			$this->session->set_flashdata('route_error', isset($messages[$this->Route->last_error]) ? $messages[$this->Route->last_error] : lang('routes_create_error'));
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
		$data['damage_summary'] = $this->Route->get_damage_summary($route_id);
		$data['credit_collections'] = $this->Route->get_credit_collections($route_id);
		$this->load->view('routes/view', $data);
	}

	public function classify_damage($route_id)
	{
		$route = $this->Route->get_info($route_id);
		if (!$route || $route->status !== 'open' || (int)$route->location_id !== (int)$this->Employee->get_logged_in_employee_current_location_id())
		{
			show_404();
		}
		$result = $this->Route->classify_lot_damage(
			$route_id,
			(int)$this->input->post('route_inventory_lot_id'),
			(float)$this->input->post('quantity'),
			$this->input->post('classification'),
			$this->input->post('unit_price'),
			$this->Employee->get_logged_in_employee_info()->person_id,
			(string)$this->input->post('notes')
		);
		$this->session->set_flashdata($result ? 'route_success' : 'route_error', $result ? lang('routes_damage_success') : lang('routes_damage_error'));
		redirect('routes/view/'.$route_id);
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
		if (!$route)
		{
			show_404();
		}

		$employee_id = $this->Employee->get_logged_in_employee_info()->person_id;
		$location_id = $this->Employee->get_logged_in_employee_current_location_id();

		//Each check gets its own specific message instead of a blanket 404,
		//so the operator understands exactly why they can't sell from it.
		if ($route->status !== 'open')
		{
			$this->session->set_flashdata('route_error', lang('routes_sell_not_open'));
			redirect('routes/view/'.$route_id);
		}
		if ((int)$route->location_id !== (int)$location_id)
		{
			//Wrong location: route belongs to a different branch than the one
			//the employee is currently in -- send them to their own route list.
			$this->session->set_flashdata('route_error', lang('routes_sell_wrong_location'));
			redirect('routes');
		}
		if (!$route->employee_id)
		{
			$this->session->set_flashdata('route_error', lang('routes_sell_no_employee'));
			redirect('routes/view/'.$route_id);
		}
		if ((int)$route->employee_id !== (int)$employee_id)
		{
			$this->session->set_flashdata('route_error', lang('routes_sell_wrong_employee'));
			redirect('routes/view/'.$route_id);
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

		if ($result)
		{
			//If the employee closing the route also has it tagged on their own
			//(still-empty) sale cart, drop the tag now instead of leaving a
			//stale "Venta de ruta" banner around. Never touch a cart that
			//already has items -- Sales.php re-validates against the database
			//on every load and blocks checkout if the route is no longer open
			//(Fase 3 adds the clearer "blocked, please cancel" UX for that case).
			require_once (APPPATH.'models/cart/PHPPOSCartSale.php');
			$cart = PHPPOSCartSale::get_instance('sale');
			if ((int)$cart->route_id === (int)$route_id && count($cart->get_items()) === 0)
			{
				$cart->route_id = NULL;
				$cart->route_name = NULL;
				$cart->save();
			}
		}

		$this->session->set_flashdata($result ? 'route_success' : 'route_error', $result ? lang('routes_close_success') : lang('routes_close_error'));
		redirect('routes/view/'.$route_id);
	}
}
