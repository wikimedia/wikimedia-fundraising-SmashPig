CREATE TABLE damaged (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `original_date` datetime NOT NULL,
  `damaged_date` datetime NOT NULL,
  `retry_date` datetime NULL,
  `original_queue` varchar(255) NOT NULL,
  `gateway` varchar(255) NOT NULL,
  `order_id` varchar(255) NULL,
  `gateway_txn_id` varchar(255) NULL,
  `error` text NULL,
  `message` text NOT NULL,
  INDEX `idx_damaged_original_date` (`original_date`),
  INDEX `idx_damaged_original_date_original_queue` (`original_date`, `original_queue`),
  INDEX `idx_damaged_retry_date` (`retry_date`),
  INDEX `idx_damaged_order_id_gateway` (`order_id`, `gateway`),
  INDEX `idx_damaged_gateway_txn_id_gateway` (`gateway_txn_id`, `gateway`),
  PRIMARY KEY `pk_damaged_id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
