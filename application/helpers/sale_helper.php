<?php

/**
 * Combine receipt-only rows created by inventory lot allocation.
 * Database sale lines and lot movements remain untouched; only rows with the
 * same public-facing product, variation, price and discount are combined.
 */
function consolidate_inventory_lot_receipt_items($cart_items)
{
	$consolidated = array();
	$group_indexes = array();

	foreach ($cart_items as $line => $item)
	{
		// A completed-sale edit can leave a zero-quantity database line behind.
		// It is useful for internal history but must never be printed to customers.
		if ($item instanceof PHPPOSCartItemSale && abs((float)$item->quantity) <= 0.0000000001)
		{
			continue;
		}

		if (!($item instanceof PHPPOSCartItemSale) || $item->quantity <= 0)
		{
			$consolidated[$line] = $item;
			continue;
		}

		$variation_id = $item->variation_id ? (int)$item->variation_id : 0;
		$quantity_unit_id = $item->quantity_unit_id ? (int)$item->quantity_unit_id : 0;
		$group_key = implode('|', array(
			(int)$item->item_id,
			$variation_id,
			$quantity_unit_id,
			number_format((float)$item->unit_price, 10, '.', ''),
			number_format((float)$item->discount, 10, '.', ''),
			(string)$item->description,
			(string)$item->serialnumber,
			md5(serialize($item->modifier_items)),
			md5(serialize(array($item->override_tax_names, $item->override_tax_percents, $item->override_tax_cumulatives, $item->override_tax_class)))
		));

		if (!isset($group_indexes[$group_key]))
		{
			$receipt_item = clone $item;
			$receipt_item->selected_lot_id = NULL;
			$receipt_item->selected_lot_code = NULL;
			$receipt_item->selected_lot_quantity_available = NULL;
			$consolidated[$line] = $receipt_item;
			$group_indexes[$group_key] = $line;
		}
		else
		{
			$consolidated[$group_indexes[$group_key]]->quantity += (float)$item->quantity;
		}
	}

	return $consolidated;
}

/**
 * Show repeated cash adjustments from completed-sale edits as one net payment.
 * The original payment rows remain unchanged in the database and reports.
 */
function consolidate_cash_receipt_payments($payments)
{
	$consolidated = array();
	$cash_index = NULL;
	$cash_name = lang('common_cash', '', array(), TRUE);

	foreach ($payments as $payment_id => $payment)
	{
		if ($payment->payment_type !== $cash_name)
		{
			$consolidated[$payment_id] = $payment;
			continue;
		}

		if ($cash_index === NULL)
		{
			$receipt_payment = clone $payment;
			$consolidated[$payment_id] = $receipt_payment;
			$cash_index = $payment_id;
		}
		else
		{
			$consolidated[$cash_index]->payment_amount += (float)$payment->payment_amount;
		}
	}

	return $consolidated;
}

function is_sale_integrated_giftcard_processing($cart)
{
	$CI =& get_instance();
	$igc_payment_amount = $cart->get_payment_amount(lang('common_integrated_gift_card'));
	return $CI->Location->get_info_for_key('integrated_gift_cards') && $igc_payment_amount != 0;
}

function is_sale_integrated_cc_processing($cart)
{
	$CI =& get_instance();
	$cc_payment_amount = $cart->get_payment_amount(lang('common_credit'));
	return $CI->Location->get_info_for_key('enable_credit_card_processing') && $cc_payment_amount != 0;
}

function is_sale_integrated_ebt_sale($cart)
{
	$CI =& get_instance();
	return (is_ebt_sale($cart) && $CI->Location->get_info_for_key('enable_credit_card_processing') && $CI->Location->get_info_for_key('ebt_integrated') && ($CI->Location->get_info_for_key('emv_merchant_id') || $CI->Location->get_info_for_key('blockchyp_api_key')));
}

function is_ebt_sale($cart)
{
	$CI =& get_instance();
	$ebt_payment_amount = $cart->get_payment_amount(lang('common_ebt'));
	$ebt_cash_payment_amount = $cart->get_payment_amount(lang('common_ebt_cash'));
	$ebt_wic_amount = $cart->get_payment_amount(lang('common_wic'));
	
	return  $CI->config->item('enable_ebt_payments') && ($ebt_payment_amount != 0 || $ebt_cash_payment_amount != 0 || $ebt_wic_amount != 0 );
}

function is_system_integrated_ebt()
{
	$CI =& get_instance();
	return $CI->Location->get_info_for_key('enable_credit_card_processing') && $CI->config->item('enable_ebt_payments');
}

function is_ebt_sale_not_ebt_cash($cart)
{
	$CI =& get_instance();
	$ebt_payment_amount = $cart->get_payment_amount(lang('common_ebt'));
	$wic_payment_amount = $cart->get_payment_amount(lang('common_wic'));
	return $CI->config->item('enable_ebt_payments') && ($ebt_payment_amount != 0 || $wic_payment_amount != 0);
	
}

function is_credit_card_sale($cart)
{
	$cc_payment_amount = $cart->get_payment_amount(lang('common_credit'));
	return $cc_payment_amount != 0;
}

function is_debit_card_sale($cart)
{
	$cc_payment_amount = $cart->get_payment_amount(lang('common_debit'));
	return $cc_payment_amount != 0;
}


function is_store_account_sale($cart)
{
	$store_account_amount = $cart->get_payment_amount(lang('common_store_account'));
	return $store_account_amount != 0;
}


function sale_has_partial_credit_card_payment($cart)
{
	$cc_partial_payment_amount = $cart->get_payment_amount(lang('sales_partial_credit'));
	return $cc_partial_payment_amount != 0;
}

function sale_has_partial_ebt_payment($cart)
{
	$ebt_partial = $cart->get_payment_amount(lang('common_partial_ebt'));
	$ebt_cash_partial = $cart->get_payment_amount(lang('common_partial_ebt_cash'));
	$ebt_wic_partial= $cart->get_payment_amount(lang('common_wic'));

	return $ebt_partial != 0 || $ebt_cash_partial != 0 || $ebt_wic_partial !=0;
}

function sale_id_receipt_link_formatter($sale_id)
{
	$CI =& get_instance();
	return anchor('sales/receipt/'.$sale_id, ($CI->config->item('sale_prefix') ? $CI->config->item('sale_prefix') : 'POS') .' '.$sale_id, array('target' => '_blank'));
}

?>
