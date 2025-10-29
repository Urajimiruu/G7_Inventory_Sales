<?php
session_start();
if ($_SESSION["role"] !== "shop") {
    header("Location: admin.php");
    exit();
}

$role = "shop";
$page = $_GET["page"] ?? "home";

$title = ucwords(str_replace('_', ' ', strtoupper($page)));

// Custom nicer titles
$customTitles = [
  "home" => "Dashboard",
  "sales" => "Sales Transaction",
  "branch_inventory" => "Branch Inventory",
  "report_sales" => "Sales Report",
  "report_profitloss" => "Profit / Loss Report",
  "report_inventory" => "Inventory Report",
];
if (isset($customTitles[$page])) {
  $title = $customTitles[$page];
}

//Descriptions
$descriptions = [
  "home" => "Welcome to your dashboard overview.",
  "sales" => "Manage and record sales transactions efficiently.",
  "branch_inventory" => "Monitor inventory specific to each branch.",
  "report_sales" => "View detailed sales reports and filter any way you want.",
  "report_profitloss" => "View profit and loss summary per shop or date.",
  "report_inventory" => "Get current inventory status and valuation reports.",
];

$description = $descriptions[$page] ?? "";


include "includes/base.php";
