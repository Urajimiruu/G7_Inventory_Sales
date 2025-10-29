<?php
session_start();
if ($_SESSION["role"] !== "shop") {
    header("Location: admin.php");
    exit();
}

$role = "shop";
$page = $_GET["page"] ?? "home";
$title = strtoupper($page) . " MODULE";
$description = "Lorem ipsum dolor sit amet, consectetur adipiscing elit.";

include "includes/base.php";
