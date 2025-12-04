<?php
session_start();
if (!isset($_SESSION["otp_verified"]) || !$_SESSION["otp_verified"]) {
    header("Location: index.php");
    exit();
}

if ($_SESSION["role"] !== "shop") {
    header("Location: index.php"); // force login
    exit();
}

$role = "shop";

// --- VALID PAGES MUST BE DECLARED FIRST ---
$allowedPages = [
    "home",
    "sales",
    "branch_inventory",
    "report_sales",
    "report_profitloss",
    "report_inventory"
];

// Get requested page
$page = $_GET["page"] ?? "home";

// Validate page: if not allowed → redirect to home and clean URL
if (!in_array($page, $allowedPages, true)) {
    header("Location: shop.php?page=home");
    exit();
}

// Default title
$title = ucwords(str_replace('_', ' ', $page));

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

// Descriptions
$descriptions = [
    "home" => "Welcome to your dashboard overview. View current stock, sales history, and activity summary for this branch.",
    "sales" => "Manage and record sales transactions efficiently.",
    "branch_inventory" => "Monitor inventory specific to this branch.",
    "report_sales" => "View detailed sales reports with customizable filters.",
    "report_profitloss" => "View profit and loss summary for this branch.",
    "report_inventory" => "Get real-time inventory status and valuation reports.",
];

$description = $descriptions[$page] ?? "";

include "includes/base.php";
