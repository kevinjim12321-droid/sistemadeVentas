<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once('PHPPOSCartItem.php');

class PHPPOSCartItemRecv extends PHPPOSCartItem
{
	public $selling_price;
	public $location_selling_price;
	public $expire_date;
	public $lot_code;
	public $manufactured_date;
	public $track_inventory_lots;
	public $lot_allocation_policy;
	public $cost_price_preview;
	
	public function __construct(array $params = array())
	{		
		$params['type'] = 'receiving';
		parent::__construct($params);
	}
	
}
