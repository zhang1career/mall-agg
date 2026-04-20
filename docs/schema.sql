CREATE DATABASE IF NOT EXISTS `mall_agg` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `mall_agg`;

-- Dumping structure for table mall_agg.order
CREATE TABLE IF NOT EXISTS `order` (
                                       `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `uid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'user ID',
    `total_price` int(11) NOT NULL DEFAULT '0' COMMENT '总价，单位：分',
    `status` tinyint(3) unsigned NOT NULL DEFAULT '0' COMMENT '0-pending, 1-paid, 2-cancelled',
    `saga_idem_key` bigint(20) unsigned DEFAULT NULL,
    `tcc_idem_key` bigint(20) unsigned DEFAULT NULL,
    `tid` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'global tx ID',
    `checkout_phase` smallint(5) unsigned NOT NULL DEFAULT '0',
    `ext_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'external reserve ID',
    `ext_inventory` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'external inventory',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE KEY `uni_mall_order_saga_idem` (`saga_idem_key`),
    UNIQUE KEY `uni_mall_order_tcc_idem` (`tcc_idem_key`),
    KEY `idx_mall_order_user` (`uid`) USING BTREE,
    KEY `idx_mall_order_tx` (`tid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='订单';

-- Data exporting was unselected.

-- Dumping structure for table mall_agg.order_item
CREATE TABLE IF NOT EXISTS `order_item` (
                                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `oid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'order ID',
    `pid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'product ID',
    `quantity` int(10) unsigned NOT NULL DEFAULT '1' COMMENT '数量',
    `unit_price` int(11) NOT NULL DEFAULT '0' COMMENT '单价，单位：分',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
    PRIMARY KEY (`id`) USING BTREE,
    KEY `idx_mall_order_order` (`oid`) USING BTREE,
    KEY `idx_mall_order_product` (`pid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='订单元素';

-- Data exporting was unselected.

-- Dumping structure for table mall_agg.points_balance
CREATE TABLE IF NOT EXISTS `points_balance` (
                                                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `uid` bigint(20) unsigned NOT NULL COMMENT 'user ID',
    `balance_minor` bigint(20) NOT NULL DEFAULT '0',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`) USING BTREE,
    UNIQUE KEY `uni_mall_points_bal_user` (`uid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='积分账户';

-- Data exporting was unselected.

-- Dumping structure for table mall_agg.points_flow
CREATE TABLE IF NOT EXISTS `points_flow` (
                                             `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `uid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'user ID',
    `oid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'order ID',
    `amount_minor` bigint(20) NOT NULL DEFAULT '0',
    `state` tinyint(3) unsigned NOT NULL DEFAULT '0',
    `try_idem_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uni_mall_points_flow_tcc_idem` (`try_idem_key`) USING BTREE,
    KEY `idx_mall_points_flow_user_order` (`uid`,`oid`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='积分流水';

-- Data exporting was unselected.

-- Dumping structure for table mall_agg.product_inventory
CREATE TABLE IF NOT EXISTS `product_inventory` (
                                                   `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `pid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'product ID',
    `quantity` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '存量',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
    PRIMARY KEY (`id`) USING BTREE,
    KEY `idx_mall_inventory_product` (`pid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='商品库存';

-- Data exporting was unselected.

-- Dumping structure for table mall_agg.product_price
CREATE TABLE IF NOT EXISTS `product_price` (
                                               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `pid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'product ID',
    `price` int(10) NOT NULL DEFAULT '0' COMMENT '价格，单位：分',
    `ct` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Create time in Unix milliseconds',
    `ut` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'Update time in Unix milliseconds',
    PRIMARY KEY (`id`) USING BTREE,
    KEY `idx_mall_price_product` (`pid`) USING BTREE
    ) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC COMMENT='商品价格';

-- Data exporting was unselected.

-- Dumping structure for table mall_agg.sessions
CREATE TABLE IF NOT EXISTS `sessions` (
                                          `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    `user_id` bigint(20) unsigned DEFAULT NULL,
    `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `user_agent` text COLLATE utf8mb4_unicode_ci,
    `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
    `last_activity` int(11) NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_mall_sessions_user` (`user_id`) USING BTREE,
    KEY `idx_mall_sessions_last_activity` (`last_activity`) USING BTREE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
