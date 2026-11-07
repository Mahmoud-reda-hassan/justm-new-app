-- Just-M database installer, assembled from the provided project archive.
-- Target: MariaDB 10.5+ / MySQL compatible mode. Review the seed data before production.
-- اختر قاعدة البيانات المستهدفة من phpMyAdmin قبل تشغيل هذا الملف.

-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- مضيف: localhost:3306
-- وقت الجيل: 01 نوفمبر 2025 الساعة 16:46
-- إصدار الخادم: 10.5.25-MariaDB
-- نسخة PHP: 8.4.5

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- قاعدة بيانات: `cptest`
--

-- --------------------------------------------------------

--
-- بنية الجدول `countries`
--

CREATE TABLE IF NOT EXISTS `countries` (
  `CO_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CO_NAME` varchar(50) NOT NULL,
  `CO_NAME_EN` varchar(50) NOT NULL,
  PRIMARY KEY (`CO_ID`),
  UNIQUE KEY `CO_NAME` (`CO_NAME`),
  UNIQUE KEY `CO_NAME_EN` (`CO_NAME_EN`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `regional_areas`
--

CREATE TABLE IF NOT EXISTS `regional_areas` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `REGIONAL_NAME` varchar(100) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `REGIONAL_NAME` (`REGIONAL_NAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `gov`
--

CREATE TABLE IF NOT EXISTS `gov` (
  `GOV_ID` int(11) NOT NULL AUTO_INCREMENT,
  `COUNTRY_ID` int(11) NOT NULL,
  `GOV_NAME` varchar(50) NOT NULL,
  `REGIONAL_AREAS_ID` int(11) DEFAULT NULL,
  `GOV_NAME_EN` varchar(50) NOT NULL,
  PRIMARY KEY (`GOV_ID`),
  UNIQUE KEY `GOV_NAME` (`GOV_NAME`),
  UNIQUE KEY `GOV_NAME_EN` (`GOV_NAME_EN`),
  KEY `GOV_COUNTRY_ID_COUNTRIES_CO_ID` (`COUNTRY_ID`),
  KEY `GOV_REGIONAL_AREAS_ID_REGIONAL_AREAS_ID` (`REGIONAL_AREAS_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `shipping_type`
--

CREATE TABLE IF NOT EXISTS `shipping_type` (
  `SHIPPING_T_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SHIPPING_T_NAME` varchar(50) NOT NULL,
  PRIMARY KEY (`SHIPPING_T_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `zones`
--

CREATE TABLE IF NOT EXISTS `zones` (
  `ZONE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ZONE_NAME` varchar(50) NOT NULL,
  `ZONE_NAME_EN` varchar(50) NOT NULL,
  `SHIPPING_RATE` decimal(18,2) NOT NULL DEFAULT 0.00,
  `SHIPPING_LOWEST` decimal(18,2) NOT NULL DEFAULT 0.00,
  `SHIPPING_TIME_MAX` int(11) NOT NULL DEFAULT 2,
  `SHIPPING_TYPE` int(11) NOT NULL,
  `STORE_ID` int(11) NOT NULL,
  PRIMARY KEY (`ZONE_ID`),
  UNIQUE KEY `ZONE_NAME` (`ZONE_NAME`),
  UNIQUE KEY `ZONE_NAME_EN` (`ZONE_NAME_EN`),
  UNIQUE KEY `ZONE_NAME_UNIQUE` (`ZONE_NAME`,`STORE_ID`),
  UNIQUE KEY `ZONE_NAME_EN_UNIQUE` (`ZONE_NAME_EN`,`STORE_ID`),
  KEY `STORE_ID` (`STORE_ID`),
  KEY `STORE_ID_key` (`STORE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `city`
--

CREATE TABLE IF NOT EXISTS `city` (
  `CI_ID` int(11) NOT NULL AUTO_INCREMENT,
  `GOV_ID` int(11) DEFAULT NULL,
  `ZONE_ID` int(11) DEFAULT NULL,
  `CI_NAME` varchar(50) NOT NULL,
  `CI_NAME_EN` varchar(50) NOT NULL,
  PRIMARY KEY (`CI_ID`),
  UNIQUE KEY `CI_NAME` (`CI_NAME`),
  UNIQUE KEY `CI_NAME_EN` (`CI_NAME_EN`),
  KEY `CITY_GOV_ID_GOV_GOV_ID` (`GOV_ID`),
  KEY `CITY_ZONE_ID_ZONES_ZONE_ID` (`ZONE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `companies`
--

CREATE TABLE IF NOT EXISTS `companies` (
  `COM_ID` int(11) NOT NULL AUTO_INCREMENT,
  `COM_NAME` varchar(30) NOT NULL,
  `COM_TEL` varchar(255) NOT NULL,
  `COM_FAX` varchar(255) NOT NULL,
  `COM_MOBILE` varchar(255) NOT NULL,
  `COM_EMAIL` varchar(255) NOT NULL,
  `CITY_ID` int(20) DEFAULT NULL,
  `COM_ADDRESS` text NOT NULL,
  `LOGOBRAND` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`COM_ID`),
  UNIQUE KEY `COM_NAME` (`COM_NAME`),
  KEY `COMPANY_R_CITY_ID` (`CITY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `branches`
--

CREATE TABLE IF NOT EXISTS `branches` (
  `BR_ID` int(11) NOT NULL AUTO_INCREMENT,
  `COMPANIE_ID` int(11) NOT NULL,
  `CITY_ID` int(11) DEFAULT NULL,
  `BR_NAME` varchar(50) NOT NULL,
  `BR_MAIN` tinyint(1) NOT NULL,
  `BR_ADDRESS` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`BR_ID`),
  UNIQUE KEY `BR_NAME` (`BR_NAME`),
  KEY `BRANCHES_COMPANIE_ID_COMPANIES_COM_ID` (`COMPANIE_ID`),
  KEY `BRANCHES_CITY_ID_CITY_CI_ID` (`CITY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `stores_type`
--

CREATE TABLE IF NOT EXISTS `stores_type` (
  `STTY_ID` int(11) NOT NULL AUTO_INCREMENT,
  `STTY_NAME` varchar(100) NOT NULL,
  `STTY_DESCRIPTION` text DEFAULT NULL,
  PRIMARY KEY (`STTY_ID`),
  UNIQUE KEY `STTP_NAME` (`STTY_NAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `stores`
--

CREATE TABLE IF NOT EXISTS `stores` (
  `STO_ID` int(11) NOT NULL AUTO_INCREMENT,
  `BRANCH_ID` int(11) NOT NULL,
  `STO_NAME` varchar(100) NOT NULL,
  `STTY_ID` int(11) NOT NULL,
  `IS_COUNTING` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0 = مفتوح ، 1 = تحت الجرد',
  `COUNTING_TOKEN` varchar(64) DEFAULT NULL,
  `COUNTING_EXPIRES` datetime DEFAULT NULL,
  PRIMARY KEY (`STO_ID`),
  UNIQUE KEY `BRANCH_ID` (`BRANCH_ID`,`STO_NAME`),
  KEY `STORES_BRANCH_ID_BRANCHES_BR_ID` (`BRANCH_ID`),
  KEY `STORES_STTY_ID_STORES_TYPE_STTY_ID` (`STTY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `user_gruop`
--

CREATE TABLE IF NOT EXISTS `user_gruop` (
  `USER_GRUOP_ID` int(11) NOT NULL AUTO_INCREMENT,
  `USER_GROUP_PARENT` int(11) DEFAULT NULL,
  `USER_GRUOP_NAME` varchar(20) NOT NULL,
  `USER_SCRIPT` text NOT NULL,
  PRIMARY KEY (`USER_GRUOP_ID`),
  UNIQUE KEY `USER_GRUOP_NAME` (`USER_GRUOP_NAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `USER_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `USER_GRUOP_ID` int(11) DEFAULT NULL,
  `BRANCH_ID` int(11) NOT NULL,
  `FORENAME` varchar(50) NOT NULL,
  `LASTNAME` varchar(50) NOT NULL,
  `GENDER` char(10) NOT NULL,
  `SUSPENDED` tinyint(1) NOT NULL DEFAULT 1,
  `USER_LOGIN` varchar(50) NOT NULL,
  `USER_PASSWORD` varchar(100) NOT NULL,
  `RE_PASSCODE` int(5) DEFAULT NULL,
  `RE_PASS_EXPIRE` datetime NOT NULL DEFAULT current_timestamp(),
  `ADMIN_TYPE` tinyint(1) NOT NULL DEFAULT 0,
  `MERCHANT_TYPE` tinyint(1) NOT NULL DEFAULT 0,
  `AFFILIATE_TYPE` tinyint(1) NOT NULL DEFAULT 0,
  `INTERMEDIARY_TYPE` tinyint(1) NOT NULL DEFAULT 0,
  `DELIVERY_TYPE` tinyint(1) NOT NULL DEFAULT 0,
  `USER_IMG_LINK` varchar(100) DEFAULT NULL,
  `USER_DATE_CREATED` datetime NOT NULL DEFAULT current_timestamp(),
  `BIRTHDAY` date NOT NULL,
  PRIMARY KEY (`USER_ID`),
  UNIQUE KEY `USER_LOGIN` (`USER_LOGIN`),
  KEY `USERS_USER_GRUOP_ID_USER_GRUOP_USER_GRUOP_ID` (`USER_GRUOP_ID`),
  KEY `USERS_BRANCH_ID_BRANCHES_BR_ID` (`BRANCH_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `users_affiliate`
--

CREATE TABLE IF NOT EXISTS `users_affiliate` (
  `USER_ID` bigint(20) NOT NULL,
  `AFFILIATEED_WITH` bigint(20) DEFAULT NULL,
  `LIVEL` int(11) NOT NULL DEFAULT 0,
  `COMMISSION_SALES` float(18,2) NOT NULL DEFAULT 0.00,
  `TEAM_COMMISSION` float(18,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`USER_ID`),
  KEY `AFFILIATEED_WITH` (`AFFILIATEED_WITH`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `users_delivery`
--

CREATE TABLE IF NOT EXISTS `users_delivery` (
  `USER_ID` bigint(20) NOT NULL,
  `ZONE_ID` int(11) DEFAULT NULL,
  `SALARY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `PERCENT` float(12,2) NOT NULL DEFAULT 0.00,
  `CUSTODY_STORE` longtext NOT NULL DEFAULT '',
  PRIMARY KEY (`USER_ID`),
  KEY `ZONE_ID` (`ZONE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `client`
--

CREATE TABLE IF NOT EXISTS `client` (
  `CLIENT_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CLIENT_NAME` varchar(100) NOT NULL,
  `CLIENT_EMAIL` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`CLIENT_ID`),
  UNIQUE KEY `CLIENT_EMAIL` (`CLIENT_EMAIL`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `suppliers`
--

CREATE TABLE IF NOT EXISTS `suppliers` (
  `SUP_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `SUP_NAME` varchar(50) NOT NULL,
  `SUP_EMAIL` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`SUP_ID`),
  KEY `idx_supplier_id` (`SUP_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `telephones`
--

CREATE TABLE IF NOT EXISTS `telephones` (
  `TEL_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `USER_ID` bigint(20) DEFAULT NULL,
  `CLIENT_ID` bigint(20) DEFAULT NULL,
  `SUPPLIER_ID` bigint(20) DEFAULT NULL,
  `TYPE` int(2) NOT NULL DEFAULT 0,
  `DEFAULTTELL` tinyint(1) NOT NULL DEFAULT 0,
  `WHATSAPP` tinyint(1) NOT NULL,
  `TELEGRAM` tinyint(1) NOT NULL,
  `TEL_LTYPE` varchar(10) NOT NULL,
  `TEL_NUMBER` varchar(15) NOT NULL,
  PRIMARY KEY (`TEL_ID`),
  UNIQUE KEY `USER_ID_2` (`USER_ID`,`CLIENT_ID`,`TEL_NUMBER`),
  UNIQUE KEY `USER_ID_3` (`USER_ID`,`CLIENT_ID`,`SUPPLIER_ID`,`TEL_NUMBER`),
  UNIQUE KEY `USER_ID_4` (`USER_ID`,`CLIENT_ID`,`SUPPLIER_ID`,`TEL_NUMBER`),
  KEY `USER_ID` (`USER_ID`),
  KEY `CLIENT_ID` (`CLIENT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `addresses`
--

CREATE TABLE IF NOT EXISTS `addresses` (
  `ADDRESS_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CITY_ID` int(11) NOT NULL,
  `USER_ID` bigint(20) DEFAULT NULL,
  `CLIENT_ID` bigint(20) DEFAULT NULL,
  `SUPPLIER_ID` bigint(20) DEFAULT NULL,
  `ADDRESS_TITLE` varchar(30) NOT NULL,
  `DEFAULTADD` tinyint(1) NOT NULL DEFAULT 0,
  `ADDRESS` text NOT NULL,
  PRIMARY KEY (`ADDRESS_ID`),
  KEY `ADDRESSES_CITY_ID_CITY_CI_ID` (`CITY_ID`),
  KEY `ADDRESSES_USER_ID_USERS_USER_ID` (`USER_ID`),
  KEY `CLIENT_ID` (`CLIENT_ID`),
  KEY `ADDRESS_ID` (`ADDRESS_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `product_category`
--

CREATE TABLE IF NOT EXISTS `product_category` (
  `CAT_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CAT_NAME` varchar(255) NOT NULL,
  `CAT_PARENT` varchar(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`CAT_ID`),
  UNIQUE KEY `CAT_NAME` (`CAT_NAME`),
  KEY `PRODUCT_CATEGORY_CAT_PARENT_PRODUCT_CATEGORY_CAT_ID` (`CAT_PARENT`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `products`
--

CREATE TABLE IF NOT EXISTS `products` (
  `PRO_ID` int(11) NOT NULL AUTO_INCREMENT,
  `CAT_ID` int(11) NOT NULL,
  `PRO_VISBLE` tinyint(1) NOT NULL,
  `PRO_NAME` varchar(255) NOT NULL,
  `PER_BOOKING` tinyint(1) NOT NULL,
  `PRO_DESCRIPTION` text NOT NULL,
  `LENGTH` double NOT NULL,
  `WIDTH` double NOT NULL,
  `HEIGHT` double NOT NULL,
  `WEIGHT` double NOT NULL,
  `PRO_IMG_LINK` text NOT NULL,
  PRIMARY KEY (`PRO_ID`),
  UNIQUE KEY `PRO_NAME` (`PRO_NAME`),
  KEY `PRODUCTS_CAT_ID_PRODUCT_CATEGORY_CAT_ID` (`CAT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `product_option_group`
--

CREATE TABLE IF NOT EXISTS `product_option_group` (
  `OPTION_ID` int(11) NOT NULL AUTO_INCREMENT,
  `OPTION_NAME` varchar(250) NOT NULL,
  `OPTION_TYPE` int(11) NOT NULL,
  PRIMARY KEY (`OPTION_ID`),
  UNIQUE KEY `OPTION_NAME` (`OPTION_NAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `product_option`
--

CREATE TABLE IF NOT EXISTS `product_option` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `OPTION_ID` int(11) NOT NULL,
  `VALUE` varchar(250) NOT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `OPTION_ID_VALUE` (`OPTION_ID`,`VALUE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `product_codes`
--

CREATE TABLE IF NOT EXISTS `product_codes` (
  `PRODUCT_CODE_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `PRODUCT_ID` int(11) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `CODE_PRICE` decimal(18,2) NOT NULL,
  `CODE_COST_PRICE` decimal(18,2) NOT NULL,
  `LOWEST_PRICE` decimal(18,2) NOT NULL,
  `CODE_MIN_STOCK` decimal(18,3) NOT NULL DEFAULT 20.000,
  `CODE_MAX_STOCK` decimal(18,3) NOT NULL DEFAULT 0.000,
  PRIMARY KEY (`PRODUCT_CODE_ID`),
  UNIQUE KEY `PRO_CODE` (`PRO_CODE`),
  KEY `PRODUCT_ID` (`PRODUCT_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `product_join_option_group`
--

CREATE TABLE IF NOT EXISTS `product_join_option_group` (
  `PRODUCT_ID` int(11) NOT NULL,
  `OPTION_GROUP_ID` int(11) NOT NULL,
  PRIMARY KEY (`PRODUCT_ID`,`OPTION_GROUP_ID`),
  KEY `OPTION_GROUP_ID` (`OPTION_GROUP_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `product_code_join_option`
--

CREATE TABLE IF NOT EXISTS `product_code_join_option` (
  `JOIN_ID` int(11) NOT NULL AUTO_INCREMENT,
  `PRODUCT_CODE` varchar(250) NOT NULL,
  `OPTION_VALUE_ID` int(11) NOT NULL,
  PRIMARY KEY (`JOIN_ID`),
  UNIQUE KEY `PRODUCT_CODE` (`PRODUCT_CODE`,`OPTION_VALUE_ID`),
  KEY `PRODUCT_CODE_JOIN_OPTION_ibfk_2` (`OPTION_VALUE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `stores_product`
--

CREATE TABLE IF NOT EXISTS `stores_product` (
  `STO_ID` int(11) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `STO_QTY` decimal(18,0) NOT NULL,
  `LAST_UPDATE` timestamp(6) NOT NULL DEFAULT current_timestamp(6),
  UNIQUE KEY `STO_ID` (`STO_ID`,`PRO_CODE`),
  KEY `STORES_PRODUCT_STO_ID_STORES_STO_ID` (`STO_ID`),
  KEY `STORES_PRODUCT_PRO_CODE_PRODUCT_CODES_PRO_CODE` (`PRO_CODE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `barcode_counter`
--

CREATE TABLE IF NOT EXISTS `barcode_counter` (
  `last_code` bigint(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `entities`
--

CREATE TABLE IF NOT EXISTS `entities` (
  `ENTITY_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CREATE_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ENTITY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `order_type`
--

CREATE TABLE IF NOT EXISTS `order_type` (
  `ORDER_TYPE_ID` int(11) NOT NULL AUTO_INCREMENT,
  `ORDER_TYPE_NAME` varchar(50) NOT NULL,
  PRIMARY KEY (`ORDER_TYPE_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `order_statuse`
--

CREATE TABLE IF NOT EXISTS `order_statuse` (
  `STA_ID` int(11) NOT NULL AUTO_INCREMENT,
  `STA_NAME` varchar(50) NOT NULL,
  `STA_NAME_EN` varchar(50) NOT NULL,
  `COLOR_CLASS` varchar(25) NOT NULL,
  PRIMARY KEY (`STA_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `order_exists`
--

CREATE TABLE IF NOT EXISTS `order_exists` (
  `EXISTS_ID` int(11) NOT NULL AUTO_INCREMENT,
  `EXISTS_NAME` varchar(50) NOT NULL,
  `EXISTS_NAME_EN` varchar(50) NOT NULL,
  `STA_ID` int(3) DEFAULT NULL COMMENT 'حالة امر البيع',
  `TRANSFER` tinyint(1) NOT NULL DEFAULT 0,
  `USER_STORE` int(1) NOT NULL DEFAULT 0 COMMENT 'اذا كانت القيمه true يخصم جميع المنتجات من اصل اجمالى المخزون',
  `ADMIN_STORE` int(1) NOT NULL DEFAULT 0 COMMENT 'اذا كانت القيمه true يخصم جميع المنتجات من اصل اجمالى المخزون',
  `FINANCE` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'اضافة اوامر البيع الى كشف حساب العميل',
  `COLOR_CLASS` varchar(30) NOT NULL,
  PRIMARY KEY (`EXISTS_ID`),
  UNIQUE KEY `EXISTS_NAME` (`EXISTS_NAME`),
  UNIQUE KEY `EXISTS_NAME_EN` (`EXISTS_NAME_EN`),
  KEY `STA_ID` (`STA_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `orders`
--

CREATE TABLE IF NOT EXISTS `orders` (
  `ORDER_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `SUB_ORDER_ID` varchar(19) NOT NULL,
  `ORDER_TYPE_ID` int(20) NOT NULL,
  `DELIVERY_ID` bigint(20) DEFAULT NULL,
  `ORDER_BY` bigint(20) NOT NULL,
  `COUPON_CODE` varchar(10) DEFAULT NULL,
  `CITY_ID` int(11) NOT NULL,
  `ADDRESS_ID` bigint(20) NOT NULL,
  `ADDRESS` varchar(300) NOT NULL,
  `COMMENT` varchar(200) DEFAULT NULL,
  `TEL_ID` bigint(20) NOT NULL,
  `TELEPHONE` varchar(20) NOT NULL,
  `DATE_CREATED` datetime NOT NULL DEFAULT current_timestamp(),
  `DELIVERY_DATE` datetime NOT NULL,
  `DELIVERY_TYPE` int(1) NOT NULL DEFAULT 0,
  `SHIPPING_DATE` datetime DEFAULT NULL,
  `DELIVERED_DATE` timestamp NULL DEFAULT NULL COMMENT 'تاريخ التسليم',
  `SHIPPING_VAL` decimal(18,2) NOT NULL,
  `SHIPP_PAYMENT` tinyint(1) NOT NULL DEFAULT 1,
  `ORDER_STATUS` int(2) NOT NULL,
  `SALE_ENTRY_ID` BIGINT(20) UNSIGNED DEFAULT NULL,
  `RETURN_ENTRY_ID` BIGINT(20) UNSIGNED DEFAULT NULL,
  `EXISTS_ID` int(4) NOT NULL DEFAULT 1,
  `PRINT` tinyint(1) NOT NULL DEFAULT 0,
  `ORDER_SUCCESS` tinyint(1) NOT NULL,
  `STO_ID` int(11) NOT NULL,
  `LAST_UPDATE` datetime(3) NOT NULL DEFAULT current_timestamp(3),
  `EXCHANGE` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`ORDER_ID`),
  UNIQUE KEY `SUB_ORDER_ID` (`SUB_ORDER_ID`),
  UNIQUE KEY `EXCHANGE` (`EXCHANGE`),
  KEY `ORDERS_CITY_ID_CITY_CI_ID` (`CITY_ID`),
  KEY `ORDERS_DELIVERY_ID_USERS_USER_ID` (`DELIVERY_ID`),
  KEY `ORDER_TYPE_ID` (`ORDER_TYPE_ID`),
  KEY `ADDRESS_ID` (`ADDRESS_ID`),
  KEY `TEL_ID` (`TEL_ID`),
  KEY `STO_ID` (`STO_ID`),
  KEY `ORDER_BY` (`ORDER_BY`),
  KEY `orders_date_crated_index` (`DATE_CREATED`),
  KEY `orders_shipping_date_index` (`SHIPPING_DATE`),
  KEY `order_delivery_date_index` (`DELIVERY_DATE`),
  KEY `orders_exists_id_index` (`EXISTS_ID`),
  KEY `orders_statuse_index` (`ORDER_STATUS`),
  KEY `idx_last_update` (`LAST_UPDATE`),
  KEY `idx_orders_id_status` (`ORDER_ID`,`ORDER_STATUS`),
  KEY `idx_orders_sale_entry` (`SALE_ENTRY_ID`),
  KEY `idx_orders_return_entry` (`RETURN_ENTRY_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `order_product`
--

CREATE TABLE IF NOT EXISTS `order_product` (
  `ORDER_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `QTY` decimal(18,2) NOT NULL,
  `PRICE` decimal(18,2) NOT NULL,
  `COST_PRICE` DECIMAL(18,2) NULL COMMENT 'تكلفة الوحدة وقت إنشاء أمر البيع؛ NULL للسجلات التاريخية غير المعروفة',
  `DISCOUNT` decimal(18,2) NOT NULL DEFAULT 0.00,
  `DELIVERED_QTY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `RETURNED_QTY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `PRO_STATUSE` enum('waiting','complete','partial','rejected','partial_refund','refund') NOT NULL DEFAULT 'waiting',
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ORDER_ID`,`PRO_CODE`),
  KEY `ORDER_PRODUCT_ibfk_2` (`PRO_CODE`),
  KEY `idx_order_product_orderid_status` (`ORDER_ID`,`PRO_STATUSE`),
  KEY `idx_order_product_status` (`ORDER_ID`,`PRO_STATUSE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `order_service_type`
--

CREATE TABLE IF NOT EXISTS `order_service_type` (
  `OST_ID` int(11) NOT NULL AUTO_INCREMENT,
  `OST_TYPE` varchar(50) NOT NULL,
  PRIMARY KEY (`OST_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `order_service`
--

CREATE TABLE IF NOT EXISTS `order_service` (
  `OS_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `ORDER_ID` bigint(20) NOT NULL,
  `USER_ID` bigint(20) NOT NULL,
  `OST_ID` int(11) NOT NULL,
  `OS_DATE_OPEN` timestamp NOT NULL DEFAULT current_timestamp(),
  `OS_DATE_CLOSE` timestamp NULL DEFAULT NULL,
  `OS_STATUSE` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`OS_ID`),
  KEY `ORDERID_ID` (`ORDER_ID`),
  KEY `order_service_ibfk_2` (`USER_ID`),
  KEY `OS_TYPE` (`OST_ID`),
  KEY `idx_order_user` (`ORDER_ID`,`USER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `order_service_text`
--

CREATE TABLE IF NOT EXISTS `order_service_text` (
  `OSTX_ID` varchar(300) NOT NULL,
  `USER_ID` bigint(20) NOT NULL,
  `USER_TYPE` int(3) NOT NULL,
  `OS_ID` bigint(20) NOT NULL,
  `OSTX_DATE` timestamp NOT NULL DEFAULT current_timestamp(),
  `OSTX_MSG` varchar(1000) NOT NULL,
  `OSTX_READ` tinyint(1) NOT NULL DEFAULT 0,
  `OSTX_READ_DATE` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`OSTX_ID`),
  KEY `OS_ID` (`OS_ID`),
  KEY `USER_ID` (`USER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `orders_log`
--

CREATE TABLE IF NOT EXISTS `orders_log` (
  `LOG_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `ORDER_ID` bigint(20) NOT NULL,
  `USER_ACTION` bigint(20) NOT NULL,
  `DATE_ACTION` datetime NOT NULL DEFAULT current_timestamp(),
  `LOG_TEXT` longtext NOT NULL,
  PRIMARY KEY (`LOG_ID`),
  KEY `ORDER_ID` (`ORDER_ID`),
  KEY `USER_ACTION` (`USER_ACTION`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `inventory_movement`
--

CREATE TABLE IF NOT EXISTS `inventory_movement` (
  `IM_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CREATED_DATE` timestamp NOT NULL DEFAULT current_timestamp(),
  `IM_MOVE_TYPE` enum('RECEIVING','SHIPPING','SALES','TRANSFER','SETTLEMENT','REFUND','RETURN_SHIPP','RETURN_SALES','RETURN_SHIPP_SALSE','REFUNDPURCHISES') NOT NULL,
  `IM_USER` bigint(20) NOT NULL,
  `IM_BRANCH` int(11) NOT NULL,
  `STO_ID` int(11) NOT NULL,
  `STO_DEST` int(11) DEFAULT NULL,
  `REFERENCE` text DEFAULT NULL,
  `UNIQUE_ID` varchar(100) DEFAULT NULL COMMENT 'معرف تجميعي موحد لحركة المخزون',
  `IM_DETAILS` text DEFAULT '\'\'',
  `IM_APPROVAL_DISBURSED` tinyint(1) NOT NULL DEFAULT 0,
  `IM_APPROVAL_RECEIVED` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`IM_ID`),
  KEY `inventory_movement_ibfk_1` (`STO_ID`),
  KEY `IM_USER` (`IM_USER`),
  KEY `idx_inventory_unique_id` (`UNIQUE_ID`),
  KEY `IM_PRANCH` (`IM_BRANCH`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `inventory_product_movement`
--

CREATE TABLE IF NOT EXISTS `inventory_product_movement` (
  `IPM_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `IM_ID` bigint(20) NOT NULL,
  `ORDER_ID` bigint(20) DEFAULT NULL COMMENT 'مرجع الطلب التشغيلي المرتبط بالحركة؛ يشمل البيع والشراء',
  `IPM_CODE` varchar(50) NOT NULL,
  `IPM_TYPE_MOV` enum('IN','OUT') NOT NULL,
  `IPM_QTY` decimal(18,4) NOT NULL,
  `IPM_STO_QTY` decimal(18,4) NOT NULL COMMENT 'رصيد المخزون بعد',
  `IS_PROCESSED` tinyint(1) NOT NULL DEFAULT 0,
  `CREATE_AT` timestamp(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`IPM_ID`),
  KEY `IM_ID` (`IM_ID`),
  KEY `idx_ipm_order_code` (`ORDER_ID`,`IPM_CODE`),
  KEY `IPM_CODE` (`IPM_CODE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `shipping_movement`
--

CREATE TABLE IF NOT EXISTS `shipping_movement` (
  `shipmov_id` int(11) NOT NULL AUTO_INCREMENT,
  `UNIQUE_ID` varchar(50) DEFAULT NULL,
  `movement_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'From store = 0 |  to store = 1 | to customer = 2 | from customer = 3 | to colleague = 4',
  `delivery_id` bigint(20) NOT NULL,
  `user_id` bigint(20) NOT NULL,
  `shipment_orders` bigint(20) NOT NULL,
  `MOVING_DATE` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`shipmov_id`),
  KEY `delivery_id` (`delivery_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_movement_type_date` (`movement_type`,`MOVING_DATE`),
  KEY `idx_moving_date` (`MOVING_DATE`),
  KEY `movement_type` (`movement_type`,`shipment_orders`),
  KEY `shipment_orders` (`shipment_orders`,`MOVING_DATE`),
  KEY `movement_type_2` (`movement_type`,`shipment_orders`,`MOVING_DATE`),
  KEY `idx_shipment_orders_moving` (`shipment_orders`,`MOVING_DATE`,`shipmov_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `custody_orders`
--

CREATE TABLE IF NOT EXISTS `custody_orders` (
  `ORDER_ID` bigint(20) NOT NULL,
  `USER_ID` bigint(20) NOT NULL,
  `SHIPPING_ADDRESS` text NOT NULL,
  `RETURNS` tinyint(1) NOT NULL DEFAULT 0,
  `CUST_STAT` enum('WAITING','FREEZE','REFUND','DELIVERED','SETTLEMENT') NOT NULL DEFAULT 'WAITING',
  `ORDER_STATUSE` int(11) NOT NULL,
  `CREATE_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ORDER_ID`,`USER_ID`),
  KEY `custody_orders_ibfk_2` (`USER_ID`),
  KEY `idx_custody_orders_id_status` (`ORDER_ID`,`ORDER_STATUSE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `custody_product`
--

CREATE TABLE IF NOT EXISTS `custody_product` (
  `ORDER_ID` bigint(20) NOT NULL,
  `USER_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `QTY` decimal(18,0) NOT NULL,
  `PRICE` decimal(18,2) NOT NULL,
  `DISCOUNT` decimal(18,2) NOT NULL,
  `DELIVERED_QTY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `RETURNED_QTY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `PRO_STATUSE` enum('waiting','partial','complete','rejected','freeze') NOT NULL DEFAULT 'waiting',
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ORDER_ID`,`USER_ID`,`PRO_CODE`),
  KEY `product_code_update_cascad` (`PRO_CODE`),
  KEY `idx_custody_product_orderid_status` (`ORDER_ID`,`PRO_STATUSE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `custody_return_orders`
--

CREATE TABLE IF NOT EXISTS `custody_return_orders` (
  `ORDER_ID` bigint(20) NOT NULL,
  `USER_ID` bigint(20) NOT NULL,
  `SHIPPING_ADDRESS` text NOT NULL,
  `RETURNS` tinyint(1) NOT NULL DEFAULT 0,
  `CUST_STAT` enum('WAITING','RETURNED') NOT NULL DEFAULT 'WAITING',
  `ORDER_STATUSE` int(11) NOT NULL,
  `CREATE_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ORDER_ID`,`USER_ID`),
  KEY `custody_return_orders_ibfk_2` (`USER_ID`),
  KEY `idx_custody_return_orders_id_status_2` (`ORDER_ID`,`ORDER_STATUSE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `custody_return_product`
--

CREATE TABLE IF NOT EXISTS `custody_return_product` (
  `ORDER_ID` bigint(20) NOT NULL,
  `USER_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `QTY` decimal(18,0) NOT NULL,
  `PRICE` decimal(18,2) NOT NULL,
  `DISCOUNT` decimal(18,2) NOT NULL,
  `DELIVERED_QTY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `RETURNED_QTY` decimal(18,2) NOT NULL DEFAULT 0.00,
  `PRO_STATUSE` enum('partial','complete','partial_refund','refund') NOT NULL DEFAULT 'complete',
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ORDER_ID`,`USER_ID`,`PRO_CODE`),
  KEY `custody_return_product_code_update_cascad` (`PRO_CODE`),
  KEY `idx_custody_return_product_orderid_status2` (`ORDER_ID`,`PRO_STATUSE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `sales_return_orders`
--

CREATE TABLE IF NOT EXISTS `sales_return_orders` (
  `RETURN_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `ORDER_ID` bigint(20) NOT NULL,
  `RETURN_BY` bigint(11) NOT NULL,
  `CREATE_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`RETURN_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `purchases_orders`
--

CREATE TABLE IF NOT EXISTS `purchases_orders` (
  `ORDER_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CREATE_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `SUPPLIER_ID` bigint(20) DEFAULT NULL,
  `REFERANC` varchar(255) DEFAULT NULL,
  `TOTAL_PRICE` float(10,2) NOT NULL,
  `TOTAL_PAYMENTS` float(10,2) NOT NULL DEFAULT 0.00,
  `STATUSE` enum('DEFERRED','COMPLETED') NOT NULL DEFAULT 'DEFERRED',
  `NOTE` text DEFAULT NULL,
  PRIMARY KEY (`ORDER_ID`),
  KEY `SUPPLIER_ID` (`SUPPLIER_ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `purchases_products`
--

CREATE TABLE IF NOT EXISTS `purchases_products` (
  `ORDER_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(15) NOT NULL,
  `PURCHASE_UNIT_PRICE` float(10,2) NOT NULL,
  `QTY` decimal(10,2) NOT NULL,
  `QTY_SUPPLIED` decimal(10,2) NOT NULL,
  PRIMARY KEY (`ORDER_ID`,`PRO_CODE`),
  KEY `purchases_products_ibfk_2` (`PRO_CODE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `purchases_return_orders`
--

CREATE TABLE IF NOT EXISTS `purchases_return_orders` (
  `ORDER_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CREATE_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `SUPPLIER_ID` bigint(20) DEFAULT NULL,
  `REFERANC` bigint(20) NOT NULL,
  `TOTAL_PRICE` float(10,2) NOT NULL,
  `TOTAL_PAYMENTS` float(10,2) NOT NULL DEFAULT 0.00,
  `STATUSE` enum('DEFERRED','COMPLETED') NOT NULL DEFAULT 'DEFERRED',
  `NOTE` text DEFAULT NULL,
  PRIMARY KEY (`ORDER_ID`),
  KEY `SUPPLIER_ID` (`SUPPLIER_ID`),
  KEY `purchases_return_orders_ibfk_1` (`REFERANC`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `purchases_return_products`
--

CREATE TABLE IF NOT EXISTS `purchases_return_products` (
  `ORDER_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(15) NOT NULL,
  `PURCHASE_UNIT_PRICE` float(10,2) NOT NULL,
  `QTY_SUPPLIED` decimal(10,2) NOT NULL,
  `QTY_RETURN` decimal(10,2) NOT NULL,
  PRIMARY KEY (`ORDER_ID`,`PRO_CODE`),
  KEY `purchases_products_ibfk_2` (`PRO_CODE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `supply_orders`
--

CREATE TABLE IF NOT EXISTS `supply_orders` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `order_by` bigint(20) NOT NULL,
  `status` enum('Pending','Completed','Cancelled') DEFAULT 'Pending',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `order_by` (`order_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `supply_order_details`
--

CREATE TABLE IF NOT EXISTS `supply_order_details` (
  `supply_order_id` bigint(20) NOT NULL,
  `pro_code` varchar(50) NOT NULL,
  `quantity` float(11,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`supply_order_id`,`pro_code`),
  KEY `pro_code` (`pro_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `invoices`
--

CREATE TABLE IF NOT EXISTS `invoices` (
  `INVO_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CREATE_AT` datetime NOT NULL DEFAULT current_timestamp(),
  `REFERENCE_ID` bigint(20) NOT NULL,
  `REFERENCE_TYPE` enum('SALES','PURCHASES') NOT NULL,
  `TOTAL_PRICE` float(12,2) NOT NULL,
  `RETURN_PRICE` float(12,2) NOT NULL,
  `AMOUNT_PAID` float(12,2) NOT NULL,
  `PAYMENT_SUCCESS` tinyint(1) NOT NULL,
  `REFUND_PRICE` float(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`INVO_ID`),
  KEY `reference index` (`REFERENCE_ID`,`REFERENCE_TYPE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `payment_methods`
--

CREATE TABLE IF NOT EXISTS `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `transaction_categories`
--

CREATE TABLE IF NOT EXISTS `transaction_categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_type` enum('REVENUE','EXPENSES') DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `parent_category_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_category_id` (`parent_category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `financial_transactions`
--

CREATE TABLE IF NOT EXISTS `financial_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `branch_id` int(11) NOT NULL,
  `transaction_user_id` bigint(20) DEFAULT NULL,
  `transaction_date` datetime NOT NULL DEFAULT current_timestamp(),
  `invoice_id` bigint(20) DEFAULT NULL,
  `entity_type` enum('CLIENT','SUPPLIER','EMPLOYEE','DELIVERY','MARKETER') DEFAULT NULL,
  `entity_id` bigint(20) DEFAULT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `status` enum('COMPLETED','CANCELLED','DEFERRED') DEFAULT 'COMPLETED',
  `accounting_posted` tinyint(1) NOT NULL DEFAULT 0,
  `accounting_entry_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `note` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_method_id` (`payment_method_id`),
  KEY `category_id` (`category_id`),
  KEY `transaction_USER_ID` (`transaction_user_id`),
  KEY `branch_id` (`branch_id`),
  KEY `idx_financial_accounting_entry` (`accounting_entry_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- بنية الجدول `partners_balance`
--

CREATE TABLE IF NOT EXISTS `partners_balance` (
  `ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `CREATE_AT` datetime NOT NULL DEFAULT current_timestamp(),
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp(),
  `PARTNER_TYPE` enum('CLIENT','DELIVERY','MARKETER','SUPPLIER') NOT NULL,
  `PARTNER_ID` bigint(20) NOT NULL,
  `REFERENCE_ID` bigint(11) NOT NULL,
  `TRANSACTION_TYPE` enum('CREDIT','DEBIT') NOT NULL,
  `BALANCE` float(12,2) NOT NULL,
  `NOTE` text NOT NULL,
  `TRANSACTION_CATEGORY_ID` int(3) NOT NULL DEFAULT 0 COMMENT 'نوع العمليه',
  `USE_OF_CREDIT` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`ID`),
  KEY `CREATE_AT` (`CREATE_AT`,`PARTNER_TYPE`,`PARTNER_ID`),
  KEY `idx_partner_balance` (`PARTNER_ID`,`PARTNER_TYPE`,`TRANSACTION_TYPE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
-- --------------------------------------------------------

--
-- بنية الجدول `product_options_view`
--

CREATE OR REPLACE VIEW `product_options_view` AS
select 
    `p`.`PRO_ID` AS `PRO_ID`,
    `pc`.`PRO_CODE` AS `PRO_CODE`,
    `pc`.`CODE_COST_PRICE` AS `PURCHASE_PRICE`,
    `pc`.`CODE_PRICE` AS `SELL_PRICE`,
    `pc`.`CODE_MIN_STOCK` AS `MIN_STOCK`,
    `pc`.`CODE_MAX_STOCK` AS `MAX_STOCK`,
    `p`.`PRO_NAME` AS `PRO_NAME`,
    NULL AS `OPT_IDS_JSON`,
    NULL AS `OPT_JSON`
from (`products` `p` join `product_codes` `pc` on(`p`.`PRO_ID` = `pc`.`PRODUCT_ID`));

-- --------------------------------------------------------

--
-- بنية الجدول `purchases_orders_view`
--

CREATE OR REPLACE VIEW `purchases_orders_view` AS
select 
    `po`.`ORDER_ID` AS `ORDER_ID`,
    `po`.`CREATE_AT` AS `CREATE_AT`,
    `po`.`SUPPLIER_ID` AS `SUPPLIER_ID`,
    `s`.`SUP_NAME` AS `SUP_NAME`,
    `po`.`NOTE` AS `NOTE`,
    `s`.`SUP_EMAIL` AS `SUP_EMAIL`,
    NULL AS `TEL_NUMBER`,
    `po`.`TOTAL_PRICE` AS `TOTAL_PRICE`,
    NULL AS `QTY_ORDER_TOTAL`,
    NULL AS `QTY_SUPPLIED_TOTAL`,
    NULL AS `QTY_ORDER_WAITING`,
    NULL AS `QTY_RETURN`,
    NULL AS `SUPPLIED_STATUSE`,
    `po`.`STATUSE` AS `PAYMENT_STATUSE`,
    NULL AS `RETURN_STATUS`,
    NULL AS `TOTAL_WAIT_PRICE`,
    NULL AS `TOTAL_ORDER_PRICE`,
    NULL AS `TOTAL_SUP_PRICE`,
    `po`.`TOTAL_PAYMENTS` AS `TOTAL_PAYMENTS`,
    NULL AS `PRODUCT_DETAILS`
from (`purchases_orders` `po` left join `suppliers` `s` on(`po`.`SUPPLIER_ID` = `s`.`SUP_ID`));

-- --------------------------------------------------------

--
-- جدول مفقود: `sales_return_products`
--

CREATE TABLE IF NOT EXISTS `sales_return_products` (
  `RETURN_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `QTY` decimal(18,2) NOT NULL,
  `PRICE` decimal(18,2) NOT NULL,
  `DISCOUNT` decimal(18,2) NOT NULL DEFAULT 0.00,
  `RETURN_REASON` text DEFAULT NULL,
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`RETURN_ID`,`PRO_CODE`),
  KEY `PRO_CODE` (`PRO_CODE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- جدول مفقود: `purchases_payments`
--

CREATE TABLE IF NOT EXISTS `purchases_payments` (
  `PAYMENT_ID` bigint(20) NOT NULL AUTO_INCREMENT,
  `ORDER_ID` bigint(20) NOT NULL,
  `PAYMENT_DATE` datetime NOT NULL DEFAULT current_timestamp(),
  `AMOUNT` decimal(18,2) NOT NULL,
  `PAYMENT_METHOD` int(11) NOT NULL,
  `NOTE` text DEFAULT NULL,
  `CREATED_BY` bigint(20) NOT NULL,
  PRIMARY KEY (`PAYMENT_ID`),
  KEY `ORDER_ID` (`ORDER_ID`),
  KEY `CREATED_BY` (`CREATED_BY`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- جدول مفقود: `system_settings`
--

CREATE TABLE IF NOT EXISTS `system_settings` (
  `SETTING_ID` int(11) NOT NULL AUTO_INCREMENT,
  `SETTING_KEY` varchar(100) NOT NULL,
  `SETTING_VALUE` text NOT NULL,
  `SETTING_DESCRIPTION` text DEFAULT NULL,
  `LAST_UPDATE` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`SETTING_ID`),
  UNIQUE KEY `SETTING_KEY` (`SETTING_KEY`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- قيود الجداول الملقاة
--

--
-- قيود الجداول `gov`
--
ALTER TABLE `gov`
  ADD CONSTRAINT `GOV_ibfk_1` FOREIGN KEY (`COUNTRY_ID`) REFERENCES `countries` (`CO_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `GOV_ibfk_2` FOREIGN KEY (`REGIONAL_AREAS_ID`) REFERENCES `regional_areas` (`ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `zones`
--
ALTER TABLE `zones`
  ADD CONSTRAINT `zones_ibfk_1` FOREIGN KEY (`STORE_ID`) REFERENCES `stores` (`STO_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `city`
--
ALTER TABLE `city`
  ADD CONSTRAINT `CITY_ibfk_1` FOREIGN KEY (`GOV_ID`) REFERENCES `gov` (`GOV_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `CITY_ibfk_2` FOREIGN KEY (`ZONE_ID`) REFERENCES `zones` (`ZONE_ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- قيود الجداول `companies`
--
ALTER TABLE `companies`
  ADD CONSTRAINT `COMPANY_ibfk_1` FOREIGN KEY (`CITY_ID`) REFERENCES `city` (`CI_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `BRANCHES_ibfk_1` FOREIGN KEY (`CITY_ID`) REFERENCES `city` (`CI_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `BRANCHES_ibfk_2` FOREIGN KEY (`COMPANIE_ID`) REFERENCES `companies` (`COM_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `stores`
--
ALTER TABLE `stores`
  ADD CONSTRAINT `STORES_ibfk_1` FOREIGN KEY (`BRANCH_ID`) REFERENCES `branches` (`BR_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `STORES_ibfk_2` FOREIGN KEY (`STTY_ID`) REFERENCES `stores_type` (`STTY_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `USERS_ibfk_1` FOREIGN KEY (`BRANCH_ID`) REFERENCES `branches` (`BR_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `USERS_ibfk_2` FOREIGN KEY (`USER_GRUOP_ID`) REFERENCES `user_gruop` (`USER_GRUOP_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `users_affiliate`
--
ALTER TABLE `users_affiliate`
  ADD CONSTRAINT `users_affiliate_ibfk_1` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `users_affiliate_ibfk_2` FOREIGN KEY (`AFFILIATEED_WITH`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `users_delivery`
--
ALTER TABLE `users_delivery`
  ADD CONSTRAINT `users_delivery_ibfk_1` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `users_delivery_ibfk_2` FOREIGN KEY (`ZONE_ID`) REFERENCES `zones` (`ZONE_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `telephones`
--
ALTER TABLE `telephones`
  ADD CONSTRAINT `telephones_ibfk_1` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `telephones_ibfk_2` FOREIGN KEY (`CLIENT_ID`) REFERENCES `client` (`CLIENT_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `telephones_ibfk_3` FOREIGN KEY (`SUPPLIER_ID`) REFERENCES `suppliers` (`SUP_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `addresses`
--
ALTER TABLE `addresses`
  ADD CONSTRAINT `ADDRESSES_ibfk_1` FOREIGN KEY (`CLIENT_ID`) REFERENCES `client` (`CLIENT_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ADDRESSES_ibfk_2` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ADDRESSES_ibfk_3` FOREIGN KEY (`CITY_ID`) REFERENCES `city` (`CI_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `PRODUCTS_ibfk_1` FOREIGN KEY (`CAT_ID`) REFERENCES `product_category` (`CAT_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `product_option`
--
ALTER TABLE `product_option`
  ADD CONSTRAINT `product_option_ibfk_1` FOREIGN KEY (`OPTION_ID`) REFERENCES `product_option_group` (`OPTION_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `product_codes`
--
ALTER TABLE `product_codes`
  ADD CONSTRAINT `product_codes_ibfk_1` FOREIGN KEY (`PRODUCT_ID`) REFERENCES `products` (`PRO_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `product_join_option_group`
--
ALTER TABLE `product_join_option_group`
  ADD CONSTRAINT `product_join_option_group_ibfk_1` FOREIGN KEY (`PRODUCT_ID`) REFERENCES `products` (`PRO_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `product_join_option_group_ibfk_2` FOREIGN KEY (`OPTION_GROUP_ID`) REFERENCES `product_option_group` (`OPTION_ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `product_code_join_option`
--
ALTER TABLE `product_code_join_option`
  ADD CONSTRAINT `product_code_join_option_ibfk_1` FOREIGN KEY (`PRODUCT_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `product_code_join_option_ibfk_2` FOREIGN KEY (`OPTION_VALUE_ID`) REFERENCES `product_option` (`ID`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- قيود الجداول `stores_product`
--
ALTER TABLE `stores_product`
  ADD CONSTRAINT `STORES_PRODUCT_ibfk_1` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE,
  ADD CONSTRAINT `STORES_PRODUCT_ibfk_2` FOREIGN KEY (`STO_ID`) REFERENCES `stores` (`STO_ID`) ON UPDATE CASCADE;
 
--
-- قيود الجداول `order_exists`
--
ALTER TABLE `order_exists`
  ADD CONSTRAINT `order_exists_ibfk_1` FOREIGN KEY (`STA_ID`) REFERENCES `order_statuse` (`STA_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `ORDERS_ibfk_1` FOREIGN KEY (`CITY_ID`) REFERENCES `city` (`CI_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_ibfk_2` FOREIGN KEY (`DELIVERY_ID`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_ibfk_3` FOREIGN KEY (`ORDER_TYPE_ID`) REFERENCES `order_type` (`ORDER_TYPE_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_ibfk_4` FOREIGN KEY (`ADDRESS_ID`) REFERENCES `addresses` (`ADDRESS_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_ibfk_5` FOREIGN KEY (`TEL_ID`) REFERENCES `telephones` (`TEL_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_ibfk_6` FOREIGN KEY (`ORDER_STATUS`) REFERENCES `order_statuse` (`STA_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_ibfk_7` FOREIGN KEY (`STO_ID`) REFERENCES `stores` (`STO_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDERS_ibfk_8` FOREIGN KEY (`ORDER_BY`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `order_product`
--
ALTER TABLE `order_product`
  ADD CONSTRAINT `ORDER_PRODUCT_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `ORDER_PRODUCT_ibfk_2` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- قيود الجداول `order_service`
--
ALTER TABLE `order_service`
  ADD CONSTRAINT `order_service_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_service_ibfk_2` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `order_service_ibfk_3` FOREIGN KEY (`OST_ID`) REFERENCES `order_service_type` (`OST_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `order_service_text`
--
ALTER TABLE `order_service_text`
  ADD CONSTRAINT `order_service_text_ibfk_1` FOREIGN KEY (`OS_ID`) REFERENCES `order_service` (`OS_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `order_service_text_ibfk_2` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `orders_log`
--
ALTER TABLE `orders_log`
  ADD CONSTRAINT `orders_log_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `orders_log_ibfk_2` FOREIGN KEY (`USER_ACTION`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `inventory_movement`
--
ALTER TABLE `inventory_movement`
  ADD CONSTRAINT `inventory_movement_ibfk_1` FOREIGN KEY (`STO_ID`) REFERENCES `stores` (`STO_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `inventory_movement_ibfk_2` FOREIGN KEY (`IM_USER`) REFERENCES `users` (`USER_ID`) ON DELETE NO ACTION ON UPDATE CASCADE,
  ADD CONSTRAINT `inventory_movement_ibfk_3` FOREIGN KEY (`IM_BRANCH`) REFERENCES `branches` (`BR_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `inventory_product_movement`
--
ALTER TABLE `inventory_product_movement`
  ADD CONSTRAINT `inventory_product_movement_ibfk_1` FOREIGN KEY (`IM_ID`) REFERENCES `inventory_movement` (`IM_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `inventory_product_movement_ibfk_2` FOREIGN KEY (`IPM_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- قيود الجداول `shipping_movement`
--
ALTER TABLE `shipping_movement`
  ADD CONSTRAINT `shipping_movement_ibfk_1` FOREIGN KEY (`delivery_id`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `shipping_movement_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `custody_orders`
--
ALTER TABLE `custody_orders`
  ADD CONSTRAINT `custody_orders_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `custody_orders_ibfk_2` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `custody_product`
--
ALTER TABLE `custody_product`
  ADD CONSTRAINT `custody_product_ibfk_1` FOREIGN KEY (`ORDER_ID`,`USER_ID`) REFERENCES `custody_orders` (`ORDER_ID`, `USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `custody_product_ibfk_2` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- قيود الجداول `custody_return_orders`
--
ALTER TABLE `custody_return_orders`
  ADD CONSTRAINT `custody_return_orders_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `custody_return_orders_ibfk_2` FOREIGN KEY (`USER_ID`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `custody_return_product`
--
ALTER TABLE `custody_return_product`
  ADD CONSTRAINT `custody_return_product_ibfk_1` FOREIGN KEY (`ORDER_ID`,`USER_ID`) REFERENCES `custody_return_orders` (`ORDER_ID`, `USER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `custody_return_product_ibfk_2` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- قيود الجداول `sales_return_orders`
--
ALTER TABLE `sales_return_orders`
  ADD CONSTRAINT `sales_return_orders_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `orders` (`ORDER_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `sales_return_products`
--
ALTER TABLE `sales_return_products`
  ADD CONSTRAINT `sales_return_products_ibfk_1` FOREIGN KEY (`RETURN_ID`) REFERENCES `sales_return_orders` (`RETURN_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `sales_return_products_ibfk_2` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- قيود الجداول `purchases_orders`
--
ALTER TABLE `purchases_orders`
  ADD CONSTRAINT `purchases_orders_ibfk_1` FOREIGN KEY (`SUPPLIER_ID`) REFERENCES `suppliers` (`SUP_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `purchases_products`
--
ALTER TABLE `purchases_products`
  ADD CONSTRAINT `purchases_products_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `purchases_orders` (`ORDER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `purchases_products_ibfk_2` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- قيود الجداول `purchases_return_orders`
--
ALTER TABLE `purchases_return_orders`
  ADD CONSTRAINT `purchases_return_orders_ibfk_1` FOREIGN KEY (`SUPPLIER_ID`) REFERENCES `suppliers` (`SUP_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `purchases_return_orders_ibfk_2` FOREIGN KEY (`REFERANC`) REFERENCES `purchases_orders` (`ORDER_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `purchases_return_products`
--
ALTER TABLE `purchases_return_products`
  ADD CONSTRAINT `purchases_return_products_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `purchases_return_orders` (`ORDER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `purchases_return_products_ibfk_2` FOREIGN KEY (`PRO_CODE`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- قيود الجداول `purchases_payments`
--
ALTER TABLE `purchases_payments`
  ADD CONSTRAINT `purchases_payments_ibfk_1` FOREIGN KEY (`ORDER_ID`) REFERENCES `purchases_orders` (`ORDER_ID`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `purchases_payments_ibfk_2` FOREIGN KEY (`CREATED_BY`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `supply_orders`
--
ALTER TABLE `supply_orders`
  ADD CONSTRAINT `supply_orders_ibfk_1` FOREIGN KEY (`order_by`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `supply_order_details`
--
ALTER TABLE `supply_order_details`
  ADD CONSTRAINT `supply_order_details_ibfk_1` FOREIGN KEY (`supply_order_id`) REFERENCES `supply_orders` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `supply_order_details_ibfk_2` FOREIGN KEY (`pro_code`) REFERENCES `product_codes` (`PRO_CODE`) ON UPDATE CASCADE;

--
-- قيود الجداول `transaction_categories`
--
ALTER TABLE `transaction_categories`
  ADD CONSTRAINT `transaction_categories_ibfk_1` FOREIGN KEY (`parent_category_id`) REFERENCES `transaction_categories` (`id`) ON UPDATE CASCADE;

--
-- قيود الجداول `financial_transactions`
--
ALTER TABLE `financial_transactions`
  ADD CONSTRAINT `financial_transactions_ibfk_1` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `financial_transactions_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `transaction_categories` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `financial_transactions_ibfk_3` FOREIGN KEY (`transaction_user_id`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `financial_transactions_ibfk_4` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`BR_ID`) ON UPDATE CASCADE;

--
-- قيود الجداول `partners_balance`
--
ALTER TABLE `partners_balance`
  ADD CONSTRAINT `partners_balance_ibfk_1` FOREIGN KEY (`PARTNER_ID`) REFERENCES `users` (`USER_ID`) ON UPDATE CASCADE,
  ADD CONSTRAINT `partners_balance_ibfk_2` FOREIGN KEY (`TRANSACTION_CATEGORY_ID`) REFERENCES `transaction_categories` (`id`) ON UPDATE CASCADE;

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

-- --------------------------------------------------------
-- إدخال البيانات بالترتيب الصحيح للمفاتيح الأجنبية
-- --------------------------------------------------------

--
-- إرجاع أو استيراد بيانات الجدول `countries`
--

INSERT INTO `countries` (`CO_ID`, `CO_NAME`, `CO_NAME_EN`) VALUES
(1, 'مصر', 'Egypt');

--
-- إرجاع أو استيراد بيانات الجدول `regional_areas`
--

INSERT INTO `regional_areas` (`ID`, `REGIONAL_NAME`) VALUES
(1, 'إقليم القاهرة الكبرى'),
(2, 'إقليم الإسكندرية'),
(3, 'إقليم الدلتا'),
(4, 'إقليم القناة'),
(5, 'إقليم شمال الصعيد'),
(6, 'إقليم وسط الصعيد'),
(7, 'إقليم جنوب الصعيد');

--
-- إرجاع أو استيراد بيانات الجدول `gov`
--

INSERT INTO `gov` (`GOV_ID`, `COUNTRY_ID`, `GOV_NAME`, `REGIONAL_AREAS_ID`, `GOV_NAME_EN`) VALUES
(1, 1, 'القاهره', 1, 'Cairo'),
(2, 1, 'الاسكندريه', 2, 'Alexandria'),
(3, 1, 'الجيزه', 1, 'Giza'),
(4, 1, 'القليوبيه', 1, 'Qalyubia'),
(5, 1, 'الشرقيه', 4, 'Sharqia'),
(6, 1, 'الدقهليه', 3, 'Dakahlia'),
(7, 1, 'البحيره', 2, 'Beheira'),
(8, 1, 'كفر الشيخ', 3, 'Kafr El Sheikh'),
(9, 1, 'الغربيه', 3, 'Gharbia'),
(10, 1, 'المنوفيه', 3, 'Menofia'),
(11, 1, 'الفيوم', 5, 'Faiyum'),
(12, 1, 'بنى سويف', 5, 'Beni Suef'),
(13, 1, 'المنيا', 5, 'Minya'),
(14, 1, 'اسيوط', 6, 'Assiut'),
(15, 1, 'سوهاج', 7, 'Sohag'),
(16, 1, 'قنا', 7, 'Qena'),
(17, 1, 'الاقصر', 7, 'Luxor'),
(18, 1, 'اسوان', 7, 'Aswan'),
(19, 1, 'البحر الأحمر', 4, 'Red Sea'),
(20, 1, 'الوادي الجديد', 6, 'New Valley'),
(21, 1, 'مطروح', 2, 'Matrouh'),
(22, 1, 'شمال سيناء', 4, 'North Sinai'),
(23, 1, 'جنوب سيناء', 4, 'South Sinai'),
(24, 1, 'بورسعيد', 4, 'Port Said'),
(25, 1, 'الاسماعيليه', 4, 'Ismailia'),
(26, 1, 'السويس', 4, 'Suez'),
(27, 1, 'دمياط', 3, 'Damietta');

--
-- إرجاع أو استيراد بيانات الجدول `shipping_type`
--

INSERT INTO `shipping_type` (`SHIPPING_T_ID`, `SHIPPING_T_NAME`) VALUES
(1, 'شحن داخلى'),
(2, 'شحن خارجى');

--
-- إرجاع أو استيراد بيانات الجدول `stores_type`
--

INSERT INTO `stores_type` (`STTY_ID`, `STTY_NAME`, `STTY_DESCRIPTION`) VALUES
(1, 'عادى', 'المخزن من نوع (عادى) يستخدم فقظ للتحويلات الداخلية بين المخازن الاخرى'),
(2, 'بيع مباشر', 'المخزن من نوع (بيع مباشر) يستخدم فى اذون الصرف الخاصة بأومر البيع كا مثال تسليم العهده للمنديب او العملاء');

--
-- إرجاع أو استيراد بيانات الجدول `user_gruop`
--

INSERT INTO `user_gruop` (`USER_GRUOP_ID`, `USER_GROUP_PARENT`, `USER_GRUOP_NAME`, `USER_SCRIPT`) VALUES
(1, 0, 'Administrator', 'a:1:{s:5:\"Admin\";a:49:{s:9:\"dashboard\";a:5:{s:5:\"Admin\";s:4:\"true\";s:16:\"CustomerService1\";s:4:\"true\";s:16:\"CustomerService2\";s:4:\"true\";s:7:\"Storage\";s:4:\"true\";s:8:\"Delivery\";s:4:\"true\";}s:9:\"Countries\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:3:\"Gov\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:4:\"City\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:14:\"Local-Shipping\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:22:\"International-Shipping\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:10:\"Enterprise\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:24:\"Administrative-Structure\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:8:\"Branches\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:9:\"Warehouse\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:5:\"Users\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:7:\"Clients\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:9:\"Suppliers\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:23:\"UserSpecificPermissions\";a:3:{s:14:\"viewAllCompany\";s:5:\"false\";s:15:\"viewAllBranches\";s:5:\"false\";s:13:\"viewAllStores\";s:5:\"false\";}s:7:\"Reports\";a:1:{s:4:\"View\";s:4:\"true\";}s:18:\"Product-Categories\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:13:\"ProductOption\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:8:\"Products\";a:4:{s:4:\"View\";s:4:\"true\";s:6:\"Addnew\";s:4:\"true\";s:6:\"Update\";s:4:\"true\";s:6:\"Delete\";s:4:\"true\";}s:8:\"NewOrder\";a:1:{s:12:\"Create_Order\";s:4:\"true\";}s:13:\"orderlist_ex0\";a:2:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";}s:11:\"selseReport\";a:1:{s:4:\"View\";s:5:\"false\";}s:13:\"orderlist_ex1\";a:13:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";}s:13:\"orderlist_ex2\";a:13:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:5:\"false\";s:19:\"Confirm sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";}s:13:\"orderlist_ex3\";a:13:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";}s:13:\"orderlist_ex4\";a:13:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:5:\"false\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";}s:13:\"orderlist_ex5\";a:13:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:5:\"false\";s:11:\"Direct sale\";s:5:\"false\";}s:17:\"SupportAfterSales\";a:2:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";}s:13:\"SupportOrders\";a:11:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:8:\"Prepared\";s:4:\"true\";}s:13:\"orderlist_ex7\";a:14:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:12:\"Invoce_Print\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:8:\"Prepared\";s:4:\"true\";s:11:\"Direct sale\";s:5:\"false\";s:7:\"Nostore\";s:5:\"false\";}s:13:\"orderlist_ex8\";a:15:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:4:\"true\";s:8:\"Shipment\";s:4:\"true\";s:12:\"Shipmentdate\";s:4:\"true\";s:14:\"transformation\";s:4:\"true\";s:7:\"Nostore\";s:4:\"true\";s:22:\"Advance booking orders\";s:4:\"true\";s:12:\"Cancel order\";s:4:\"true\";s:10:\"Processing\";s:4:\"true\";s:19:\"Confirm sale orders\";s:4:\"true\";s:19:\"pending sale orders\";s:4:\"true\";s:28:\"The client is not responding\";s:4:\"true\";s:11:\"Direct sale\";s:5:\"false\";}s:13:\"orderlist_ex9\";a:11:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:18:\"DeliveredFromAdmin\";s:4:\"true\";s:14:\"RefundShipment\";s:4:\"true\";s:12:\"ChooseRefund\";s:4:\"true\";s:3:\"os2\";s:4:\"true\";s:3:\"os1\";s:4:\"true\";s:14:\"transformation\";s:5:\"false\";s:13:\"EditDelevered\";s:5:\"false\";s:10:\"Edit_order\";s:5:\"false\";s:12:\"Delete_order\";s:5:\"false\";}s:13:\"OrdersCustody\";a:6:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:13:\"EditDelevered\";s:4:\"true\";s:16:\"ShipmentApproved\";s:4:\"true\";s:14:\"RefundShipment\";s:4:\"true\";s:12:\"ChooseRefund\";s:4:\"true\";}s:12:\"StockReceipt\";a:2:{s:4:\"view\";s:4:\"true\";s:15:\"viewAllBranches\";s:5:\"false\";}s:10:\"StockIssue\";a:1:{s:4:\"view\";s:4:\"true\";}s:16:\"InternalTransfer\";a:1:{s:4:\"view\";s:4:\"true\";}s:10:\"StockAudit\";a:3:{s:4:\"view\";s:4:\"true\";s:15:\"viewAllBranches\";s:5:\"false\";s:12:\"viewStoreQty\";s:5:\"false\";}s:19:\"CriticalItemsReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:23:\"DelegatesShipmentReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"DelegatesInventoryReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:18:\"suppliersQtyReport\";a:1:{s:4:\"view\";s:4:\"true\";}s:15:\"PaymentsReceipt\";a:1:{s:4:\"view\";s:4:\"true\";}s:19:\"PaymentDisbursement\";a:1:{s:4:\"view\";s:4:\"true\";}s:19:\"CashReceiptVouchers\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"AccountStatementSupplier\";a:1:{s:4:\"view\";s:4:\"true\";}s:22:\"AccountStatementClient\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"AccountStatementMarketer\";a:1:{s:4:\"view\";s:4:\"true\";}s:24:\"AccountStatementDelivery\";a:1:{s:4:\"view\";s:4:\"true\";}s:14:\"orderlist_ex10\";a:13:{s:15:\"View_order_list\";s:4:\"true\";s:10:\"View_order\";s:4:\"true\";s:10:\"Edit_order\";s:4:\"true\";s:12:\"Delete_order\";s:5:\"false\";s:14:\"transformation\";s:5:\"false\";s:8:\"Shipping\";s:5:\"false\";s:3:\"os2\";s:5:\"false\";s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";}s:14:\"orderlist_ex11\";a:7:{s:19:\"Confirm sale orders\";s:5:\"false\";s:19:\"pending sale orders\";s:5:\"false\";s:22:\"Advance booking orders\";s:5:\"false\";s:28:\"The client is not responding\";s:5:\"false\";s:12:\"Cancel order\";s:5:\"false\";s:10:\"Processing\";s:5:\"false\";s:8:\"Prepared\";s:5:\"false\";}}}');







--
-- 9. المناطق
--
SET FOREIGN_KEY_CHECKS=0;

INSERT INTO `zones` (`ZONE_ID`, `ZONE_NAME`, `ZONE_NAME_EN`, `SHIPPING_RATE`, `SHIPPING_LOWEST`, `SHIPPING_TIME_MAX`, `SHIPPING_TYPE`, `STORE_ID`) VALUES
(12, 'مدينه نصر', 'Madenet Nasr', 50.00, 1.00, 1, 1, 1),
(13, 'مصر الجديده', 'Misr Elgedida', 50.00, 1.00, 1, 1, 1),
(14, 'المعادى', 'Elmaadi', 50.00, 1.00, 1, 1, 1),
(15, 'وسط البلد', 'West Elbalad', 50.00, 1.00, 1, 1, 1),
(16, 'التجمع', 'Eltagamoa', 50.00, 1.00, 1, 2, 1),
(17, 'اكتوبر', 'October', 60.00, 1.00, 1, 1, 1),
(18, 'الجيزه1', 'Elgiza', 50.00, 1.00, 1, 1, 1),
(19, 'اسوان', 'Aswan', 85.00, 0.00, 3, 2, 1),
(20, 'اسكندريه', 'Alexandria', 60.00, 0.00, 3, 2, 1),
(21, 'اسيوط', 'Asyout', 80.00, 0.00, 3, 2, 1),
(22, 'الاسماعيليه', 'Elesmailia', 60.00, 0.00, 3, 2, 1),
(23, 'الاقصر', 'Luoxur', 85.00, 0.00, 3, 2, 1),
(24, 'البجر الاحمر', 'Elbahr Elahmar', 85.00, 0.00, 3, 2, 1),
(25, 'الدقهليه', 'Eldaahlya', 60.00, 0.00, 3, 2, 1),
(26, 'السويس', 'Elswyes', 60.00, 0.00, 3, 2, 1),
(27, 'الشرقيه', 'Elsharqia', 60.00, 0.00, 3, 2, 1),
(28, 'الغربيه', 'Elgharbya', 60.00, 0.00, 3, 2, 1),
(29, 'الفيوم', 'Elfayoum', 70.00, 0.00, 3, 2, 1),
(30, 'القليوبيه', 'Elqalyoubia', 60.00, 0.00, 3, 2, 1),
(31, 'المنوفيه', 'Elmnofia', 60.00, 0.00, 3, 2, 1),
(32, 'المنيا', 'Elmnian', 70.00, 0.00, 3, 2, 1),
(33, 'الوادى الجديد', 'Elwadielgded', 100.00, 0.00, 3, 2, 1),
(34, 'بنى سويف', 'BaniSwef', 70.00, 0.00, 3, 2, 1),
(35, 'بورسعيد', 'portsaid', 60.00, 0.00, 3, 2, 1),
(36, 'سيناء', 'Saynai', 85.00, 0.00, 3, 2, 1),
(37, 'دمياط', 'Domyat', 60.00, 0.00, 3, 2, 1),
(38, 'سوهاج', 'Sohag', 80.00, 0.00, 3, 2, 1),
(39, 'قنا', 'Qina', 80.00, 0.00, 3, 2, 1),
(40, 'كفر الشيخ', 'Kafrelshegh', 60.00, 0.00, 3, 2, 1),
(41, 'الباقى', 'elbake', 60.00, 0.00, 3, 2, 1),
(42, 'مطروح', 'Matroh', 85.00, 1.00, 3, 2, 1),
(43, 'الجيزه2', 'Algiza', 50.00, 0.00, 2, 1, 1);
--
-- 10. المدن (أول 50 مدينة كمثال)
--
INSERT INTO `city` (`CI_ID`, `GOV_ID`, `ZONE_ID`, `CI_NAME`, `CI_NAME_EN`) VALUES
(2, 1, 13, 'الزيتون', 'Zeitoun'),
(3, 1, 15, 'الزاوية الحمراء', 'Zawya al-Hamra'),
(5, 1, 15, 'الشرابية', 'Sharbia'),
(6, 1, 15, 'الساحل', 'El Sahel'),
(7, 1, 15, 'شبرا', 'Shubra'),
(8, 1, 15, 'روض الفرج', 'Rod El Farag'),
(9, 1, 13, 'الاميرية', 'El Ameeria'),
(10, 1, 13, 'السلام', 'Salam'),
(12, 1, 13, 'المرج', 'El Marg'),
(13, 1, 13, 'المطريه', 'El Matariya'),
(14, 1, 13, 'عين شمس', 'Ain Shams'),
(15, 1, 13, 'النزهة', 'Al Nozha'),
(16, 1, 13, 'مصر الجديدة', 'Heliopolis'),
(17, 1, 12, 'مدينة نصر', 'Nasr City'),
(19, 1, 15, 'الوايلي', 'El Waily'),
(20, 1, 12, 'منشاه ناصر', 'Mansheyet Nasser'),
(21, 1, 15, 'وسط البلد', 'Downtown'),
(22, 1, 15, 'باب الشعرية', 'Bab al-Sharia'),
(23, 1, 15, 'الازبكية', 'Azbakeya'),
(24, 1, 15, 'بولاق ابو العلا', 'Bulaq abu elela'),
(25, 1, 15, 'الموسكي', 'El Mosky'),
(26, 1, 15, 'عابدين', 'Abdeen'),
(28, 1, 16, 'المقطم', 'Mokattam'),
(29, 1, 15, 'الخليفة', 'El Khalifa'),
(30, 1, 15, 'السيدة زينب', 'Sayeda Zeinab'),
(31, 1, 15, 'مصر القديمة', 'Old Cairo'),
(32, 1, 14, 'دار السلام', 'Dar El Salam'),
(33, 1, 14, 'البساتين', 'El Bassateen'),
(34, 1, 14, 'المعادي', 'Maadi'),
(35, 1, 14, 'طره', 'Torah'),
(36, 1, 14, 'المعصرة', 'El Maasara'),
(37, 1, 14, '15 مايو', 'May 15'),
(38, 1, 14, 'حلوان', 'Helwan'),
(39, 1, 14, 'التبين', 'Tebeen'),
(41, 2, 20, 'المنتزه', 'Montaza 1'),
(45, 2, 20, 'الجمرك', 'El Gomrok'),
(47, 2, 20, 'العجمي', 'El Agamy'),
(48, 2, 20, 'العامريه', 'Amreya 1'),
(50, 2, 20, 'برج العرب', 'Borg El Arab'),
(51, 2, 20, 'سموحة', 'Smouha'),
(52, 2, 20, 'المندرة', 'Mandara'),
(53, 2, 20, 'ابو قير', 'Abu Qir'),
(54, 2, 20, 'كوم الدكة', 'Kom El Dekka'),
(55, 2, 20, 'المنشية', 'El Mansheya'),
(56, 2, 20, 'العطارين', 'Attarine'),
(57, 2, 20, 'محرم بك', 'Moharram Bek'),
(58, 2, 20, 'سيدي جابر', 'Sidi Gaber'),
(59, 2, 20, 'الورديان', 'El Wardian'),
(60, 2, 20, 'القباري', 'El Kabari'),
(61, 2, 20, 'البيطاش', 'El Beitash');

--
-- 6. الشركات
--
INSERT INTO `companies` (`COM_ID`, `COM_NAME`, `COM_TEL`, `COM_FAX`, `COM_MOBILE`, `COM_EMAIL`, `CITY_ID`, `COM_ADDRESS`, `LOGOBRAND`) VALUES
(1, 'just-M', '0225502984', '0225502984', '012-25133341', 'm.r.01225133341@gmail.com', 38, '121', NULL);

--
-- 7. الفروع
--
INSERT INTO `branches` (`BR_ID`, `COMPANIE_ID`, `CITY_ID`, `BR_NAME`, `BR_MAIN`, `BR_ADDRESS`) VALUES
(1, 1, 30, 'المقر الرئيسى', 1, '30 كورنيش النيل الدور الاول');

--
-- 8. المخازن
--
INSERT INTO `stores` (`STO_ID`, `BRANCH_ID`, `STO_NAME`, `STTY_ID`) VALUES
(1, 1, 'المخزن الرئيسى', 2);

SET FOREIGN_KEY_CHECKS=1;



--
-- 11. فئات المنتجات
--
INSERT INTO `product_category` (`CAT_ID`, `CAT_NAME`, `CAT_PARENT`) VALUES
(1, 'جميع الاقسام', '0'),
(3, 'بنطلون', '1'),
(4, 'اسدالات', '1');

--
-- 12. أنواع الطلبات
--
INSERT INTO `order_type` (`ORDER_TYPE_ID`, `ORDER_TYPE_NAME`) VALUES
(1, 'إدارى'),
(2, 'مسوق'),
(3, 'وسيط بيع'),
(4, 'تاجر'),
(5, 'بيع مباشر');

--
-- 13. حالات الطلبات
--
INSERT INTO `order_statuse` (`STA_ID`, `STA_NAME`, `STA_NAME_EN`, `COLOR_CLASS`) VALUES
(0, 'قيد التأكيد', 'pending', 'dark'),
(1, 'جارى تجهيزالمنتج للشحن', 'Processing', 'primary '),
(2, 'جارى الشحن', 'Shipping', 'warning'),
(3, 'تم التسليم', 'Complete ', 'success '),
(4, 'تسليم جزئى', 'Partial ', 'info'),
(5, 'مرفوض', 'Rejected', 'danger'),
(6, 'مرتجع', 'Refund', 'danger'),
(7, 'مرتجع جزئى', 'Partial Refund', 'warning');

--
-- 14. حالات وجود الطلبات
--
INSERT INTO `order_exists` (`EXISTS_ID`, `EXISTS_NAME`, `EXISTS_NAME_EN`, `STA_ID`, `TRANSFER`, `USER_STORE`, `ADMIN_STORE`, `COLOR_CLASS`) VALUES
(1, 'قيد المراجعه', 'Confirm sale orders', 0, 0, 1, 0, 'badge-info'),
(2, 'مؤجل', 'pending sale orders', 0, 0, 1, 0, 'badge-info'),
(3, 'غير متوفر', 'Advance booking orders', 0, 0, 1, 0, 'badge-info'),
(4, 'العميل لايستجيب', 'The client is not responding', 0, 0, 0, 0, 'badge-red20'),
(5, ' إلغاء امر البيع', 'Cancel order', 0, 0, 0, 0, 'badge-danger'),
(7, 'طلبات قيد التجهيز', 'Processing', 1, 0, 1, 1, 'badge-green10'),
(8, 'طلبات تم التجهيز', 'Prepared', 2, 0, 1, 1, 'badge-green20'),
(9, 'طلبات جارى الشحن', 'Shipping', 2, 1, 0, 0, 'badge-green60'),
(10, 'عهدة المندوبين', 'delivered ', NULL, 3, 0, 0, 'badge-green100'),
(11, 'بيع مباشر', 'Direct sale', NULL, 0, 1, 1, 'badge-praimary');

--
-- 15. أنواع خدمة الطلبات
--
INSERT INTO `order_service_type` (`OST_ID`, `OST_TYPE`) VALUES
(1, 'أخرى'),
(2, 'طلب استعجل الشحن');

--
-- 16. طرق الدفع
--
INSERT INTO `payment_methods` (`id`, `name`, `description`, `is_active`) VALUES
(1, 'Cash', 'الدفع نقدًا عند تسليم المنتج أو الخدمة', 1),
(2, 'Bank Transfer', 'الدفع عبر تحويلات بنكية من حساب إلى حساب', 1),
(3, 'Credit Card', 'الدفع باستخدام بطاقات الائتمان مثل فيزا وماستر كارد', 1),
(4, 'Cash on Delivery', 'الدفع عند استلام العميل للمنتج أو الخدمة', 1),
(5, 'E-Wallet', 'الدفع باستخدام المحفظات الإلكترونية مثل باي بال، فودافون كاش، أو غيرها', 1);

--
-- 17. فئات المعاملات
--
INSERT INTO `transaction_categories` (`id`, `transaction_type`, `name`, `description`, `parent_category_id`) VALUES
(1, NULL, 'مبيعات', 'فئة المعاملات الخاصة بالمبيعات من العملاء، تشمل جميع العمليات المتعلقة بالبيع من فواتير أو تحصيلات نقدية.', NULL),
(2, NULL, 'مشتريات', 'فئة المعاملات الخاصة بالمشتريات من الموردين، تشمل جميع المدفوعات والمشتريات من الموردين.', NULL),
(3, NULL, 'مدفوعات', 'فئة المعاملات الخاصة بالمدفوعات المالية المختلفة مثل دفع الفواتير، المدفوعات المتعلقة بالموظفين أو الموردين.', NULL),
(4, NULL, 'خصومات', 'فئة المعاملات الخاصة بالخصومات الممنوحة للعملاء أو الموردين، مثل الخصم على الفواتير أو العروض الخاصة.', NULL),
(5, NULL, 'استردادات', 'فئة المعاملات الخاصة بالاستردادات أو الإرجاع، مثل استرداد الأموال أو إرجاع السلع من العملاء.', NULL),
(6, 'EXPENSES', 'مرتبات', 'فئة المعاملات الخاصة بالرواتب للموظفين، تشمل المدفوعات المتعلقة بالرواتب الشهرية والتعويضات.', NULL),
(7, 'REVENUE', 'إيرادات أخرى', 'فئة المعاملات الخاصة بالإيرادات الأخرى التي لا تندرج تحت فئات المبيعات أو المدفوعات، مثل إيرادات استثمارية أو إضافية.', NULL),
(8, 'REVENUE', 'فواتير مبيعات', 'فئة فرعية تختص بالفواتير التي يتم إصدارها للعملاء مقابل المبيعات، تشمل التفاصيل الخاصة بكل فاتورة بيع.', 1),
(9, 'REVENUE', 'تحصيلات مبيعات', 'فئة فرعية تختص بالمدفوعات المستلمة من العملاء مقابل الفواتير المباعة، تشمل المدفوعات النقدية أو عبر الحوالات البنكية. من خلال الندوب', 1),
(10, 'EXPENSES', 'فواتير مشتريات', 'فئة فرعية تختص بالفواتير التي يتم استلامها من الموردين مقابل المنتجات أو الخدمات المشتراة.', 2),
(11, 'EXPENSES', 'مدفوعات مشتريات', 'فئة فرعية تختص بالمدفوعات التي يتم دفعها للموردين مقابل الفواتير المستلمة، تشمل المدفوعات النقدية أو عبر الحوالات البنكية.', 2),
(12, 'EXPENSES', 'مدفوعات مرتبات', 'فئة فرعية تختص بالمدفوعات المتعلقة برواتب الموظفين، بما في ذلك المدفوعات الشهرية أو المكافآت.', 3),
(13, 'EXPENSES', 'مدفوعات فواتير خدمات', 'فئة فرعية تختص بالمدفوعات الخاصة بفواتير الخدمات المختلفة مثل الكهرباء، المياه، الإنترنت، إلخ.', 3),
(14, 'EXPENSES', 'استردادات مبيعات', 'فئة فرعية تختص بمعاملات استرداد الأموال من العملاء عند إرجاع المنتجات أو عدم رضاهم عن الخدمة المقدمة.', 5),
(15, 'REVENUE', 'استردادات مشتريات', 'فئة فرعية تختص بمعاملات استرداد المدفوعات للموردين في حال إرجاع البضائع المشتراة أو طلب استبدالها.', 5);
--
-- 18. مجموعات خيارات المنتجات
--
INSERT INTO `product_option_group` (`OPTION_ID`, `OPTION_NAME`, `OPTION_TYPE`) VALUES
(1, 'اللون', 1),
(2, 'المقاس', 1);

--
-- 19. خيارات المنتجات
--
INSERT INTO `product_option` (`OPTION_ID`, `VALUE`) VALUES
(1, 'ابيض'),
(1, 'اسود'),
(1, 'بنى'),
(1, 'بيج'),
(1, 'جينز'),
(1, 'رمادى'),
(1, 'زيتى'),
(1, 'غامق'),
(1, 'فاتح'),
(1, 'كحلى'),
(1, 'وسط'),
(1, 'نحاسى'),
(1, 'نبيتى'),
(2, '2'),
(2, '4'),
(2, '6'),
(2, '8'),
(2, '10'),
(2, '12'),
(2, '14'),
(2, '16'),
(2, '28'),
(2, '30'),
(2, '32'),
(2, '34'),
(2, '36'),
(2, '38'),
(2, '40'),
(2, '42'),
(2, '44'),
(2, '46'),
(2, '48'),
(2, '50'),
(2, '52');


--
-- 20. عداد الباركود
--
INSERT INTO `barcode_counter` (`last_code`) VALUES
(9900000000000);

--
-- 21. المستخدمين
--
INSERT INTO `users` (`USER_ID`, `USER_GRUOP_ID`, `BRANCH_ID`, `FORENAME`, `LASTNAME`, `GENDER`, `SUSPENDED`, `USER_LOGIN`, `USER_PASSWORD`, `RE_PASSCODE`, `RE_PASS_EXPIRE`, `ADMIN_TYPE`, `MERCHANT_TYPE`, `AFFILIATE_TYPE`, `INTERMEDIARY_TYPE`, `DELIVERY_TYPE`, `USER_IMG_LINK`, `USER_DATE_CREATED`, `BIRTHDAY`) VALUES
(1, NULL, 1, 'admin1', 'admin', 'male', 1, 'm.r.01225133341@gmail.com', '8bccf53148edac6cd30515d13ef51632', NULL, '2022-01-09 12:46:43', 1, 0, 1, 0, 1, '', '2022-01-09 12:46:43', '1984-09-30');

--
-- 26. أرقام الهواتف
--
INSERT INTO `telephones` (`TEL_ID`, `USER_ID`, `CLIENT_ID`, `SUPPLIER_ID`, `TYPE`, `DEFAULTTELL`, `WHATSAPP`, `TELEGRAM`, `TEL_LTYPE`, `TEL_NUMBER`) VALUES
(1, 1, NULL, NULL, 0, 1, 1, 0, 'mobile', '01225133341');

--
-- 27. العناوين
--
INSERT INTO `addresses` (`ADDRESS_ID`, `CITY_ID`, `USER_ID`, `CLIENT_ID`, `SUPPLIER_ID`, `ADDRESS_TITLE`, `DEFAULTADD`, `ADDRESS`) VALUES
(1, 30, 1, NULL, NULL, 'عنوان المنزل', 1, '30 كورنيش النيل الدور الاول');

-- ================================================================
-- NEW ACCOUNTING LEDGER CORE TABLES
-- These tables are required by the accounting classes in this package.
-- The original project archive did not include their DDL.
-- No chart-of-accounts IDs or account mappings are invented here.
-- ================================================================

CREATE TABLE IF NOT EXISTS `accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED DEFAULT NULL,
  `account_code` VARCHAR(30) NOT NULL,
  `account_name` VARCHAR(150) NOT NULL,
  `account_type` VARCHAR(30) NOT NULL,
  `normal_balance` ENUM('DEBIT','CREDIT') NOT NULL,
  `account_level` INT NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_posting` TINYINT(1) NOT NULL DEFAULT 1,
  `system_account` TINYINT(1) NOT NULL DEFAULT 0,
  `is_system_created` TINYINT(1) NOT NULL DEFAULT 0,
  `description` VARCHAR(500) DEFAULT NULL,
  `note` TEXT DEFAULT NULL,
  `created_by` INT(11) DEFAULT NULL,
  `updated_by` INT(11) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accounts_code` (`account_code`),
  KEY `idx_accounts_parent` (`parent_id`),
  KEY `idx_accounts_type_active` (`account_type`,`is_active`),
  CONSTRAINT `fk_accounts_parent` FOREIGN KEY (`parent_id`) REFERENCES `accounts` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `financial_years` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year_name` VARCHAR(10) NOT NULL,
  `date_from` DATE NOT NULL,
  `date_to` DATE NOT NULL,
  `is_closed` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_financial_year_name` (`year_name`),
  KEY `idx_financial_year_dates` (`date_from`,`date_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `account_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(60) NOT NULL,
  `account_id` BIGINT UNSIGNED NOT NULL,
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_settings_key` (`setting_key`),
  KEY `idx_account_settings_account` (`account_id`),
  CONSTRAINT `fk_account_settings_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PHASE17: حسابات مستقلة لعمولات المسوقين وقادة الفرق.
INSERT INTO accounts
    (account_code, account_name, account_type, normal_balance, account_level,
     is_active, allow_posting, system_account, is_system_created, description)
SELECT
    '2152', 'مستحقات عمولات المسوقين وقادة الفرق', 'LIABILITY', 'CREDIT', 1,
    1, 1, 1, 1, 'مستحقات مستقلة لعمولات المسوقين وقادة الفرق'
WHERE NOT EXISTS (SELECT 1 FROM accounts WHERE account_code = '2152');

INSERT INTO accounts
    (account_code, account_name, account_type, normal_balance, account_level,
     is_active, allow_posting, system_account, is_system_created, description)
SELECT
    '5230', 'مصروف عمولات المسوقين وقادة الفرق', 'EXPENSE', 'DEBIT', 1,
    1, 1, 1, 1, 'مصروف مستقل لعمولات المسوقين وقادة الفرق'
WHERE NOT EXISTS (SELECT 1 FROM accounts WHERE account_code = '5230');

INSERT INTO account_settings (setting_key, account_id, description)
SELECT 'MARKETER_COMMISSION_PAYABLE', id,
       'حساب مستحقات عمولات المسوقين وقادة الفرق - 2152'
FROM accounts WHERE account_code = '2152'
ON DUPLICATE KEY UPDATE account_id = VALUES(account_id),
                        description = VALUES(description);

INSERT INTO account_settings (setting_key, account_id, description)
SELECT 'MARKETER_COMMISSION_EXPENSE', id,
       'حساب مصروف عمولات المسوقين وقادة الفرق - 5230'
FROM accounts WHERE account_code = '5230'
ON DUPLICATE KEY UPDATE account_id = VALUES(account_id),
                        description = VALUES(description);

CREATE TABLE IF NOT EXISTS `cash_boxes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `branch_id` INT NOT NULL,
  `account_id` BIGINT UNSIGNED NOT NULL,
  `box_name` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cash_boxes_branch_active` (`branch_id`,`is_active`),
  KEY `idx_cash_boxes_account` (`account_id`),
  CONSTRAINT `fk_cash_boxes_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `entry_no` VARCHAR(40) DEFAULT NULL,
  `fiscal_year` BIGINT UNSIGNED NOT NULL,
  `branch_id` INT NOT NULL,
  `entry_date` DATETIME NOT NULL,
  `reference_type` VARCHAR(60) DEFAULT NULL,
  `reference_id` BIGINT UNSIGNED DEFAULT NULL,
  `description` VARCHAR(1000) DEFAULT NULL,
  `total_debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `status` ENUM('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'POSTED',
  `posted_at` DATETIME DEFAULT NULL,
  `posted_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_journal_entry_no` (`entry_no`),
  KEY `idx_journal_branch_date` (`branch_id`,`entry_date`),
  KEY `idx_journal_reference` (`reference_type`,`reference_id`),
  KEY `idx_journal_status_date` (`status`,`entry_date`),
  KEY `idx_journal_fiscal_year` (`fiscal_year`),
  CONSTRAINT `fk_journal_fiscal_year` FOREIGN KEY (`fiscal_year`) REFERENCES `financial_years` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accounting_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `idempotency_key` VARCHAR(128) NOT NULL,
  `event_type` VARCHAR(60) NOT NULL,
  `source_type` VARCHAR(60) NOT NULL,
  `source_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` INT NOT NULL,
  `event_date` DATETIME NOT NULL,
  `payload_hash` CHAR(64) NOT NULL,
  `status` ENUM('PROCESSING','POSTED') NOT NULL DEFAULT 'PROCESSING',
  `journal_entry_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `posted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accounting_event_idempotency` (`idempotency_key`),
  KEY `idx_accounting_event_source` (`source_type`,`source_id`,`event_type`),
  KEY `idx_accounting_event_status_date` (`status`,`event_date`),
  UNIQUE KEY `uq_accounting_event_journal` (`journal_entry_id`),
  CONSTRAINT `fk_accounting_event_journal` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journal_entry_details` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `journal_entry_id` BIGINT UNSIGNED NOT NULL,
  `line_no` INT NOT NULL,
  `account_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` INT DEFAULT NULL,
  `store_id` BIGINT UNSIGNED DEFAULT NULL,
  `cost_center_id` BIGINT UNSIGNED DEFAULT NULL,
  `partner_type` VARCHAR(30) DEFAULT NULL,
  `partner_id` BIGINT UNSIGNED DEFAULT NULL,
  `reference_type` VARCHAR(60) DEFAULT NULL,
  `reference_id` BIGINT UNSIGNED DEFAULT NULL,
  `debit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `credit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `currency_id` INT(11) DEFAULT NULL,
  `exchange_rate` DECIMAL(18,8) NOT NULL DEFAULT 1.00000000,
  `note` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_journal_line_no` (`journal_entry_id`,`line_no`),
  KEY `idx_journal_detail_account` (`account_id`,`branch_id`),
  KEY `idx_journal_detail_partner` (`partner_type`,`partner_id`),
  KEY `idx_journal_detail_reference` (`reference_type`,`reference_id`),
  CONSTRAINT `fk_journal_detail_header` FOREIGN KEY (`journal_entry_id`) REFERENCES `journal_entries` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_journal_detail_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `chk_journal_detail_nonnegative` CHECK (`debit` >= 0 AND `credit` >= 0),
  CONSTRAINT `chk_journal_detail_one_side` CHECK (`debit` = 0 OR `credit` = 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
-- FINANCIAL PATCH TABLES (phases 11, 12 and 14)
-- Phase 13 account creation is intentionally not auto-run: its parent
-- account and chart-of-accounts code must be confirmed by the accountant.
-- ================================================================

CREATE TABLE IF NOT EXISTS `payment_method_accounts` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `payment_method_id` INT NOT NULL,
    `branch_id` INT NOT NULL,
    `account_id` BIGINT UNSIGNED NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_payment_method_branch` (`payment_method_id`, `branch_id`),
    KEY `idx_pma_account` (`account_id`),
    CONSTRAINT `fk_pma_method` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`id`),
    CONSTRAINT `fk_pma_account` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `accounting_line_allocations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `debit_line_id` BIGINT UNSIGNED NOT NULL,
    `credit_line_id` BIGINT UNSIGNED NOT NULL,
    `amount` DECIMAL(15,2) NOT NULL,
    `allocation_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('ACTIVE','REVERSED') NOT NULL DEFAULT 'ACTIVE',
    `note` VARCHAR(255) DEFAULT NULL,
    `created_by` BIGINT UNSIGNED DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `reversed_by` BIGINT UNSIGNED DEFAULT NULL,
    `reversed_at` DATETIME DEFAULT NULL,
    `reversal_reason` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_alloc_debit_active` (`debit_line_id`,`status`),
    KEY `idx_alloc_credit_active` (`credit_line_id`,`status`),
    KEY `idx_alloc_date` (`allocation_date`),
    CONSTRAINT `fk_alloc_debit_line` FOREIGN KEY (`debit_line_id`) REFERENCES `journal_entry_details` (`id`),
    CONSTRAINT `fk_alloc_credit_line` FOREIGN KEY (`credit_line_id`) REFERENCES `journal_entry_details` (`id`),
    CONSTRAINT `chk_alloc_amount_positive` CHECK (`amount` > 0),
    CONSTRAINT `chk_alloc_distinct_lines` CHECK (`debit_line_id` <> `credit_line_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_accounting_settings` (
    `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY DEFAULT 1,
    `commission_settlement_mode` ENUM('DEDUCT_FROM_CUSTODY','SEPARATE','USER_CHOICE') NOT NULL DEFAULT 'USER_CHOICE',
    `courier_custody_account_id` BIGINT UNSIGNED NULL,
    `courier_commission_expense_account_id` BIGINT UNSIGNED NULL,
    `courier_commission_payable_account_id` BIGINT UNSIGNED NULL,
    `updated_by` BIGINT UNSIGNED NULL,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `chk_delivery_accounting_settings_singleton` CHECK (`id` = 1),
    CONSTRAINT `fk_delivery_custody_account` FOREIGN KEY (`courier_custody_account_id`) REFERENCES `accounts` (`id`),
    CONSTRAINT `fk_delivery_commission_expense_account` FOREIGN KEY (`courier_commission_expense_account_id`) REFERENCES `accounts` (`id`),
    CONSTRAINT `fk_delivery_commission_payable_account` FOREIGN KEY (`courier_commission_payable_account_id`) REFERENCES `accounts` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO `delivery_accounting_settings` (`id`,`commission_settlement_mode`) VALUES (1,'USER_CHOICE');

CREATE TABLE IF NOT EXISTS `delivery_customer_collections` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `receipt_no` VARCHAR(40) NULL,
    `delivery_user_id` BIGINT UNSIGNED NOT NULL,
    `customer_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NOT NULL,
    `payment_method_id` BIGINT UNSIGNED NULL,
    `collection_source` ENUM('ORDER_COLLECTION','REJECTED_SHIPPING') NOT NULL DEFAULT 'ORDER_COLLECTION',
    `collection_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `customer_paid_amount` DECIMAL(15,2) NOT NULL,
    `commission_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `commission_mode` ENUM('DEDUCT_FROM_CUSTODY','SEPARATE') NULL,
    `collection_journal_id` BIGINT UNSIGNED NULL,
    `commission_journal_id` BIGINT UNSIGNED NULL,
    `status` ENUM('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'DRAFT',
    `note` VARCHAR(500) NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `posted_at` DATETIME NULL,
    `reversed_at` DATETIME NULL,
    `reversed_by` BIGINT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_delivery_collection_receipt_no` (`receipt_no`),
    KEY `idx_delivery_collection_courier_status` (`delivery_user_id`,`status`,`collection_date`),
    KEY `idx_delivery_collection_customer` (`customer_id`,`collection_date`),
    KEY `idx_delivery_collection_journal` (`collection_journal_id`),
    KEY `idx_delivery_collection_commission_journal` (`commission_journal_id`),
    CONSTRAINT `chk_delivery_customer_paid_positive` CHECK (`customer_paid_amount` > 0),
    CONSTRAINT `chk_delivery_commission_nonnegative` CHECK (`commission_amount` >= 0),
    CONSTRAINT `chk_delivery_collection_commission_not_overpaid` CHECK (`commission_amount` <= `customer_paid_amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_collection_order_allocations` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `collection_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `customer_amount` DECIMAL(15,2) NOT NULL,
    `commission_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_delivery_collection_order` (`collection_id`,`order_id`),
    KEY `idx_delivery_alloc_order` (`order_id`),
    CONSTRAINT `fk_delivery_alloc_collection` FOREIGN KEY (`collection_id`) REFERENCES `delivery_customer_collections` (`id`),
    CONSTRAINT `chk_delivery_alloc_customer_amount` CHECK (`customer_amount` > 0),
    CONSTRAINT `chk_delivery_alloc_commission_amount` CHECK (`commission_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_company_settlements` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `settlement_no` VARCHAR(40) NULL,
    `delivery_user_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NOT NULL,
    `payment_method_id` BIGINT UNSIGNED NOT NULL,
    `settlement_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `commission_mode` ENUM('DEDUCT_FROM_CUSTODY','SEPARATE') NOT NULL,
    `collected_gross_amount` DECIMAL(15,2) NOT NULL,
    `commission_offset_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `cash_received_amount` DECIMAL(15,2) NOT NULL,
    `journal_id` BIGINT UNSIGNED NULL,
    `status` ENUM('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'DRAFT',
    `note` VARCHAR(500) NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `request_key` VARCHAR(64) NULL,
    `request_hash` CHAR(64) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `posted_at` DATETIME NULL,
    `reversed_at` DATETIME NULL,
    `reversed_by` BIGINT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_delivery_settlement_request_key` (`request_key`),
    UNIQUE KEY `uq_delivery_settlement_no` (`settlement_no`),
    KEY `idx_delivery_settlement_courier` (`delivery_user_id`,`status`,`settlement_date`),
    KEY `idx_delivery_settlement_journal` (`journal_id`),
    CONSTRAINT `chk_delivery_settlement_gross` CHECK (`collected_gross_amount` > 0),
    CONSTRAINT `chk_delivery_settlement_commission` CHECK (`commission_offset_amount` >= 0),
    CONSTRAINT `chk_delivery_settlement_cash` CHECK (`cash_received_amount` >= 0),
    CONSTRAINT `chk_delivery_settlement_math` CHECK (ROUND(`collected_gross_amount` - `commission_offset_amount`,2) = ROUND(`cash_received_amount`,2))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_commission_payments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_no` VARCHAR(40) NULL,
    `delivery_user_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NOT NULL,
    `payment_method_id` BIGINT UNSIGNED NOT NULL,
    `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `amount` DECIMAL(15,2) NOT NULL,
    `journal_id` BIGINT UNSIGNED NULL,
    `status` ENUM('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'DRAFT',
    `note` VARCHAR(500) NULL,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `request_key` VARCHAR(64) NULL,
    `request_hash` CHAR(64) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `posted_at` DATETIME NULL,
    `reversed_at` DATETIME NULL,
    `reversed_by` BIGINT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_delivery_commission_payment_request_key` (`request_key`),
    UNIQUE KEY `uq_delivery_commission_payment_no` (`payment_no`),
    KEY `idx_delivery_commission_payment_courier` (`delivery_user_id`,`status`,`payment_date`),
    KEY `idx_delivery_commission_payment_journal` (`journal_id`),
    CONSTRAINT `chk_delivery_commission_payment_positive` CHECK (`amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_settlement_collection_links` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `settlement_id` BIGINT UNSIGNED NOT NULL,
    `collection_id` BIGINT UNSIGNED NOT NULL,
    `allocation_id` BIGINT UNSIGNED NOT NULL,
    `applied_gross_amount` DECIMAL(15,2) NOT NULL,
    `applied_commission_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_delivery_settlement_allocation` (`settlement_id`,`allocation_id`),
    KEY `idx_delivery_link_collection` (`collection_id`),
    KEY `idx_delivery_link_allocation` (`allocation_id`),
    CONSTRAINT `fk_delivery_link_settlement` FOREIGN KEY (`settlement_id`) REFERENCES `delivery_company_settlements` (`id`),
    CONSTRAINT `fk_delivery_link_collection` FOREIGN KEY (`collection_id`) REFERENCES `delivery_customer_collections` (`id`),
    CONSTRAINT `fk_delivery_link_allocation` FOREIGN KEY (`allocation_id`) REFERENCES `delivery_collection_order_allocations` (`id`),
    CONSTRAINT `chk_delivery_link_gross` CHECK (`applied_gross_amount` > 0),
    CONSTRAINT `chk_delivery_link_commission` CHECK (`applied_commission_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- IMPORTANT: This script creates table structure only for the new ledger.
-- Before posting transactions, an accountant must populate accounts and map:
-- CUSTOMERS, SALES, COGS, INVENTORY, SALES_RETURNS in account_settings;
-- branch cash boxes in cash_boxes; and payment_method_accounts for each branch.
-- The actual account chart and those mappings were not included in the supplied archive.


-- PHASE16: supplier mixed payment allocation audit
-- PHASE16: توثيق توزيع دفعات الموردين بين فواتير ومقدم غير مخصص.
-- لا يعتمد على مفاتيح خارجية حتى لا يتعطل التثبيت على قواعد البيانات القديمة.
CREATE TABLE IF NOT EXISTS `supplier_payment_allocations` (
  `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
  `financial_transaction_id` INT(11) NOT NULL,
  `supplier_id` BIGINT(20) NOT NULL,
  `purchase_order_id` BIGINT(20) DEFAULT NULL,
  `allocation_type` ENUM('INVOICE','ADVANCE') NOT NULL,
  `amount` DECIMAL(18,2) NOT NULL,
  `created_by` BIGINT(20) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_supplier_payment_invoice` (`financial_transaction_id`,`purchase_order_id`,`allocation_type`),
  KEY `idx_supplier_payment_transaction` (`financial_transaction_id`),
  KEY `idx_supplier_payment_supplier` (`supplier_id`,`created_at`),
  KEY `idx_supplier_payment_order` (`purchase_order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- =========================================================
-- Tables present in the latest cptest structure backup (2026-11-05)
-- =========================================================

CREATE TABLE IF NOT EXISTS `banks` (
  `id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `bank_name` varchar(150) DEFAULT NULL,
  `account_number` varchar(100) DEFAULT NULL,
  `iban` varchar(100) DEFAULT NULL,
  `swift_code` varchar(50) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `cost_centers` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `code` varchar(20) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `crm_order_logs` (
  `id` int(11) NOT NULL,
  `ORDER_ID` bigint(20) NOT NULL,
  `EMPLOYEE_ID` int(11) NOT NULL,
  `employee_type` enum('admin','delivery','marketing','other') DEFAULT NULL,
  `ACTION_TYPE` enum('call','whatsapp','sms','note','status_change') DEFAULT NULL,
  `RESULT` enum('confirmed','editaddress','postponement','no_answer','phone_closed','call_later','cancelled','wrong_number','recall') DEFAULT NULL,
  `call_duration_sec` int(11) DEFAULT NULL,
  `NEXT_FOLLOW_UP` datetime DEFAULT NULL,
  `NOTES` text DEFAULT NULL,
  `CREATED_AT` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `currencies` (
  `id` int(11) NOT NULL,
  `code` varchar(10) DEFAULT NULL,
  `name` varchar(100) DEFAULT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `exchange_rate` decimal(18,8) DEFAULT 1.00000000,
  `is_default` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `inventory_counts` (
  `COUNT_ID` bigint(20) NOT NULL,
  `COUNT_CODE` varchar(30) NOT NULL,
  `BRANCH_ID` int(11) NOT NULL,
  `STORE_ID` int(11) NOT NULL,
  `COUNT_DATE` datetime NOT NULL,
  `STATUS` tinyint(4) NOT NULL DEFAULT 0 COMMENT '\n0=DRAFT\n1=COUNTING\n2=APPROVED\n3=CANCELLED',
  `NOTE` text DEFAULT NULL,
  `INVENTORY_MOVEMENT_ID` bigint(20) DEFAULT NULL,
  `JOURNAL_ENTRY_ID` bigint(20) DEFAULT NULL,
  `CREATED_BY` bigint(20) NOT NULL,
  `CREATED_AT` timestamp NOT NULL DEFAULT current_timestamp(),
  `UPDATED_BY` bigint(20) DEFAULT NULL,
  `UPDATED_AT` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
  `APPROVED_BY` bigint(20) DEFAULT NULL,
  `APPROVED_AT` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `inventory_count_details` (
  `ID` bigint(20) NOT NULL,
  `COUNT_ID` bigint(20) NOT NULL,
  `PRO_CODE` varchar(50) NOT NULL,
  `SYSTEM_QTY` decimal(18,4) NOT NULL,
  `ACTUAL_QTY` decimal(18,4) DEFAULT NULL,
  `DIFFERENCE_QTY` decimal(18,4) DEFAULT 0.0000,
  `UNIT_COST` decimal(18,4) DEFAULT 0.0000,
  `DIFFERENCE_COST` decimal(18,4) DEFAULT 0.0000,
  `NOTE` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `reasons_data` (
  `ID` int(10) UNSIGNED NOT NULL,
  `REASON` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE IF NOT EXISTS `reasons_for_product_rejection` (
  `ID` bigint(20) UNSIGNED NOT NULL,
  `ORDER_ID` bigint(20) UNSIGNED NOT NULL,
  `PRO_CODE` varchar(100) NOT NULL,
  `REASON_ID` int(11) NOT NULL,
  `COMMENT` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- جداول تسوية رفض التوصيل ومحاولات العمولة.

CREATE TABLE IF NOT EXISTS `delivery_attempt_commissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `shipmov_id` INT(11) NOT NULL,
    `order_id` BIGINT(20) NOT NULL,
    `delivery_user_id` BIGINT(20) NOT NULL,
    `branch_id` INT(11) NOT NULL,
    `shipping_amount_snapshot` DECIMAL(15,2) NOT NULL,
    `commission_percent_snapshot` DECIMAL(9,4) NOT NULL,
    `commission_amount` DECIMAL(15,2) NOT NULL,
    `accounting_event_id` BIGINT UNSIGNED DEFAULT NULL,
    `journal_entry_id` BIGINT UNSIGNED DEFAULT NULL,
    `status` ENUM('POSTED','NO_COMMISSION') NOT NULL,
    `created_by` BIGINT(20) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_delivery_attempt_commission_shipmov` (`shipmov_id`),
    UNIQUE KEY `uq_delivery_attempt_commission_event` (`accounting_event_id`),
    UNIQUE KEY `uq_delivery_attempt_commission_journal` (`journal_entry_id`),
    KEY `idx_delivery_attempt_commission_delivery` (`delivery_user_id`,`status`,`created_at`),
    KEY `idx_delivery_attempt_commission_order` (`order_id`),
    CONSTRAINT `chk_delivery_attempt_shipping_nonnegative` CHECK (`shipping_amount_snapshot` >= 0),
    CONSTRAINT `chk_delivery_attempt_percent_range` CHECK (`commission_percent_snapshot` >= 0 AND `commission_percent_snapshot` <= 100),
    CONSTRAINT `chk_delivery_attempt_commission_nonnegative` CHECK (`commission_amount` >= 0),
    CONSTRAINT `chk_delivery_attempt_commission_journal` CHECK (
        (`status` = 'POSTED' AND `commission_amount` > 0 AND `accounting_event_id` IS NOT NULL AND `journal_entry_id` IS NOT NULL)
        OR
        (`status` = 'NO_COMMISSION' AND `commission_amount` = 0 AND `accounting_event_id` IS NULL AND `journal_entry_id` IS NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_rejected_shipping_collections` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `shipmov_id` INT(11) NOT NULL,
    `delivery_user_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` INT(11) NOT NULL,
    `shipping_amount_snapshot` DECIMAL(15,2) NOT NULL,
    `accounting_event_id` BIGINT UNSIGNED NOT NULL,
    `journal_entry_id` BIGINT UNSIGNED NOT NULL,
    `delivery_collection_id` BIGINT UNSIGNED NULL,
    `status` ENUM('POSTED') NOT NULL DEFAULT 'POSTED',
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_rejected_shipping_order` (`order_id`),
    UNIQUE KEY `uq_rejected_shipping_attempt` (`shipmov_id`),
    UNIQUE KEY `uq_rejected_shipping_event` (`accounting_event_id`),
    UNIQUE KEY `uq_rejected_shipping_journal` (`journal_entry_id`),
    UNIQUE KEY `uq_rejected_shipping_delivery_collection` (`delivery_collection_id`),
    KEY `idx_rejected_shipping_delivery` (`delivery_user_id`, `created_at`),
    KEY `idx_rejected_shipping_branch` (`branch_id`, `created_at`),
    CONSTRAINT `chk_rejected_shipping_amount_positive` CHECK (`shipping_amount_snapshot` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_rejected_shipping_reviews` (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 shipmov_id INT(11) NOT NULL,
 order_id BIGINT UNSIGNED NOT NULL,
 delivery_user_id BIGINT UNSIGNED NOT NULL,
 branch_id INT(11) NOT NULL,
 shipping_amount_snapshot DECIMAL(15,2) NOT NULL,
 commission_percent_snapshot DECIMAL(9,4) NOT NULL,
 estimated_compensation DECIMAL(15,2) NOT NULL DEFAULT 0,
 approved_compensation DECIMAL(15,2) NOT NULL DEFAULT 0,
 status ENUM('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
 accounting_event_id BIGINT UNSIGNED NULL,
 journal_entry_id BIGINT UNSIGNED NULL,
 decision_note VARCHAR(500) NULL,
 decided_by BIGINT UNSIGNED NULL,
 decided_at DATETIME NULL,
 created_by BIGINT UNSIGNED NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 UNIQUE KEY uq_rejected_shipping_review_attempt (shipmov_id),
 UNIQUE KEY uq_rejected_shipping_review_event (accounting_event_id),
 UNIQUE KEY uq_rejected_shipping_review_journal (journal_entry_id),
 KEY idx_rejected_shipping_review_delivery (delivery_user_id,status,created_at),
 KEY idx_rejected_shipping_review_branch (branch_id,status,created_at),
 KEY idx_rejected_shipping_review_order (order_id),
 CONSTRAINT chk_rejected_shipping_review_amounts CHECK (
  shipping_amount_snapshot >= 0 AND commission_percent_snapshot >= 0
  AND commission_percent_snapshot <= 100 AND estimated_compensation >= 0
  AND approved_compensation >= 0
 ),
 CONSTRAINT chk_rejected_shipping_review_decision CHECK (
  (status='PENDING' AND approved_compensation=0 AND accounting_event_id IS NULL AND journal_entry_id IS NULL AND decided_by IS NULL)
  OR (status='REJECTED' AND approved_compensation=0 AND accounting_event_id IS NULL AND journal_entry_id IS NULL AND decided_by IS NOT NULL)
  OR (status='APPROVED' AND approved_compensation>0 AND accounting_event_id IS NOT NULL AND journal_entry_id IS NOT NULL AND decided_by IS NOT NULL)
 )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- جداول منع تكرار تأكيد الشحن والتحصيل.

CREATE TABLE IF NOT EXISTS `delivery_confirmation_requests` (
    `request_key` VARCHAR(64) NOT NULL,
    `request_hash` CHAR(64) NOT NULL,
    `session_id` VARCHAR(64) NOT NULL,
    `actor_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('PROCESSING','COMPLETED') NOT NULL DEFAULT 'PROCESSING',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    PRIMARY KEY (`request_key`),
    KEY `idx_delivery_confirmation_status_created` (`status`, `created_at`),
    KEY `idx_delivery_confirmation_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_collection_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_key` VARCHAR(64) NOT NULL,
    `request_hash` CHAR(64) NOT NULL,
    `delivery_user_id` BIGINT UNSIGNED NOT NULL,
    `branch_id` BIGINT UNSIGNED NOT NULL,
    `payment_method_id` BIGINT UNSIGNED NOT NULL,
    `total_amount` DECIMAL(15,2) NOT NULL,
    `order_count` INT UNSIGNED NOT NULL,
    `note` VARCHAR(500) NULL,
    `status` ENUM('PROCESSING','POSTED') NOT NULL DEFAULT 'PROCESSING',
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `completed_at` DATETIME NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_delivery_collection_request_key` (`request_key`),
    KEY `idx_delivery_collection_request_delivery` (`delivery_user_id`,`created_at`),
    CONSTRAINT `chk_delivery_collection_request_total` CHECK (`total_amount` > 0),
    CONSTRAINT `chk_delivery_collection_request_orders` CHECK (`order_count` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delivery_collection_request_collections` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `request_id` BIGINT UNSIGNED NOT NULL,
    `collection_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `customer_amount` DECIMAL(15,2) NOT NULL,
    `commission_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_delivery_request_order` (`request_id`,`order_id`),
    UNIQUE KEY `uq_delivery_request_collection` (`collection_id`),
    KEY `idx_delivery_request_collection_request` (`request_id`),
    CONSTRAINT `fk_delivery_request_collection_request` FOREIGN KEY (`request_id`) REFERENCES `delivery_collection_requests` (`id`),
    CONSTRAINT `fk_delivery_request_collection_document` FOREIGN KEY (`collection_id`) REFERENCES `delivery_customer_collections` (`id`),
    CONSTRAINT `chk_delivery_request_collection_amount` CHECK (`customer_amount` > 0),
    CONSTRAINT `chk_delivery_request_collection_commission` CHECK (`commission_amount` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- PHASE17: مستندات صرف مستحقات المسوقين وقادة الفرق عبر الأستاذ العام.
CREATE TABLE IF NOT EXISTS marketer_commission_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    payment_no VARCHAR(40) NULL,
    marketer_user_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    payment_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(15,2) NOT NULL,
    journal_id BIGINT UNSIGNED NULL,
    status ENUM('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'DRAFT',
    note VARCHAR(500) NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    request_key VARCHAR(64) NULL,
    request_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    posted_at DATETIME NULL,
    reversed_at DATETIME NULL,
    reversed_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_marketer_commission_payment_request_key (request_key),
    UNIQUE KEY uq_marketer_commission_payment_no (payment_no),
    KEY idx_marketer_commission_payment_partner (marketer_user_id,status,payment_date),
    KEY idx_marketer_commission_payment_journal (journal_id),
    CONSTRAINT chk_marketer_commission_payment_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- PHASE18: مستندات رد مبالغ مرتجعات العملاء من الأستاذ العام فقط.
CREATE TABLE IF NOT EXISTS customer_refund_payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    refund_no VARCHAR(40) NULL,
    order_id BIGINT UNSIGNED NOT NULL,
    customer_id BIGINT UNSIGNED NOT NULL,
    branch_id BIGINT UNSIGNED NOT NULL,
    payment_method_id BIGINT UNSIGNED NOT NULL,
    refund_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    amount DECIMAL(15,2) NOT NULL,
    journal_id BIGINT UNSIGNED NULL,
    status ENUM('DRAFT','POSTED','REVERSED') NOT NULL DEFAULT 'DRAFT',
    created_by BIGINT UNSIGNED NOT NULL,
    request_key VARCHAR(64) NULL,
    request_hash CHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    posted_at DATETIME NULL,
    reversed_at DATETIME NULL,
    reversed_by BIGINT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_customer_refund_request_key (request_key),
    UNIQUE KEY uq_customer_refund_no (refund_no),
    KEY idx_customer_refund_order (order_id,status,refund_date),
    KEY idx_customer_refund_customer (customer_id,status,refund_date),
    KEY idx_customer_refund_journal (journal_id),
    CONSTRAINT chk_customer_refund_positive CHECK (amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- PHASE19: ربط حسابات دورة الموردين وفق دليل الحسابات المعتمد.
-- لا ينشئ حسابات بديلة؛ يجب استيراد دليل الحسابات أولاً.
INSERT INTO account_settings (setting_key, account_id, description)
SELECT 'SUPPLIERS', id, 'حسابات الموردين الدائنة - 2111'
FROM accounts WHERE account_code = '2111'
ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), description = VALUES(description);

INSERT INTO account_settings (setting_key, account_id, description)
SELECT 'INVENTORY_IN_TRANSIT', id, 'مخزون بالطريق للمشتريات - 1162'
FROM accounts WHERE account_code = '1162'
ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), description = VALUES(description);

INSERT INTO account_settings (setting_key, account_id, description)
SELECT 'SUPPLIER_ADVANCES', id, 'دفعات مقدمة للموردين - 1171'
FROM accounts WHERE account_code = '1171'
ON DUPLICATE KEY UPDATE account_id = VALUES(account_id), description = VALUES(description);

SELECT aset.setting_key, a.account_code, a.account_name
FROM account_settings aset
INNER JOIN accounts a ON a.id = aset.account_id
WHERE aset.setting_key IN ('SUPPLIERS','INVENTORY_IN_TRANSIT','SUPPLIER_ADVANCES')
ORDER BY aset.setting_key;
