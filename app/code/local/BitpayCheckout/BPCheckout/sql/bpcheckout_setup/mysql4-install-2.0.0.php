<?php
$prefix = (string) Mage::getConfig()->getTablePrefix();
$table_name = $prefix.'bitpay_transactions';
$installer = $this;
$installer->startSetup();
$installer->run("
CREATE TABLE IF NOT EXISTS $table_name (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `order_id` int(11) NOT NULL,
        `transaction_id` varchar(255) NOT NULL,
        `transaction_status` varchar(50) NOT NULL DEFAULT 'new',
        `date_added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`))
");
$installer->endSetup();
