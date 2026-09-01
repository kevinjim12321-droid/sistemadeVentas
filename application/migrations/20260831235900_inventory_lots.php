<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_inventory_lots extends MY_Migration
{
	public function up()
	{
		$this->execute_sql(realpath(dirname(__FILE__).'/20260831235900_inventory_lots.sql'));
	}

	public function down()
	{
		$this->db->query('DROP TABLE IF EXISTS phppos_inventory_lot_movements');
		$this->db->query('DROP TABLE IF EXISTS phppos_inventory_lots');
		$this->db->query('ALTER TABLE phppos_receivings_items DROP COLUMN manufactured_date, DROP COLUMN lot_code');
		$this->db->query('ALTER TABLE phppos_items DROP COLUMN lot_allocation_policy, DROP COLUMN track_inventory_lots');
	}
}
