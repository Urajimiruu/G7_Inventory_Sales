<?php
session_start();
if (!isset($_SESSION["otp_verified"]) || !$_SESSION["otp_verified"]) {
    header("Location: index.php");
    exit();
}

if ($_SESSION["role"] !== "admin") {
    header("Location: index.php"); // force login, not shop.php
    exit();
}


$role = "admin";

// --- VALID PAGES MUST BE DECLARED FIRST ---
$allowedPages = [
    "home",
    "sales",
    "main_inventory",
    "branch_inventory",
    "stock_transfer",
    "report_sales",
    "report_profitloss",
    "report_inventory",
    "maintenance_products",
    "maintenance_branches",
    "maintenance_users",
    "returns"
];

// Get requested page
$page = $_GET["page"] ?? "home";

// Validate page: if not allowed → redirect and clean URL
if (!in_array($page, $allowedPages, true)) {
    header("Location: admin.php?page=home");
    exit();
}

// Default title
$title = ucwords(str_replace('_', ' ', $page));

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

// Descriptions
$descriptions = [
    "home" => "Welcome to your dashboard overview. Monitor all branches, users, and performance metrics from a centralized view.",
    "sales" => "Manage and record sales transactions efficiently.",
    "main_inventory" => "Monitor and manage all items in the main warehouse.",
    "branch_inventory" => "Monitor inventory specific to each branch.",
    "stock_transfer" => "Facilitate stock transfers between main and branch inventories.",
    "report_sales" => "View detailed sales reports and filter any way you want.",
    "report_profitloss" => "View profit and loss summary per shop or date.",
    "report_inventory" => "Get current inventory status and valuation reports.",
    "maintenance_products" => "Manage and update all products in the inventory.",
    "maintenance_branches" => "Configure and maintain branch information.",
    "maintenance_users" => "Oversee user accounts and permissions.",
    "returns" => "Handle product returns and manage records.",
];

$description = $descriptions[$page] ?? "";

include "includes/base.php";
