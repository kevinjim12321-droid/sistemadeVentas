-- Inventory lots and immutable lot movement ledger.
ALTER TABLE `phppos_items`
  ADD COLUMN IF NOT EXISTS `track_inventory_lots` tinyint(1) NOT NULL DEFAULT '0' AFTER `expire_days`,
  ADD COLUMN IF NOT EXISTS `lot_allocation_policy` varchar(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'FEFO' AFTER `track_inventory_lots`;

ALTER TABLE `phppos_receivings_items`
  ADD COLUMN IF NOT EXISTS `lot_code` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL AFTER `expire_date`,
  ADD COLUMN IF NOT EXISTS `manufactured_date` date DEFAULT NULL AFTER `lot_code`;

CREATE TABLE IF NOT EXISTS `phppos_inventory_lots` (
  `lot_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lot_code` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `item_id` int(10) NOT NULL,
  `item_variation_id` int(10) DEFAULT NULL,
  `location_id` int(10) NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `receiving_id` int(10) DEFAULT NULL,
  `receiving_line` int(11) DEFAULT NULL,
  `manufactured_date` date DEFAULT NULL,
  `expire_date` date DEFAULT NULL,
  `received_at` datetime NOT NULL,
  `quantity_initial` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
  `quantity_remaining` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
  `unit_cost` decimal(23,10) NOT NULL DEFAULT '0.0000000000',
  `status` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
  `notes` text COLLATE utf8_unicode_ci,
  `created_by` int(10) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`lot_id`),
  KEY `lot_item_location` (`item_id`,`item_variation_id`,`location_id`),
  KEY `lot_allocation` (`item_id`,`item_variation_id`,`location_id`,`status`,`expire_date`,`received_at`),
  KEY `lot_code` (`lot_code`),
  KEY `lot_supplier` (`supplier_id`),
  KEY `lot_receiving` (`receiving_id`,`receiving_line`),
  CONSTRAINT `phppos_inventory_lots_ibfk_1` FOREIGN KEY (`item_id`) REFERENCES `phppos_items` (`item_id`),
  CONSTRAINT `phppos_inventory_lots_ibfk_2` FOREIGN KEY (`item_variation_id`) REFERENCES `phppos_item_variations` (`id`),
  CONSTRAINT `phppos_inventory_lots_ibfk_3` FOREIGN KEY (`location_id`) REFERENCES `phppos_locations` (`location_id`),
  CONSTRAINT `phppos_inventory_lots_ibfk_4` FOREIGN KEY (`supplier_id`) REFERENCES `phppos_suppliers` (`person_id`),
  CONSTRAINT `phppos_inventory_lots_ibfk_5` FOREIGN KEY (`receiving_id`) REFERENCES `phppos_receivings` (`receiving_id`),
  CONSTRAINT `phppos_inventory_lots_ibfk_6` FOREIGN KEY (`created_by`) REFERENCES `phppos_employees` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `phppos_inventory_lot_movements` (
  `movement_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lot_id` bigint(20) unsigned NOT NULL,
  `movement_type` varchar(30) COLLATE utf8_unicode_ci NOT NULL,
  `quantity_delta` decimal(23,10) NOT NULL,
  `balance_after` decimal(23,10) NOT NULL,
  `sale_id` int(10) DEFAULT NULL,
  `sale_line` int(11) DEFAULT NULL,
  `receiving_id` int(10) DEFAULT NULL,
  `receiving_line` int(11) DEFAULT NULL,
  `reference_type` varchar(30) COLLATE utf8_unicode_ci DEFAULT NULL,
  `reference_id` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `employee_id` int(10) DEFAULT NULL,
  `occurred_at` datetime NOT NULL,
  `notes` text COLLATE utf8_unicode_ci,
  PRIMARY KEY (`movement_id`),
  KEY `movement_lot_date` (`lot_id`,`occurred_at`),
  KEY `movement_sale` (`sale_id`,`sale_line`),
  KEY `movement_receiving` (`receiving_id`,`receiving_line`),
  KEY `movement_reference` (`reference_type`,`reference_id`),
  CONSTRAINT `phppos_inventory_lot_movements_ibfk_1` FOREIGN KEY (`lot_id`) REFERENCES `phppos_inventory_lots` (`lot_id`),
  CONSTRAINT `phppos_inventory_lot_movements_ibfk_2` FOREIGN KEY (`sale_id`) REFERENCES `phppos_sales` (`sale_id`),
  CONSTRAINT `phppos_inventory_lot_movements_ibfk_3` FOREIGN KEY (`receiving_id`) REFERENCES `phppos_receivings` (`receiving_id`),
  CONSTRAINT `phppos_inventory_lot_movements_ibfk_4` FOREIGN KEY (`employee_id`) REFERENCES `phppos_employees` (`person_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
