<?php
session_start();
if ($_SESSION["role"] !== "admin") {
    header("Location: shop.php");
    exit();
}

$role = "admin";
$page = $_GET["page"] ?? "home";

$title = ucwords(str_replace('_', ' ', strtoupper($page)));

// Custom nicer titles
$customTitles = [
  "home" => "Dashboard",
  "sales" => "Sales Transaction",
  "main_inventory" => "Main Inventory",
  "branch_inventory" => "Branch Inventory",
  "stock_transfer" => "Transfer Stock",
  "report_sales" => "Sales Report",
  "report_profitloss" => "Profit / Loss Report",
  "report_inventory" => "Inventory Report",
  "maintenance_products" => "Product Management",
  "maintenance_branches" => "Branch Management",
  "maintenance_users" => "User Management",
  "returns" => "Returns",
];
if (isset($customTitles[$page])) {
  $title = $customTitles[$page];
}

//Descriptions
$descriptions = [
  "home" => "Welcome to your dashboard overview.",
  "sales" => "Manage and record sales transactions efficiently.",
  "main_inventory" => "Monitor and manage all items in the main warehouse.",
  "branch_inventory" => "Monitor inventory specific to each branch.",
  "stock_transfer" => "Facilitate stock transfers between main and branch inventories.",
  "report_sales" => "View detailed sales reports and filter any way you want.",
  "report_profitloss" => "View profit and loss summary per shop or date.",
  "report_inventory" => "Get current inventory status and valuation reports.",
  "maintenance_products" => "Manage the products available in the inventory.",
  "maintenance_branches" => "Manage branch details and settings.",
  "maintenance_users" => "Manage user accounts and permissions.",
  "returns" => "Handle product returns and manage return records.",
];

$description = $descriptions[$page] ?? "";

include "includes/base.php";
