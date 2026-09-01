-- Add an optional selling price to each inventory lot.
-- Existing lots inherit the current item selling price.
ALTER TABLE `phppos_inventory_lots`
  ADD COLUMN IF NOT EXISTS `unit_price` decimal(23,10) DEFAULT NULL AFTER `unit_cost`;

UPDATE `phppos_inventory_lots` lots
INNER JOIN `phppos_items` items ON items.item_id = lots.item_id
SET lots.unit_price = items.unit_price
WHERE lots.unit_price IS NULL;
