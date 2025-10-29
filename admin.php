<?php
session_start();
if ($_SESSION["role"] !== "admin") {
    header("Location: shop.php");
    exit();
}

$role = "admin";
$page = $_GET["page"] ?? "home";
$title = strtoupper($page) . " MODULE";
$description = "Lorem ipsum dolor sit amet, consectetur adipiscing elit.";

include "includes/base.php";
